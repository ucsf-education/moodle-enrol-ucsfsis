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

require_once($CFG->libdir.'/oauthlib.php');

class enrol_ucsfsis_plugin extends enrol_plugin {
    /** @var object SIS client object */
    protected $_sisclient = null;

    /**
     * Returns localised name of enrol instance.
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;

        $enrol = $this->get_name();
        $iname = get_string('pluginname_short', 'enrol_'.$enrol);

        if (!empty($instance)) {
            // Append assigned role
            if (!empty($instance->roleid) and $role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                $iname .= ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
            }
            // Append details of selected course (disable for now until we implement this in edit_form.php
            // if (!empty($instance->customtext1)) {
            //     $iname .= '<br /><small>'.$instance->customtext1.'</small><br />';
            // }

            // Experiment: displaying course name...performance hit on the listing enrolled students page.
            // $http = $this->get_http_client();
            // $c = $http->get_object('/courses/'.$instance->customint1);
            // if (!empty($c)) {
            //     // $iname .= '<br /><pre>'.print_r($c,1).'</pre>';
            //     // $iname .= '<br /><em><strong>'.trim($c->subjectForCorrespondTo).' '.trim($c->courseNumber).':</strong> '.trim($c->name).' ('.$c->term.')</em>';
            //     // $iname .= '<br /><small><em><strong>'.trim($c->departmentForBelongTo).trim($c->courseNumber).':</strong> '.trim($c->name).' ('.$c->term.')</em></small><br />';
            //     // $iname .= '<br /><em>'.trim($c->departmentForBelongTo).trim($c->courseNumber).' '.trim($c->name).', '.$c->term.'</em><br />';
            //     // $iname .= '<br /><small><strong>'.trim($c->departmentForBelongTo).trim($c->courseNumber).' '.trim($c->name).'</strong> <em>(Joe Smith)</em>, '.$c->term.'</small><br />';
            //     $iname .= '<br /><small><strong>'.trim($c->departmentForBelongTo).trim($c->courseNumber).' '.trim($c->name).'</strong>, '.$c->term.'<br /><em>Instructor: </em>Joe Smith</small><br />';
            //     // $iname .= '<br /><em><small>('.trim($c->term).') '.trim($c->departmentForBelongTo).trim($c->courseNumber).' '.trim($c->name).' (Joe Smith)</small></em><br />';
            // }
        }
        return $iname;
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

        $coursecontext = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $coursecontext) or !has_capability('enrol/ucsfsis:config', $coursecontext)) {
            return false;
        }

        if ($DB->record_exists('enrol', array('courseid'=>$courseid, 'enrol'=>'ucsfsis'))) {
            return false;
        }

        return true;
    }

    /**
     * Returns enrolment instance manage link.
     *
     * By defaults looks for manage.php file and tests for manage capability.
     *
     * @param navigation_node $instancesnode
     * @param stdClass $instance
     * @return moodle_url;
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'ucsfsis') {
             throw new coding_exception('Invalid enrol instance type!');
        }

        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/ucsfsis:config', $context)) {
            $managelink = new moodle_url('/enrol/ucsfsis/edit.php', array('courseid'=>$instance->courseid));
            // We want to show the enrol plugin name here instead the instance's name; therefore the 'null' argument.
            $instancesnode->add($this->get_instance_name(null), $managelink, navigation_node::TYPE_SETTING);
        }
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
    // public function cron($trace=null) {
    //     global $CFG;

    //     require_once("$CFG->dirroot/enrol/ucsfsis/locallib.php");
    //     $trace = new text_progress_trace();
    //     // $this->sync($trace);
    //     enrol_ucsfsis_sync($trace);

    //     $trace->finished();
    // }

    /**
     * Execute synchronisation.
     * @param progress_trace
     * @return int exit code, 0 means ok, 2 means plugin disabled
     */
    public function sync(progress_trace $trace) {
        // TODO: Modify this to perform something similar to enrol_ucsfsis_sync($trace)
        //       Or just call it.
        // TODO: Is there a way to break this cron into sections to run?
        if (!enrol_is_enabled('ucsfsis')) {
            $trace->output('UCSF SIS enrolment sync plugin is disabled, unassigning all plugin roles and stopping.');
            role_unassign_all(array('component'=>'enrol_ucsfsis'));
            return 2;
        }

        return 0;
    }

    /**
     * Sorry, we do not want to show paths in cron output.
     *
     * @param string $filepath
     * @return string
     */
    protected function obfuscate_filepath($filepath) {
        global $CFG;

        if (strpos($filepath, $CFG->dataroot.'/') === 0 or strpos($filepath, $CFG->dataroot.'\\') === 0) {
            $disclosefile = '$CFG->dataroot'.substr($filepath, strlen($CFG->dataroot));

        } else if (strpos($filepath, $CFG->dirroot.'/') === 0 or strpos($filepath, $CFG->dirroot.'\\') === 0) {
            $disclosefile = '$CFG->dirroot'.substr($filepath, strlen($CFG->dirroot));

        } else {
            $disclosefile = basename($filepath);
        }

        return $disclosefile;
    }

