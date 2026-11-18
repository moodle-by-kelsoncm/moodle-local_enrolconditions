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
 * Strings for local_enrolconditions.
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cachedef_observedevents'] = 'Events observed by the conditions of the enrolment instances that re-check on events';
$string['checkbatchsize'] = 'Enrolments per periodic check';
$string['checkbatchsize_desc'] = 'Maximum number of enrolments re-checked by each run of the scheduled task. The next run continues where the previous one stopped.';
$string['checkconditionstask'] = 'Re-check the conditions of existing enrolments';
$string['checkmodeenrol'] = 'When the user enrols (always)';
$string['checkmodeevents'] = 'When something the conditions depend on changes';
$string['checkmodelogin'] = 'When the user logs in';
$string['checkmodeperiodic'] = 'Periodically, in the scheduled task';
$string['checkmodes'] = 'Check the conditions';
$string['checkmodes_help'] = 'The conditions are always checked when a user enrols. Existing enrolments can also be re-checked:

* When the user logs in.
* Periodically, by the scheduled task, in batches.
* When something the conditions depend on changes, for example the user profile or a cohort membership. Each condition type declares the events it depends on.

When a re-check finds that the conditions are no longer met, the action below is applied.';
$string['conditionaction'] = 'When the conditions are no longer met';
$string['conditionaction_help'] = 'What happens to an existing enrolment when a re-check finds that the user no longer meets the conditions.

* Nothing: it is only recorded in the logs, once until the conditions are met again.
* Suspend: the enrolment is suspended.
* Remove: the user is unenrolled. Some user data in the course may be lost.

When a condition cannot be evaluated (for example, its type was uninstalled), the enrolment never changes.

Changing this setting does not undo what was already done: enrolments suspended because of the conditions stay suspended until the user meets them again and reactivation is enabled.';
$string['conditionactionnone'] = 'Nothing (only record it)';
$string['conditionactionremove'] = 'Remove the enrolment';
$string['conditionactionsuspend'] = 'Suspend the enrolment';
$string['conditiondisabled'] = 'Condition "{$a}" disabled.';
$string['conditionenabled'] = 'Condition "{$a}" enabled.';
$string['conditionnotinstalled'] = 'The condition "{$a}" is not installed.';
$string['conditionreactivate'] = 'Reactivate when the conditions are met again';
$string['conditionreactivate_help'] = 'If enabled, enrolments suspended because of the conditions are reactivated when the user meets the conditions again. Removing all the conditions counts as meeting them.

Suspensions made by other people or processes are never reactivated. If someone else reactivates an enrolment suspended because of the conditions, or changes it directly in the database, it is no longer reactivated. Changing only its dates, while it stays suspended, keeps it.';
$string['editoraddcondition'] = 'Add condition';
$string['editoraddgroup'] = 'Add group';
$string['editorall'] = 'All of these';
$string['editorany'] = 'Any of these';
$string['editorchoosetype'] = 'Condition type';
$string['editoredit'] = 'Edit';
$string['editorempty'] = 'No conditions.';
$string['editornoscript'] = 'The condition editor needs JavaScript. The saved conditions are kept unchanged.';
$string['editornot'] = 'Must not be met';
$string['editoroperator'] = 'How to combine the items of this group';
$string['editorremove'] = 'Remove';
$string['editorremovegroup'] = 'Remove group';
$string['editorunknowntype'] = 'The condition type "{$a}" is not installed. Self enrolment is refused while it is in use.';
$string['eventconditionsnotmet'] = 'Enrolment conditions no longer met';
$string['eventuserenrolmentreactivated'] = 'User enrolment reactivated by conditions';
$string['eventuserenrolmentsuspended'] = 'User enrolment suspended by conditions';
$string['failurenot'] = 'You must not meet this condition: {$a}';
$string['failurenotgroup'] = 'You meet a group of conditions that must not be met.';
$string['failureoneof'] = 'You must meet at least one of the following:';
$string['failureunknown'] = 'A condition can no longer be checked. Contact the course teacher.';
$string['manageconditions'] = 'Manage conditions';
$string['pluginname'] = 'Enrolment conditions';
$string['privacy:metadata:state'] = 'Actions taken when the conditions of an enrolment were no longer met. Enrolments are stored by core.';
$string['privacy:metadata:state:action'] = 'Action taken: none (only recorded) or suspend.';
$string['privacy:metadata:state:closereason'] = 'Why the record stopped applying.';
$string['privacy:metadata:state:enrol'] = 'The enrolment method.';
$string['privacy:metadata:state:reason'] = 'Conditions that were not met.';
$string['privacy:metadata:state:timeclosed'] = 'When the record stopped applying.';
$string['privacy:metadata:state:timereactivated'] = 'When the enrolment was reactivated.';
$string['privacy:metadata:state:timesuspended'] = 'When the action was taken.';
$string['privacy:metadata:state:userenrolmentid'] = 'The enrolment of the user.';
$string['privacy:path:state'] = 'Condition actions';
$string['settings'] = 'Re-checks';
$string['subplugintype_enrolcond'] = 'Condition';
$string['subplugintype_enrolcond_plural'] = 'Conditions';
$string['treebadnode'] = 'Invalid node at position {$a}.';
$string['treeconditionerror'] = '{$a->name}: {$a->error}';
$string['treeinvalid'] = 'Invalid condition tree: {$a}';
$string['treeinvalidjson'] = 'The conditions are not valid JSON.';
$string['treeroot'] = 'root';
$string['treerootnotgroup'] = 'The root of the conditions must be a group.';
$string['treetoodeep'] = 'Groups can be nested at most {$a} levels deep.';
$string['treetoomanynodes'] = 'At most {$a} conditions and groups are allowed.';
$string['treeunknowntype'] = 'The condition type "{$a}" is not available.';
