<?php
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
 * Adds instance form
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once("lib.php");

class enrol_ucsfsis_edit_form extends moodleform {

    /**
     * @inheritdoc
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function definition() {
        global $PAGE, $OUTPUT;

        $mform  = $this->_form;

        /**
         * @var \stdClass $instance
         * @var enrol_ucsfsis_plugin $enrol
         * @var \stdClass $course
         */
        list($instance, $enrol, $course) = $this->_customdata;
        $context = context_course::instance($course->id);

        /** @var \enrol_ucsfsis\ucsfsis_oauth_client $http */
        $http  = $enrol->get_http_client();

        $sisisdown = !$http->is_logged_in();

        $selected_term
            = $selected_subject
            = $selected_course
            = '';

        // Load Term options
        $terms = array();
        if (!$sisisdown) {
            $terms = $http->get_active_terms();
        }

        if (empty($terms)) {
            $sisisdown = true;
            $termoptions = array('' => get_string('choosedots'));
        } else {
            // Load $termoptions
            foreach($terms as $term) {
                // Skip if enrollmentStartTime is in the future.
                $enrollmentStartTime = strtotime($term->fileDateForEnrollment->enrollmentStart);
                if ( time() < $enrollmentStartTime ) {
                    $termoptions[$term->id] = $term->id . ": ". $term->name
                                                  . get_string('enrolmentstartson', 'enrol_ucsfsis',  date("M j, Y", $enrollmentStartTime));
                } else {
                    if (empty($selected_term)) {
                        $selected_term = $term->id;
                    }
                    $termoptions[$term->id] = $term->id . ": ". $term->name;
                }
            }

            if ($instance->id) {
                $siscourseid = $instance->customint1;
                $siscourse = $http->get_course($siscourseid);
                $selected_term = $siscourse->term;
                $selected_subject = $siscourse->subjectForCorrespondTo;
                $selected_course = $siscourseid;
            }
            $selected_term = isset($instance->submitted_termid) ?  $instance->submitted_termid : $selected_term;
        }

        // Display error message (setConstant and hardFreeze fields)
        if ($sisisdown) {
            $mform->addElement('html', $OUTPUT->notification(get_string('sisserverdown','enrol_ucsfsis'), 'notifyproblem'));
        }

        // Add header text
        $mform->addElement('header','general', get_string('pluginname_short', 'enrol_ucsfsis'));

        // Add 'Enable' Select box
        $options = array(
            ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        );
        $mform->addElement('select', 'status', get_string('status', 'enrol_ucsfsis'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_ucsfsis');
        if ($sisisdown)
            $mform->hardFreeze('status', $instance->status);

        // Add Term Select box
        $mform->addElement('select', 'selectterm', get_string('term', 'enrol_ucsfsis'), $termoptions);
        $mform->addHelpButton('selectterm', 'term', 'enrol_ucsfsis');

        $subjects = array();
        $courses = array();
        $subjectoptions = array('' => get_string('choosesubjectdots', 'enrol_ucsfsis'));
        $courseoptions = array('' => get_string('choosecoursedots', 'enrol_ucsfsis'));

        if (!$sisisdown) {
            $subjects = $http->get_subjects_in_term($selected_term);
            if (!empty($subjects)) {
                foreach ($subjects as $subject) {
                    $subjectoptions[$subject->id] = $subject->code . ": " . $subject->name . " (" . $subject->id . ")";
                }
            }

            $courses = $http->get_courses_in_term($selected_term);
            if (!empty($courses) && $selected_subject) {
                foreach ($courses as $course) {
                    if ($selected_subject !== $course->subjectForCorrespondTo) {
                        continue;
                    }

                    if (empty($selected_course)) {
                        $selected_course = $course->id;
                    }

                    $instructorname = '';
                    if (!empty($course->userForInstructorOfRecord)) {
                        $instr = $course->userForInstructorOfRecord;
                        $instructorname = " ($instr->firstName $instr->lastName)";
                    }
                    $courseoptions[" " . $course->id]
                        = $course->courseNumber . ": " . $course->name . $instructorname;
                }
            }
        }

        // initialize the client-side form handler with the data we've loaded so far.
        $term_ids = array_column($terms, 'id');
        $PAGE->requires->js_call_amd(
            'enrol_ucsfsis/edit_form',
            'init',
            array(
                $term_ids,
                $selected_term,
                $subjects,
                $selected_subject,
                $courses,
                $selected_course,
                get_string('choosesubjectdots', 'enrol_ucsfsis'),
                get_string('choosecoursedots', 'enrol_ucsfsis'),
            )
        );

        $element = $mform->addElement('select', 'selectsubject', get_string('subject', 'enrol_ucsfsis'), $subjectoptions);
        $mform->addHelpButton('selectsubject', 'subject', 'enrol_ucsfsis');
        $element->setValue($selected_subject);

        $element = $mform->addElement('select', 'selectcourse', get_string('course', 'enrol_ucsfsis'), $courseoptions);
        $mform->addHelpButton('selectcourse', 'course', 'enrol_ucsfsis');
        $element->setValue(" " . $selected_course);

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $enrol->get_config('default_student_roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('assignrole', 'role'), $roles);
        $mform->setDefault('roleid', $enrol->get_config('default_student_roleid'));

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        if ($instance->id) {
            $this->add_action_buttons(true);
        } else {
            $this->add_action_buttons(true, get_string('addinstance', 'enrol'));
        }

        $this->set_data($instance);
    }
}
