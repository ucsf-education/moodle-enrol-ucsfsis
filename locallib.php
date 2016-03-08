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
 * Local stuff for UCSF SIS enrolment plugin.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/locallib.php');


/**
 * Event handler for ucsfsis enrolment plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
// class enrol_cohort_handler {
//     /**
//      * Event processor - cohort member added.
//      * @param \core\event\cohort_member_added $event
//      * @return bool
//      */
//     public static function member_added(\core\event\cohort_member_added $event) {
//         global $DB, $CFG;
//         require_once("$CFG->dirroot/group/lib.php");

//         if (!enrol_is_enabled('cohort')) {
//             return true;
//         }

//         // Does any enabled cohort instance want to sync with this cohort?
//         $sql = "SELECT e.*, r.id as roleexists
//                   FROM {enrol} e
//              LEFT JOIN {role} r ON (r.id = e.roleid)
//                  WHERE e.customint1 = :cohortid AND e.enrol = 'cohort'
//               ORDER BY e.id ASC";
//         if (!$instances = $DB->get_records_sql($sql, array('cohortid'=>$event->objectid))) {
//             return true;
//         }

//         $plugin = enrol_get_plugin('cohort');
//         foreach ($instances as $instance) {
//             if ($instance->status != ENROL_INSTANCE_ENABLED ) {
//                 // No roles for disabled instances.
//                 $instance->roleid = 0;
//             } else if ($instance->roleid and !$instance->roleexists) {
//                 // Invalid role - let's just enrol, they will have to create new sync and delete this one.
//                 $instance->roleid = 0;
//             }
//             unset($instance->roleexists);
//             // No problem if already enrolled.
//             $plugin->enrol_user($instance, $event->relateduserid, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);

//             // Sync groups.
//             if ($instance->customint2) {
//                 if (!groups_is_member($instance->customint2, $event->relateduserid)) {
//                     if ($group = $DB->get_record('groups', array('id'=>$instance->customint2, 'courseid'=>$instance->courseid))) {
//                         groups_add_member($group->id, $event->relateduserid, 'enrol_cohort', $instance->id);
//                     }
//                 }
//             }
//         }

//         return true;
//     }

//     /**
//      * Event processor - cohort member removed.
//      * @param \core\event\cohort_member_removed $event
//      * @return bool
//      */
//     public static function member_removed(\core\event\cohort_member_removed $event) {
//         global $DB;

//         // Does anything want to sync with this cohort?
//         if (!$instances = $DB->get_records('enrol', array('customint1'=>$event->objectid, 'enrol'=>'cohort'), 'id ASC')) {
//             return true;
//         }

//         $plugin = enrol_get_plugin('cohort');
//         $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

//         foreach ($instances as $instance) {
//             if (!$ue = $DB->get_record('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$event->relateduserid))) {
//                 continue;
//             }
//             if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
//                 $plugin->unenrol_user($instance, $event->relateduserid);

//             } else {
//                 if ($ue->status != ENROL_USER_SUSPENDED) {
//                     $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
//                     $context = context_course::instance($instance->courseid);
//                     role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$context->id, 'component'=>'enrol_cohort', 'itemid'=>$instance->id));
//                 }
//             }
//         }

//         return true;
//     }

//     /**
//      * Event processor - cohort deleted.
//      * @param \core\event\cohort_deleted $event
//      * @return bool
//      */
//     public static function deleted(\core\event\cohort_deleted $event) {
//         global $DB;

//         // Does anything want to sync with this cohort?
//         if (!$instances = $DB->get_records('enrol', array('customint1'=>$event->objectid, 'enrol'=>'cohort'), 'id ASC')) {
//             return true;
//         }

//         $plugin = enrol_get_plugin('cohort');
//         $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

//         foreach ($instances as $instance) {
//             if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
//                 $context = context_course::instance($instance->courseid);
//                 role_unassign_all(array('contextid'=>$context->id, 'component'=>'enrol_cohort', 'itemid'=>$instance->id));
//                 $plugin->update_status($instance, ENROL_INSTANCE_DISABLED);
//             } else {
//                 $plugin->delete_instance($instance);
//             }
//         }

//         return true;
//     }
// }