    /**
     * Process flatfile.
     * @param progress_trace $trace
     * @return bool true if any data processed, false if not
     */
    protected function process_file(progress_trace $trace) {
        global $CFG, $DB;

        // We may need more memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $filelocation = $this->get_config('location');

        // @TODO Do we want a default location for LibCourse.del?
        if (empty($filelocation)) {
            // Default legacy location.
            $filelocation = "$CFG->dataroot/sis/LibCourse.del";
        }
        $disclosefile = $this->obfuscate_filepath($filelocation);

        if (!file_exists($filelocation)) {
            $trace->output("SIS enrolments file not found: $disclosefile");
            $trace->finished();
            return false;
        }
        $trace->output("Processing SIS file enrolments from: $disclosefile ...");

        $oldlineendings = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', true);
        $filecontent = file($filelocation);
        ini_set('auto_detect_line_endings', $oldlineendings);

        if ($filecontent === false) {
            $trace->output("Failed to read SIS enrolments file: $disclosefile");
            $trace->finished();
            return false;
        }

        $content = array_map('str_getcsv', $filecontent);

        // @TODO Implement import file to tables
        if ($content !== false) {

            $line = 0;
            $lastupdatedtime = time();

            foreach($content as $fields) {
                $line++;

                if (count($fields) < 9 or count($fields) > 10) {
                    $trace->output("Line incorrectly formatted - ignoring $line", 1);
                    continue;
                }

                $studentid = $fields[0];
                $studentucsfid = $fields[1];
                $term = $fields[2];
                $subjectcode = $fields[3];
                $coursenumber = $fields[4];
                $subject = $fields[5];
                $coursetitle = $fields[6];
                $instructorcode = $fields[7];
                $instructorid = $fields[8];
                $instructorname = $fields[9];

                $trace->output("$line: $fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $fields[5], $fields[6], $fields[7], $fields[8], $fields[9]", 1);

                // Save these values (above) into database tables.
                $courseidnumber = str_replace(' ','_',$subject).'_'.$coursenumber.'_'.$term;

                // Create or update enrolment table for student role
                $record = $DB->get_record('enrol_ucsfsis_enrolment', array('courseuid'=>$courseidnumber, 'userid'=>$studentid, 'role'=>'student'));
                if ($record !== false) {
                    $record->lastupdated = $lastupdatedtime;
                    $DB->update_record('enrol_ucsfsis_enrolment', $record);
                } else {
                    $record = new stdClass();
                    $record->courseuid = $courseidnumber;
                    $record->userid = $studentid;
                    $record->role = "student";
                    $record->timecreated = $record->lastupdated = $lastupdatedtime;
                    $record->id = $DB->insert_record('enrol_ucsfsis_enrolment', $record);
                }

                // Create or update enrolment table for instructor role
                $record = $DB->get_record('enrol_ucsfsis_enrolment', array('courseuid'=>$courseidnumber, 'userid'=>$instructorid, 'role'=>'instructor'));
                if ($record !== false) {
                    $record->lastupdated = $lastupdatedtime;
                    $DB->update_record('enrol_ucsfsis_enrolment', $record);
                } else {
                    $record = new stdClass();
                    $record->courseuid = $courseidnumber;
                    $record->userid = $instructorid;
                    $record->role = "instructor";
                    $record->timecreated = $record->lastupdated = $lastupdatedtime;
                    $record->id = $DB->insert_record('enrol_ucsfsis_enrolment', $record);
                }
            }

            // Remove records that has not been updated
            $DB->delete_records_select('enrol_ucsfsis_enrolment', "lastupdated < $lastupdatedtime");

            unset($content);
        }

        // if (!unlink($filelocation)) {
        //     $eventdata = new stdClass();
        //     $eventdata->modulename        = 'moodle';
        //     $eventdata->component         = 'enrol_ucsfsis';
        //     $eventdata->name              = 'ucsfsis_enrolment';
        //     $eventdata->userfrom          = get_admin();
        //     $eventdata->userto            = get_admin();
        //     $eventdata->subject           = get_string('filelockedmailsubject', 'enrol_flatfile');
        //     $eventdata->fullmessage       = get_string('filelockedmail', 'enrol_flatfile', $filelocation);
        //     $eventdata->fullmessageformat = FORMAT_PLAIN;
        //     $eventdata->fullmessagehtml   = '';
        //     $eventdata->smallmessage      = '';
        //     message_send($eventdata);
        //     $trace->output("Error deleting enrolment file: $disclosefile", 1);
        // } else {
        //     $trace->output("Deleted enrolment file", 1);
        // }

        $trace->output("...finished enrolment file processing.");
        $trace->finished();

        return true;
    }


    /**
     * Fetch enrolments and course information from feed file.
     * @param string feedfilename
     * @return array $courses or false when there's an error
     */
    protected function get_enrolments_from_feed_file( $filename, progress_trace $trace ) {
        global $CFG, $DB;

        // We may need more memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $oldlineendings = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', true);
        $filecontent = file($filename);
        ini_set('auto_detect_line_endings', $oldlineendings);

        if ($filecontent === false) {
            $trace->output("SIS enrolments file not found: $disclosefile");
            return false;
        }

        $content = array_map('str_getcsv', $filecontent);
        $courses = array();

        if ($content !== false) {

            $line = 0;
            $lastupdatedtime = time();

            foreach($content as $fields) {
                $line++;

                if (count($fields) !== 11) {
                    $trace->output("Line incorrectly formatted (expected ". count($fields)." fields) - ignoring $line", 1);
                    continue;
                }

                $studentid = trim($fields[0]);
                $studentucsfid = trim($fields[1]);
                $term = trim($fields[2]);
                $subjectcode = trim($fields[3]);
                $coursenumber = trim($fields[4]);
                $subject = trim($fields[5]);
                $coursetitle = trim($fields[6]);
                $instructorcode = trim($fields[7]);
                $instructorid = trim($fields[8]);
                $instructorname = trim($fields[9]);
                $courseid = trim($fields[10]);

                // $trace->output("$line: $fields[0], $fields[1], $fields[2], $fields[3], $fields[4], $fields[5], $fields[6], $fields[7], $fields[8], $fields[9], $fields[10]", 1);

                // Save these values (above) into database tables.
                $courseidnumber = str_replace(' ','_',$subject).'_'.$coursenumber.'_'.$term;

                if (!isset($courses[$courseid])) {
                    $courses[$courseid] = array( 'term'    => $term,
                                                 'subject' => $subject,
                                                 'subjectCode'=> $subjectcode,
                                                 'courseNumber' => $coursenumber,
                                                 'courseTitle' => $coursetitle,
                                                 'idnumber' => $courseidnumber,    // CLEAE course ID
                                                 'students' => array( $studentid => array( 'id' => $studentid, 'ucsfid' => $studentucsfid ) ),
                                                 'instructors' => array( $instructorid => array('id' => $instructorid, 'code' => $instructorcode, 'name' => $instructorname) ) );
                } else if (!empty($courses[$courseid])) {
                    $students =& $courses[$courseid]['students'];
                    $instructors =& $courses[$courseid]['instructors'];

                    if (!isset($students[$studentid])) {
                        $students[$studentid] = array( 'id' => $studentid, 'ucsfid' => $studentucsfid );
                    }
                    if (!isset($instructors[$instructorid])) {
                        $instructors[$instructorid] = array( 'id' => $instructorid, 'code' => $instructorcode, 'name' => $instructorname );
                    }
                }
            }
            unset($content);
        }
        return $courses;
    }

