Privacy
=======

No data leaves Moodle.

* ``local_enrolconditions`` stores, for each enrolment suspended or recorded because its conditions were no longer met, the enrolment method, the enrolment, the action, the reason and the dates. These records are exported and deleted with the course or the user, through the Privacy API. The condition trees belong to the enrolment methods that use them.
* ``enrolcond_userfile`` finds the users listed in the files of usernames: a data request of those users exports the names of the files, and a deletion removes their line from the files.
* The other condition types store no personal data. The lists from a query read data that is already in Moodle; only administrators define them (see :doc:`administration`).
