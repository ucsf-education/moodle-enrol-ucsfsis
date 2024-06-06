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
 * Local lib file for enrol_ucsfsis.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates and returns a simplified version of a given SIS course object.
 *
 * @param stdClass $siscourse The SIS course object.
 * @return stdClass The simplified object.
 * @package enrol_ucsfsis
 */
function enrol_ucsfsis_simplify_sis_course(stdClass $siscourse): stdClass {
    $simplecourse = new stdClass();
    $simplecourse->id = $siscourse->id;
    $simplecourse->title = $siscourse->courseNumber . ": " . $siscourse->name;
    $simplecourse->subjectId = $siscourse->subjectForCorrespondTo;
    if (! empty($siscourse->userForInstructorOfRecord)) {
        $instr = $siscourse->userForInstructorOfRecord;
        $simplecourse->title = $simplecourse->title . " ($instr->firstName $instr->lastName)";
    }
    return $simplecourse;
}

/**
 * Creates and returns a simplified version of a given SIS subject object.
 *
 * @param stdClass $sissubject The SIS subject object.
 * @return stdClass The simplified object.
 * @package enrol_ucsfsis
 */
function enrol_ucsfsis_simplify_sis_subject(stdClass $sissubject): stdClass {
    $simplesubject = new stdClass();
    $simplesubject->id = $sissubject->id;
    $simplesubject->title = $sissubject->code . ": " . $sissubject->name . " (" . $sissubject->id . ")";
    return $simplesubject;
}

/**
 * Creates and returns as simplified version of a given SIS term object.
 *
 * @param stdClass $sisterm The SIS term object.
 * @param int $time A UNIX timestamp.
 * @return stdClass The simplified object.
 * @throws coding_exception
 * @package enrol_ucsfsis
 */
function enrol_ucsfsis_simplify_sis_term(stdClass $sisterm, $time = 0) {
    $simpleterm = new stdClass();
    $simpleterm->id = $sisterm->id;
    $simpleterm->title = $sisterm->id . ": ". $sisterm->name;
    $starttime = strtotime($sisterm->fileDateForEnrollment->enrollmentStart);
    $simpleterm->hasStarted = true;
    if ($time < $starttime) {
        $simpleterm->hasStarted = false;
        $simpleterm->title = $simpleterm->title
            . get_string('enrolmentstartson', 'enrol_ucsfsis',  date(
                "M j, Y",
                $starttime
            ));
    }
    return $simpleterm;
}
