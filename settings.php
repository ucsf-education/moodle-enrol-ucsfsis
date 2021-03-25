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
require_once(__DIR__.'/lib.php');


defined('MOODLE_INTERNAL') || die();

use enrol_ucsfsis\ucsfsis_oauth_client;

if ($ADMIN->fulltree) {

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(
        new admin_setting_heading(
            'enrol_ucsfsis_api_server_settings',
            get_string('api_server_settings', 'enrol_ucsfsis'),
            get_string('oauthinfo', 'enrol_ucsfsis')
        )
    );


    //--- enrol instance defaults ----------------------------------------------------------------------------
    if (!during_initial_install()) {

        $default_api_host = ucsfsis_oauth_client::DEFAULT_HOST;

        $settings->add(
            new admin_setting_configtext(
                'enrol_ucsfsis/host_url',
                get_string('host_url', 'enrol_ucsfsis'),
                get_string('host_url_desc', 'enrol_ucsfsis'),
                $default_api_host
            )
        );

        // TODO: Remove resourceid and password.  Should ask prompt admin to log in and do callbacks
        $settings->add(
            new admin_setting_configtext(
                'enrol_ucsfsis/resourceid',
                get_string('resourceid', 'enrol_ucsfsis'),
                get_string('resourceid_desc', 'enrol_ucsfsis'),
                ''
            )
        );
        $settings->add(
            new admin_setting_configpasswordunmask(
                'enrol_ucsfsis/resourcepassword',
                get_string('resourcepassword', 'enrol_ucsfsis'),
                get_string('resourcepassword_desc', 'enrol_ucsfsis'),
                ''
            )
        );

        $settings->add(
            new admin_setting_configtext(
                'enrol_ucsfsis/clientid',
                get_string('clientid', 'enrol_ucsfsis'),
                get_string('clientid_desc', 'enrol_ucsfsis'),
                ''
            )
        );
        $settings->add(
            new admin_setting_configpasswordunmask(
                'enrol_ucsfsis/secret',
                get_string('secret', 'enrol_ucsfsis'),
                get_string('secret_desc', 'enrol_ucsfsis'),
                ''
            )
        );
        $settings->add(
            new admin_setting_configselect(
                'enrol_ucsfsis/requestmethod',
                get_string('requestmethod', 'enrol_ucsfsis'),
                get_string('requestmethod_desc', 'enrol_ucsfsis'),
                0,
                array(
                    0 => new lang_string('http_get', 'enrol_ucsfsis'),
                    1 => new lang_string('http_post', 'enrol_ucsfsis')
                )
            )
        );

        $settings->add(
            new admin_setting_heading(
                'enrol_ucsfsis_defaults',
                get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')
            )
        );

        // Default role for students
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(
            new admin_setting_configselect(
                'enrol_ucsfsis/default_student_roleid',
                get_string('defaultrole', 'role').' for students', '', $student->id, $options
            )
        );

        // Check out role mapping
        $options = array(
            ENROL_EXT_REMOVED_UNENROL => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_KEEP => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPEND => get_string('extremovedsuspend', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol')
        );
        $settings->add(
            new admin_setting_configselect(
                'enrol_ucsfsis/unenrolaction',
                get_string('extremovedaction', 'enrol'),
                get_string('extremovedaction_help', 'enrol'),
                ENROL_EXT_REMOVED_UNENROL,
                $options
            )
        );
    }
}
