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

use renderer_base;
use stdClass;

/**
 * Work waiting for grading, as the block shows it.
 *
 * @package    block_gradingqueue
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_list implements \renderable, \templatable {
    /** @var int Work older than this is flagged. */
    public const OVERDUE = 7 * DAYSECS;

    /**
     * Constructor.
     *
     * @param stdClass[] $items from queue::for_user()
     * @param bool $showcourse whether to name the course on each line
     * @param int $now
     */
    public function __construct(
        /** @var stdClass[] */
        private array $items,
        /** @var bool */
        private bool $showcourse,
        /** @var int */
        private int $now
    ) {
    }

    /**
     * How long the oldest piece of work has waited.
     *
     * @param int $seconds
     * @return string
     */
    private static function age(int $seconds): string {
        if ($seconds < HOURSECS) {
            return get_string('age:new', 'block_gradingqueue');
        }
        if ($seconds < DAYSECS) {
            $hours = intdiv($seconds, HOURSECS);
            return get_string($hours === 1 ? 'age:hour' : 'age:hours', 'block_gradingqueue', $hours);
        }
        $days = intdiv($seconds, DAYSECS);
        return get_string($days === 1 ? 'age:day' : 'age:days', 'block_gradingqueue', $days);
    }

    /**
     * Export for block_gradingqueue/queue.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rows = [];
        $total = 0;
        $courses = [];
        foreach ($this->items as $item) {
            $total += $item->count;
            $courses[$item->courseid] = true;
            $waited = max(0, $this->now - $item->oldest);
            $rows[] = [
                'url' => $item->url,
                'name' => $item->name,
                'type' => get_string('modulename', $item->modname),
                'coursename' => $item->coursename,
                'showcourse' => $this->showcourse,
                'waiting' => get_string('waiting', 'block_gradingqueue', $item->count),
                'age' => self::age($waited),
                'since' => userdate($item->oldest, get_string('strftimedatetimeshort', 'langconfig')),
                'overdue' => $waited >= self::OVERDUE,
                'purpose' => plugin_supports('mod', $item->modname, FEATURE_MOD_PURPOSE, MOD_PURPOSE_OTHER),
                'icon' => $output->pix_icon('monologo', '', $item->modname, ['class' => 'activityicon']),
            ];
        }
        $summary = '';
        if ($rows) {
            $summary = count($courses) > 1
                ? get_string('summary:courses', 'block_gradingqueue', ['total' => $total, 'courses' => count($courses)])
                : get_string('summary', 'block_gradingqueue', $total);
        }
        return ['rows' => $rows, 'hasrows' => (bool) $rows, 'summary' => $summary];
    }
}
