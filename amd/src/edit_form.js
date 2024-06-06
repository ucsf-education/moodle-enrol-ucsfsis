// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form controller for the Edit Form of the UCSF SIS enrolment plugin.
 *
 * @module enrol_ucsfsis/edit_form
 */
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

  return {
    cache: {
      courses: {},
      subjects: {}
    },
    courseId: null,
    termId: null,
    coursesDefaultOptionText: '',
    subjectsDefaultOptionText: '',

    $termSelect: null,
    $subjectSelect: null,
    $courseSelect: null,

    init: function(
      courseId,
      termId,
      subjects,
      courses,
      subjectDefaultOptionsText,
      coursesDefaultOptionText
    ) {
      this.$termSelect = $('#id_selectterm');
      this.$subjectSelect = $('#id_selectsubject');
      this.$courseSelect = $('#id_selectcourse');
      this.courseId = courseId;
      this.termId = termId;
      this.subjectsDefaultOptionText = subjectDefaultOptionsText;
      this.coursesDefaultOptionText = coursesDefaultOptionText;

      this.cacheSubjectsAndCourses(this.termId, subjects, courses);

      this.$termSelect.change($.proxy(this, 'changeTerm'));
      this.$subjectSelect.change($.proxy(this, 'changeSubject'));
      this.enableForm();
    },

    changeSubject: function(event) {
      var subjectId = $(event.target).find(":selected").val();
      var courses = this.cache.courses[this.termId][subjectId];
      this.repopulateSelect(this.$courseSelect, courses, this.coursesDefaultOptionText);
    },

    changeTerm: function(event) {
      var termId = $(event.target).find(":selected").val();
      var that = this;

      this.disableForm();

      this.termId = termId;

      if (this.cache.subjects.hasOwnProperty(this.termId)) {
        setTimeout(function() {
          that.repopulateSelect(that.$subjectSelect, that.cache.subjects[that.termId], that.subjectsDefaultOptionText);
          that.repopulateSelect(that.$courseSelect, [], that.coursesDefaultOptionText);
          that.enableForm();
        }, 500);
      } else {
        Ajax.call([{
          methodname: 'enrol_ucsfsis_get_subjects_and_courses_by_term',
          args: {courseid: this.courseId, termid: termId},
          done: this.processResponse.bind(this, termId),
          fail: Notification.exception
        }]);
      }
    },

    processResponse: function(termId, data) {
      this.cacheSubjectsAndCourses(termId, data.subjects, data.courses);
      this.repopulateSelect(this.$subjectSelect, data.subjects, this.subjectsDefaultOptionText);
      this.repopulateSelect(this.$courseSelect, [], this.coursesDefaultOptionText);
      this.enableForm();
    },

    enableForm: function() {
      $('#id_status').prop('disabled', false);
      this.$termSelect.prop('disabled', false);
      this.$subjectSelect.prop('disabled', false);
      this.$courseSelect.prop('disabled', false);
      $('#id_roleid').prop('disabled', false);
      $('#id_submitbutton').prop('disabled', false);
    },

    disableForm: function() {
      $('#id_status').prop('disabled', true);
      this.$termSelect.prop('disabled', true);
      this.$subjectSelect.prop('disabled', true);
      this.$courseSelect.prop('disabled', true);
      $('#id_roleid').prop('disabled', true);
      $('#id_submitbutton').prop('disabled', true);
    },

    repopulateSelect: function($select, options, defaultOptionTitle) {
      $select.children().remove().end();
      $select.append($('<option>', {
        value: '',
        text: defaultOptionTitle
      }));
      $.each(options, function(i, option) {
        $select.append($('<option>', {
          value: option.id,
          text: option.title,
        }));
      });
    },

    cacheSubjectsAndCourses: function(termId, subjects, courses) {
      var course, i, n;
      this.cache.subjects[termId] = subjects;
      this.cache.courses[termId] = {};

      for (i = 0, n = courses.length; i < n; i++) {
        course = courses[i];
        if (!this.cache.courses[termId].hasOwnProperty(course.subjectId)) {
          this.cache.courses[termId][course.subjectId] = [];
        }
        this.cache.courses[termId][course.subjectId].push(course);
      }
    }
  };
});
