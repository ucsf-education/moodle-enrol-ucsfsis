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
 * This file processes AJAX enrolment actions and returns JSON for the UCSFSIS plugin
 *
 * The general idea behind this file is that any errors should throw exceptions
 * which will be returned and acted upon by the calling AJAX script.
 *
 * @package    enrol_ucsfsis
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @copyright  2015 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require('../../config.php');

$id      = required_param('id', PARAM_INT); // course id
$action  = required_param('action', PARAM_ALPHANUMEXT);

$PAGE->set_url(new moodle_url('/enrol/ucsfsis/ajax.php', array('id'=>$id, 'action'=>$action)));

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

if ($course->id == SITEID) {
    throw new moodle_exception('invalidcourse');
}

require_login($course);
require_capability('moodle/course:enrolreview', $context);
require_sesskey();

if (!enrol_is_enabled('ucsfsis')) {
    // This should never happen, no need to invent new error strings.
    throw new enrol_ajax_exception('errorenrolucsfsis');
}

echo $OUTPUT->header(); // Send headers.

$outcome = new stdClass();
$outcome->success = true;
$outcome->response = new stdClass();
$outcome->error = '';

$enrol = enrol_get_plugin('ucsfsis');

switch ($action) {
    case 'gettermoptions':
        require_capability('moodle/course:enrolconfig', $context);
        $termid = required_param('termid', PARAM_ALPHANUMEXT);

        $subjectoptions = array('' => get_string('choosesubjectdots', 'enrol_ucsfsis'));
        $subjectcourseoptions[''] = array('' => get_string('choosecoursedots', 'enrol_ucsfsis'));

        $http = $enrol->get_http_client();
        if ($http->is_logged_in()) {
            // $subjects = $http->get_objects('/terms/' . $termid . '/subjects', null, 'name');
            $subjects = $http->get_subjects_in_term($termid);
            if (!empty($subjects)) {
                foreach ($subjects as $subject) {
                    $subjectoptions[$subject->id] = $subject->code . ": " . $subject->name;
                    $subjectcourseoptions[$subject->id] = array('' => get_string('choosecoursedots', 'enrol_ucsfsis'));
                }
            }

            $courses = $http->get_courses_in_term($termid);
            if (!empty($courses)) {
                foreach ($courses as $course) {
                        $instructorname = '';
                        if (!empty($course->userForInstructorOfRecord)) {
                            $instr = $course->userForInstructorOfRecord;
                            $instructorname = " ($instr->firstName $instr->lastName)";
                        }
                        // Course index needs to be a string (by prefixing with a space); otherwise, it will be sorted as Int.
                        $subjectcourseoptions[$course->subjectForCorrespondTo][" ".$course->id]
                            = $course->courseNumber . ": " . $course->name . $instructorname;
                }
            }
        }

        $outcome->response = array( $subjectoptions, $subjectcourseoptions );
        break;
    default:
        throw new enrol_ajax_exception('unknowajaxaction');
}

echo json_encode($outcome);
die();
