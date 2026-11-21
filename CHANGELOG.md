# Changelog

All notable changes to the Enrolment conditions plugin will be documented in this file.

## 1.2.0 - 2026-11-21

### Added
- Condition `enrolcond_courseenrolled`: users enrolled in other courses (at least one or all of them, up to 50), optionally only active enrolments; re-checked on `user_enrolment_created`, `user_enrolment_updated` and `user_enrolment_deleted`.

## 1.1.0 - 2026-11-18

### Added
- Interface `\local_enrolconditions\enforceable`: any enrolment method that implements it gets the re-checks of existing enrolments (at login, in the scheduled task and on the events observed by the conditions), the action when the conditions are no longer met (nothing, suspend or remove) and the reactivation of the enrolments suspended by the enforcer.
- Enforcer `\local_enrolconditions\local\enforcer`, observers, scheduled task *Re-check the conditions of existing enrolments* and cache of observed events, moved from `enrol_conditional` and made generic.
- Table `local_enrolconditions_state`, with the enrolment method of each record, and its Privacy API provider.
- Events `user_enrolment_suspended`, `user_enrolment_reactivated` and `conditions_not_met`, with the enrolment method in `other['enrol']`.
- Form helper `\local_enrolconditions\output\enforcement_form` for the action, the reactivation and the check modes.
- Settings page *Re-checks* with *Enrolments per periodic check*.
- Documentation: *Using the engine from another enrolment method*.

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
