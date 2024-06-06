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

use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use enrol_ucsfsis\ucsfsis_oauth_client;

defined('MOODLE_INTERNAL') || die();

require_once( $CFG->libdir . '/externallib.php');
require_once( __DIR__ . '/../locallib.php');

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
     * Defines the input parameters for the enrol_ucsfsis_get_subjects_and_courses_by_term web service endpoint.
     *
     * @return external_function_parameters The parameter definition.
     */
    public static function get_subjects_and_courses_by_term_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
                'termid' => new external_value(PARAM_TEXT, 'Term ID', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Implements the enrol_ucsfsis_get_subjects_and_courses_by_term web service endpoint.
     *
     * Returns the SIS courses and SIS subjects for a given Moodle course and Moodle term.
     *
     * @param int $courseid The course ID.
     * @param int $termid The term ID.
     * @return array A list of SIS courses and a list of SIS subjects.
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws require_login_exception
     * @throws required_capability_exception
     */
    public static function get_subjects_and_courses_by_term($courseid, $termid): array {
        global $DB;
        $raw = [
            'courses' => [],
            'subjects' => [],
        ];

        $clean = [
            'courses' => [],
            'subjects' => [],
        ];

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $context = context_course::instance($course->id, MUST_EXIST);

        if ($course->id == SITEID) {
            throw new moodle_exception('invalidcourse');
        }

        require_login($course);
        require_capability('moodle/course:enrolreview', $context);

        /* @var enrol_ucsfsis_plugin $enrol This enrolment plugin. */
        $enrol = enrol_get_plugin('ucsfsis');
        /* @var ucsfsis_oauth_client $http The SIS API client. */
        $http  = $enrol->get_http_client();

        if ($http->is_logged_in()) {
            $raw['courses'] = $http->get_courses_in_term($termid);
            $raw['subjects'] = $http->get_subjects_in_term($termid);

            foreach ($raw['courses'] as $course) {
                $clean['courses'][] = enrol_ucsfsis_simplify_sis_course($course);
            }
            foreach ($raw['subjects'] as $subject) {
                $clean['subjects'][] = enrol_ucsfsis_simplify_sis_subject($subject);
            }
        }
        return $clean;
    }

    /**
     * Defines the output structure for the enrol_ucsfsis_get_subjects_and_courses_by_term web service endpoint.
     *
     * @return external_description The output structure definition.
     */
    public static function get_subjects_and_courses_by_term_returns(): external_description {
        return new external_function_parameters(
            [
                'courses' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'id' => new external_value(PARAM_TEXT, 'course id'),
                            'title' => new external_value(PARAM_TEXT, 'course title'),
                            'subjectId' => new external_value(PARAM_TEXT, 'the id of the course-owning subject'),
                        ]
                    )
                ),
                'subjects' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'id' => new external_value(PARAM_TEXT, 'subject id'),
                            'title' => new external_value(PARAM_TEXT, 'subject title'),
                        ]
                    )
                ),
            ]
        );
    }
}