    /**
     * Convert database enrol to SIS enrol using a feedfile.
     * @param progress_trace $trace
     * @return bool true if any data processed, false if not
     */
    public function convert_cleae(progress_trace $trace) {
        global $CFG, $DB;

        // We may need more memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $filelocation = $this->get_config('location');

        // @TODO Do we want a default location for LibCourse.del?
        if (empty($filelocation)) {
            // Default legacy location.
            $filelocation = $CFG->dataroot."/sis/LibCourseId.del";
        }
        $disclosefile = $this->obfuscate_filepath($filelocation);

        if (!file_exists($filelocation)) {
            $trace->output("SIS enrolments file not found: $disclosefile");
            $trace->finished();
            return false;
        }

        // Make sure ucsfsis is enabled
        // It's okay if it has not been enabled yet.
        // if (!enrol_is_enabled('ucsfsis')) {
        //     $trace->output("UCSFSIS enrol needs to be enabled for this to run.  Please enable it and try again.");
        //     return false;
        // }

        $trace->output("Processing SIS file enrolments from: $disclosefile ...");

        $courses = $this->get_enrolments_from_feed_file($filelocation, $trace);

        // $instructors = array();
        // foreach ($courses as $courseid => $course) {
        //     foreach ($course['instructors'] as $id => $instr) {
        //         $instructors[$id] = $instr;
        //     }
        // }
        // $trace->output("List all instructors: ".print_r($instructors,1));

        foreach ($courses as $courseid => $course) {
            $idnumber = $course['idnumber'];
            $m_courses = $DB->get_records('course', array('idnumber' => $idnumber));
            foreach ($m_courses as $c) {

                // Set 'enrol' from 'database' to 'ucsfsis', 'customint1' to $courseid, 'customchar1' to $idnumber
                $rec = $DB->get_record('enrol', array('courseid'=>$c->id, 'enrol'=>'database'));

                $trace->output("Converting course ".$c->id.", ".$c->fullname.": idnumber = ".$idnumber.", SIS courseid = ".$courseid.".");

                if (!empty($rec)) {
                    $rec->enrol       = 'ucsfsis';
                    $rec->customint1  = $courseid;
                    $rec->customchar1 = $idnumber;
                    $rec->customtext1 = "<strong>{$course['subjectCode']}{$course['courseNumber']} {$course['courseTitle']}</strong>, {$course['term']}";
                    $rec->roleid      = $this->get_config('default_student_roleid');
                    $DB->update_record('enrol', $rec);
                }

                // Clear the idnumber field on the course table
                $DB->set_field('course', 'idnumber', '', array('id' => $c->id));
            }
        }

        $trace->output("...finished enrolment file processing.");

        $trace->finished();

        return true;
    }

    /**
     * Update instance status
     *
     * Override when plugin needs to do some action when enabled or disabled.
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
    // public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
    //     // TODO: Test this feature
    //     if ($ue->status == ENROL_USER_SUSPENDED) {
    //         return true;
    //     }

    //     return false;
    // }

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


    public function get_http_client() {
        if (empty($this->_sisclient)) {
            $this->_sisclient = new sis_client(
                $this->get_config('host_url'),
                $this->get_config('resourceid'),
                $this->get_config('resourcepassword'),
                $this->get_config('clientid'),
                $this->get_config('secret'),
                $this->get_config('accesstoken', null),
                $this->get_config('refreshtoken', null),
                $this->get_config('accesstokenexpiretime', 0),
                "enrol_".$this->get_name()
            );
        }
        return $this->_sisclient;
    }

    /**
     * Test plugin settings, print info to output.
     */
    public function test_settings() {
        // $this->test_apis();

        // Debugging
        global $CFG, $OUTPUT;
        $returnurl = new moodle_url('/enrol/ucsfsis/oauth_callback.php');
        $returnurl->param('callback', 'yes');
        // $returnurl->param('repo_id', $this->id);
        $returnurl->param('sesskey', sesskey());

        // $clientid = $this->get_config('clientid');
        // $secret = $this->get_config('secret');

        $clientid = '9ff1baa0198748fc91ff717d38063799';
        $secret = 'e09f3c79e4a44d0e98475BE2B9D0661A';

        $oauth = new ucsfsis_oauth($clientid, $secret, $returnurl, '');

        $url = $oauth->get_login_url();

        // if ($this->options['ajax']) {
        //     $popup = new stdClass();
        //     $popup->type = 'popup';
        //     $popup->url = $url->out(false);
        //     return array('login' => array($popup));
        // } else {
            echo '<a target="_blank" href="'.$url->out(false).'">'.get_string('login', 'repository').'</a>';
        // }


        // $ret = $oauth->is_logged_in();

        // var_dump($ret);
    }

    /**
     * Check enrollment status from API against feed
     */
    private function test_check_enrollment_status_against_feed_file() {
        // $this->test_apis();

        // Debugging
        global $CFG, $OUTPUT;

        $this->load_config();
        $api = $this->get_http_client();

        // $result = $api->get_objects('/courseEnrollments?courseId=8041');
        // $result = $api->get_objects('/courseEnrollments?courseId=3989');
        // $result = $api->get_objects('/courseEnrollments?courseId=8041', null, null, array('status'=>'A'));
        // $result = $api->get_objects('/courseEnrollments?courseId=8041', null, null, array('status'=>'A'));
        // $results = $api->get_objects('/courseEnrollments?courseId=11542');
        // $users = array();
        // foreach ($results as $result) {
        //     if (!empty($result->student) && !empty($result->student->empno)) {
        //         $users[$result->student->empno][] = $result->status;
        //     }
        // }
        // echo $OUTPUT->notification("<pre>".print_r($users,1)."</pre>");

        raise_memory_limit(MEMORY_HUGE);
        $trace = new text_progress_trace();
        $fcourses = $this->get_enrolments_from_feed_file($CFG->dataroot."/sis/LibCourseId.del", $trace);
        $trace->finished();

        $issueCourses = array();
        $courses = $api->get_objects('/terms/FA16/courses');
        foreach ($courses as $course) {
            $enrollments = $api->get_objects('/courseEnrollments?courseId='.trim($course->id));
            $users = array();
            foreach ($enrollments as $enrollment) {
                if (!empty($enrollment->student) && !empty($enrollment->student->empno)) {
                    $users[$enrollment->student->empno][] = $enrollment->status;
                }
            }
            foreach ($users as $key => $user) {
                // if (count($user) > 1) {
                //     $issueCourses[trim($course->id)][$key]['status'] = $user;
                //     if (isset($fcourses[trim($course->id)]['students'][$key])) {
                //         $issueCourses[trim($course->id)][$key]['FoundinFeed'] = 'yes';
                //     } else {
                //         $issueCourses[trim($course->id)][$key]['FoundinFeed'] = 'no';
                //     }
                // }
                if (in_array('A', $user)) {
                    if (!isset($fcourses[trim($course->id)]['students'][$key])) {
                        $issueCourses[trim($course->id)][$key]['status'] = $user;
                        $issueCourses[trim($course->id)][$key]['FoundinFeed'] = 'no';
                    }
                } else {
                    if (isset($fcourses[trim($course->id)]['students'][$key])) {
                        $issueCourses[trim($course->id)][$key]['status'] = $user;
                        $issueCourses[trim($course->id)][$key]['FoundinFeed'] = 'yes';
                    }
                }
            }
        }
        echo $OUTPUT->notification("<pre>".print_r($issueCourses,1)."</pre>");
    }

