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

namespace block_gradingqueue;

#[\PHPUnit\Framework\Attributes\CoversClass(\block_gradingqueue::class)]
/**
 * Tests for the block class.
 *
 * @package    block_gradingqueue
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_gradingqueue
 */
final class block_test extends \advanced_testcase {
    /**
     * A block instance on a page for a course (or the site).
     *
     * @param \stdClass $course
     * @return \block_gradingqueue
     */
    private function block(\stdClass $course): \block_gradingqueue {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/gradingqueue/block_gradingqueue.php');
        $block = new \block_gradingqueue();
        $page = new \moodle_page();
        $page->set_course($course);
        $page->set_url('/my/');
        $block->page = $page;
        return $block;
    }

    public function test_content_for_teacher_and_guest(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectMessages();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Biology']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'name' => 'Essay 1']);
        $DB->insert_record('assign_submission', (object) ['assignment' => $assign->id, 'userid' => $student->id,
            'timecreated' => 1000, 'timemodified' => 1000, 'status' => 'submitted', 'groupid' => 0, 'attemptnumber' => 0,
            'latest' => 1]);

        $this->setUser($teacher);
        $text = $this->block(get_site())->get_content()->text;
        $this->assertStringContainsString('Essay 1', $text);
        $this->assertStringContainsString('Biology', $text, 'The Dashboard names the course.');
        $this->assertStringNotContainsString('Biology', $this->block($course)->get_content()->text, 'A course page does not.');

        $this->setGuestUser();
        $this->assertSame('', $this->block(get_site())->get_content()->text);
    }

    public function test_settings(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/gradingqueue/block_gradingqueue.php');
        $block = new \block_gradingqueue();
        $block->init();
        $this->assertSame('Needs grading', $block->title);
        $this->assertFalse($block->instance_allow_multiple());
        $this->assertTrue($block->applicable_formats()['my']);
    }
}
