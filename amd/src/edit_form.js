/**
 * Form controller for the Edit Form of the UCSF SIS enrolment plugin.
 *
 * @package enrol_ucsfsis
 * @module enrol_ucsfsis/edit_form
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str' ], function($, Ajax, Notification, Str ) {

  return {
    courseId: null,
    courses: {},
    subjects: {},
    termIds: [],
    selectedTermId: null,
    selectedSubjectId: null,
    selectedCourseId:null,
    coursesDefaultOptionText: '',
    subjectsDefaultOptionText: '',

    init: function(
      courseId,
      termIds,
      selectedTermId,
      subjects,
      selectedSubjectId,
      courses,
      selectedCourseId,
      subjectDefaultOptionsText,
      coursesDefaultOptionText
    ) {
      var course, i, n;

      this.courseId = courseId;
      this.selectedTermId = selectedTermId;
      this.selectedSubjectId = selectedSubjectId;
      this.selectedCourseId = selectedCourseId;
      this.subjectsDefaultOptionText = subjectDefaultOptionsText;
      this.coursesDefaultOptionText = coursesDefaultOptionText;

      this.subjects[selectedTermId] = subjects;
      this.courses[selectedTermId] = {};

      for(i = 0, n = courses.length; i < n; i++) {
        course = courses[i];
        if (! this.courses[selectedTermId].hasOwnProperty(course.subjectId)) {
          this.courses[selectedTermId][course.subjectId] = [];
        }
        this.courses[selectedTermId][course.subjectId].push(course);
      }

      $('#id_selectterm').change($.proxy(this, 'changeTerm'));
      $('#id_selectsubject').change($.proxy(this, 'changeSubject'));
      this.enableForm();
    },

    changeSubject: function(event) {
      var subjectId = $(event.target).find(":selected").val();
      var courseSelect = $('#id_selectcourse');
      var courses = this.courses[this.selectedTermId][subjectId];
      courseSelect.children().remove().end();
      courseSelect.append($('<option>', {
        value: '',
        text: this.coursesDefaultOptionText
      }));
      $.each(courses, function(i, course) {
        courseSelect.append($('<option>', {
          value: course.id,
          text: course.title,
        }));
      });
    },

    changeTerm: function(event) {
      var termId = $(event.target).find(":selected").val();
      this.disableForm();
      Ajax.call([{
        methodname: 'enrol_ucsfsis_get_subjects_and_courses_by_term',
        args: {courseid: this.courseId, termid: termId},
        done: this.processResponse.bind(this),
        fail: Notification.exception
      }]);
    },

    processResponse: function(response) {
      console.log(response);
      this.enableForm();
    },

    enableForm: function(){
      $('#id_selectstatus').prop('disabled', false);
      $('#id_selectterm').prop('disabled', false);
      $('#id_selectcourse').prop('disabled', false);
      $('#id_selectsubject').prop('disabled', false);
      $('#id_roleid').prop('disabled', false);
      $('#id_submitbutton').prop('disabled', false);
    },

    disableForm: function(){
      $('#id_selectstatus').prop('disabled', true);
      $('#id_selectterm').prop('disabled', true);
      $('#id_selectcourse').prop('disabled', true);
      $('#id_selectsubject').prop('disabled', true);
      $('#id_roleid').prop('disabled', true);
      $('#id_submitbutton').prop('disabled', true);
    }
  }
});