    public function test_apis() {
        global $CFG, $OUTPUT;

        // NOTE: this is not localised intentionally, admins are supposed to understand English at least a bit...
        raise_memory_limit(MEMORY_HUGE);

        $this->load_config();

        // Check API settings
        $hosturl = $this->get_config('host_url');
        $clientid = $this->get_config('clientid');
        $secret = $this->get_config('secret');

        if (empty($hosturl)) {
            echo $OUTPUT->notification('API Host URL not specified.', 'notifyproblem');
        } else {
            $api = $this->get_http_client();

            // $result = $api->get_objects('/terms', null, '-termStartDate', array( 'year'=>['2015','2016']));
            // $result = $api->get_objects('/terms', null, '-termStartDate', array( 'year'=>['2015','2016']), array('fileDateForEnrollment'=>null));
            $result = $api->get_objects('/terms', null, '-termStartDate', null, array('fileDateForEnrollment'=>null));
            echo $OUTPUT->notification("Quick test:<pre>".print_r($result,true)."</pre>");

            // @TEST setup
            $urls = array(
                "/schools",
                "/departments",
                "/terms",
                "/subjects",
                "/courses",
                "/courseEnrollments",
                "/instructorsWithEnrolledStudents"
            );

            // @TEST for limit and offsets
            foreach ($urls as $url) {
                $this->test_usinglimitandoffsetreturnsamelist($url,$api);
            }

            // @TEST for /someapi/1 should be same as /someapi?id=1

            // @TODO Check for duplicates like in /instructorsWithEnrolledStudents?courseId={course_id}.

            /**
             * Courses API
             */
            // $url = '/courseEnrollments';
            // $objects = $this->testapiurlinvalid($url.'?courseId=somerandomnumber',$api);
            // $objects = $this->testapiurlinvalid($url.'?courseId=999999',$api);

            // $url = 'instructorsWithEnrolledStudents';
            // $objects = $this->testapiurlinvalid($url.'?courseId=somerandomnumber',$api);
            // $objects = $this->testapiurlinvalid($url.'?courseId=999999',$api);

            // $url = '/courses';
            // $objects = $this->testapiurl($url,$api);

            // foreach ($objects as $obj) {
            //     $retobj = $this->testapiurl($url.'/'.trim($obj->id), $api);
            //     $retobj = $this->testapiurl($url.'/'.trim($obj->id).'/instructors', $api);
            //     $retobj = $this->testapiurl('/courseEnrollments?courseId='.trim($obj->id), $api);
            //     $retobj = $this->testapiurl('/instructorsWithEnrolledStudents?courseId='.trim($obj->id), $api);
            // }


            // $obj = $this->testapiurlinvalid($url.'/somerandomstring', $api);
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring/instructors', $api);
            // $obj = $this->testapiurlinvalid($url.'/99999', $api);
            // $obj = $this->testapiurlinvalid($url.'/99999/instructors', $api);


            /*
             * Subjects API
             */
            // // No /subjects API
            $url = '/subjects';

            // $obj = $this->testapiurlinvalid($url.'/1', $api);
            // $obj = $this->testapiurlinvalid($url.'/01', $api);
            // $obj = $this->testapiurlinvalid($url.'/001', $api);
            // $obj = $this->testapiurlinvalid($url.'/N', $api);
            // $obj = $this->testapiurlinvalid($url.'/PT', $api);
            // $obj = $this->testapiurlinvalid($url.'/CL PHARM', $api);
            // $obj = $this->testapiurlinvalid($url.'/CL%20PHARM', $api);
            // $obj = $this->testapiurlinvalid($url.'/NEUROLOGY', $api);
            // $obj = $this->testapiurlinvalid('/departments/NE', $api);


            // $objects = $this->testapiurlinvalid($url,$api);
            // // foreach ($objects as $obj) {
            // //     $retobj = $this->testapiurl($url.'/'.trim($obj->id), $api);
            // // }
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring', $api);
            // $obj = $this->testapiurlinvalid($url.'/99999', $api);

            /*
             * Terms API
             */
            // $url = '/terms/SP16/courses';
            // $obj = $this->testapiurlinvalid($url, $api);

            // $url = '/terms/ST16/courses';
            // $obj = $this->testapiurlinvalid($url, $api);

            // $url = '/terms/SP16/subjects';
            // $obj = $this->testapiurlinvalid($url, $api);

            // $url = '/terms/ST16/subjects';
            // $obj = $this->testapiurlinvalid($url, $api);

            // $url = '/terms?sort=-termStartDate&year=2016';

            // @BUG Is this supposed to work?
            $url = '/terms?year=2016';
            $objects = $this->testapiurl($url,$api);

            // $url = '/terms';
            // $objects = $this->testapiurl($url,$api);
            // foreach ($objects as $obj) {
            //     $retobj = $this->testapiurl($url.'/'.trim($obj->id), $api);
            //     $subjects = $this->testapiurl($url.'/'.trim($obj->id).'/subjects', $api);
            //     foreach ($subjects as $subject) {
            //         $retobj = $this->testapiurl('/subjects/'.trim($subjects->id), $api);
            //     }
            //     $courses = $this->testapiurl($url.'/'.trim($obj->id).'/courses', $api);
            //     foreach ($courses as $course) {
            //         $retobj = $this->testapiurl('/courses/'.trim($courses->id), $api);
            //     }
            // }
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring', $api);
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring/subjects', $api);
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring/courses', $api);
            // $obj = $this->testapiurlinvalid($url.'/99999', $api);
            // $obj = $this->testapiurlinvalid($url.'/99999/subjects', $api);
            // $obj = $this->testapiurlinvalid($url.'/99999/courses', $api);
            $url = '/departments';
            $this->test_get_objectsshouldreturnssomething($url, $api);

            $url = '/terms/WI16/subjects';
            $this->test_get_objectsshouldreturnssomething($url, $api);


            /*
             * Departments API
             */
            // $url = '/departments/G';
            // $objects = $this->testapiurlinvalid($url,$api);
            // $url = '/departments/U ';
            // $objects = $this->testapiurlinvalid($url,$api);
            // $url = '/departments/P ';
            // $objects = $this->testapiurlinvalid($url,$api);
            // $url = '/departments/T';
            // $objects = $this->testapiurl($url,$api);

            // // @TODO More testings on '/departments/SH', which has an &amp; in the 'name'
            // // @TODO More testings on '/departments/PB', which has an &amp; in the 'shortname'
            // //       'PS&PG PROG'
            // // @TODO More testings on '/departments/EB', which has an &amp; in the 'shortname'
            // //       'EPID & BIO'
            // // @TODO More testings on '/departments/G', which has an &amp; in the 'shortname'

            // $url = '/departments';
            // $objects = $this->testapiurl($url,$api);
            // foreach ($objects as $obj) {
            //     try {
            //         // @BUG Need to trim ID
            //         $retobj = $this->testapiurl($url.'/'.trim($obj->id), $api);
            //     } catch (Exception $e) {
            //         echo $OUTPUT->notification("ERROR: ".$url.'/'.trim($obj->id). ": ".$e->getMessage());
            //         // sleep(90);
            //         // $retobj = $this->testapiurl($url.'/'.trim($obj->id), $api);
            //         try {
            //             $retobj = $this->testapiurlinvalid($url.'/'.trim($obj->id), $api);
            //         } catch (Exception $e) {
            //             echo $OUTPUT->notification("ERROR: ".$url.'/'.trim($obj->id). ": ".$e->getMessage());
            //         }
            //     }
            // }
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring', $api);
            // $obj = $this->testapiurlinvalid($url.'/99999', $api);

            /*
             * Schools API
             */
            $url = '/schools';

            // @BUG id = '#' show up in the second url, inconsistent list
            $objects = $this->testapiurlinvalid($url, $api);
            $objects = $this->testapiurlinvalid($url.'?sort=name', $api);
            // // Practical use:
            // $objects = $this->testapiurl($url.'?limit=6&offset=2&fields=name', $api);
            // $objects = $this->testapiurl($url.'?limit=6&offset=2&fields=name&sort=name', $api);

            // $objects = $this->testapiurl($url.'?limit=2', $api);
            // $objects = $this->testapiurl($url.'?limit=2&offset=3', $api);
            // $objects = $this->testapiurl($url.'?limit=2&offset=5', $api);
            // $objects = $this->testapiurl($url.'?limit=2&offset=7', $api);
            // $objects = $this->testapiurl($url.'?limit=2&offset=9', $api);
            // $objects = $this->testapiurl($url.'?limit=6&offset=2&fields=name', $api);
            // $objects = $this->testapiurl($url.'?limit=6&offset=2&fields=name&sort=name', $api);
            // $objects = $this->testapiurl($url.'?limit=6&offset=2&sort=-name', $api);

            $objects = $this->testapiurl($url, $api);

            foreach ($objects as $obj) {
                try {  // try with raw id
                    $retobj = $this->testapiurl($url.'/'.$obj->id, $api);
                } catch (Exception $e) {
                    echo $OUTPUT->notification("ERROR: ". $e->getMessage() . "<br /> Trying with trim($obj->id).");
                    $retobj = $this->testapiurl($url.'/'.trim($obj->id), $api);
                }
            }
            // $obj = $this->testapiurlinvalid($url.'/1', $api);
            // $obj = $this->testapiurlinvalid($url.'/2', $api);
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring', $api);
            // $obj = $this->testapiurlinvalid($url.'/12345', $api);

            // // test for /school/{school_id}/departments
            // foreach ($objects as $obj) {
            //     try {  // try with raw id
            //         $retobj = $this->testapiurl($url.'/'.$obj->id.'/departments', $api);
            //     } catch (Exception $e) {
            //         echo $OUTPUT->notification("ERROR: ". $e->getMessage() . "<br /> Trying with trim($obj->id).");
            //         $retobj = $this->testapiurl($url.'/'.trim($obj->id).'/departments', $api);
            //     }
            // }
            // $obj = $this->testapiurlinvalid($url.'/1/departments', $api);
            // $obj = $this->testapiurlinvalid($url.'/2/departments', $api);
            // $obj = $this->testapiurlinvalid($url.'/somerandomstring/departments', $api);
            // $obj = $this->testapiurlinvalid($url.'/12345/departments', $api);
        }
        return;
    }

