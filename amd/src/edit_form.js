/**
 * Form controller for the Edit Form of the UCSF SIS enrolment plugin.
 *
 * @package enrol_ucsfsis
 * @module enrol_ucsfsis/edit_form
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str' ], function($, Ajax, Notification, Str ) {

  return {
    courses: {},
    subjects: {},
    termIds: [],
    selectedTermId: null,
    selectedSubjectId: null,
    selectedCourseId:null,
    coursesDefaultOptionText: '',
    subjectsDefaultOptionText: '',

    init: function(
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

      this.selectedTermId = selectedTermId;
      this.selectedSubjectId = selectedSubjectId;
      this.selectedCourseId = selectedCourseId;
      this.subjectsDefaultOptionText = subjectDefaultOptionsText;
      this.coursesDefaultOptionText = coursesDefaultOptionText;

      this.subjects[selectedTermId] = subjects;
      this.courses[selectedTermId] = {};

      for(i = 0, n = courses.length; i < n; i++) {
        course = courses[i];
        if (! this.courses[selectedTermId].hasOwnProperty(course.subjectForCorrespondTo)) {
          this.courses[selectedTermId][course.subjectForCorrespondTo] = [];
        }
        this.courses[selectedTermId][course.subjectForCorrespondTo].push(course);
      }

      $('#id_selectTerm').change($.proxy(this, 'changeTerm'));
      $('#id_selectsubject').change($.proxy(this, 'changeSubject'));
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
        var text = course['courseNumber'] + ': ' + course['name'];
        var instructor;
        if(course.hasOwnProperty('userForInstructorOfRecord')) {
          instructor = course['userForInstructorOfRecord'];
          text = text + ' (' + instructor['firstName'] + ' ' + instructor['lastName'] + ')'
        }
        courseSelect.append($('<option>', {
          value: course.id,
          text: text,
        }));
      });
    },

    changeTerm: function(event) {
      // @todo implement [ST 2018/08/10]
    },
  }

});
