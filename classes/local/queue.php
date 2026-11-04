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

use moodle_url;
use stdClass;

/**
 * Work waiting for grading across every course a user can grade in.
 *
 * Assignments: the latest submission is submitted and has no grade, or was changed after it was
 * graded (the rule mod_assign uses for its own "needs grading" count), from students with an active
 * enrolment. Quizzes: finished attempts with no total yet, which means a question such as an essay is
 * waiting for manual grading.
 *
 * Counts are per activity. Group restrictions are applied on the grading page the link opens.
 *
 * @package    block_gradingqueue
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queue {
    /**
     * Activities with work waiting, oldest waiting first.
     *
     * @param int $userid
     * @param int $courseid Only this course, or 0 for all.
     * @return stdClass[] courseid, coursename, cmid, modname, name, url, count, oldest
     */
    public static function for_user(int $userid, int $courseid = 0): array {
        $cms = ['assign' => [], 'quiz' => []];
        foreach (enrol_get_users_courses($userid, true) as $course) {
            if ($courseid && (int) $course->id !== $courseid) {
                continue;
            }
            $modinfo = get_fast_modinfo($course, $userid);
            foreach (['assign' => 'mod/assign:grade', 'quiz' => 'mod/quiz:grade'] as $modname => $capability) {
                foreach ($modinfo->get_instances_of($modname) as $cm) {
                    if ($cm->uservisible && has_capability($capability, $cm->context, $userid)) {
                        $cms[$modname][$cm->instance] = $cm;
                    }
                }
            }
        }

        $items = [];
        foreach (self::assign_counts(array_keys($cms['assign'])) as $row) {
            $cm = $cms['assign'][$row->instance];
            $items[] = self::item(
                $cm,
                (int) $row->total,
                (int) $row->oldest,
                new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading'])
            );
        }
        foreach (self::quiz_counts(array_keys($cms['quiz'])) as $row) {
            $cm = $cms['quiz'][$row->instance];
            $items[] = self::item(
                $cm,
                (int) $row->total,
                (int) $row->oldest,
                new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'grading'])
            );
        }
        usort($items, fn($a, $b) => [$a->oldest, $a->cmid] <=> [$b->oldest, $b->cmid]);
        return $items;
    }

    /**
     * Submissions waiting per assignment.
     *
     * @param int[] $ids Assignment ids.
     * @return stdClass[] instance, total, oldest
     */
    private static function assign_counts(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params += ['submitted' => 'submitted', 'active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED];
        return array_values($DB->get_records_sql(
            "SELECT s.assignment AS instance, COUNT(s.id) AS total, MIN(s.timemodified) AS oldest
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
          LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                                     AND g.attemptnumber = s.attemptnumber
              WHERE s.assignment $insql
                AND s.latest = 1
                AND s.userid > 0
                AND s.status = :submitted
                AND s.timemodified IS NOT NULL
                AND (g.id IS NULL OR g.grade IS NULL OR s.timemodified >= g.timemodified OR (a.grade < 0 AND g.grade = -1))
                AND EXISTS (SELECT 1
                              FROM {user_enrolments} ue
                              JOIN {enrol} e ON e.id = ue.enrolid
                             WHERE e.courseid = a.course AND ue.userid = s.userid
                               AND ue.status = :active AND e.status = :enabled)
           GROUP BY s.assignment",
            $params
        ));
    }

    /**
     * Finished attempts with no total yet, per quiz.
     *
     * @param int[] $ids Quiz ids.
     * @return stdClass[] instance, total, oldest
     */
    private static function quiz_counts(array $ids): array {
        global $DB;
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $params['finished'] = 'finished';
        return array_values($DB->get_records_sql(
            "SELECT qa.quiz AS instance, COUNT(qa.id) AS total, MIN(qa.timefinish) AS oldest
               FROM {quiz_attempts} qa
              WHERE qa.quiz $insql
                AND qa.state = :finished
                AND qa.preview = 0
                AND qa.sumgrades IS NULL
           GROUP BY qa.quiz",
            $params
        ));
    }

    /**
     * One row of the queue.
     *
     * @param \cm_info $cm
     * @param int $count
     * @param int $oldest
     * @param moodle_url $url
     * @return stdClass
     */
    private static function item(\cm_info $cm, int $count, int $oldest, moodle_url $url): stdClass {
        return (object) [
            'courseid' => (int) $cm->course,
            'coursename' => format_string($cm->get_course()->fullname, true, ['context' => $cm->context->get_course_context()]),
            'cmid' => (int) $cm->id,
            'modname' => $cm->modname,
            'name' => $cm->get_formatted_name(),
            'url' => $url->out(false),
            'count' => $count,
            'oldest' => $oldest,
        ];
    }
}
