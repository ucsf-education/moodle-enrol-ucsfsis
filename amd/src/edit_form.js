/**
 * Form controller for the Edit Form of the UCSF SIS enrolment plugin.
 *
 * @package enrol_ucsfsis
 * @module enrol_ucsfsis/edit_form
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str' ], function($, Ajax, Notification, Str ) {

  return {
    courses: [],
    subjects: [],
    termIds: [],

    init: function(termIds, selectedTermId, subjects, selectedSubjectId, courses, selectedCourseId) {
      console.log(arguments);
      this.termIds = termIds;
    }
  }
});
