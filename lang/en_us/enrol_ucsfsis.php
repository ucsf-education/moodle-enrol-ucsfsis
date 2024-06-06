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
 * Strings for component 'enrol_ucsfsis', language 'en_us'.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Todo: Convert 'enrol' to 'enroll' in en_us directory.
// Todo: Convert 'Enrolment' to 'Enrollment' in en_us directory.
// Todo: Convert 'synchronise' to 'synchronize' in en_us directory.
// Todo: Enter text for help tips.

$string['accesstoken'] = 'Access token';
$string['accesstoken_desc'] = 'Access token description';
$string['addgroup'] = 'Add to group';
$string['aftersaving...'] = 'Once you have saved your settings, you may wish to';
$string['ajaxmore'] = 'More...';
$string['api_server_settings'] = 'API server settings';
$string['assignrole'] = 'Assign role';
$string['choosecoursedots'] = 'Choose a course...';
$string['choosesubjectdots'] = 'Choose a subject...';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Enter client ID for your application that you obtained from your UCSF SIS server.';
$string['convertedfrom'] = 'This enrolment was converted from \'{$a}\'.';
$string['course'] = 'Select a course';
$string['course_help'] = 'Select a course from the Student Information System to be used for enrollment.';
$string['courseoptionsupdate'] = 'Update Course options';
$string['coursesearch'] = 'Search';
$string['crontask'] = 'Synchronize SIS enrollment';
$string['department'] = 'Department';
$string['department_help'] = 'Help text for Department';
$string['departmentoptionsupdate'] = 'Department Options Update';
$string['encoding'] = 'File encoding';
$string['enrol'] = 'Enroll SIS students';
$string['enrolmentstartson'] = ' (enrollment starts on {$a})';
$string['enrolusers'] = 'Enroll users';
$string['expiredaction'] = 'Enrollment expiration action';
$string['expiredaction_help'] = 'Select action to carry out when user enrollment expires. Please note that some user data and settings are purged from course during course unenrollment.';
$string['filelockedmail'] = 'The text file you are using for file-based enrollments ({$a}) can not be deleted by the cron process.  This usually means the permissions are wrong on it.  Please fix the permissions so that Moodle can delete the file, otherwise it might be processed repeatedly.';
$string['filelockedmailsubject'] = 'Important error: Enrollment file';
$string['flatfile:manage'] = 'Manage user enrollments manually';
$string['flatfile:unenrol'] = 'Unenroll users from the course manually';
$string['host_url'] = 'Host URL';
$string['host_url_desc'] = 'Enter alternate UCSF SIS API server address here, e.g. <em>https://stage-unified-api.ucsf.edu</em>';
$string['http_get'] = 'GET';
$string['http_post'] = 'POST';
$string['instanceexists'] = 'Cohort is already synchronized with selected role';
$string['location'] = 'File location';
$string['location_desc'] = 'Specify full path to the enrollment file. The file is automatically deleted after processing.';
$string['mapping'] = 'Flat file role mapping';
$string['messageprovider:flatfile_enrollment'] = 'Flat file enrollment messages';
$string['notifyadmin'] = 'Notify administrator';
$string['notifyenrolled'] = 'Notify enrolled users';
$string['notifyenroller'] = 'Notify user responsible for enrollments';
$string['oauthinfo'] = '<p>To use this plugin, you must register your site with UCSF at <a href="https://anypoint.mulesoft.com/apiplatform/ucsf#/portals">Mulesoft</a>.</p>
<p>As part of the registration process, you will be provided with a client ID and client secret which can be entered here.</p>';
$string['pluginname'] = 'UCSF Student Information System enrollments';
$string['pluginname_desc'] = 'This method repeatedly check for and process a specially-formatted text file in the location that you specify.
The file is a comma separated file assumed to have four or six fields per line:

    // operation, role, user idnumber, course idnumber [, starttime [, endtime]]
    ucid, ucsfid, term code, department code, course number, subject shortname, course title, instructor code, instructor ucid, instructor name

where:

