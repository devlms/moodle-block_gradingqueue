# Changes

## 1.0.0 (2026-11-08)

First stable release.

- Counts follow Moodle's own rule more closely: enrolments that ended or have not started are left out, group submission assignments are left out, and quiz attempts count only for enrolled students.
- On a course page, graders who are not enrolled (managers, admins) now see that course's queue.
- Courses where the user cannot grade at course level are skipped early, so the Dashboard stays fast for students.
- Names with "&" no longer show as "&amp;".
- Screen readers hear when work has waited a week or more.
- CI covers Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 on PostgreSQL and MariaDB.

## 0.1.0 (2026-11-05)

First alpha.