    public function test_feedfile() {
        global $CFG, $OUTPUT;

        // Check CSV file - enrolment
        $enrolfilelocation = $this->get_config('location');

        if (empty($enrolfilelocation)) {
            echo $OUTPUT->notification('Enrolment file location not specified.', 'notifyproblem');
        }

        $olddebug = $CFG->debug;
        $olddisplay = ini_get('display_errors');
        ini_set('display_errors', '1');
        $CFG->debug = DEBUG_DEVELOPER;
        $this->config->debugdb = 1;
        error_reporting($CFG->debug);

        // Read CSV file into Array
        $oldlineendings = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', true);
        $filecontent = file($enrolfilelocation);
        ini_set('auto_detect_line_endings', $oldlineendings);

        if ($filecontent === false) {
            echo $OUTPUT->notification("Failed to read file: $enrolfilelocation.", 'notifyproblem');
            return;
        }

        $noflines = count($filecontent);
        $content = array_map('str_getcsv', $filecontent);
        $coursearray = array();

        if ($content !== false) {
            $line = 0;
            $noffields = 0;
            $lastnoffields = 0;
            foreach($content as $fields) {
                $line++;
                // Insert value to table
                // @TODO CHECK and LEARN how enrolment happen (RE-DESIGN)
                $noffields = count($fields);
                if ($lastnoffields != 0 and $lastnoffields != $noffields) {
                    echo $OUTPUT->notification("This line does not have the same number of fields like the others: <pre>".print_r($fields,true)."</pre>", 'notifyproblem');
                } else {
                    $lastnoffields = $noffields;
                }

                if (count($fields) < 9 or count($fields) > 10) {
                    echo $OUTPUT->notification("Line incorrectly formatted - ignoring $line", 'notifyproblem');
                    continue;
                }

                // if ($line <= 20) {
                //     echo $OUTPUT->notification("Line $line: <pre>".print_r($fields,true)."</pre>", 'notifyproblem');
                // }

                // if ($fields[2] == "FA14" and $fields[4] == "212A" and $fields[3] == "PT" and $fields[6] == "Muscle and Nerve Biology") {
                //     echo $OUTPUT->notification("Line $line: <pre>".print_r($fields,true)."</pre>", 'notifyproblem');
                // }

                $studentid = $fields[0];
                $studentucsfid = $fields[1];
                $term = $fields[2];
                $subjectcode = $fields[3];
                $coursenumber = $fields[4];
                $subject = $fields[5];
                $coursetitle = $fields[6];
                $instructorcode = $fields[7];
                $instructorid = $fields[8];
                $instructorname = $fields[9];

                $courseidnumber = str_replace(' ','_',$subject).'_'.$coursenumber.'_'.$term;

                if (!isset($coursearray[$courseidnumber])) {
                    $coursearray[$courseidnumber] = 1;
                } else {
                    $coursearray[$courseidnumber]++;
                }

            }
            if ($line != $noflines) {
                echo $OUTPUT->notification("Total number of lines is different than what was read.", 'notifyproblem');
            }
            echo $OUTPUT->notification("All courses: <pre>".print_r($coursearray,true)."</pre>", 'notifyproblem');
        }

        // Check CSV file - courses
        $coursefilelocation = "/home/moodle/moodledata/LibCoursesAllOffered.del";

        ini_set('auto_detect_line_endings', true);
        $filecontent = file($coursefilelocation);
        ini_set('auto_detect_line_endings', $oldlineendings);

        if ($filecontent === false) {
            echo $OUTPUT->notification("Failed to read file: $coursefilelocation.", 'notifyproblem');
            return;
        }

        $content = array_map('str_getcsv', $filecontent);

        $missingcoursearray = array();

        if ($content !== false) {
            $line = 0;
            $noffields = 0;
            $lastnoffields = 0;
            foreach($content as $fields) {
                $line++;
                // Insert value to table
                // @TODO CHECK and LEARN how enrolment happen (RE-DESIGN)
                $noffields = count($fields);
                if ($lastnoffields != 0 and $lastnoffields != $noffields) {
                    echo $OUTPUT->notification("This line does not have the same number of fields like the others: <pre>".print_r($fields,true)."</pre>", 'notifyproblem');
                } else {
                    $lastnoffields = $noffields;
                }

                // if (count($fields) < 9 or count($fields) > 10) {
                //     echo $OUTPUT->notification("Line incorrectly formatted - ignoring $line", 'notifyproblem');
                //     continue;
                // }

                // if ($line <= 20) {
                //     echo $OUTPUT->notification("Line $line: <pre>".print_r($fields,true)."</pre>", 'notifyproblem');
                // }
                $term = $fields[0];        // term id
                $instructorcode = $fields[6];  // "***" means unknown or not set
                $subject = $fields[7];    // subject id (contend
                $subjectcode = $fields[8];
                $coursenumber = $fields[9];
                $minunits = $fields[13];
                $maxunits = $fields[14];
                $instructorname = $fields[15];  // "Staff unknown" for "***" code.
                $coursename = $fields[16];  // course title

                // if ($fields[0] == "FA14" and $fields[9] == "212A" and $fields[8] == "PT" and $fields[16] == "Muscle and Nerve Biology") {
                //     echo $OUTPUT->notification("Line $line: <pre>".print_r($fields,true)."</pre>", 'notifyproblem');
                // }

                $courseidnumber = str_replace(' ','_',$subject).'_'.$coursenumber.'_'.$term;

                if (!isset($coursearray[$courseidnumber])) {
                    $missingcoursearray[] = $courseidnumber;
                }

            }
            if ($line != $noflines) {
                echo $OUTPUT->notification("Total number of lines is different than what was read.", 'notifyproblem');
            }
            echo $OUTPUT->notification("Missing courses: <pre>". print_r($missingcoursearray,true) . "</pre>", 'notifyproblem');
        }

        $CFG->debug = $olddebug;
        ini_set('display_errors', $olddisplay);
        error_reporting($CFG->debug);
        ob_end_flush();
    }