/**
 * Sync all SIS course links.
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_ucsfsis_sync(progress_trace $trace, $courseid = NULL) {
    global $CFG, $DB;
    require_once("$CFG->dirroot/group/lib.php");

    // Purge all roles if cohort sync disabled, those can be recreated later here by cron or CLI.
    $trace->output('...user enrolment synchronisation finished.');

    return 0;
}

/**
 * Enrols all of the users through a manual plugin instance.
 *
 * In order for this to succeed the course must contain a valid manual
 * enrolment plugin instance that the user has permission to enrol users through.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $courseid
 * @param int $roleid
 * @return int
 */
function enrol_ucsfsis_enrol_all_users(course_enrolment_manager $manager, $courseid, $roleid) {
    global $DB;
    $context = $manager->get_context();
    require_capability('moodle/course:enrolconfig', $context);

    return $count;
}

/**
 * Gets all the courses the user is able to view.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @return array
 */
function enrol_ucsfsis_get_courses(course_enrolment_manager $manager) {
    global $DB;
    $context = $manager->get_context();
    $courses = array();
    return $courses;
}

/**
 * Check if course exists and user is allowed to enrol it.
 *
 * @global moodle_database $DB
 * @param int $courseid Course ID
 * @return boolean
 */
function enrol_ucsfsis_can_view_course($courseid) {
    global $DB;
    // $cohort = $DB->get_record('cohort', array('id' => $cohortid), 'id, contextid');
    // if ($cohort) {
    //     $context = context::instance_by_id($cohort->contextid);
    //     if (has_capability('moodle/cohort:view', $context)) {
    //         return true;
    //     }
    // }
    return false;
}

/**
 * Gets coourses the user is able to view.
 *
 * @global moodle_database $DB
 * @param course_enrolment_manager $manager
 * @param int $offset limit output from
 * @param int $limit items to output per load
 * @param string $search search string
 * @return array    Array(more => bool, offset => int, cohorts => array)
 */
function enrol_ucsfsis_search_courses(course_enrolment_manager $manager, $offset = 0, $limit = 25, $search = '') {
    global $DB;
    $context = $manager->get_context();
    $cohorts = array();
    $instances = $manager->get_enrolment_instances();
    // $enrolled = array();
    // foreach ($instances as $instance) {
    //     if ($instance->enrol == 'cohort') {
    //         $enrolled[] = $instance->customint1;
    //     }
    // }

    // list($sqlparents, $params) = $DB->get_in_or_equal($context->get_parent_context_ids());

    // // Add some additional sensible conditions.
    // $tests = array('contextid ' . $sqlparents);

    // // Modify the query to perform the search if required.
    // if (!empty($search)) {
    //     $conditions = array(
    //         'name',
    //         'idnumber',
    //         'description'
    //     );
    //     $searchparam = '%' . $DB->sql_like_escape($search) . '%';
    //     foreach ($conditions as $key=>$condition) {
    //         $conditions[$key] = $DB->sql_like($condition, "?", false);
    //         $params[] = $searchparam;
    //     }
    //     $tests[] = '(' . implode(' OR ', $conditions) . ')';
    // }
    // $wherecondition = implode(' AND ', $tests);

    // $sql = "SELECT id, name, idnumber, contextid, description
    //           FROM {cohort}
    //          WHERE $wherecondition
    //       ORDER BY name ASC, idnumber ASC";
    // $rs = $DB->get_recordset_sql($sql, $params, $offset);

    // // Produce the output respecting parameters.
    // foreach ($rs as $c) {
    //     // Track offset.
    //     $offset++;
    //     // Check capabilities.
    //     $context = context::instance_by_id($c->contextid);
    //     if (!has_capability('moodle/cohort:view', $context)) {
    //         continue;
    //     }
    //     if ($limit === 0) {
    //         // We have reached the required number of items and know that there are more, exit now.
    //         $offset--;
    //         break;
    //     }
    //     $cohorts[$c->id] = array(
    //         'cohortid' => $c->id,
    //         'name'     => shorten_text(format_string($c->name, true, array('context'=>context::instance_by_id($c->contextid))), 35),
    //         'users'    => $DB->count_records('cohort_members', array('cohortid'=>$c->id)),
    //         'enrolled' => in_array($c->id, $enrolled)
    //     );
    //     // Count items.
    //     $limit--;
    // }
    // $rs->close();
    return array('more' => !(bool)$limit, 'offset' => $offset, 'cohorts' => $cohorts);
}
