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

    // @TODO Cache the api calls.  If it's in the cache, don't bother to make the call again.
    // @TODO Update Course list in definition_after_data()

    function definition() {
        global $CFG, $DB, $PAGE, $OUTPUT;

        $mform  = $this->_form;
        list($instance, $enrol, $course) = $this->_customdata;
        $context = context_course::instance($course->id);

        // TODO: Improve AJAX calls, instead of just simulate a click button.
        // $PAGE->requires->yui_module( 'moodle-enrol_ucsfsis-groupchoosers',
        //                              'M.enrol_ucsfsis.init_groupchoosers',
        //                              array(array('formid' => $mform->getAttribute('id'),
        //                                          'courseid' => $course->id)) );
        $PAGE->requires->js_init_call('M.enrol_ucsfsis.init',
                                      array(array('formid' => $mform->getAttribute('id'),
                                                  'courseid' => $course->id)));

        $http  = $enrol->get_http_client();
        $sisisdown = !$http->is_logged_in();

        // Load Term options
        if (!$sisisdown) {
            $terms = $http->get_active_terms();
        } else {
            $terms = null;
        }
        $selected_term = $selected_subject = $selected_course = '';

        // Can I refactor this part?
            if (empty($terms)) {
                $sisisdown = true;
                $termoptions = array('' => get_string('choosedots'));
                $subjectoptions = array('' => get_string('choosesubjectdots', 'enrol_ucsfsis'));
                $subjectcourseoptions[''] = array('' => get_string('choosecoursedots', 'enrol_ucsfsis'));
            } else {
                // Load $termoptions
                // $termoptions = array('' => get_string('choosedots'));
                foreach($terms as $term) {
                    // Skip if enrollmentStartTime is in the future.
                    $enrollmentStartTime = strtotime($term->fileDateForEnrollment->enrollmentStart);
                    if ( time() < $enrollmentStartTime ) {
                        $termoptions[trim($term->id)] = trim($term->id) . ": ". trim($term->name)
                                                      . get_string('enrolmentstartson', 'enrol_ucsfsis',  date("M j, Y", $enrollmentStartTime));
                    } else {
                        if (empty($selected_term)) {
                            $selected_term = trim($term->id);
                        }
                        // DEBUG: Show termStartDate to make sure it is in descending order
                        // $termoptions[trim($term->id)] = trim($term->id) . ": ". trim($term->name). " (".$term->termStartDate." to ".$term->termEndDate.")";
                        $termoptions[trim($term->id)] = trim($term->id) . ": ". trim($term->name);
                    }
                }

                if ($instance->id) {
                    $siscourseid = $instance->customint1;
                    $siscourse = $http->get_course($siscourseid);
                    $selected_term = trim($siscourse->term);
                    $selected_subject = trim($siscourse->subjectForCorrespondTo);
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

        // Display notice if this enrolment is converted from CLEAE
        if (isset($instance->customchar1) && !empty($instance->customchar1)) {
            $mform->addElement('html', $OUTPUT->notification(get_string('convertedfrom', 'enrol_ucsfsis', $instance->customchar1), 'notifysuccess'));
        }

        // Add 'Enable' Select box
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_ucsfsis'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_ucsfsis');
        if ($sisisdown)
            $mform->hardFreeze('status', $instance->status);

        // Add Term Select box
        $element = &$mform->addElement('select', 'selectterm', get_string('term', 'enrol_ucsfsis'), $termoptions);
        $mform->addHelpButton('selectterm', 'term', 'enrol_ucsfsis');
        $mform->registerNoSubmitButton('submitterm');
        $mform->addElement('submit', 'submitterm', get_string('termoptionsupdate', 'enrol_ucsfsis'));
        $element->setValue($selected_term);

        // Can I refractor this part?

            // Populate subjectoptions
            if (!$sisisdown) {
                $subjects = $http->get_subjects_in_term( $selected_term );
                $subjectoptions = array('' => get_string('choosesubjectdots', 'enrol_ucsfsis'));
                $subjectcourseoptions[''] = array('' => get_string('choosecoursedots', 'enrol_ucsfsis'));
                if (!empty($subjects)) {
                    foreach ($subjects as $subject) {
                        $subjectoptions[trim($subject->id)] = trim($subject->code) . ": " . $subject->name . " (" . $subject->id . ")";
                        $subjectcourseoptions[trim($subject->id)] = array('' => get_string('choosecoursedots', 'enrol_ucsfsis'));
                    }
                }
            }
            // Populate subjectcourseoptions
            if (!$sisisdown) {
                $courses = $http->get_courses_in_term($selected_term);
                if (!empty($courses)) {
                    foreach ($courses as $course) {
                        if (empty($selected_course)) {
                            $selected_course = trim($course->id);
                        }
                        $instructorname = '';
                        if (!empty($course->userForInstructorOfRecord)) {
                            $instr = $course->userForInstructorOfRecord;
                            $instructorname = " ($instr->firstName $instr->lastName)";
                        }
                        // $subjectcourseoptions[trim($course->subjectForCorrespondTo)]['"'.trim($course->id).'"']
                        // Course index needs to be a string (by prefixing with a space); otherwise, it will be sorted as Int.
                        $subjectcourseoptions[trim($course->subjectForCorrespondTo)][" ".trim($course->id)]
                                                                                        // = trim($course->courseNumber) . ": " . $course->name . " (" . $course->id .")";
                                                                                        = trim($course->courseNumber) . ": " . $course->name . $instructorname;
                    }
                }
            }


        $element = &$mform->addElement('hierselect', 'selectsubjectcourse', get_string('subject_course', 'enrol_ucsfsis'), '', '<br />');
        $element->setOptions(array($subjectoptions, $subjectcourseoptions));
        $mform->addGroupRule('selectsubjectcourse', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('selectsubjectcourse', 'subject_course', 'enrol_ucsfsis');
        $element->setValue(array($selected_subject, " ".$selected_course));

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

    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        return $errors;
    }
}
