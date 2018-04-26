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
 * UCSF Student Information System enrolment task.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam <carson.tam@ucsf.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_ucsfsis\task;

/**
 * Simple task to run the stats cron.
 */
class cron_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        global $CFG, $DB;

        // Task Name
        $task_name = get_string('crontask', 'enrol_ucsfsis');

        // Additional information
        $enrol = enrol_get_plugin('ucsfsis');
        $info  = '';

        // calculate completed percentage
        $index = $enrol->get_config('last_sync_course_index', 0);
        if (!empty($index)) {
            $total = $DB->count_records('enrol', array( 'enrol'=>'ucsfsis', 'status' => '0'));
            if (!empty($total)) {
                $info .= '<br />'.round($index/$total * 100) . "% has been completed.";
            }
        }

        // last completed time
        $last_completed = $enrol->get_config('last_completed_time');
        if (!empty($last_completed)) {
            $info .= "<br />Last complete run was on ".userdate($last_completed).'.';
        }

        // Append and format extra information
        if (!empty($info)) {
            $task_name .= "<small><em>$info</em></small>";
        }

        return $task_name;
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/ucsfsis/lib.php');
        require_once($CFG->libdir . '/weblib.php');

        $enrol = enrol_get_plugin('ucsfsis');
        $total = $DB->count_records('enrol', array( 'enrol'=>'ucsfsis', 'status' => '0'));
        $numlimit   = ceil($total / 6);  // Number of courses to sync on each run (Try to have a complete run within an hour.)
        $numupdated = 0;                 // Number of courses has been sync'd on this run
        $startindex = $enrol->get_config('last_sync_course_index', 0);

        $courses = $DB->get_records( 'enrol',
                                     array( 'enrol'=>'ucsfsis', 'status' => '0' ),
                                     'timecreated',
                                     'id, courseid, roleid, customint1',
                                     $startindex, $numlimit );
        if (empty($courses)) {
            // Reset startindex for next run
            $startindex = 0;    // start from beginning again
            $enrol->set_config('last_sync_course_index', $startindex);
            // $courses = $DB->get_records( 'enrol',
            //                              array( 'enrol'=>'ucsfsis', 'status' => '0' ),
            //                              'timecreated',
            //                              'id, courseid, roleid, customint1',
            //                              $startindex, $numlimit );

            // Prefetch common cache data
            $enrol->prefetch_common_cache_data();
            return;
        }

        $trace = new \text_progress_trace();

        foreach ($courses as $course) {
            $enrol->sync($trace, $course->courseid);
            $numupdated++;
            $enrol->set_config('last_sync_course_index', $startindex + $numupdated);
        }

        if ($startindex + $numupdated >= $total) {
            $enrol->set_config('last_completed_time', time());
        }

        $trace->finished();
    }

}
