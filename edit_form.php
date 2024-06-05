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

        $selectedterm
            = $selectedsubject
            = $selectedcourse
            = '';

        $terms = [];
        // Load Term options
        $rawterms = [];
        if (!$sisisdown) {
            $rawterms = $http->get_active_terms();
        }
        if (empty($rawterms)) {
            $sisisdown = true;
            $termoptions = ['' => get_string('choosedots')];
        } else {
            foreach($rawterms as $rawterm) {
                $time = time();
                $cleanterm = enrol_ucsfsis_simplify_sis_term($rawterm, $time);
                $terms[] = $cleanterm;
                if ($cleanterm->hasStarted) {
                    if (empty($selectedterm)) {
                        $selectedterm = $cleanterm->id;
                    }
                }
                $termoptions[$cleanterm->id] = $cleanterm->title;
            }

            if ($instance->id) {
                $siscourseid = $instance->customint1;
                $siscourse = $http->get_course($siscourseid);
                $selectedterm = $siscourse->term;
                $selectedsubject = $siscourse->subjectForCorrespondTo;
                $selectedcourse = $siscourseid;
            }
            $selectedterm = isset($instance->submitted_termid) ? $instance->submitted_termid : $selectedterm;
        }

        // Display error message (setConstant and hardFreeze fields)
        if ($sisisdown) {
            $mform->addElement('html', $OUTPUT->notification(get_string('sisserverdown', 'enrol_ucsfsis'), 'notifyproblem'));
        }

        // Add header text
        $mform->addElement('header', 'general', get_string('pluginname_short', 'enrol_ucsfsis'));

        // Add 'Enable' Select box
        $options = [
            ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ];
        $mform->addElement(
            'select',
            'status',
            get_string('status', 'enrol_ucsfsis'),
            $options,
            ['disabled' => 'disabled']
        );
        $mform->addHelpButton('status', 'status', 'enrol_ucsfsis');
        $mform->addRule('status', null, 'required', null, 'client');

        if ($sisisdown) {
            $mform->hardFreeze('status', $instance->status);
        }

        // Add Term Select box
        $element = $mform->addElement(
            'select',
            'selectterm',
            get_string('term', 'enrol_ucsfsis'),
            $termoptions,
            ['disabled' => 'disabled']
        );
        $mform->addHelpButton('selectterm', 'term', 'enrol_ucsfsis');
        $element->setValue($selectedterm);
        $mform->addRule('selectterm', null, 'required', null, 'client');

        $subjects = [];
        $courses = [];
        $subjectoptions = ['' => get_string('choosesubjectdots', 'enrol_ucsfsis')];
        $courseoptions = ['' => get_string('choosecoursedots', 'enrol_ucsfsis')];

        if (!$sisisdown) {
            $rawsubjects = $http->get_subjects_in_term($selectedterm);
            if (!empty($rawsubjects)) {
                foreach ($rawsubjects as $rawsubject) {
                    $cleansubject = enrol_ucsfsis_simplify_sis_subject($rawsubject);
                    $subjects[] = $cleansubject;
                    $subjectoptions[$cleansubject->id] = $cleansubject->title;
                }
            }

            $rawcourses = $http->get_courses_in_term($selectedterm);
            if (!empty($rawcourses)) {
                foreach ($rawcourses as $rawcourse) {
                    $cleancourse = enrol_ucsfsis_simplify_sis_course($rawcourse);
                    $courses[] = $cleancourse;

                    if ($selectedsubject === $cleancourse->subjectId) {
                        if (empty($selectedcourse)) {
                            $selectedcourse = $cleancourse->id;
                        }

                        $courseoptions[" " . $cleancourse->id] = $cleancourse->title;
                    }
                }
            }
        }

        // initialize the client-side form handler with the data we've loaded so far.
        $termids = array_column($terms, 'id');
        $PAGE->requires->js_call_amd(
            'enrol_ucsfsis/edit_form',
            'init',
            [
                $course->id,
                $selectedterm,
                $subjects,
                $courses,
                get_string('choosesubjectdots', 'enrol_ucsfsis'),
                get_string('choosecoursedots', 'enrol_ucsfsis'),
            ]
        );

        $element = $mform->addElement(
            'select',
            'selectsubject',
            get_string('subject', 'enrol_ucsfsis'),
            $subjectoptions,
            ['disabled' => 'disabled']
        );
        $mform->addHelpButton('selectsubject', 'subject', 'enrol_ucsfsis');
        $element->setValue($selectedsubject);
        $mform->addRule('selectsubject', null, 'required', null, 'client');

        $element = $mform->addElement(
            'select',
            'selectcourse',
            get_string('course', 'enrol_ucsfsis'),
            $courseoptions,
            ['disabled' => 'disabled']
        );
        $mform->addHelpButton('selectcourse', 'course', 'enrol_ucsfsis');
        $element->setValue(" " . $selectedcourse);
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
            ['disabled' => 'disabled']
        );
        $mform->setDefault('roleid', $enrol->get_config('default_student_roleid'));
        $mform->addRule('roleid', null, 'required', null, 'client');

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
    public function get_submit_value($elementname) {
        return $this->_form->getSubmitValue($elementname);
    }
}
