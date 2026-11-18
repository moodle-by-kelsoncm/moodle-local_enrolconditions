<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_enrolconditions;

use local_enrolconditions\local\enforcer;

/**
 * Observers of the enforcer: re-checks on events and keeps local_enrolconditions_state consistent.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /** @var bool[] Users being re-checked by any_event(), to avoid loops. */
    protected static array $running = [];

    /** @var int[] Open suspensions (state id by user enrolment id) that stay owned after the update in progress. */
    protected static array $keep = [];

    /**
     * Re-checks the enrolments of the user of an event observed by the conditions (CHECK_EVENTS).
     *
     * Observes every event, so it returns early unless the event is in the cached map.
     *
     * @param \core\event\base $event
     */
    public static function any_event(\core\event\base $event): void {
        global $CFG;
        if (!empty($CFG->upgraderunning) || during_initial_install() || enforcer::is_acting()) {
            return;
        }
        $enforcer = new enforcer();
        if (!$enforcer->get_plugins()) {
            return;
        }
        $instanceids = $enforcer->get_event_map()[ltrim($event->eventname, '\\')] ?? [];
        if (!$instanceids) {
            return;
        }
        $userid = (int) ($event->relateduserid ?: $event->userid);
        if ($userid <= 0 || isguestuser($userid) || !empty(self::$running[$userid])) {
            return;
        }
        self::$running[$userid] = true;
        try {
            $enforcer->check_user_in_instances($userid, $instanceids);
        } finally {
            unset(self::$running[$userid]);
        }
    }

    /**
     * An enrolment instance was created, updated or deleted: the observed events may have changed.
     *
     * @param \core\event\base $event enrol_instance_created, enrol_instance_updated or enrol_instance_deleted.
     */
    public static function enrol_instance_changed(\core\event\base $event): void {
        enforcer::purge_event_map();
    }

    /**
     * Whether an enrolment method is handled by the enforcer.
     *
     * @param string $enrol
     * @return bool
     */
    protected static function is_enforceable(string $enrol): bool {
        return $enrol !== '' && enrol_get_plugin($enrol) instanceof enforceable;
    }

    /**
     * Open suspensions of a user enrolment.
     *
     * @param int $ueid
     * @return \stdClass[]
     */
    protected static function open_suspensions(int $ueid): array {
        global $DB;
        return $DB->get_records_select(
            enforcer::TABLE,
            'userenrolmentid = :ueid AND action = :action AND timeclosed IS NULL',
            ['ueid' => $ueid, 'action' => enforcer::STATE_SUSPEND]
        );
    }

    /**
     * Someone else is about to change an enrolment suspended by the enforcer.
     *
     * The record still has the previous timemodified here. The enforcer keeps the suspension only when it still
     * owned it and the enrolment stays suspended (for example, only its dates change).
     *
     * @param \core_enrol\hook\before_user_enrolment_updated $hook
     */
    public static function before_user_enrolment_updated(\core_enrol\hook\before_user_enrolment_updated $hook): void {
        $ue = $hook->userenrolmentinstance;
        if (enforcer::is_acting() || !self::is_enforceable((string) $hook->enrolinstance->enrol)) {
            return;
        }
        unset(self::$keep[(int) $ue->id]);
        foreach (self::open_suspensions((int) $ue->id) as $state) {
            $stillowned = (int) $ue->status === ENROL_USER_SUSPENDED
                && (int) $ue->timemodified === (int) $state->uetimemodified;
            if ($stillowned) {
                self::$keep[(int) $ue->id] = (int) $state->id;
            } else {
                enforcer::close($state, enforcer::CLOSE_EXTERNAL);
            }
        }
    }

    /**
     * Someone else changed an enrolment suspended by the enforcer.
     *
     * Suspensions kept by before_user_enrolment_updated() get the new timemodified. Any other open
     * suspension is no longer owned.
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        global $DB;
        if (enforcer::is_acting() || !self::is_enforceable((string) ($event->other['enrol'] ?? ''))) {
            return;
        }
        $ueid = (int) $event->objectid;
        $keep = self::$keep[$ueid] ?? null;
        unset(self::$keep[$ueid]);
        $ue = $DB->get_record('user_enrolments', ['id' => $ueid]);
        foreach (self::open_suspensions($ueid) as $state) {
            if ($ue && enforcer::owns($state, $ue)) {
                // Unchanged since the enforcer acted: events triggered inside other observers are delivered
                // later, so this may be the enforcer's own suspension.
                continue;
            }
            if ($ue && (int) $state->id === $keep && (int) $ue->status === ENROL_USER_SUSPENDED) {
                $DB->set_field(enforcer::TABLE, 'uetimemodified', $ue->timemodified, ['id' => $state->id]);
            } else {
                enforcer::close($state, enforcer::CLOSE_EXTERNAL);
            }
        }
    }

    /**
     * The enrolment no longer exists, so its records go too.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        global $DB;
        $DB->delete_records(enforcer::TABLE, ['userenrolmentid' => $event->objectid]);
    }

    /**
     * The instance no longer exists, so its records go too.
     *
     * @param \core\event\enrol_instance_deleted $event
     */
    public static function enrol_instance_deleted(\core\event\enrol_instance_deleted $event): void {
        global $DB;
        $DB->delete_records(enforcer::TABLE, ['instanceid' => $event->objectid]);
        enforcer::purge_event_map();
    }
}
