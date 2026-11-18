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

namespace local_enrolconditions\task;

use local_enrolconditions\local\enforcer;

/**
 * Re-checks, in batches, the enrolments of the instances with the periodic check mode.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_conditions extends \core\task\scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('checkconditionstask', 'local_enrolconditions');
    }

    #[\Override]
    public function execute() {
        $enforcer = new enforcer();
        if (!$enforcer->get_plugins()) {
            mtrace('No enabled enrolment method re-checks conditions.');
            return;
        }
        $limit = (int) get_config('local_enrolconditions', 'checkbatchsize') ?: 500;
        $outcomes = $enforcer->check_batch($limit, new \text_progress_trace());
        mtrace(count($outcomes) . ' enrolments checked.');
    }
}
