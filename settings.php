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
 * UCSF Student Information System enrolment plugin settings and presets.
 *
 * @package    enrol_ucsfsis
 * @copyright  2016 The Regents of the University of California
 * @author     Carson Tam
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/adminlib.php');

$currenttab = optional_param('tabview', 'settings', PARAM_ALPHA);

if ($ADMIN->fulltree) {

    $tabs = array();
    $tabs[] = new tabobject('settings',
                            new moodle_url('/admin/settings.php', array('section' => 'enrolsettingsucsfsis')),
                            get_string('settings'));

    // $tabs[] = new tabobject('legacy',
    //                         new moodle_url('/admin/settings.php', array('section' => 'enrolsettingsucsfsis', 'tabview' => 'legacy')),
    //                         get_string('legacysettings', 'enrol_ucsfsis'));

    $tabs[] = new tabobject('conversion',
                            new moodle_url('/admin/settings.php', array('section' => 'enrolsettingsucsfsis', 'tabview' => 'conversion')),
                            'CLEAE Conversion');

    $settings->add(new admin_setting_tabtree('enrol_ucsfsis_settings_tabtree', 'treetabvisiblename', 'treetabinformation', $currenttab, $tabs));


    //--- general settings -----------------------------------------------------------------------------------

    if ($currenttab == "conversion") {
        $settings->add(new admin_setting_heading('enrol_ucsfsis_tabview', '', '<input type="hidden" name="tabview" value="'.$currenttab.'" />'));
        $settings->add(new admin_setting_heading('enrol_ucsfsis_conversion', '', get_string('cleae_conversion_desc', 'enrol_ucsfsis')));
        $settings->add(new admin_setting_configfile('enrol_ucsfsis/location', get_string('location', 'enrol_ucsfsis'), get_string('location_desc', 'enrol_ucsfsis'), ''));

        $converturl = new moodle_url('/enrol/ucsfsis/convertnow.php', array('sesskey' => sesskey()));
        $convertnowstring = get_string('aftersaving...', 'enrol_ucsfsis').' ';
        $convertnowstring .= html_writer::link($converturl, get_string('doitnow', 'enrol_ucsfsis'));
        $settings->add(new admin_setting_heading('enrol_ucsfsis_doitnowmessage', '', $convertnowstring));


    } else if ($currenttab == "legacy") {

        //--- legacy settings -----------------------------------------------------------------------------------

        $settings->add(new admin_setting_heading('enrol_ucsfsis_settings', '', get_string('pluginname_desc', 'enrol_ucsfsis')));

        $settings->add(new admin_setting_configfile('enrol_ucsfsis/location', get_string('location', 'enrol_ucsfsis'), get_string('location_desc', 'enrol_ucsfsis'), ''));

        $settings->add(new admin_setting_configtext('enrol_ucsfsis/stopondroppercent', get_string('stopondroppercent', 'enrol_ucsfsis'), get_string('stopondroppercent_desc', 'enrol_ucsfsis'), '100'));

        $options = core_text::get_encodings();
        $settings->add(new admin_setting_configselect('enrol_ucsfsis/encoding', get_string('encoding', 'enrol_ucsfsis'), '', 'UTF-8', $options));

        // $settings->add(new admin_setting_configcheckbox('enrol_ucsfsis/mailstudents', get_string('notifyenrolled', 'enrol_ucsfsis'), '', 0));
        // $settings->add(new admin_setting_configcheckbox('enrol_ucsfsis/mailteachers', get_string('notifyenroller', 'enrol_ucsfsis'), '', 0));
        $settings->add(new admin_setting_configcheckbox('enrol_ucsfsis/mailadmins', get_string('notifyadmin', 'enrol_ucsfsis'), '', 0));

        $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                         ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                         ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
        $settings->add(new admin_setting_configselect('enrol_ucsfsis/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    } else {

        /**
         * Indicates whether we are in the middle of the initial Moodle install.
         *
         * Very occasionally it is necessary avoid running certain bits of code before the
         * Moodle installation has completed. The installed flag is set in admin/index.php
         * after Moodle core and all the plugins have been installed, but just before
         * the person doing the initial install is asked to choose the admin password.
         *
         */

        /* Useful Examples:
        http://www.moodle.dev/admin/settings.php?section=modsettinglti
        http://www.moodle.dev/admin/settings.php?section=modsettingglossary
        http://www.moodle.dev/admin/settings.php?section=modsettingassign
        http://www.moodle.dev/admin/settings.php?section=assignfeedback_editpdf

        */
        $default_api_host = 'https://unified-api.ucsf.edu';
        // $default_api_host = 'https://stage-unified-api.ucsf.edu';

        $settings->add(new admin_setting_heading('enrol_ucsfsis_api_server_settings', get_string('api_server_settings', 'enrol_ucsfsis'), get_string('oauthinfo', 'enrol_ucsfsis')));

        $settings->add(new admin_setting_configtext('enrol_ucsfsis/host_url', get_string('host_url', 'enrol_ucsfsis'), get_string('host_url_desc', 'enrol_ucsfsis'), $default_api_host));
        // TODO: Remove resourceid and password.  Should ask prompt admin to log into MyAccess for token
        $settings->add(new admin_setting_configtext('enrol_ucsfsis/resourceid', get_string('resourceid', 'enrol_ucsfsis'), get_string('resourceid_desc', 'enrol_ucsfsis'), ''));
        $settings->add(new admin_setting_configpasswordunmask('enrol_ucsfsis/resourcepassword', get_string('resourcepassword', 'enrol_ucsfsis'), get_string('resourcepassword_desc', 'enrol_ucsfsis'), ''));

        $settings->add(new admin_setting_configtext('enrol_ucsfsis/clientid', get_string('clientid', 'enrol_ucsfsis'), get_string('clientid_desc', 'enrol_ucsfsis'), ''));
        $settings->add(new admin_setting_configpasswordunmask('enrol_ucsfsis/secret', get_string('secret', 'enrol_ucsfsis'), get_string('secret_desc', 'enrol_ucsfsis'), ''));

        // TODO: Add a button to generate token / refresh token, any example?
        // $settings->add(new admin_setting_configtext('enrol_ucsfsis/accesstoken', get_string('accesstoken', 'enrol_ucsfsis'), get_string('accesstoken_desc', 'enrol_ucsfsis'), ''));
        // $settings->add(new admin_setting_configtext('enrol_ucsfsis/refreshtoken', get_string('refreshtoken', 'enrol_ucsfsis'), get_string('refreshtoken_desc', 'enrol_ucsfsis'), ''));
        // $settings->add(new admin_setting_configtext('enrol_ucsfsis/accesstokenexpiretime', 'Expiration', 'Expiration description', ''));


        //--- enrol instance defaults ----------------------------------------------------------------------------
        $settings->add(new admin_setting_heading('enrol_ucsfsis_defaults',
            get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));


        if (!during_initial_install()) {
            // Default role for students
            $options = get_default_enrol_roles(context_system::instance());
            $student = get_archetype_roles('student');
            $student = reset($student);
            $settings->add(new admin_setting_configselect('enrol_ucsfsis/default_student_roleid',
                                                          get_string('defaultrole', 'role') . ' for students', '', $student->id, $options));

//            // TODO: Add default role for primary instructor
//            $options = get_default_enrol_roles(context_system::instance());
//            $editingteacher = get_archetype_roles('editingteacher');
//            $editingteacher = reset($editingteacher);
//            $settings->add(new admin_setting_configselect('enrol_ucsfsis/default_primary_instructor_roleid',
//                get_string('defaultrole', 'role') . ' for primary instructor', '', $editingteacher->id, $options));
//
//            // TODO: Add default role for other instructors
//            $options = get_default_enrol_roles(context_system::instance());
//            $teacher = get_archetype_roles('teacher');
//            $teacher = reset($teacher);
//            $settings->add(new admin_setting_configselect('enrol_ucsfsis/default_instructor_roleid',
//                get_string('defaultrole', 'role') . ' for other instructors', '', $teacher->id, $options));
        }

        // Check out role mapping
        $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                         ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                         ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                         ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
        $settings->add(new admin_setting_configselect('enrol_ucsfsis/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));
    }
}
