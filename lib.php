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

use enrol_ucsfsis\ucsfsis_oauth_client;

/**
 * UCSF Student Information System enrolment plugin class.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_ucsfsis_plugin extends enrol_plugin {
    /**
     * @var object SIS client object.
     */
    protected $_sisclient = null;

    /**
     * Returns localised name of enrol instance.
     *
     * @param object|null $instance The enrol instance.
     * @return string The localised name of the enrol instance.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_instance_name($instance): string {
        global $DB;

        $enrol = $this->get_name();
        $iname = get_string('pluginname_short', 'enrol_'.$enrol);

        if (!empty($instance)) {
            // Append assigned role.
            if (!empty($instance->roleid) && $role = $DB->get_record('role', ['id' => $instance->roleid])) {
                $iname .= ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
            }
        }
        return $iname;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     *
     * @param int $courseid The course ID.
     * @return moodle_url|null The page URL or NULL if no new instances can be added.
     * @throws moodle_exception
     */
    public function get_newinstance_link($courseid) {

        if (!$this->can_add_new_instances($courseid)) {
            return null;
        }

        return new moodle_url('/enrol/ucsfsis/edit.php', ['courseid' => $courseid]);
    }

    /**
     * Checks if the current user has the capabilities to create a new enrolment instance in a given course.
     *
     * @param int $courseid The course ID.
     * @return bool TRUE if this user is able to add a new enrolment instance, FALSE otherwise.
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function can_add_new_instances($courseid): bool {
        global $DB;

        $coursecontext = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $coursecontext)
            || !has_capability('enrol/ucsfsis:config', $coursecontext)) {
            return false;
        }

        if ($DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => 'ucsfsis'])) {
            return false;
        }

        return true;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance The enrol instance.
     * @return bool TRUE if this user can delete this enrol instance, FALSE otherwise.
     * @throws coding_exception
     */
    public function can_delete_instance($instance): bool {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ucsfsis:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance The enrol instance.
     * @return bool TRUE if this use can hide/show this enrol instance, FALSE otherwise.
     * @throws coding_exception
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/ucsfsis:config', $context);
    }

    /**
     * Adds navigation links into course admin block.
     *
     * @param navigation_node $instancesnode The instance navigation node.
     * @param stdClass $instance The enrol instance.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function add_course_navigation($instancesnode, stdClass $instance): void {
        if ($instance->enrol !== 'ucsfsis') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/ucsfsis:config', $context)) {
            $managelink = new moodle_url('/enrol/ucsfsis/edit.php', ['courseid' => $instance->courseid]);
            // We want to show the enrol plugin name here instead the instance's name; therefore the 'null' argument.
            $instancesnode->add($this->get_instance_name(null), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances.
     *
     * @param stdClass $instance The enrol instance.
     * @return array The list of links.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'ucsfsis') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = [];

        if (has_capability('enrol/ucsfsis:config', $context)) {
            $editlink = new moodle_url("/enrol/ucsfsis/edit.php", ['courseid' => $instance->courseid, 'id' => $instance->id]);
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                                                                    ['class' => 'iconsmall']));
        }

        return $icons;
    }

    /**
     * Execute synchronisation of a given course, or all courses.
     *
     * @param progress_trace $trace The progress tracer.
     * @param int|null $courseid The course ID, or NULL for all courses.
     * @return int Exit code, 0 means ok, 1 means error, 2 means plugin disabled.
     * @throws coding_exception
     * @throws moodle_exception
     *
     * Todo: Remove return code and only throw exceptions on error instead.
     */
    public function sync(progress_trace $trace, $courseid = null): int {
        global $CFG, $DB;
        // TODO: Is there a way to break this cron into sections to run?
        if (!enrol_is_enabled('ucsfsis')) {
            $trace->output('UCSF SIS enrolment sync plugin is disabled, unassigning all plugin roles and stopping.');
            role_unassign_all(['component' => 'enrol_ucsfsis']);
            return 2;
        }

        // Unfortunately this may take a long time, this script can be interrupted without problems.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $trace->output('Starting user enrolment synchronisation...');

        $allroles = get_all_roles();
        $http = $this->get_http_client();
        $unenrolaction = $this->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
        $sql = "SELECT * FROM {enrol} e WHERE e.enrol = 'ucsfsis' $onecourse";

        $params = [];
        $params['courseid'] = $courseid;
        $instances = $DB->get_recordset_sql($sql, $params);

        foreach ($instances as $instance) {
            $siscourseid = $instance->customint1;
            $context = context_course::instance($instance->courseid);

            $trace->output("Synchronizing course {$instance->courseid} (SIS cid = $siscourseid)...");

            $courseenrolments = false;
            if ($http->is_logged_in()) {
                $courseenrolments = $http->get_course_enrollment($siscourseid);
            }

            if ($courseenrolments === false) {
                $trace->output("Unable to fetch data from SIS for course {$instance->courseid} (SIS cid = $siscourseid).", 1);
                if (empty($courseid)) {
                    // Continue if this is not the only course we are syncing.
                    continue;
                } else {
                    return 1;
                }
            }

            if (empty($courseenrolments)) {
                $trace->output(
                    "Skipping: No enrolment data from SIS for course {$instance->courseid} (SIS cid = $siscourseid).",
                    1
                );
                continue;
            }

            // Todo: Consider doing this in bulk instead of iterating each student.
            // NOTES: OK, so enrol_user() will put the user on mdl_user_enrolments table as well as mdl_role_assignments.
            // But update_user_enrol() will just update mdl_user_enrolments but not mdl_role_assignments.
            $enrolleduserids = [];

            foreach ($courseenrolments as $ucid => $userenrol) {
                $status = $userenrol->status;
                $urec = $DB->get_record('user', [ 'idnumber' => $ucid ]);
                if (!empty($urec)) {
                    $userid = $urec->id;
                    // Looks like enrol_user does it all.
                    $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
                    if (!empty($ue)) {
                        if ($status !== (int)$ue->status) {
                            $this->enrol_user($instance, $userid, $instance->roleid, 0, 0, $status);
                            $trace->output(
                                "changing enrollment status to '{$status}' from '{$ue->status}': userid $userid ==> courseid "
                                . $instance->courseid
                                , 1
                            );
                        }
                    } else {
                        $this->enrol_user($instance, $userid, $instance->roleid, 0, 0, $status);
                        $trace->output("enrolling with $status status: userid $userid ==> courseid ".$instance->courseid, 1);
                    }
                    $enrolleduserids[] = $userid;
                } else {
                    $trace->output("skipping: Cannot find UCID, ".$ucid.", that matches a Moodle user.");
                }
            }

            // Unenrol as neccessary.
            if (!empty($enrolleduserids)) {
                $sql = "SELECT ue.* FROM {user_enrolments} ue WHERE ue.enrolid = $instance->id AND ue.userid NOT IN ( "
                    . implode(",", $enrolleduserids)
                    . " )";

                $rs = $DB->get_recordset_sql($sql);
                foreach ($rs as $ue) {
                    if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                        // Remove enrolment together with group membership, grades, preferences, etc.
                        $this->unenrol_user($instance, $ue->userid);
                        $trace->output("unenrolling: $ue->userid ==> ".$instance->courseid, 1);
                    } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND || $unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                        // Suspend enrolments.
                        if ($ue->status != ENROL_USER_SUSPENDED) {
                            $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                            $trace->output("suspending: userid ".$ue->userid." ==> courseid ".$instance->courseid, 1);
                        }
                        if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                            $context = context_course::instance($instance->courseid);
                            role_unassign_all([
                                'userid' => $ue->userid,
                                'contextid' => $context->id,
                                'component' => 'enrol_ucsfsis',
                                'itemid' => $instance->id,
                                ]
                            );
                            $trace->output(
                                "unsassigning all roles: userid "
                                . $ue->userid." ==> courseid "
                                . $instance->courseid
                                , 1
                            );
                        }
                    }
                }
                $rs->close();
            }
        }

        // Now assign all necessary roles to enrolled users - skip suspended instances and users.
        // Todo: BUG: does not unassign the old role... :(.
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
        $sql = "SELECT e.roleid, ue.userid, c.id AS contextid, e.id AS itemid, e.courseid
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'ucsfsis' AND e.status = :statusenabled $onecourse)
              JOIN {role} r ON (r.id = e.roleid)
              JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :coursecontext)
              JOIN {user} u ON (u.id = ue.userid AND u.deleted = 0)
         LEFT JOIN {role_assignments} ra
              ON (
                  ra.contextid = c.id
                  AND ra.userid = ue.userid
                  AND ra.itemid = e.id
                  AND ra.component = 'enrol_ucsfsis'
                  AND e.roleid = ra.roleid
              )
         WHERE ue.status = :useractive AND ra.id IS NULL";
        $params = [];
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['useractive'] = ENROL_USER_ACTIVE;
        $params['coursecontext'] = CONTEXT_COURSE;
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ra) {
            role_assign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_ucsfsis', $ra->itemid);
            $trace->output("assigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname, 1);
        }
        $rs->close();

        // Remove unwanted roles - sync role can not be changed, we only remove role when unenrolled.
        $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
        $sql = "SELECT ra.roleid, ra.userid, ra.contextid, ra.itemid, e.courseid
              FROM {role_assignments} ra
              JOIN {context} c ON (c.id = ra.contextid AND c.contextlevel = :coursecontext)
              JOIN {enrol} e ON (e.id = ra.itemid AND e.enrol = 'ucsfsis' $onecourse)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = ra.userid AND ue.status = :useractive)
             WHERE ra.component = 'enrol_ucsfsis' AND (ue.id IS NULL OR e.status <> :statusenabled OR e.roleid <> ra.roleid)";
        $params = [];
        $params['statusenabled'] = ENROL_INSTANCE_ENABLED;
        $params['useractive'] = ENROL_USER_ACTIVE;
        $params['coursecontext'] = CONTEXT_COURSE;
        $params['courseid'] = $courseid;

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $ra) {
            role_unassign($ra->roleid, $ra->userid, $ra->contextid, 'enrol_ucsfsis', $ra->itemid);
            $trace->output("unassigning role: $ra->userid ==> $ra->courseid as ".$allroles[$ra->roleid]->shortname, 1);
        }
        $rs->close();

        $trace->output("Completed synchronizing course {$instance->courseid}.");

        return 0;
    }

    /**
     * Update instance status
     *
     * Override when plugin needs to do some action when enabled or disabled.
     *
     * @param stdClass $instance The enrol instance.
     * @param int $newstatus One of ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function update_status($instance, $newstatus): void {
        parent::update_status($instance, $newstatus);

        $trace = new null_progress_trace();
        $this->sync($trace, $instance->courseid);
        $trace->finished();
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager The course enrolment manager.
     * @param stdClass $ue A user enrolment object.
     * @return array An array of user_enrolment_actions.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue): array {
        $actions = [];
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/ucsfsis:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/delete', ''),
                get_string('unenrol', 'enrol'),
                $url,
                ['class' => 'unenrollink', 'rel' => $ue->id]
            );
        }
        return $actions;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step The restore enrolments structure step.
     * @param stdClass $data The data object.
     * @param stdClass $course The course object.
     * @param int $oldid The old enrolment instance ID.
     * @throws Exception
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid): void {
        global $DB;
        // There is only 1 SIS enrol instance per course.
        if ($instances = $DB->get_records('enrol', ['courseid' => $data->courseid, 'enrol' => 'ucsfsis'], 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);

        $trace = new null_progress_trace();
        $this->sync($trace, $course->id);
        $trace->finished();
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step The restore enrolment structure step.
     * @param stdClass $data The data object.
     * @param stdClass $instance The enrolment instance.
     * @param int $userid The user ID.
     * @param int $oldinstancestatus The old enrolment instance status.
     * @throws Exception
     */
    public function restore_user_enrolment(
        restore_enrolments_structure_step $step,
        $data,
        $instance,
        $userid,
        $oldinstancestatus
    ): void {
        global $DB;

        // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedIf
        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
        } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_KEEP) {
            if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
                $this->enrol_user($instance, $userid, null, 0, 0, $data->status);
            }
        } else {
            if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
                $this->enrol_user($instance, $userid, null, 0, 0, ENROL_USER_SUSPENDED);
            }
        }
        // phpcs:enable Generic.CodeAnalysis.EmptyStatement.DetectedIf
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance The enrol instance.
     * @param int $roleid The role ID.
     * @param int $userid The user ID.
     * @param int $contextid The context ID.
     * @throws dml_exception
     * @throws coding_exception
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid): void {
        global $DB;

        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL
            || $this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Skip any roles restore, they should be already synced automatically.
            return;
        }

        // Just restore every role.
        if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
            role_assign($roleid, $userid, $contextid, 'enrol_'.$instance->enrol, $instance->id);
        }
    }


    /**
     * Lazy-loads and returns the SIS API client.
     * @return ucsfsis_oauth_client
     * @throws moodle_exception
     */
    public function get_http_client() {
        if (empty($this->_sisclient)) {
            $this->_sisclient = new ucsfsis_oauth_client(
                $this->get_config('clientid'),
                $this->get_config('secret'),
                $this->get_config('resourceid'),
                $this->get_config('resourcepassword'),
                $this->get_config('host_url'),
                true
            );
        }
        return $this->_sisclient;
    }

    /**
     * Put last 6 terms of subjects data in cache data here. Will be called by cron.
     *
     * @throws moodle_exception
     */
    public function prefetch_subjects_data_to_cache(): void {
        $prefetchtermnum = 5;
        $oauth = $this->get_http_client();

        if ($oauth->is_logged_in()) {
            // Terms data.
            $terms = $oauth->get_active_terms();
            if (!empty($terms)) {
                foreach ($terms as $key => $term) {
                    if ($key > $prefetchtermnum) {
                        break;
                    }
                    $oauth->get_subjects_in_term($term->id);
                }
            }
        }
    }

    /**
     * Put last 6 terms of courses data in cache data here. Will be called by cron.
     *
     * @throws moodle_exception
     */
    public function prefetch_courses_data_to_cache(): void {
        $prefetchtermnum = 5;
        $oauth = $this->get_http_client();

        if ($oauth->is_logged_in()) {
            // Terms data.
            $terms = $oauth->get_active_terms();
            if (!empty($terms)) {
                foreach ($terms as $key => $term) {
                    if ($key > $prefetchtermnum) {
                        break;
                    }
                    $oauth->get_courses_in_term($term->id);
                }
            }
        }
    }

    /**
     * Test plugin settings, print info to output.
     *
     * @throws moodle_exception
     */
    public function test_settings(): void {
        global $CFG, $OUTPUT;
        // NOTE: this is not localised intentionally, admins are supposed to understand English at least a bit...

        raise_memory_limit(MEMORY_HUGE);

        $this->load_config();

        $hosturl = $this->get_config('host_url');
        if (empty($hosturl)) {
            echo $OUTPUT->notification('Host URL not specified, use default.', 'notifysuccess');
        }

        $resourceid = $this->get_config('resourceid');
        $resourcepw = $this->get_config('resourcepassword');

        if (empty($resourceid)) {
            echo $OUTPUT->notification('Resource ID not specified.', 'notifyproblem');
        }

        if (empty($resourcepw)) {
            echo $OUTPUT->notification('Resource password not specified.', 'notifyproblem');
        }

        if (empty($resourceid) && empty($resourcepw)) {
            return;
        }

        $clientid = $this->get_config('clientid');
        $secret = $this->get_config('secret');

        if (empty($clientid)) {
            echo $OUTPUT->notification('Client ID not specified.', 'notifyproblem');
        }

        if (empty($secret)) {
            echo $OUTPUT->notification('Client secret not specified.', 'notifyproblem');
        }

        if (empty($clientid) && empty($secret)) {
            return;
        }

        // Set up for debugging.
        $olddebug = $CFG->debug;
        $olddisplay = ini_get('display_errors');
        ini_set('display_errors', '1');
        $CFG->debug = DEBUG_DEVELOPER;
        error_reporting($CFG->debug);

        // Testing.
        $oauth = new ucsfsis_oauth_client($clientid, $secret, $resourceid, $resourcepw, $hosturl, true);

        $loggedin = $oauth->is_logged_in();
        if ($loggedin) {
            // Log out and try log in again.
            $oauth->log_out();
            $loggedin = $oauth->is_logged_in();
        }

        if (!$loggedin) {
            echo $OUTPUT->notification('Failed to log in with the specified settings', 'notifyproblem');
            return;
        }

        $atoken = $oauth->get_accesstoken();
        if (empty($atoken)) {
            echo $OUTPUT->notification('Failed to obtain an access token.', 'notifyproblem');
            return;
        }

        $rtoken = $oauth->get_refreshtoken();
        if (empty($rtoken)) {
            echo $OUTPUT->notification('Failed to obtain a refresh token.', 'notifyproblem');
            return;
        }

        echo $OUTPUT->notification('Logged in successfully to obtain an access token.', 'notifysuccess');

        // Get some data...
        echo "<pre>";
        if (empty($hosturl)) {
            $hosturl = ucsfsis_oauth_client::DEFAULT_HOST;
        }

        $result = $oauth->get_all_data($hosturl.'/general/sis/1.0/schools');
        echo "School Data: <br />";
        var_dump($result);

        $terms = $oauth->get_active_terms();
        echo "Active Term Data: <br />";
        var_dump($terms);

        $result = $oauth->get_all_data($hosturl.'/general/sis/1.0/departments');
        echo "Department Data: <br />";
        var_dump($result);

        // Actual requests used in code (and cache them).
        if (!empty($terms)) {
            foreach ($terms as $key => $term) {
                if ($key > 4) {
                    break;
                }
                $result = $oauth->get_subjects_in_term($term->id);
                echo "Subject Data in Term " . $term->name . " (" . count($result) ."): <br />";
                var_dump($result);
            }
        }

        if (!empty($terms)) {
            foreach ($terms as $key => $term) {
                if ($key > 4) {
                    break;
                }
                $result = $oauth->get_courses_in_term($term->id);
                echo "Course Data in Term " . $term->name . " (" . count($result) ."): <br />";
                var_dump($result);
            }
        }

        echo "</pre>";

        $CFG->debug = $olddebug;
        ini_set('display_errors', $olddisplay);
        error_reporting($CFG->debug);
    }
}
