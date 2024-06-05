<?php

defined('MOODLE_INTERNAL') || die();

/**
 * @param \stdClass $sisCourse
 * @return \stdClass
 * @package enrol_ucsfsis
 */
function enrol_ucsfsis_simplify_sis_course(\stdClass $siscourse) {
    $simplecourse = new \stdClass();
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
 * @param \stdClass $sisSubject
 * @return \stdClass
 * @package enrol_ucsfsis
 */
function enrol_ucsfsis_simplify_sis_subject(\stdClass $sissubject) {
    $simplesubject = new \stdClass();
    $simplesubject->id = $sissubject->id;
    $simplesubject->title = $sissubject->code . ": " . $sissubject->name . " (" . $sissubject->id . ")";
    return $simplesubject;
}

/**
 * @param stdClass $sisTerm
 * @param int $time
 * @return \stdClass
 * @throws coding_exception
 * @package enrol_ucsfsis
 */
function enrol_ucsfsis_simplify_sis_term(\stdClass $sisterm, $time = 0) {
    $simpleterm = new \stdClass();
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