    private function testapiurl($url, $api) {
        global $CFG, $OUTPUT;
        echo $OUTPUT->notification("Testing '$url'", 'notifyproblem');
        // check for consistency for arrays
        $obj = $api->getobj($url);

        if (is_array($obj)) {
            $total1 = count($obj);
            $obj = $api->getobj($url);
            $total2 = count($obj);
            if ($total1 != $total2) {
                echo $OUTPUT->notification("ERROR: $url, 1st call total: $total1; 2nd call total: $total2", 'notifyproblem');
            }
        }

        echo $OUTPUT->notification("$url:<pre>".print_r($obj,true).'</pre>', 'notifyproblem');
        return $obj;
    }

    private function testapiurlinvalid($url, $api) {
        global $CFG, $OUTPUT;
        echo $OUTPUT->notification("Testing '$url'", 'notifyproblem');
        $obj = $api->get($url);
        echo $OUTPUT->notification("$url:<pre>".print_r($obj,true).'</pre>', 'notifyproblem');
        return $obj;
    }

    private function test_get_objectsshouldreturnssomething($url, $api) {
        global $OUTPUT;

        echo $OUTPUT->notification("Testing get_objects($url)");
        $objs = $api->get_objects($url);
        if (empty($objs)) {
            echo $OUTPUT->notification("ERROR: get_objects($url) returns empty.", 'notifyproblem');
        } else {
            echo $OUTPUT->notification("get_objects($url) returns: <pre>".print_r($objs, true).'</pre>');
        }

        return $objs;
    }

    private function test_usinglimitandoffsetreturnsamelist($url, $api) {
        global $CFG, $OUTPUT;

        require_once("$CFG->dirroot/enrol/ucsfsis/locallib.php");

        $ret1 = $api->get($url);
        $ret2 = $api->get($url.'?limit=100');

        if (strcmp($ret1, $ret2) !== 0) {
            echo $OUTPUT->notification("$url failed '?limit=100' tests", 'notifyproblem');

            $arr1 = $api->parse_result($ret1,true);
            $arr2 = $api->parse_result($ret2,true);
            echo $OUTPUT->notification("Total number of records returned:<pre>".count($arr1['data'])." vs. ".count($arr2['data'])."</pre>");
            // echo $OUTPUT->notification("$url:<pre>".print_r($arr1, true)."</pre>");
            // echo $OUTPUT->notification("$url?limit=100:<pre>".print_r($arr2, true)."</pre>");

            // $diff = array_diff_recursive($arr1['data'],$arr2['data']);

            $arr1 = array_to_assoc($arr1['data']);
            $arr2 = array_to_assoc($arr2['data']);
            $diff = array_diff_recursive($arr1,$arr2);
            echo $OUTPUT->notification("Not in first list:<pre>".print_r(implode($diff,',') ,true)."</pre>");
            $diff = array_diff_recursive($arr2,$arr1);
            echo $OUTPUT->notification("Not in second list:<pre>".print_r(implode($diff,',') ,true)."</pre>");

            // echo $OUTPUT->notification("$url:<pre>".$ret1."</pre>");
            // echo $OUTPUT->notification("$url?limit=100:<pre>".$ret2."</pre>");
        }

        $ret3 = $api->get($url.'?limit=100&offset=1');

        if (strcmp($ret1, $ret3) !== 0) {
            echo $OUTPUT->notification("$url failed '?limit=100&offset=1' tests", 'notifyproblem');

            $arr1 = $api->parse_result($ret1,true);
            $arr3 = $api->parse_result($ret3,true);
            echo $OUTPUT->notification("Total number of records returned:<pre>".count($arr1['data'])." vs. ".count($arr3['data'])."</pre>");
            // $diff = array_diff_recursive($arr1['data'], $arr3['data']);

            $arr1 = array_to_assoc($arr1['data']);
            $arr3 = array_to_assoc($arr3['data']);
            $diff = array_diff_recursive($arr1,$arr3);
            echo $OUTPUT->notification("Not in first list:<pre>".print_r(implode($diff,','),true)."</pre>");
            $diff = array_diff_recursive($arr3,$arr1);
            echo $OUTPUT->notification("Not in second list:<pre>".print_r(implode($diff,','),true)."</pre>");

            // echo $OUTPUT->notification("$url:<pre>".$ret1."</pre>");
            // echo $OUTPUT->notification("$url?limit=100:<pre>".$ret3."</pre>");
        }
    }

}


// First you have to get access token calling https://stage-unified-api.ucsf.edu/oauth/1.0/access_token using HTTP post with following http header
// grant_type: password
// client_Id:your_clientid
// username: yourresourceuser
// password: your_resourceuser_password
// client_secret:your_clientsecret

// You should get token like this as the http response
// {
//   "expires_in": 86399,
//   "token_type": "bearer",
//   "refresh_token": "UFXgxkXDVKNB-g8UY6XiKV3PXtlRDDYF6y6Br4bwLUxgZu8InaJl5JLpTZFBd2XnjDp5gby2wLwF2-UWAmvbbg",
//   "access_token": "vZoyXmrY1C-aZaRdBStOo5Gcm3bLkOrWZwMU_HJhlm_IkrFK1cnytQdNyk62QRarGwZT5p9FmtGYE8jebd6gJw"
// }

// Save the token and used it for subsequent call. Eg.



