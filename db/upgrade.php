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

/**
 * Upgrade steps for local_enrolconditions.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin database.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_local_enrolconditions_upgrade(int $oldversion): bool {
    if ($oldversion < 2026111801) {
        // State of the generic enforcer. The upgrade of enrol_conditional, which runs first, may have created it
        // already to move its own records.
        \local_enrolconditions\local\schema::create_state_table();
        upgrade_plugin_savepoint(true, 2026111801, 'local', 'enrolconditions');
    }

    return true;
}
