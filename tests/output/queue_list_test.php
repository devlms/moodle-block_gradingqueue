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

namespace block_gradingqueue\output;

/**
 * Tests for the data behind the block's list.
 *
 * @package    block_gradingqueue
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_gradingqueue\output\queue_list
 */
final class queue_list_test extends \advanced_testcase {
    /** @var int A fixed "now". */
    private const NOW = 1800000000;

    /**
     * A queue item as queue::for_user() returns it.
     *
     * @param int $courseid
     * @param string $modname
     * @param int $count
     * @param int $age seconds waiting
     * @return \stdClass
     */
    private function item(int $courseid, string $modname, int $count, int $age): \stdClass {
        return (object) ['courseid' => $courseid, 'coursename' => "Course $courseid", 'cmid' => $courseid * 10 + $count,
            'modname' => $modname, 'name' => "Work $count", 'url' => '#', 'count' => $count, 'oldest' => self::NOW - $age];
    }

    /**
     * Export a list.
     *
     * @param \stdClass[] $items
     * @param bool $showcourse
     * @return array
     */
    private function export(array $items, bool $showcourse = true): array {
        global $PAGE;
        $list = new queue_list($items, $showcourse, self::NOW);
        return json_decode(json_encode($list->export_for_template($PAGE->get_renderer('core'))), true);
    }

    public function test_summary_counts_work_and_courses(): void {
        $data = $this->export([
            $this->item(1, 'assign', 3, 9 * DAYSECS),
            $this->item(1, 'quiz', 1, 2 * HOURSECS),
            $this->item(2, 'assign', 2, 30),
        ]);
        $this->assertSame('6 to grade in 2 courses', $data['summary']);
        $this->assertSame('3 waiting', $data['rows'][0]['waiting']);
        $this->assertSame('Oldest 9 days', $data['rows'][0]['age']);
        $this->assertTrue($data['rows'][0]['overdue']);
        $this->assertSame('Oldest 2 hours', $data['rows'][1]['age']);
        $this->assertFalse($data['rows'][1]['overdue']);
        $this->assertSame('Just in', $data['rows'][2]['age']);
        $this->assertSame('Quiz', $data['rows'][1]['type']);
        $this->assertTrue($data['rows'][0]['showcourse']);
    }

    public function test_one_course_and_one_item(): void {
        $data = $this->export([$this->item(1, 'assign', 1, DAYSECS + 5)], false);
        $this->assertSame('1 to grade', $data['summary']);
        $this->assertSame('Oldest 1 day', $data['rows'][0]['age']);
        $this->assertFalse($data['rows'][0]['showcourse']);
    }

    public function test_empty(): void {
        $data = $this->export([]);
        $this->assertFalse($data['hasrows']);
        $this->assertSame('', $data['summary']);
    }
}