// https://stage-unified-api.ucsf.edu/general/sis/1.0/schools/85?access_token=AYQwy_oodGvyoPIjWfbYBGk9TDmpToVLizFSq-4PBrwCwHpfyrp0VMjeU942w3knUMzUbzFSW6qL3PN5vfwIng

// Note the access_token shouldn’t be generated for each call but rather reused.

// Refresh token can be used with current Oauth provider using
// GET /oauth/1.0/access_token?refresh_token=FtF8VbpHRrgSt210iu6KxbIfK7UWAzbK5hDAexfGUgO1Nlxe-1abi4b7PT9PcJHCWDuRjoXDSgAGgWeSXodqpA HTTP/1.1
// Host: stage-unified-api.ucsf.edu
// grant_type: refresh_token
// client_Id:yourclientid
// client_secret:yourclientsecret

// Authenticating from the website
// https://unified-api.ucsf.edu/oauth/1.0/authorize?scope=&client_id=b8cbf4743b0c4d7f88857f8c232d43b5&redirect_uri=https%3A%2F%2Fanypoint.mulesoft.com%2Fapiplatform%2Fucsf%2Fauthentication%2Foauth2.html&response_type=token


class sis_client extends curl {
    /** @const API URL */
    const API_URL = '/general/sis/1.0';
    const TOKEN_URL = '/oauth/1.0/access_token';
    const AUTH_URL = '/oauth/1.0/authorize';

    private $_host = '';
    private $_userid = '';
    private $_userpw = '';
    private $_clientid = '';
    private $_secret = '';
    private $_accesstoken = null;
    private $_refreshtoken = null;
    private $_expires_on = 0;
    private $_pluginname = '';


    public function __construct($host, $userid, $userpw, $clientid, $clientsecret, $accesstoken = null, $refreshtoken = null, $expiretime = 0, $pluginname = '') {
        parent::__construct();

        $this->_host = $host;
        $this->_userid = $userid;
        $this->_userpw = $userpw;
        $this->_clientid = $clientid;
        $this->_secret = $clientsecret;
        $this->_accesstoken = $accesstoken;
        $this->_refreshtoken = $refreshtoken;
        $this->_expires_on = $expiretime;
        $this->_pluginname = $pluginname;

        // echo "What's the current state: <pre>".print_r($this, true). "</pre>";
        if ($this->_expires_on < time()) {
            $this->_accesstoken = null;    // clear the current token since it's already expired.

            if (!empty($this->_refreshtoken)) {
                $this->refreshToken();
                $this->saveToken();
            }

            if (empty($this->_accesstoken)) {
                $this->createToken();
                $this->saveToken();
            }
        }
    }

    private function refreshToken() {
        $this->resetHeader();
        $this->setHeader( 'grant_type:refresh_token' );
        $this->setHeader( 'client_id:'.$this->_clientid );
        $this->setHeader( 'client_secret:'.$this->_secret );

        $currenttime = time();
        $result = parent::get($this->_host . self::TOKEN_URL ."?refresh_token=".$this->_refreshtoken);
        $parsed_result = $this->parse_result($result);

        if (!empty($parsed_result->access_token)) {
            $this->_accesstoken = $parsed_result->access_token;
            $this->_refreshtoken = $parsed_result->refresh_token;
            $this->_expires_on = $currenttime + $parsed_result->expires_in;
        }
        $this->saveToken();
        return $result;
    }

    private function createToken() {
        $this->resetHeader();
        $this->setHeader( 'grant_type:password' );
        $this->setHeader( 'username:'.$this->_userid );
        $this->setHeader( 'password:'.$this->_userpw );
        $this->setHeader( 'client_id:'.$this->_clientid );
        $this->setHeader( 'client_secret:'.$this->_secret );

        $currenttime = time();
        $result = parent::get($this->_host . self::TOKEN_URL);
        $parsed_result = $this->parse_result($result);

        if (!empty($parsed_result->access_token)) {
            $this->_accesstoken = $parsed_result->access_token;
            $this->_refreshtoken = $parsed_result->refresh_token;
            $this->_expires_on = $currenttime + $parsed_result->expires_in;
        }
        return $result;
    }

    private function saveToken() {
        global $CFG;

        // echo "Saving token: <pre>".print_r($this, true). "</pre>";
        if (!empty($this->_pluginname)) {
            require_once($CFG->libdir . '/moodlelib.php');
            set_config('accesstoken', $this->_accesstoken, $this->_pluginname);
            set_config('refreshtoken', $this->_refreshtoken, $this->_pluginname);
            set_config('accesstokenexpiretime', $this->_expires_on, $this->_pluginname);
        }
        // echo "Saved token: <pre>".print_r($this, true). "</pre>";
    }

    // @param $filters array of filters like, array('id' => [1,2,3,4,5], 'lastname' = 'Smith')
    // @param $not     array of not filters, fields that don't match items in this array

    public function get_objects($collection_name, $fields=[], $sorts=[], $filters=[], $not=[]) {

        // @BUG OK, using $filters does not work yet
        // $all = $this->getallobj($collection_name, $fields, $sorts, $filters);
        $all = $this->getallobj($collection_name, $fields, $sorts);
        $retobj = array();

        if (!empty($filters)) {
            foreach ($all as $obj) {
                if (is_array($filters)) {
                    foreach ($filters as $field=>$filter) {
                        if (is_array($filter)) {
                            foreach ($filter as $match) {
                                if ($obj->$field == $match) {
                                    $retobj[] = $obj;
                                    break;
                                }
                            }
                        } else {
                            if ($obj->$field == $filter) {
                                $retobj[] = $obj;
                            }
                        }
                    }
                }
            }
        } else {
            $retobj = $all;
        }

        $all = $retobj;
        $retobj = array();

        if (!empty($not)) {
            foreach ($all as $obj) {
                if (is_array($not)) {
                    foreach ($not as $field=>$filter) {
                        if (is_array($filter)) {
                            $found = false;
                            foreach ($filter as $match) {
                                if ($obj->$field == $match) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $retobj[] = $obj;
                            }
                        } else {
                            if ($obj->$field != $filter) {
                                $retobj[] = $obj;
                            }
                        }
                    }
                }
            }
        } else {
            $retobj = $all;
        }

        return $retobj;
    }

