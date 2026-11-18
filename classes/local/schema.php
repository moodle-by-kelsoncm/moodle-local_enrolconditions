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

/**
 * Schema helpers for upgrades.
 *
 * Moodle upgrades the enrol plugins before the local plugins, so an enrolment method that moves its records into
 * local_enrolconditions_state may need the table before the upgrade of this plugin runs.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema {
    /**
     * Creates local_enrolconditions_state, as in install.xml, unless it exists.
     */
    public static function create_state_table(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table(enforcer::TABLE);
        if ($dbman->table_exists($table)) {
            return;
        }
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrol', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userenrolmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timesuspended', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('uetimemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timereactivated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timeclosed', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('closereason', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userenrolmentid', XMLDB_KEY_FOREIGN, ['userenrolmentid'], 'user_enrolments', ['id']);
        $table->add_key('instanceid', XMLDB_KEY_FOREIGN, ['instanceid'], 'enrol', ['id']);
        $table->add_index('userenrolmentid-timeclosed', XMLDB_INDEX_NOTUNIQUE, ['userenrolmentid', 'timeclosed']);
        $dbman->create_table($table);
    }
}
