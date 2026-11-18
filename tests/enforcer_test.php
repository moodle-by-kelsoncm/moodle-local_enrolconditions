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

use local_enrolconditions\condition\manager;
use local_enrolconditions\local\enforcer;
use local_enrolconditions\output\enforcement_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixtures/fake_condition.php');
require_once(__DIR__ . '/fixtures/fake_enforceable.php');

/**
 * Tests of the generic enforcer with an enrolment method other than enrol_conditional.
 *
 * The fake enrolment method keeps its configuration in other columns and lets the enforcer filter the instances
 * itself; the fake condition is met when the user id is in config['userids'].
 *
 * @package    local_enrolconditions
 * @copyright  2026 Kelson Medeiros <kelsoncm@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(enforcer::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(enforcement_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\user_enrolment_suspended::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\user_enrolment_reactivated::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(event\conditions_not_met::class)]
final class enforcer_test extends \advanced_testcase {
    /** @var \local_enrolconditions_fake_enforceable */
    protected \local_enrolconditions_fake_enforceable $plugin;

    /** @var enforcer */
    protected enforcer $enforcer;

    /** @var \stdClass Enrolled user. */
    protected \stdClass $user;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        manager::reset_caches();
        manager::register_for_testing('fake', new \enrolcond_fake\condition());
        $this->plugin = new \local_enrolconditions_fake_enforceable();
        $this->enforcer = new enforcer(['manual' => $this->plugin]);
        $this->user = $this->getDataGenerator()->create_user();
    }

    #[\Override]
    protected function tearDown(): void {
        manager::reset_caches();
        parent::tearDown();
    }

    /**
     * Creates an instance in a new course whose tree accepts the given users, and enrols the test user.
     *
     * @param int $action One of the enforceable::ACTION_ constants.
     * @param int[] $allowed Users that meet the conditions.
     * @param int $modes Check modes.
     * @param bool $reactivate
     * @return \stdClass Instance.
     */
    protected function create_instance(int $action, array $allowed, int $modes = 0, bool $reactivate = true): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        // Manual enrolments allow one instance per course.
        $DB->delete_records('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $id = $this->plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customtext1' => $this->tree($allowed),
            'customint7' => $modes,
            'customint8' => $action | ($reactivate ? 16 : 0),
        ]);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        $this->plugin->enrol_user($instance, $this->user->id);
        return $instance;
    }

    /**
     * Condition tree with the fake condition.
     *
     * @param int[] $allowed
     * @return string
     */
    protected function tree(array $allowed): string {
        return json_encode(['op' => 'and', 'children' => [['type' => 'fake', 'config' => ['userids' => $allowed]]]]);
    }

    /**
     * Changes the tree of an instance.
     *
     * @param \stdClass $instance Changed in place.
     * @param int[] $allowed
     */
    protected function set_allowed(\stdClass $instance, array $allowed): void {
        global $DB;
        $instance->customtext1 = $this->tree($allowed);
        $DB->set_field('enrol', 'customtext1', $instance->customtext1, ['id' => $instance->id]);
    }

    /**
     * Status of the user enrolment.
     *
     * @param \stdClass $instance
     * @return int|null Null when there is no enrolment.
     */
    protected function ue_status(\stdClass $instance): ?int {
        global $DB;
        $status = $DB->get_field('user_enrolments', 'status', ['enrolid' => $instance->id, 'userid' => $this->user->id]);
        return $status === false ? null : (int) $status;
    }

    public function test_suspends_and_reactivates(): void {
        global $DB;
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);

        $sink = $this->redirectEvents();
        $this->assertSame(enforcer::OUTCOME_SUSPENDED, $this->enforcer->check_user($instance, $this->user->id));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\user_enrolment_suspended));
        $sink->close();
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
        $state = $DB->get_record(enforcer::TABLE, ['instanceid' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('manual', $state->enrol);
        $this->assertSame(enforcer::STATE_SUSPEND, $state->action);
        $this->assertStringContainsString('Not a listed user', $state->reason);
        $this->assertCount(1, $events);
        $this->assertSame('manual', $events[0]->other['enrol']);
        $this->assertNotEmpty($events[0]->get_name());

        $this->set_allowed($instance, [(int) $this->user->id]);
        $sink = $this->redirectEvents();
        $this->assertSame(enforcer::OUTCOME_REACTIVATED, $this->enforcer->check_user($instance, $this->user->id));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\user_enrolment_reactivated));
        $sink->close();
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertCount(1, $events);
        $this->assertSame(enforcer::CLOSE_REACTIVATED, $DB->get_field(enforcer::TABLE, 'closereason', ['id' => $state->id]));
    }

    public function test_does_not_reactivate_suspension_of_others(): void {
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $this->plugin->update_user_enrol($instance, $this->user->id, ENROL_USER_SUSPENDED);

        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->set_allowed($instance, [(int) $this->user->id]);
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($instance));
    }

    public function test_records_and_removes(): void {
        $recorded = $this->create_instance(enforceable::ACTION_NONE, []);
        $this->assertSame(enforcer::OUTCOME_RECORDED, $this->enforcer->check_user($recorded, $this->user->id));
        $this->assertSame(enforcer::OUTCOME_NOTHING, $this->enforcer->check_user($recorded, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($recorded));

        $removed = $this->create_instance(enforceable::ACTION_REMOVE, []);
        $sink = $this->redirectEvents();
        $this->assertSame(enforcer::OUTCOME_REMOVED, $this->enforcer->check_user($removed, $this->user->id));
        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof event\conditions_not_met));
        $sink->close();
        $this->assertNull($this->ue_status($removed));
        $this->assertSame('remove', $events[0]->other['action']);
    }

    public function test_unknown_and_unhandled_change_nothing(): void {
        global $DB;
        $instance = $this->create_instance(enforceable::ACTION_SUSPEND, []);
        $DB->set_field('enrol', 'customtext1', '{"op":', ['id' => $instance->id]);
        $instance->customtext1 = '{"op":';
        $this->assertSame(enforcer::OUTCOME_UNKNOWN, $this->enforcer->check_user($instance, $this->user->id));
        $this->assertDebuggingCalled();

        // An enforcer without the method leaves its enrolments alone.
        $this->set_allowed($instance, []);
        $this->assertSame(enforcer::OUTCOME_NOTHING, (new enforcer([]))->check_user($instance, $this->user->id));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($instance));
        $this->assertNull(enforcer::evaluate('', $this->user->id, $instance));
    }

    public function test_check_user_in_mode_filters_instances(): void {
        $login = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);
        $periodic = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);

        $this->assertSame([], $this->enforcer->check_user_in_mode($this->user->id, enforceable::CHECK_LOGIN, 'self'));
        $outcomes = $this->enforcer->check_user_in_mode($this->user->id, enforceable::CHECK_LOGIN, 'manual');
        $this->assertSame([enforcer::OUTCOME_SUSPENDED], array_values($outcomes));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($login));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($periodic));
    }

    public function test_check_batch_keeps_position(): void {
        set_config('periodiccursor', 0, 'local_enrolconditions');
        $first = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);
        $second = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_PERIODIC);
        $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);

        $this->assertCount(1, $this->enforcer->check_batch(1));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($first));
        $this->assertSame(ENROL_USER_ACTIVE, $this->ue_status($second));
        $this->assertNotEquals(0, (int) get_config('local_enrolconditions', 'periodiccursor'));

        $this->assertCount(1, $this->enforcer->check_batch(5));
        $this->assertSame(ENROL_USER_SUSPENDED, $this->ue_status($second));
        $this->assertSame(0, (int) get_config('local_enrolconditions', 'periodiccursor'));
    }

    public function test_event_map(): void {
        $events = $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_EVENTS);
        $this->create_instance(enforceable::ACTION_SUSPEND, [], enforceable::CHECK_LOGIN);
        enforcer::purge_event_map();
        $this->assertSame(['core\event\user_updated' => [(int) $events->id]], $this->enforcer->get_event_map());
        $this->assertSame([], (new enforcer([]))->get_event_map());
    }

    public function test_form_submitted_values(): void {
        $fields = ['conditionaction' => '2', 'checkmodeevents' => '1', 'checkmodelogin' => '0', 'name' => 'x'];
        $submitted = enforcement_form::get_submitted($fields, enforceable::ACTION_SUSPEND, true, enforceable::CHECK_LOGIN);
        $this->assertSame([
            'action' => enforceable::ACTION_REMOVE,
            'reactivate' => true,
            'modes' => enforceable::CHECK_ENROL | enforceable::CHECK_EVENTS,
            'sent' => true,
        ], $submitted);
        $this->assertSame(['name' => 'x'], $fields);

        $fields = [];
        $this->assertFalse(enforcement_form::get_submitted($fields, 0, false, 0)['sent']);
        $this->assertCount(3, enforcement_form::get_action_options());
    }
}
