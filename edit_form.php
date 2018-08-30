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

require_once $CFG->libdir . '/formslib.php';
require_once 'lib.php';
require_once 'locallib.php';

class enrol_ucsfsis_edit_form extends moodleform {

    /**
     * @inheritdoc
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function definition() {
        global $PAGE, $OUTPUT;

        $mform = $this->_form;

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

        $terms = array();
        // Load Term options
        $rawTerms = array();
        if (!$sisisdown) {
            $rawTerms = $http->get_active_terms();
        }
        if (empty($rawTerms)) {
            $sisisdown = true;
            $termoptions = array('' => get_string('choosedots'));
        } else {
            foreach($rawTerms as $rawTerm) {
                $time = time();
                $cleanTerm = enrol_ucsfsis_simplify_sis_term($rawTerm, $time);
                $terms[] = $cleanTerm;
                if ($cleanTerm->hasStarted) {
                    if (empty($selected_term)) {
                        $selected_term = $cleanTerm->id;
                    }
                }
                $termoptions[$cleanTerm->id] = $cleanTerm->title;
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
        $mform->addElement(
            'select',
            'status',
            get_string('status', 'enrol_ucsfsis'),
            $options,
            array('disabled' => 'disabled')
        );
        $mform->addHelpButton('status', 'status', 'enrol_ucsfsis');
        if ($sisisdown)
            $mform->hardFreeze('status', $instance->status);

        // Add Term Select box
        $element = $mform->addElement(
            'select',
            'selectterm',
            get_string('term', 'enrol_ucsfsis'),
            $termoptions,
            array('disabled' => 'disabled')
        );
        $mform->addHelpButton('selectterm', 'term', 'enrol_ucsfsis');
        $element->setValue($selected_term);

        $subjects = array();
        $courses = array();
        $subjectoptions = array('' => get_string('choosesubjectdots', 'enrol_ucsfsis'));
        $courseoptions = array('' => get_string('choosecoursedots', 'enrol_ucsfsis'));

        if (!$sisisdown) {
            $rawSubjects = $http->get_subjects_in_term($selected_term);
            if (!empty($rawSubjects)) {
                foreach ($rawSubjects as $rawSubject) {
                    $cleanSubject = enrol_ucsfsis_simplify_sis_subject($rawSubject);
                    $subjects[] = $cleanSubject;
                    $subjectoptions[$cleanSubject->id] = $cleanSubject->title;
                }
            }

            $rawCourses = $http->get_courses_in_term($selected_term);
            if (!empty($rawCourses)) {
                foreach ($rawCourses as $rawCourse) {
                    $cleanCourse = enrol_ucsfsis_simplify_sis_course($rawCourse);
                    $courses[] = $cleanCourse;

                    if ($selected_subject === $cleanCourse->subjectId) {
                        if (empty($selected_course)) {
                            $selected_course = $cleanCourse->id;
                        }

                        $courseoptions[" " . $cleanCourse->id] = $cleanCourse->title;
                    }
                }
            }
        }

        // initialize the client-side form handler with the data we've loaded so far.
        $term_ids = array_column($terms, 'id');
        $PAGE->requires->js_call_amd(
            'enrol_ucsfsis/edit_form',
            'init',
            array(
                $course->id,
                $selected_term,
                $subjects,
                $courses,
                get_string('choosesubjectdots', 'enrol_ucsfsis'),
                get_string('choosecoursedots', 'enrol_ucsfsis'),
            )
        );

        $element = $mform->addElement(
            'select',
            'selectsubject',
            get_string('subject', 'enrol_ucsfsis'),
            $subjectoptions,
            array('disabled' => 'disabled')
        );
        $mform->addHelpButton('selectsubject', 'subject', 'enrol_ucsfsis');
        $element->setValue($selected_subject);

        $element = $mform->addElement(
            'select',
            'selectcourse',
            get_string('course', 'enrol_ucsfsis'),
            $courseoptions,
            array('disabled' => 'disabled')
        );
        $mform->addHelpButton('selectcourse', 'course', 'enrol_ucsfsis');
        $element->setValue(" " . $selected_course);
        $mform->addRule('selectcourse', null, 'required', null, 'client');
        $mform->addRule('selectcourse', null, 'required', null, 'server');

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $enrol->get_config('default_student_roleid'));
        }
        $mform->addElement(
            'select',
            'roleid',
            get_string('assignrole', 'role'),
            $roles,
            array('disabled' => 'disabled')
        );
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

    /**
     * HAAAAACKS!
     * We need to retrieve the raw, user-submitted data from the internal QuickForm object
     * for form-submission processing further downstream.
     * [ST 2018/08/21]
     * @param string $elementName
     * @return mixed
     * @see QuickForm::getSubmitValue
     */
    public function getSubmitValue($elementName)
    {
        return $this->_form->getSubmitValue($elementName);
    }
}
