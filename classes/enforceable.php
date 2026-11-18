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

use stdClass;

/**
 * An enrolment method whose enrolments are re-checked against a condition tree.
 *
 * Implemented by the plugin class (enrol_<name>_plugin, a subclass of enrol_plugin). While the enrolment method is
 * enabled, \local_enrolconditions\local\enforcer re-checks the enrolments of its instances when the user logs in
 * (the plugin calls the enforcer from sync_user_enrolments()), periodically and when an event observed by the
 * conditions happens, and applies the action of the instance. The method keeps the configuration of each
 * instance in its own columns of the enrol table.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface enforceable {
    /** @var int Check when the user enrols (always on; the enrolment method does it). */
    public const CHECK_ENROL = 1;

    /** @var int Re-check when the user logs in. */
    public const CHECK_LOGIN = 2;

    /** @var int Re-check in the scheduled task. */
    public const CHECK_PERIODIC = 4;

    /** @var int Re-check when an event observed by the conditions happens. */
    public const CHECK_EVENTS = 8;

    /** @var int Only record that the conditions are no longer met. */
    public const ACTION_NONE = 0;

    /** @var int Suspend the enrolment. */
    public const ACTION_SUSPEND = 1;

    /** @var int Remove the enrolment. */
    public const ACTION_REMOVE = 2;

    /**
     * Condition tree of an instance.
     *
     * @param stdClass $instance Record of the enrol table.
     * @return string|null JSON as stored by \local_enrolconditions\tree::to_json(); null or empty without conditions.
     */
    public function get_enforcement_tree(stdClass $instance): ?string;

    /**
     * When the enrolments of an instance are re-checked.
     *
     * @param stdClass $instance Record of the enrol table.
     * @return int Bitmask of the CHECK_ constants.
     */
    public function get_enforcement_check_modes(stdClass $instance): int;

    /**
     * What happens to an enrolment whose conditions are no longer met.
     *
     * @param stdClass $instance Record of the enrol table.
     * @return int One of the ACTION_ constants.
     */
    public function get_enforcement_action(stdClass $instance): int;

    /**
     * Whether enrolments suspended by the enforcer are reactivated when the conditions are met again.
     *
     * @param stdClass $instance Record of the enrol table.
     * @return bool
     */
    public function get_enforcement_reactivate(stdClass $instance): bool;

    /**
     * SQL that selects the instances of this method that use a check mode, when the configuration allows it.
     *
     * The enforcer adds the conditions on e.enrol and e.status. Name the parameters after the plugin
     * (for example :conditionalmode) so they do not clash with those of other methods.
     *
     * @param int $mode One of the CHECK_ constants.
     * @return array|null [where over the alias e, named params], or null to let the enforcer load the enabled
     *     instances and filter them with get_enforcement_check_modes().
     */
    public function get_enforcement_mode_sql(int $mode): ?array;
}
