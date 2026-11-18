# Changelog

All notable changes to the Enrolment conditions plugin will be documented in this file.

## 1.0.1 - 2026-11-18

### Changed
- README and documentation point to the repository renamed to `moodle-by-kelsoncm/moodle-local_enrolconditions`.
- New documentation site; the README shows the plugin icon and screenshots.
- The documentation folder (`docs/`) stays out of the plugin ZIP.

### Fixed
- The PHPDoc of `base::get_events()` says that `enrol_conditional` observes every event, so condition plugins need no `db/events.php` for them.

## 1.0.0 - 2026-11-09

### Added
- Subplugin type `enrolcond` (folder `condition/`) with plugin info, enable/disable and settings pages.
- Base class `\local_enrolconditions\condition\base` and `\local_enrolconditions\condition\manager`.
- Hook `\local_enrolconditions\hook\collect_conditions`, so other plugins can provide conditions.
- Condition management page under *Site administration > Plugins > Enrolments*.
- Conditions `enrolcond_date` (period), `enrolcond_profile` (standard or custom profile field), `enrolcond_cohort` (cohort member, re-checked on cohort membership events) and `enrolcond_coursecompleted` (another course completed, re-checked on `course_completed`).
- Conditions `enrolcond_userlist` (usernames typed in the condition), `enrolcond_userfile` (CSV or text file kept in the course, replaceable, with a daily cleanup of unused files and a privacy provider) and `enrolcond_sqlquery` (lists defined by administrators as SELECT queries returning `username`, cached, never run inside an open transaction).
- A condition that throws an exception is unknown: it blocks new self enrolments and never suspends.
- `base::config_to_form_data_in_context()` and `base::config_after_submission()`, for conditions that keep data outside their configuration.
- `base::restore_config()` and `tree::for_restore()`: on restores from another site, the cohort and course completed conditions point to nothing and fail until edited.
