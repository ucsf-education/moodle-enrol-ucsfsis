<?php

defined('MOODLE_INTERNAL') || die();

/**
 * @param \stdClass $sisCourse
 * @return \stdClass
 */
function enrol_ucsfsis_simplify_sis_course(\stdClass $sisCourse) {
    $simpleCourse = new \stdClass();
    $simpleCourse->id = $sisCourse->id;
    $simpleCourse->title = $sisCourse->courseNumber . ": " . $sisCourse->name;
    $simpleCourse->subjectId = $sisCourse->subjectForCorrespondTo;
    if (! empty($sisCourse->userForInstructorOfRecord)) {
        $instr = $sisCourse->userForInstructorOfRecord;
        $simpleCourse->title = $simpleCourse->title . " ($instr->firstName $instr->lastName)";
    }
    return $simpleCourse;
}

/**
 * @param \stdClass $sisSubject
 * @return \stdClass
 */
function enrol_ucsfsis_simplify_sis_subject(\stdClass $sisSubject) {
    $simpleSubject = new \stdClass();
    $simpleSubject->id = $sisSubject->id;
    $simpleSubject->title = $sisSubject->code . ": " . $sisSubject->name . " (" . $sisSubject->id . ")";
    return $simpleSubject;
}

/**
 * @param stdClass $sisTerm
 * @param int $time
 * @return \stdClass
 * @throws coding_exception
 */
function enrol_ucsfsis_simplify_sis_term(\stdClass $sisTerm, $time = 0) {
    $simpleTerm = new \stdClass();
    $simpleTerm->id = $sisTerm->id;
    $simpleTerm->title = $sisTerm->id . ": ". $sisTerm->name;
    $startTime = strtotime($sisTerm->fileDateForEnrollment->enrollmentStart);
    $simpleTerm->hasStarted = true;
    if ($time < $startTime) {
        $simpleTerm->hasStarted = false;
        $simpleTerm->title = $simpleTerm->title
            . get_string('enrolmentstartson', 'enrol_ucsfsis',  date(
                "M j, Y",
                $simpleTerm->startTime
            ));
    }
    return $simpleTerm;
}
