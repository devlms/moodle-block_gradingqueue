# block_gradingqueue design (2026-11-04)

Demand: moodle.org threads 469844, 465575, 468722, 473959 (one list of ungraded work across courses);
block_grade_me stops at Moodle 4.5. Assign rule mirrors assign::count_submissions_need_grading, batched
across all of a user's gradable assignments in one query, active enrolments only, team submissions
(userid 0) excluded. Quiz rule: finished, non-preview attempts with NULL sumgrades. No data stored.
