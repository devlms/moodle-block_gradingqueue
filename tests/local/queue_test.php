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

namespace block_gradingqueue\local;

/**
 * Tests for the grading queue.
 *
 * @package    block_gradingqueue
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_gradingqueue\local\queue
 */
final class queue_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass */
    private \stdClass $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();
        $this->course = $this->getDataGenerator()->create_course(['fullname' => 'Biology']);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * An assignment with online text submissions, no drafts.
     *
     * @param \stdClass|null $course
     * @param string $name
     * @return \stdClass
     */
    private function assignment(?\stdClass $course = null, string $name = 'Essay 1'): \stdClass {
        return $this->getDataGenerator()->create_module('assign', [
            'course' => ($course ?? $this->course)->id,
            'name' => $name,
            'assignsubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 0,
        ]);
    }

    /**
     * Submit to an assignment as a new student, at a given time.
     *
     * @param \stdClass $assign
     * @param int $time
     * @param \stdClass|null $student
     * @return \stdClass the student
     */
    private function submit(\stdClass $assign, int $time, ?\stdClass $student = null): \stdClass {
        global $DB;
        $student = $student ?? $this->getDataGenerator()->create_and_enrol(get_course($assign->course), 'student');
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_submission([
            'userid' => $student->id,
            'cmid' => $assign->cmid,
            'onlinetext_editor' => ['text' => 'My answer', 'format' => FORMAT_HTML],
            'status' => 'submitted',
        ]);
        $DB->set_field('assign_submission', 'status', 'submitted', ['assignment' => $assign->id, 'userid' => $student->id]);
        $DB->set_field('assign_submission', 'timemodified', $time, ['assignment' => $assign->id, 'userid' => $student->id]);
        return $student;
    }

    /**
     * Grade a student's latest submission.
     *
     * @param \stdClass $assign
     * @param \stdClass $student
     */
    private function grade(\stdClass $assign, \stdClass $student): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->setUser($this->teacher);
        $cm = get_coursemodule_from_id('assign', $assign->cmid);
        $instance = new \assign(\context_module::instance($cm->id), $cm, $this->course);
        $grade = $instance->get_user_grade($student->id, true);
        $grade->grade = 70;
        $instance->update_grade($grade);
    }

    public function test_submitted_work_is_queued_oldest_first(): void {
        $first = $this->assignment(null, 'Essay 1');
        $second = $this->assignment(null, 'Essay 2');
        $this->submit($first, 2000);
        $this->submit($first, 3000);
        $this->submit($second, 1000);
        $items = queue::for_user((int) $this->teacher->id);
        $this->assertSame(['Essay 2', 'Essay 1'], array_column($items, 'name'));
        $this->assertSame([1, 2], array_column($items, 'count'));
        $this->assertSame([1000, 2000], array_column($items, 'oldest'));
        $this->assertSame('Biology', $items[0]->coursename);
        $this->assertStringContainsString('action=grading', $items[0]->url);
    }

    public function test_graded_work_leaves_the_queue_and_resubmission_returns(): void {
        global $DB;
        $assign = $this->assignment();
        $student = $this->submit($assign, 1000);
        $this->grade($assign, $student);
        $this->assertSame([], queue::for_user((int) $this->teacher->id));
        // The student edits their submission after it was graded.
        $DB->set_field('assign_submission', 'timemodified', time() + 60, ['assignment' => $assign->id, 'userid' => $student->id]);
        $this->assertCount(1, queue::for_user((int) $this->teacher->id));
    }

    public function test_quiz_attempts_waiting_for_manual_grading(): void {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'name' => 'Final quiz',
            'sumgrades' => 1, 'grade' => 10]);
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quizgen->create_quiz_structure($quiz, [['Essay', 1, 'essay']]);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        $attempt = $quizgen->create_attempt($quiz->id, $student->id);
        $quizgen->submit_responses($attempt->id, [1 => 'My essay'], false, true);
        $items = queue::for_user((int) $this->teacher->id);
        $this->assertSame(['Final quiz'], array_column($items, 'name'));
        $this->assertStringContainsString('mode=grading', $items[0]->url);
    }

    public function test_only_activities_the_user_can_grade(): void {
        $assign = $this->assignment();
        $student = $this->submit($assign, 1000);
        $this->assertSame([], queue::for_user((int) $student->id));
        $other = $this->getDataGenerator()->create_user();
        $this->assertSame([], queue::for_user((int) $other->id));
    }

    public function test_unenrolled_students_are_not_counted(): void {
        $assign = $this->assignment();
        $student = $this->submit($assign, 1000);
        $this->submit($assign, 2000);
        $manual = enrol_get_plugin('manual');
        $instance = current(enrol_get_instances($this->course->id, true));
        $manual->update_user_enrol($instance, $student->id, ENROL_USER_SUSPENDED);
        $this->assertSame([1], array_column(queue::for_user((int) $this->teacher->id), 'count'));
    }

    public function test_filter_by_course(): void {
        $other = $this->getDataGenerator()->create_course(['fullname' => 'Chemistry']);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $other->id, 'editingteacher');
        $this->submit($this->assignment(), 1000);
        $this->submit($this->assignment($other, 'Lab report'), 2000);
        $this->assertCount(2, queue::for_user((int) $this->teacher->id));
        $this->assertSame(['Lab report'], array_column(queue::for_user((int) $this->teacher->id, (int) $other->id), 'name'));
    }
}