    public function getallobj($uri, $fields = array(), $sorts = array(), $filters = array()) {
        if ($this->_expires_on < time()) {
            $this->refreshToken();
        }
        $url = $this->_host . self::API_URL . $uri;
        if (strstr($uri, "?") === false) {
            $url .= '?access_token='.$this->_accesstoken;
        } else {
            $url .= '&access_token='.$this->_accesstoken;
        }
        $this->resetHeader();
        $this->setHeader( 'client_id:'.$this->_clientid );
        $this->setHeader( 'client_secret:'.$this->_secret );

        $fieldstr = '';
        if (!empty($fields)) {
            if (is_array($fields)) {
                $fieldstr = '&fields='.implode(',', $fields);
            } else {
                $fieldstr = '&fields='.$fields;
            }
        }

        $sortstr = '';
        if (!empty($sorts)) {
            if (is_array($sorts)) {
                $sortstr = '&sort='.implode(',', $sorts);
            } else {
                $sortstr = '&sort='.$sorts;
            }
        }

        $filterstr = '';
        if (!empty($filters)) {
            if (is_array($filters)) {
                $filterstr = "&".http_build_query($filters);
            } else {
                $filterstr = "&".$filters;
            }
        }
        $limit = 100;
        $offset = 0;
        $retobj = array();
        $obj = null;

        do {
            $finalurl = $url."&limit=$limit&offset=$offset".$fieldstr.$sortstr.$filterstr;

            $result = parent::get($finalurl);
            // Debugging
            // echo "<pre>".$finalurl.": ".print_r($result,1)."</pre>";
            $obj = $this->parse_result($result);
            if ($offset > 1200) { // wait this is weird, can't be these many records.
                echo "$finalurl: <pre>".print_r($obj,1)."</pre>";
                break;
            }

            if (!empty($obj) && isset($obj->data) && !empty($obj->data)) {
                $retobj = array_merge($retobj, $obj->data);
                $offset += $limit;
            } else {
                $obj = null;
            }
        } while (!empty($obj));

        return $retobj;
    }

    public function get_object($objectidurl, $fields = array()) {

        return $this->getobj($objectidurl, $fields);
    }

    public function getobj($uri, $fields = array(), $sorts = array()) {
        if ($this->_expires_on < time()) {
            $this->refreshToken();
        }
        $url = $this->_host . self::API_URL . $uri;
        if (strstr($uri, "?") === false) {
            $url .= '?access_token='.$this->_accesstoken;
        } else {
            $url .= '&access_token='.$this->_accesstoken;
        }
        $this->resetHeader();
        $this->setHeader( 'client_id:'.$this->_clientid );
        $this->setHeader( 'client_secret:'.$this->_secret );
        // echo "DEBUG: Getting '$url'";
        $result = parent::get($url);
        // echo "<pre>".print_r($result,true)."</pre>";
        $obj = $this->parse_result($result);

        if (!empty($obj) && isset($obj->data)) {
            return $obj->data;
        }
        return null;
    }

    public function get($uri, $params = array(), $options = array()) {
        if ($this->_expires_on < time()) {
            $this->refreshToken();
        }
        $url = $this->_host . self::API_URL . $uri;
        if (strstr($uri, "?") === false) {
            $url .= '?access_token='.$this->_accesstoken;
        } else {
            $url .= '&access_token='.$this->_accesstoken;
        }
        $this->resetHeader();
        $this->setHeader( 'client_id:'.$this->_clientid );
        $this->setHeader( 'client_secret:'.$this->_secret );

        $result = parent::get($url, $params, $options);
        return $result;
    }

    /**
     * A method to parse response to get token and token_secret
     * @param string $str
     * @return array
     */
    public function parse_result($str, $assoc = false) {
        if (empty($str)) {
            // throw new moodle_exception('empty string');
            throw new moodle_exception('Empty string: <pre>'.print_r($this,true).'</pre>');
        }
        $result = json_decode($str,$assoc);

        if (empty($result)) {
            throw new moodle_exception('empty object');
        }

        if (isset($result->errors)) {
            throw new moodle_exception(print_r($result->errors[0],true));
        }

        return $result;
    }

    // testing functions
    private function get_sample_terms() {
        $str = <<<'EOS'
{
  "data": [
    {
      "id": "FA07",
      "name": "Fall 2007     ",
      "year": 2007,
      "termStartDate": "2007-09-12",
      "termEndDate": "2007-12-31",
      "fileDateForEnrollment": {
        "enrollmentStart": "2007-09-13",
        "enrollmentEnd": "2007-09-28"
      }
    },
    {
      "id": "FA05",
      "name": "Fall 2005     ",
      "year": 2005,
      "termStartDate": "2005-09-12",
      "termEndDate": "2005-12-31",
      "fileDateForEnrollment": {
        "enrollmentStart": "2005-09-19",
        "enrollmentEnd": "2005-10-04"
      }
    },
    {
      "id": "FA10",
      "name": "Fall 2010     ",
      "year": 2010,
      "termStartDate": "2010-09-08",
      "termEndDate": "2010-12-31",
      "fileDateForEnrollment": {
        "enrollmentStart": "2010-09-08",
        "enrollmentEnd": "2010-10-08"
      }
    },
    {
      "id": "FA08",
      "name": "Fall 2008     ",
      "year": 2008,
      "termStartDate": "2008-09-10",
      "termEndDate": "2008-12-30",
      "fileDateForEnrollment": {
        "enrollmentStart": "2008-09-15",
        "enrollmentEnd": "2008-10-10"
      }
    },
    {
      "id": "FA09",
      "name": "Fall 2009     ",
      "year": 2009,
      "termStartDate": "2009-09-09",
      "termEndDate": "2009-12-31",
      "fileDateForEnrollment": {
        "enrollmentStart": "2009-09-09",
        "enrollmentEnd": "2009-10-09"
      }
    },
    {
      "id": "FA03",
      "name": "Fall 2003     ",
      "year": 2003,
      "termStartDate": "2003-08-27",
      "termEndDate": "2003-12-31",
      "fileDateForEnrollment": null
    },
    {
      "id": "FA04",
      "name": "Fall 2004     ",
      "year": 2004,
      "termStartDate": "2004-09-06",
      "termEndDate": "2004-12-31",
      "fileDateForEnrollment": null
    },
    {
      "id": "FA00",
      "name": "Fall 2000     ",
      "year": 2000,
      "termStartDate": "2000-09-02",
      "termEndDate": "2000-12-31",
      "fileDateForEnrollment": null
    },
    {
      "id": "FA01",
      "name": "Fall 2001     ",
      "year": 2001,
      "termStartDate": "2001-09-06",
      "termEndDate": "2001-12-31",
      "fileDateForEnrollment": null
    },
    {
      "id": "FA02",
      "name": "Fall 2002     ",
      "year": 2002,
      "termStartDate": "2002-08-28",
      "termEndDate": "2002-12-31",
      "fileDateForEnrollment": null
    }
  ]
}
EOS;

        $obj = $this->parse_result($str);
        return $obj->data;
    }
}


/**
 * OAuth 2.0 client for Ucsfsis Services
 *
 * @package   enrol_ucsfsis
 */
class ucsfsis_oauth extends oauth2_client {
    /**
     * Returns the auth url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function auth_url() {
        return 'https://unified-api.ucsf.edu/oauth/1.0/authorize';
    }

    /**
     * Returns the token url for OAuth 2.0 request
     * @return string the auth url
     */
    protected function token_url() {
        // return 'https://accounts.ucsfsis.com/o/oauth2/token';
        return 'https://unified-api.ucsf.edu/oauth/1.0/access_token';
    }

    /**
     * Resets headers and response for multiple requests
     */
    public function reset_state() {
        $this->header = array();
        $this->response = array();
    }
}
