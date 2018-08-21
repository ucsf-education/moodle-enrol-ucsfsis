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
 * Add new instance of UCSF Student Information System enrolment plugin.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('edit_form.php');

require_once('../../group/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);
$submitted_termid = optional_param('selectterm', null, PARAM_ALPHANUM);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_capability('enrol/ucsfsis:config', $context);

$PAGE->set_url('/enrol/ucsfsis/edit.php', array('courseid'=>$course->id, 'id'=>$instanceid));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array('id'=>$course->id));
if (!enrol_is_enabled('ucsfsis')) {
    redirect($returnurl);
}

$enrol = enrol_get_plugin('ucsfsis');

// Allow only one instance for each course
if ($instances = $DB->get_records('enrol', array('courseid'=>$course->id, 'enrol'=>'ucsfsis'), 'id ASC')) {

    $instance = array_shift($instances);
    if ($instances) {
        // Oh - we allow only one instance per course!!
        foreach ($instances as $del) {
            $enrol->delete_instance($del);
        }
    }

// } else if ($instanceid) {
//     // Logic to allow multiple instances
//     $instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'ucsfsis', 'id'=>$instanceid), '*', MUST_EXIST);
} else {
    // No instance yet, we have to add new instance.
    if (!$enrol->get_newinstance_link($course->id)) {
        redirect($returnurl);
    }
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id          = null;
    $instance->courseid    = $course->id;
    $instance->enrol       = 'ucsfsis';
    $instance->status      = ENROL_INSTANCE_ENABLED;
    $instance->customint1  = null;  // UCSF SIS course ID
}


// Tell $mform->definition() that we are loading a known term
if (!empty($submitted_termid)) {
    $instance->submitted_termid = $submitted_termid;
}

// Edit form to be shown here
$mform = new enrol_ucsfsis_edit_form(null, array($instance, $enrol, $course));

if ($mform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $mform->get_data()) {
    // We are here only because the form is submitted.

    /**
     * KLUDGE!
     * Moodle won't let us get values of form elements that weren't in the original form definition.
     * Since we're loading courses into the corresponding dropdown via XHR callbacks, the form submission handler may
     * disregards them.
     * So we have to dig deeper and check the raw submitted values.
     * [ST 2018/08/21]
     */
    $selectcourse = null;
    if (object_property_exists($data, 'selectcourse')) {
        $selectcourse = $data->selectcourse;
    } else {
        /** @var \stdClass $submittedData */
        $selectcourse = (int) $mform->getForm()->getSubmitValue('selectcourse');
    }

    if ($instance->id) {
        $instance->roleid          = $data->roleid;
        $instance->customint1      = $selectcourse;
        // Clear SIS course id if exists
        $instance->customchar1     = '';
        $instance->customtext1     = '';  // get the descriptive course name here.
        $instance->timemodified    = time();

        $DB->update_record('enrol', $instance);

        // Use standard API to update instance status.
        if ($instance->status != $data->status) {
            $instance = $DB->get_record('enrol', array('id'=>$instance->id));
            $enrol->update_status($instance, $data->status);
            $context->mark_dirty();
        }

    } else {
        $fields = array(
            'status'          => $data->status,
            'customint1'      => $selectcourse,
            'roleid'          => $data->roleid);
        $enrol->add_instance($course, $fields);
    }

    $trace = new null_progress_trace();
    $enrol->sync($trace, $course->id);
    $trace->finished();
    redirect($returnurl);
}

$PAGE->set_title(get_string('pluginname', 'enrol_ucsfsis'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_ucsfsis'));
$mform->display();
echo $OUTPUT->footer();
