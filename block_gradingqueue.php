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
 * Needs grading block.
 *
 * @package    block_gradingqueue
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Shows every assignment submission and quiz attempt waiting for the user to grade, oldest first.
 * On the Dashboard it covers all courses; on a course page, that course only.
 *
 * @package    block_gradingqueue
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_gradingqueue extends block_base {
    /**
     * Set the title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_gradingqueue');
    }

    /**
     * Where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true, 'course-view' => true, 'site-index' => false, 'mod' => false];
    }

    /**
     * One per page is enough.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Build the list.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER, $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }
        $courseid = $this->page->course->id != SITEID ? (int) $this->page->course->id : 0;
        $items = \block_gradingqueue\local\queue::for_user((int) $USER->id, $courseid);
        $format = get_string('strftimedatetimeshort', 'langconfig');
        $rows = array_map(fn($i) => [
            'url' => $i->url,
            'name' => $i->name,
            'coursename' => $i->coursename,
            'showcourse' => !$courseid,
            'waiting' => get_string('waiting', 'block_gradingqueue', $i->count),
            'oldest' => get_string('oldest', 'block_gradingqueue', userdate($i->oldest, $format)),
            'icon' => $OUTPUT->pix_icon('monologo', '', $i->modname, ['class' => 'icon']),
        ], $items);
        $this->content->text = $OUTPUT->render_from_template(
            'block_gradingqueue/queue',
            ['rows' => $rows, 'hasrows' => (bool) $rows]
        );
        return $this->content;
    }
}
