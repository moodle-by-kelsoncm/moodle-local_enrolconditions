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

namespace local_enrolconditions\local;

use enrol_plugin;
use local_enrolconditions\enforceable;
use local_enrolconditions\event\conditions_not_met;
use local_enrolconditions\event\user_enrolment_reactivated;
use local_enrolconditions\event\user_enrolment_suspended;
use local_enrolconditions\invalid_tree_exception;
use local_enrolconditions\output\failures;
use local_enrolconditions\tree;
use local_enrolconditions\tree_result;
use stdClass;

/**
 * Re-checks the conditions of existing enrolments and applies the action of their instance.
 *
 * Works on the instances of every enabled enrolment method that implements \local_enrolconditions\enforceable.
 *
 * Rules:
 * - An unknown result (unknown condition type, invalid tree) never changes anything.
 * - The enforcer only reactivates enrolments that it suspended itself, recorded in local_enrolconditions_state,
 *   and only while nobody else changed the enrolment since.
 * - The action "none" only records the failure once per episode (met, then not met).
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enforcer {
    /** @var string State table. */
    public const TABLE = 'local_enrolconditions_state';

    /** @var string Nothing changed. */
    public const OUTCOME_NOTHING = 'nothing';
    /** @var string The result is unknown, so nothing changed. */
    public const OUTCOME_UNKNOWN = 'unknown';
    /** @var string The failure was recorded (action none). */
    public const OUTCOME_RECORDED = 'recorded';
    /** @var string The enrolment was suspended. */
    public const OUTCOME_SUSPENDED = 'suspended';
    /** @var string The enrolment was removed. */
    public const OUTCOME_REMOVED = 'removed';
    /** @var string The enrolment was reactivated. */
    public const OUTCOME_REACTIVATED = 'reactivated';
    /** @var string A recorded failure was closed because the conditions are met again. */
    public const OUTCOME_RESOLVED = 'resolved';

    /** @var string State action: only recorded. */
    public const STATE_NONE = 'none';
    /** @var string State action: suspended by the enforcer. */
    public const STATE_SUSPEND = 'suspend';

    /** @var string Closed because the enforcer reactivated the enrolment. */
    public const CLOSE_REACTIVATED = 'reactivated';
    /** @var string Closed because the conditions are met again. */
    public const CLOSE_MET = 'met';
    /** @var string Closed because someone else changed the enrolment. */
    public const CLOSE_EXTERNAL = 'external';
    /** @var string Closed because the configured action changed. */
    public const CLOSE_SUPERSEDED = 'superseded';

    /** @var bool True while the enforcer itself is changing an enrolment. */
    protected static bool $acting = false;

    /** @var array<string, enrol_plugin&enforceable>|null Enrolment methods, by name; null until loaded. */
    protected ?array $plugins;

    /**
     * Constructor.
     *
     * @param array|null $plugins Enrolment methods (enrol_plugin implementing enforceable) by name. Defaults to the
     *     enabled methods that implement enforceable.
     */
    public function __construct(?array $plugins = null) {
        $this->plugins = $plugins;
    }

    /**
     * Enrolment methods handled by this enforcer.
     *
     * @return array<string, enrol_plugin&enforceable> By name.
     */
    public function get_plugins(): array {
        if ($this->plugins === null) {
            $this->plugins = [];
            foreach (enrol_get_plugins(true) as $name => $plugin) {
                if ($plugin instanceof enforceable) {
                    $this->plugins[$name] = $plugin;
                }
            }
        }
        return $this->plugins;
    }

    /**
     * Enrolment method of an instance, when this enforcer handles it.
     *
     * @param string $enrol Name of the enrolment method.
     * @return (enrol_plugin&enforceable)|null
     */
    public function get_plugin(string $enrol): ?enforceable {
        return $this->get_plugins()[$enrol] ?? null;
    }

    /**
     * Whether the enforcer is changing an enrolment right now.
     *
     * Observers use it to tell the enforcer's own changes from changes made by others.
     *
     * @return bool
     */
    public static function is_acting(): bool {
        return self::$acting;
    }

    /**
     * Evaluates a stored condition tree for a user.
     *
     * A stored tree that is no longer valid counts as unknown, so it refuses new enrolments and changes nothing in
     * existing ones.
     *
     * @param string|null $json Tree as stored.
     * @param int $userid
     * @param stdClass $instance Enrolment instance, passed to the conditions.
     * @return tree_result|null Null when there are no conditions.
     */
    public static function evaluate(?string $json, int $userid, stdClass $instance): ?tree_result {
        try {
            $tree = tree::from_json($json);
        } catch (invalid_tree_exception $e) {
            debugging("Invalid condition tree in enrolment instance {$instance->id}: " . $e->getMessage(), DEBUG_DEVELOPER);
            return new tree_result(null, [[
                'message' => get_string('failureunknown', 'local_enrolconditions'),
                'children' => [],
            ]]);
        }
        if ($tree->is_empty()) {
            return null;
        }
        return $tree->evaluate($userid, $instance);
    }

    /**
     * Re-checks the enrolment of a user in an instance.
     *
     * @param stdClass $instance Enrolment instance.
     * @param int $userid
     * @return string One of the OUTCOME_ constants.
     */
    public function check_user(stdClass $instance, int $userid): string {
        global $DB;
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        if (!$ue) {
            return self::OUTCOME_NOTHING;
        }
        return $this->check_user_enrolment($instance, $ue);
    }

    /**
     * Re-checks one user enrolment.
     *
     * @param stdClass $instance Enrolment instance.
     * @param stdClass $ue User enrolment record.
     * @return string One of the OUTCOME_ constants.
     */
    public function check_user_enrolment(stdClass $instance, stdClass $ue): string {
        if ((int) $ue->enrolid !== (int) $instance->id) {
            throw new \coding_exception('The user enrolment does not belong to this enrolment instance.');
        }
        $plugin = $this->get_plugin((string) $instance->enrol);
        if (!$plugin || (int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            return self::OUTCOME_NOTHING;
        }

        $result = self::evaluate($plugin->get_enforcement_tree($instance), (int) $ue->userid, $instance);
        if ($result !== null && $result->is_unknown()) {
            return self::OUTCOME_UNKNOWN;
        }
        $open = $this->get_open_state((int) $ue->id);
        if ($result === null || $result->is_met()) {
            return $this->handle_met($plugin, $instance, $ue, $open);
        }
        return $this->handle_not_met($plugin, $instance, $ue, $open, $result);
    }

    /**
     * SQL that selects the enabled instances that use a check mode.
     *
     * @param int $mode One of the CHECK_ constants.
     * @param string|null $enrol Only the instances of this enrolment method.
     * @return array [sql where, params] over the alias e.
     */
    protected function instances_in_mode_sql(int $mode, ?string $enrol = null): array {
        global $DB;
        $parts = [];
        $params = ['enabled' => ENROL_INSTANCE_ENABLED];
        $i = 0;
        foreach ($this->get_plugins() as $name => $plugin) {
            if ($enrol !== null && $name !== $enrol) {
                continue;
            }
            $i++;
            $params["enforceenrol$i"] = $name;
            $sql = $plugin->get_enforcement_mode_sql($mode);
            if ($sql !== null) {
                [$where, $pluginparams] = $sql;
                $parts[] = "(e.enrol = :enforceenrol$i AND ($where))";
                $params += $pluginparams;
                continue;
            }
            $ids = [];
            foreach ($DB->get_records('enrol', ['enrol' => $name, 'status' => ENROL_INSTANCE_ENABLED]) as $instance) {
                if (($plugin->get_enforcement_check_modes($instance) & $mode) === $mode) {
                    $ids[] = (int) $instance->id;
                }
            }
            if ($ids) {
                [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, "enforceid{$i}_");
                $parts[] = "(e.enrol = :enforceenrol$i AND e.id $insql)";
                $params += $inparams;
            }
        }
        if (!$parts) {
            return ['1 = 0', []];
        }
        return ['e.status = :enabled AND (' . implode(' OR ', $parts) . ')', $params];
    }

    /**
     * Re-checks the enrolments of a user in the instances that use a check mode.
     *
     * Enrolment methods call it with CHECK_LOGIN from sync_user_enrolments(), passing their own name.
     *
     * @param int $userid
     * @param int $mode One of the CHECK_ constants.
     * @param string|null $enrol Only the instances of this enrolment method.
     * @return string[] Outcome by user enrolment id.
     */
    public function check_user_in_mode(int $userid, int $mode, ?string $enrol = null): array {
        global $DB;
        [$where, $params] = $this->instances_in_mode_sql($mode, $enrol);
        $instances = $DB->get_records_sql(
            "SELECT e.* FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE $where AND ue.userid = :enforceuserid",
            $params + ['enforceuserid' => $userid]
        );
        return $this->check_user_in_instances($userid, array_keys($instances), $instances);
    }

    /**
     * Re-checks the enrolments of a user in some instances.
     *
     * @param int $userid
     * @param int[] $instanceids
     * @param stdClass[] $instances Instance records already loaded, by id.
     * @return string[] Outcome by user enrolment id.
     */
    public function check_user_in_instances(int $userid, array $instanceids, array $instances = []): array {
        global $DB;
        if (!$instanceids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED);
        $ues = $DB->get_records_select('user_enrolments', "userid = :userid AND enrolid $insql", $params + ['userid' => $userid]);
        $outcomes = [];
        foreach ($ues as $ue) {
            $instance = $instances[$ue->enrolid] ?? $DB->get_record('enrol', ['id' => $ue->enrolid]);
            if ($instance) {
                $outcomes[$ue->id] = $this->check_user_enrolment($instance, $ue);
            }
        }
        return $outcomes;
    }

    /**
     * Re-checks the next batch of enrolments of the instances with CHECK_PERIODIC.
     *
     * The position is kept between runs, so each run continues where the previous one stopped and the
     * cycle starts again after the last enrolment.
     *
     * @param int $limit Maximum number of enrolments.
     * @param \progress_trace|null $trace
     * @return string[] Outcome by user enrolment id.
     */
    public function check_batch(int $limit, ?\progress_trace $trace = null): array {
        global $DB;
        $limit = max(1, $limit);
        $cursor = (int) get_config('local_enrolconditions', 'periodiccursor');
        [$where, $params] = $this->instances_in_mode_sql(enforceable::CHECK_PERIODIC);
        $ues = $DB->get_records_sql(
            "SELECT ue.* FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE $where AND ue.id > :enforcecursor
           ORDER BY ue.id ASC",
            $params + ['enforcecursor' => $cursor],
            0,
            $limit
        );
        $instances = [];
        $outcomes = [];
        foreach ($ues as $ue) {
            if (!isset($instances[$ue->enrolid])) {
                $instances[$ue->enrolid] = $DB->get_record('enrol', ['id' => $ue->enrolid], '*', MUST_EXIST);
            }
            $outcome = $this->check_user_enrolment($instances[$ue->enrolid], $ue);
            $outcomes[$ue->id] = $outcome;
            if ($trace && $outcome !== self::OUTCOME_NOTHING) {
                $trace->output("User enrolment {$ue->id} (user {$ue->userid}, instance {$ue->enrolid}): {$outcome}");
            }
            $cursor = (int) $ue->id;
        }
        // A short batch means the cycle reached the end.
        set_config('periodiccursor', count($ues) < $limit ? 0 : $cursor, 'local_enrolconditions');
        return $outcomes;
    }

    /**
     * Events observed by the instances with CHECK_EVENTS.
     *
     * Cached; the cache is purged when an instance of an enrolment method is created, updated or deleted.
     *
     * @return array Instance ids by event class name, without the leading backslash.
     */
    public function get_event_map(): array {
        global $DB;
        $cache = \cache::make('local_enrolconditions', 'observedevents');
        $key = 'map_' . implode('_', array_keys($this->get_plugins()));
        $map = $cache->get($key);
        if ($map !== false) {
            return $map;
        }
        [$where, $params] = $this->instances_in_mode_sql(enforceable::CHECK_EVENTS);
        $instances = $DB->get_records_sql("SELECT e.* FROM {enrol} e WHERE $where", $params);
        $map = [];
        foreach ($instances as $instance) {
            $plugin = $this->get_plugin((string) $instance->enrol);
            try {
                $tree = tree::from_json($plugin->get_enforcement_tree($instance));
            } catch (invalid_tree_exception $e) {
                continue;
            }
            foreach ($tree->get_observed_events() as $eventname) {
                $map[ltrim($eventname, '\\')][] = (int) $instance->id;
            }
        }
        $cache->set($key, $map);
        return $map;
    }

    /**
     * Forgets the cached map of observed events, after an instance changed.
     */
    public static function purge_event_map(): void {
        \cache::make('local_enrolconditions', 'observedevents')->purge();
    }

    /**
     * Open state record of a user enrolment.
     *
     * @param int $ueid
     * @return stdClass|null
     */
    public function get_open_state(int $ueid): ?stdClass {
        global $DB;
        $records = $DB->get_records_select(
            self::TABLE,
            'userenrolmentid = :ueid AND timeclosed IS NULL',
            ['ueid' => $ueid],
            'id DESC'
        );
        return $records ? reset($records) : null;
    }

    /**
     * Whether the enforcer still owns the state of the enrolment.
     *
     * A suspension is owned while the enrolment is suspended and nobody changed it since.
     *
     * @param stdClass $state
     * @param stdClass $ue
     * @return bool
     */
    public static function owns(stdClass $state, stdClass $ue): bool {
        if ($state->action === self::STATE_NONE) {
            return true;
        }
        return (int) $ue->status === ENROL_USER_SUSPENDED
            && (int) $ue->timemodified === (int) $state->uetimemodified;
    }

    /**
     * Closes a state record.
     *
     * @param stdClass $state
     * @param string $reason One of the CLOSE_ constants.
     */
    public static function close(stdClass $state, string $reason): void {
        global $DB;
        $update = (object) [
            'id' => $state->id,
            'timeclosed' => time(),
            'closereason' => $reason,
        ];
        if ($reason === self::CLOSE_REACTIVATED) {
            $update->timereactivated = $update->timeclosed;
        }
        $DB->update_record(self::TABLE, $update);
    }

    /**
     * Applies the rules when the conditions are met (or there are none).
     *
     * @param enforceable $plugin Enrolment method (an enrol_plugin).
     * @param stdClass $instance
     * @param stdClass $ue
     * @param stdClass|null $open
     * @return string
     */
    protected function handle_met(enforceable $plugin, stdClass $instance, stdClass $ue, ?stdClass $open): string {
        if (!$open) {
            return self::OUTCOME_NOTHING;
        }
        if ($open->action === self::STATE_NONE) {
            self::close($open, self::CLOSE_MET);
            return self::OUTCOME_RESOLVED;
        }
        if (!self::owns($open, $ue)) {
            // Someone else changed the enrolment after the enforcer suspended it: it is not ours any more.
            self::close($open, self::CLOSE_EXTERNAL);
            return self::OUTCOME_NOTHING;
        }
        if (!$plugin->get_enforcement_reactivate($instance)) {
            // Kept open, so the enrolment can still be reactivated if the setting is enabled later.
            return self::OUTCOME_NOTHING;
        }

        $this->update_status($plugin, $instance, $ue, ENROL_USER_ACTIVE);
        self::close($open, self::CLOSE_REACTIVATED);
        user_enrolment_reactivated::create_from_state($instance, $ue, (int) $open->id)->trigger();
        return self::OUTCOME_REACTIVATED;
    }

    /**
     * Applies the configured action when the conditions are not met.
     *
     * @param enforceable $plugin Enrolment method (an enrol_plugin).
     * @param stdClass $instance
     * @param stdClass $ue
     * @param stdClass|null $open
     * @param tree_result $result
     * @return string
     */
    protected function handle_not_met(
        enforceable $plugin,
        stdClass $instance,
        stdClass $ue,
        ?stdClass $open,
        tree_result $result
    ): string {
        global $DB;
        $action = $plugin->get_enforcement_action($instance);
        if (!in_array($action, [enforceable::ACTION_NONE, enforceable::ACTION_SUSPEND, enforceable::ACTION_REMOVE], true)) {
            $action = enforceable::ACTION_SUSPEND;
        }

        if ($open) {
            $owned = self::owns($open, $ue);
            $same = ($open->action === self::STATE_SUSPEND && $action === enforceable::ACTION_SUSPEND)
                || ($open->action === self::STATE_NONE && $action === enforceable::ACTION_NONE);
            // An owned suspension stays when the action becomes "none": the enforcer does not reactivate on failure.
            if ($owned && ($same || $action === enforceable::ACTION_NONE)) {
                return self::OUTCOME_NOTHING;
            }
            self::close($open, $owned ? self::CLOSE_SUPERSEDED : self::CLOSE_EXTERNAL);
        }

        $reason = trim((new failures($result, ''))->to_text());

        if ($action === enforceable::ACTION_REMOVE) {
            conditions_not_met::create_from_enrolment($instance, $ue, 'remove')->trigger();
            self::$acting = true;
            try {
                $plugin->unenrol_user($instance, (int) $ue->userid);
            } finally {
                self::$acting = false;
            }
            return self::OUTCOME_REMOVED;
        }

        if ($action === enforceable::ACTION_NONE) {
            $stateid = $this->insert_state($instance, $ue, self::STATE_NONE, $reason);
            conditions_not_met::create_from_enrolment($instance, $ue, 'none', $stateid)->trigger();
            return self::OUTCOME_RECORDED;
        }

        if ((int) $ue->status === ENROL_USER_SUSPENDED) {
            // Suspended by someone else: the enforcer does not take it over.
            return self::OUTCOME_NOTHING;
        }
        $this->update_status($plugin, $instance, $ue, ENROL_USER_SUSPENDED);
        $ue = $DB->get_record('user_enrolments', ['id' => $ue->id], '*', MUST_EXIST);
        $stateid = $this->insert_state($instance, $ue, self::STATE_SUSPEND, $reason);
        user_enrolment_suspended::create_from_state($instance, $ue, $stateid)->trigger();
        return self::OUTCOME_SUSPENDED;
    }

    /**
     * Changes the status of an enrolment, marking the change as the enforcer's own.
     *
     * @param enforceable $plugin Enrolment method (an enrol_plugin).
     * @param stdClass $instance
     * @param stdClass $ue
     * @param int $status
     */
    protected function update_status(enforceable $plugin, stdClass $instance, stdClass $ue, int $status): void {
        self::$acting = true;
        try {
            $plugin->update_user_enrol($instance, (int) $ue->userid, $status);
        } finally {
            self::$acting = false;
        }
    }

    /**
     * Inserts an open state record.
     *
     * @param stdClass $instance
     * @param stdClass $ue User enrolment, as it is after the action.
     * @param string $action One of the STATE_ constants.
     * @param string $reason
     * @return int Id of the record.
     */
    protected function insert_state(stdClass $instance, stdClass $ue, string $action, string $reason): int {
        global $DB;
        return (int) $DB->insert_record(self::TABLE, (object) [
            'enrol' => $instance->enrol,
            'userenrolmentid' => $ue->id,
            'instanceid' => $instance->id,
            'action' => $action,
            'reason' => $reason,
            'timesuspended' => time(),
            'uetimemodified' => (int) $ue->timemodified,
            'timereactivated' => null,
            'timeclosed' => null,
            'closereason' => null,
        ]);
    }
}