* ucid - UC ID | Employee number, e.g. 023456789
* ucsfid - UCSF ID, e.g. sf345678
* term code - term and year, e.g. FA16
* department code - e.g. PT for Physical Therapy
* course number - e.g. 212A
* subject shortname - e.g. PHYS THER
* course title - e.g. Muscle and Nerve Biology
* instructor code - Special instructor code used in SIS, e.g. AL7
* instructor ucid - UC ID | Employee number, e.g. 023456789
* instructor name - Last name and first name, e.g. Lui Andrew J

// * operation - add | del
// * role - student | teacher | teacheredit
// * user idnumber - idnumber in the user table NB not id
// * course idnumber - idnumber in the course table NB not id
// * starttime - start time (in seconds since epoch) - optional
// * endtime - end time (in seconds since epoch) - optional

It could look something like this:
<pre class="informationbox">
   "027967363","sf796736","FA14","PT","212A","PHYS THER","Muscle and Nerve Biology","AL7","024619819","Lui Andrew J"
   "023814312","sf381431","FA14","PT","212A","PHYS THER","Muscle and Nerve Biology","AL7","024619819","Lui Andrew J"
   "020112272","sf011227","FA14","PT","212A","PHYS THER","Muscle and Nerve Biology","AL7","024619819","Lui Andrew J"
   "027401413","sf740141","FA14","PT","212A","PHYS THER","Muscle and Nerve Biology","AL7","024619819","Lui Andrew J"
   "028615748","sf861574","FA14","PT","212A","PHYS THER","Muscle and Nerve Biology","AL7","024619819","Lui Andrew J"
   // add, student, 5, CF101
   // add, teacher, 6, CF101
   // add, teacheredit, 7, CF101
   // del, student, 8, CF101
   // del, student, 17, CF101
   // add, student, 21, CF101, 1091115000, 1091215000
</pre>';
$string['pluginname_short'] = 'SIS enrollments';
$string['privacy:metadata'] = 'The UCSF SIS enrollment plugin does not store any personal data.';
$string['refreshtoken'] = 'Refresh token';
$string['refreshtoken_desc'] = 'Refresh token description';
$string['requestmethod'] = 'HTTP request method';
$string['requestmethod_desc'] = 'Select the HTTP request method that is supported by the UCSF SIS API server.';
$string['resourceid'] = 'UCSF resource account ID';
$string['resourceid_desc'] = 'Enter the UCSF resource account ID to be used to contact the SIS server here.';
$string['resourcepassword'] = 'UCSF resource account password';
$string['resourcepassword_desc'] = 'Enter the UCSF resource account password to be used to contact the UCSF SIS server here.';
$string['school'] = 'School';
$string['school_help'] = 'Help text for School';
$string['schooloptionsupdate'] = 'School options update';
$string['secret'] = 'Client secret';
$string['secret_desc'] = 'Enter the secret string generated by your UCSF SIS server here.';
$string['sisserverdown'] = 'SIS server is not available at this time. Please try again later.';
$string['status'] = 'Enable SIS enrollments';
$string['status_help'] = 'Enable enrollments from the UCSF Student Information Systems.';
$string['stopondroppercent'] = 'Stop drop percentage (in %)';
$string['stopondroppercent_desc'] = 'Do not process file when filesize has dropped more than this percentage since last process.';
$string['subject'] = 'Select a subject';
$string['subject_help'] = 'Select a subject in which the SIS course that will be used for enrollments.';
$string['subjectoptionsupdate'] = 'Update Subject option';
$string['term'] = 'Select a term (quarter)';
$string['term_help'] = 'Select an academic term (quarter) for the SIS course that will be used for enrollments for this course.';
$string['term_subject'] = 'Select Term / Subject';
$string['term_subject_help'] = 'Help text for term and Subject';
$string['termoptionsupdate'] = 'Update Term option';
$string['ucsfsis:config'] = 'Configure UCSF SIS instances';
$string['ucsfsis:unenrol'] = 'Unenroll suspended users';
$string['viewenrolments'] = 'View SIS enrollments';
