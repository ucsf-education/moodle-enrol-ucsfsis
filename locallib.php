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
 * UCSF Student Information System enrolment plugin.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Sync all sis course links.
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means OK, 1 means error, 2 means plugin disabled
 */
function enrol_ucsfsis_sync(progress_trace $trace, $courseid = NULL) {
    global $CFG, $DB;
    // require_once($CFG->diroot . '/group/lib.php');

    // Purge all roles if ilios sync disabled, those can be recreated later here by cron or CLI.
    if (!enrol_is_enabled('ucsfsis')) {
        $trace->output('UCSF SIS enrolment sync plugin is disabled, unassigning all plugin roles and stopping.');
        role_unassign_all(array('component'=>'enrol_ucsfsis'));
        return 2;
    }

    // Unfortunately this may take a long time, this script can be interrupted without problems.
    @set_time_limit(0);
    raise_memory_limit(MEMORY_HUGE);

    $trace->output('Starting user enrolment synchronisation...');

    $allroles = get_all_roles();

    $plugin = enrol_get_plugin('ucsfsis');
    $http   = $plugin->get_http_client();

    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
    $moodleusersyncfield = 'idnumber';
    $sisusersyncfield = 'studentId';

    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    $sql = "SELECT *
              FROM {enrol} e
             WHERE e.enrol = 'ucsfsis' $onecourse";

    $params = array();
    $params['courseid'] = $courseid;
    // $params['suspended'] = ENROL_USER_SUSPENDED;
    $instances = $DB->get_recordset_sql($sql, $params);

    foreach ($instances as $instance) {
        $siscourseid = trim($instance->customint1);
        $context = context_course::instance($instance->courseid);

        $trace->output("Synchronizing course {$instance->courseid}...");

        $courseEnrolments = false;
        if ($http->is_logged_in()) {
            $courseEnrolments = $http->get_course_enrollment($siscourseid);
        }

        if ($courseEnrolments === false) {
            $trace->output("Unable to fetch data from SIS for course id: $siscourseid.", 1);
            continue;
        }

        if (empty($courseEnrolments)) {
            $trace->output("Skipping: No enrolment data from SIS for course id: $siscourseid.", 1);
            continue;
        }

        // TODO: Consider doing this in bulk instead of iterating each student.
        // NOTES: OK, so enrol_user() will put the user on mdl_user_enrolments table as well as mdl_role_assignments
        //        But update_user_enrol() will just update mdl_user_enrolments but not mdl_role_assignments
        $enrolleduserids = array();

        // foreach ($student_enrol_statuses as $ucid => $status) {
        foreach ($courseEnrolments as $ucid => $userEnrol) {
            $status = $userEnrol->status;
            $urec = $DB->get_record('user', array( 'idnumber' => $ucid ));
            if (!empty($urec)) {
                $userid = $urec->id;
                // looks like enrol_user does it all
                $ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid));
                if (!empty($ue)) {
                    if ($status !== (int)$ue->status) {
                        $plugin->enrol_user($instance, $userid, $instance->roleid, 0, 0, $status);
                        $trace->output("changing enrollment status to '{$status}' from '{$ue->status}': userid $userid ==> courseid ".$instance->courseid, 1);
                    }//  else {
                    //     $trace->output("already enrolled do nothing: status = '{$ue->status}', userid $userid ==> courseid ".$instance->courseid, 1);
                    // }
                } else {
                    $plugin->enrol_user($instance, $userid, $instance->roleid, 0, 0, $status);
                    $trace->output("enrolling with $status status: userid $userid ==> courseid ".$instance->courseid, 1);
                }
                $enrolleduserids[] = $userid;
            } else {
                $trace->output("skipping: Cannot find UCID, ".$ucid.", that matches a Moodle user.");
            }
        }

        // Unenrol as neccessary.
        if (!empty($enrolleduserids)) {
            $sql = "SELECT ue.* FROM {user_enrolments} ue
                             WHERE ue.enrolid = $instance->id
                               AND ue.userid NOT IN ( ".implode(",", $enrolleduserids)." )";

            $rs = $DB->get_recordset_sql($sql);
            foreach($rs as $ue) {
                if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                    // Remove enrolment together with group membership, grades, preferences, etc.
                    $plugin->unenrol_user($instance, $ue->userid);
                    $trace->output("unenrolling: $ue->userid ==> ".$instance->courseid, 1);
                } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND or $unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                    // Suspend enrolments.
                    if ($ue->status != ENROL_USER_SUSPENDED) {
                        $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                        $trace->output("suspending: userid ".$ue->userid." ==> courseid ".$instance->courseid, 1);
                    }
                    if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                        $context = context_course::instance($instance->courseid);
                        role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$context->id, 'component'=>'enrol_ucsfsis', 'itemid'=>$instance->id));
                        $trace->output("unsassigning all roles: userid ".$ue->userid." ==> courseid ".$instance->courseid, 1);
                    }
                }
            }
            $rs->close();
        }
        $trace->output("Completed synchronizing course {$instance->courseid}.");
    }

    return 1;
}

function array_to_assoc($arr) {
    $output = array();
    foreach ($arr as $key=>$value) {
        // $output[$value['id']] = $value;
        $output[$value['id']] = $value['id'];
    }
    return $output;
}

function array_diff_recursive($arr1, $arr2)
{
    $outputDiff = [];

    foreach ($arr1 as $key => $value)
    {
        if (array_key_exists($key, $arr2))
        {
            if (is_array($value))
            {
                $recursiveDiff = array_diff_recursive($value, $arr2[$key]);

                if (count($recursiveDiff))
                {
                    $outputDiff[$key] = $recursiveDiff;
                }
            }
            else if (!in_array($value, $arr2))
            {
                $outputDiff[$key] = $value;
            }
        }
        else if (!in_array($value, $arr2))
        {
            $outputDiff[$key] = $value;
        }
    }

    return $outputDiff;
}