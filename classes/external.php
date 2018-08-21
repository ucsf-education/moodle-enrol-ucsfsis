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
 * External UCSF SIS enrolment API.
 *
 * @package    enrol_ucsfsis
 * @category   external
 * @copyright  2018 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/externallib.php';
require_once __DIR__ . '/../locallib.php';

/**
 * UCSF SIS enrolment external functions.
 *
 * @package    enrol_ucsfsis
 * @category   external
 * @copyright  2018 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 3.4
 */
class enrol_ucsfsis_external extends external_api {

    /**
     * @return external_function_parameters
     */
    public static function get_subjects_and_courses_by_term_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
                'termid' => new external_value(PARAM_TEXT, 'Term ID', VALUE_REQUIRED)
            )
        );
    }

    /**
     * @param $courseId
     * @param $termId
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function get_subjects_and_courses_by_term($courseId, $termId) {
        global $DB;
        $raw = array(
            'courses' => array(),
            'subjects' => array(),
        );

        $clean = array(
            'courses' => array(),
            'subjects' => array(),
        );

        $course = $DB->get_record('course', array('id' => $courseId), '*', MUST_EXIST);
        $context = context_course::instance($course->id, MUST_EXIST);

        if ($course->id == SITEID) {
            throw new moodle_exception('invalidcourse');
        }

        require_login($course);
        require_capability('moodle/course:enrolreview', $context);

        /** @var enrol_ucsfsis_plugin $enrol */
        $enrol = enrol_get_plugin('ucsfsis');
        /** @var \enrol_ucsfsis\ucsfsis_oauth_client $http */
        $http  = $enrol->get_http_client();

        if ($http->is_logged_in()) {
            $raw['courses'] =  $http->get_courses_in_term($termId);
            $raw['subjects'] =  $http->get_subjects_in_term($termId);

            foreach($raw['courses'] as $course) {
                $clean['courses'][] = enrol_ucsfsis_simplify_sis_course($course);
            }
            foreach($raw['subjects'] as $subject) {
                $clean['subjects'][] = enrol_ucsfsis_simplify_sis_subject($subject);
            }
        }
        return $clean;
    }

    /**
     * @return external_function_parameters
     */
    public static function get_subjects_and_courses_by_term_returns() {
        return new external_function_parameters(
            array(
                'courses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_TEXT, 'course id'),
                            'title' => new external_value(PARAM_TEXT, 'course title'),
                            'subjectId' => new external_value(PARAM_TEXT, 'the id of the course-owning subject'),
                        )
                    )
                ),
                'subjects' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_TEXT, 'subject id'),
                            'title' => new external_value(PARAM_TEXT, 'subject title'),
                        )
                    )
                )
            )
        );
    }
}
