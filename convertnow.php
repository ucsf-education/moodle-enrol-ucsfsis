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

require('../../config.php');

require_login(0, false);
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

$site = get_site();

// Get language strings.
$PAGE->set_context(context_system::instance());

$PAGE->set_url('/enrol/ucsfsis/convertnow.php');
$PAGE->set_title(get_string('convertingcleaecourseid', 'enrol_ucsfsis'));
$PAGE->set_heading(get_string('convertcleaecourseid', 'enrol_ucsfsis'));
$PAGE->navbar->add(get_string('administrationsite'));
$PAGE->navbar->add(get_string('plugins', 'admin'));
$PAGE->navbar->add(get_string('enrolments', 'enrol'));
$PAGE->navbar->add(get_string('pluginname_short', 'enrol_ucsfsis'),
    new moodle_url('/admin/settings.php', array('section' => 'enrolsettingsucsfsis')));
$PAGE->navbar->add(get_string('convertingcleaecourseid', 'enrol_ucsfsis'));
$PAGE->navigation->clear_cache();

echo $OUTPUT->header();

require_once('lib.php');

$enrol = new enrol_ucsfsis_plugin();

?>
<p>Launching CLEAE conversion function. The conversion log will appear below (giving details of any
problems that might require attention).</p>
<pre style="margin:10px; padding: 2px; border: 1px solid black; background-color: white; color: black;"><?php
       $trace = new text_progress_trace();
       $enrol->convert_cleae($trace);
       $trace->finished();
?></pre><?php
echo $OUTPUT->footer();
