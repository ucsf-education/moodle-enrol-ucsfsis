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


/**
 * UCSF Student Information System feed file enrolment plugin implementation.
 *
 * Comma separated file assumed to have four or six fields per line:
 *   operation, role, idnumber(user), idnumber(course) [, starttime [, endtime]]
 * where:
 *   operation        = add | del
 *   role             = student | teacher | teacheredit
 *   idnumber(user)   = idnumber in the user table NB not id
 *   idnumber(course) = idnumber in the course table NB not id
 *   starttime        = start time (in seconds since epoch) - optional
 *   endtime          = end time (in seconds since epoch) - optional
 *
 */
class enrol_ucsfsis_plugin extends enrol_plugin {
    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol);

        // } else if (empty($instance->name)) {
        //     $enrol = $this->get_name();
        //     $cohort = $DB->get_record('cohort', array('id'=>$instance->customint1));
        //     if (!$cohort) {
        //         return get_string('pluginname', 'enrol_'.$enrol);
        //     }
        //     $cohortname = format_string($cohort->name, true, array('context'=>context::instance_by_id($cohort->contextid)));
        //     if ($role = $DB->get_record('role', array('id'=>$instance->roleid))) {
        //         $role = role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING));
        //         return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ' - ' . $role .')';
        //     } else {
        //         return get_string('pluginname', 'enrol_'.$enrol) . ' (' . $cohortname . ')';
        //     }

        } else {
            return format_string($instance->name, true, array('context'=>context_course::instance($instance->courseid)));
        }
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        if (!$this->can_add_new_instances($courseid)) {
            return NULL;
        }
        // Multiple instances supported - multiple parent courses linked.
        return new moodle_url('/enrol/ucsfsis/edit.php', array('courseid'=>$courseid));
    }

    /**
     * Given a courseid this function returns true if the user is able to enrol.
     *
     * @param int $courseid
     * @return bool
     */
    protected function can_add_new_instances($courseid) {
        global $DB;

        $coursecontext = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $coursecontext) or !has_capability('enrol/ucsfsis:config', $coursecontext)) {
            return false;
        }
        return true;
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'ucsfsis') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/ucsfsis:config', $context)) {
            $editlink = new moodle_url("/enrol/ucsfsis/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core',
                    array('class' => 'iconsmall')));
        }

        return $icons;
    }

    /**
     * Called for all enabled enrol plugins that returned true from is_cron_required().
     * @return void
     */
    public function cron() {
        global $CFG;

        require_once("$CFG->dirroot/enrol/ucsfsis/locallib.php");
        $trace = new null_progress_trace();
        enrol_ucsfsis_sync($trace);
        $trace->finished();
    }

    /**
     * Called after updating/inserting course.
     *
     * @param bool $inserted true if course just inserted
     * @param stdClass $course
     * @param stdClass $data form data
     * @return void
     */
    public function course_updated($inserted, $course, $data) {
        // It turns out there is no need for cohorts to deal with this hook, see MDL-34870.
    }

    /**
     * Update instance status
     *
     * @param stdClass $instance
     * @param int $newstatus ENROL_INSTANCE_ENABLED, ENROL_INSTANCE_DISABLED
     * @return void
     */
    public function update_status($instance, $newstatus) {
        global $CFG;

        parent::update_status($instance, $newstatus);

        require_once("$CFG->dirroot/enrol/ucsfsis/locallib.php");
        $trace = new null_progress_trace();
        enrol_ucsfsis_sync($trace, $instance->courseid);
        $trace->finished();
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/ucsfsis:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

    /**
     * Returns a button to enrol a SIS course or its users through the manual enrolment plugin.
     *
     * This function also adds a quickenrolment JS ui to the page so that users can be enrolled
     * via AJAX.
     *
     * @param course_enrolment_manager $manager
     * @return enrol_user_button
     */
    public function get_manual_enrol_button(course_enrolment_manager $manager) {
        $course = $manager->get_course();
        if (!$this->can_add_new_instances($course->id)) {
            return false;
        }

        $ucsfsisurl = new moodle_url('/enrol/ucsfsis/edit.php', array('courseid' => $course->id));
        $button = new enrol_user_button($ucsfsisurl, get_string('enrol', 'enrol_ucsfsis'), 'get');
        $button->class .= ' enrol_ucsfsis_plugin';

        $button->strings_for_js(array(
            'enrol',
            'synced',
            ), 'enrol');
        $button->strings_for_js(array(
            'ajaxmore',
            'coursesearch',
            'enrol',
            'enrolusers',
            ), 'enrol_ucsfsis');
        $button->strings_for_js('assignroles', 'role');
        // $button->strings_for_js('cohort', 'cohort');
        $button->strings_for_js('users', 'moodle');

        // No point showing this at all if the user cant manually enrol users.
        $hasmanualinstance = has_capability('enrol/manual:enrol', $manager->get_context()) && $manager->has_instance('manual');

        $modules = array('moodle-enrol_ucsfsis-quickenrolment', 'moodle-enrol_ucsfsis-quickenrolment-skin');
        $function = 'M.enrol_ucsfsis.quickenrolment.init';
        $arguments = array(
            'courseid'        => $course->id,
            'ajaxurl'         => '/enrol/ucsfsis/ajax.php',
            'url'             => $manager->get_moodlepage()->url->out(false),
            'manualEnrolment' => $hasmanualinstance);
        $button->require_yui_module($modules, $function, array($arguments));

        return $button;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB, $CFG;

        // @TODO write a restore for this enrol plugin
        // if (!$step->get_task()->is_samesite()) {
        //     // No cohort restore from other sites.
        //     $step->set_mapping('enrol', $oldid, 0);
        //     return;
        // }

        // if (!empty($data->customint2)) {
        //     $data->customint2 = $step->get_mappingid('group', $data->customint2);
        // }

        // if ($data->roleid and $DB->record_exists('cohort', array('id'=>$data->customint1))) {
        //     $instance = $DB->get_record('enrol', array('roleid'=>$data->roleid, 'customint1'=>$data->customint1, 'courseid'=>$course->id, 'enrol'=>$this->get_name()));
        //     if ($instance) {
        //         $instanceid = $instance->id;
        //     } else {
        //         $instanceid = $this->add_instance($course, (array)$data);
        //     }
        //     $step->set_mapping('enrol', $oldid, $instanceid);

        //     require_once("$CFG->dirroot/enrol/cohort/locallib.php");
        //     $trace = new null_progress_trace();
        //     enrol_cohort_sync($trace, $course->id);
        //     $trace->finished();

        // } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
        //     $data->customint1 = 0;
        //     $instance = $DB->get_record('enrol', array('roleid'=>$data->roleid, 'customint1'=>$data->customint1, 'courseid'=>$course->id, 'enrol'=>$this->get_name()));

        //     if ($instance) {
        //         $instanceid = $instance->id;
        //     } else {
        //         $data->status = ENROL_INSTANCE_DISABLED;
        //         $instanceid = $this->add_instance($course, (array)$data);
        //     }
        //     $step->set_mapping('enrol', $oldid, $instanceid);

        //     require_once("$CFG->dirroot/enrol/cohort/locallib.php");
        //     $trace = new null_progress_trace();
        //     enrol_cohort_sync($trace, $course->id);
        //     $trace->finished();

        // } else {
        //     $step->set_mapping('enrol', $oldid, 0);
        // }
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;

        if ($this->get_config('unenrolaction') != ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
            return;
        }

        // ENROL_EXT_REMOVED_SUSPENDNOROLES means all previous enrolments are restored
        // but without roles and suspended.

        if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, ENROL_USER_SUSPENDED);
        }
    }

    /**
     * Restore user group membership.
     * @param stdClass $instance
     * @param int $groupid
     * @param int $userid
     */
    public function restore_group_member($instance, $groupid, $userid) {
        // Nothing to do here, the group members are added in $this->restore_group_restored()
        return;
    }
}