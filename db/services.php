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
 * UCSF SIS enrolment external functions and service definitions.
 *
 * @package    enrol_ucsfsis
 * @category   external
 * @copyright  2018 The Regents of the University of California
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.4
 */
defined('MOODLE_INTERNAL') || die();


$functions = [
    'enrol_ucsfsis_get_subjects_and_courses_by_term' => [
        'classname'   => 'enrol_ucsfsis_external',
        'methodname'  => 'get_subjects_and_courses_by_term',
        'description' => 'Returns the courses and subjects for a given term.',
        'type'        => 'read',
        'capabilities'  => 'moodle/course:enrolreview',
        'ajax'          => true,
    ],
];

$services = [
    'enrol_ucsfsis' => [
        'functions' => ['enrol_ucsfsis_get_subjects_and_courses_by_term'],
        'requiredcapability' => 'moodle/course:enrolreview',
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
