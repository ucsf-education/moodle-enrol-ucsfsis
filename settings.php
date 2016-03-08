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

if ($ADMIN->fulltree) {

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_ucsfsis_settings', '', get_string('pluginname_desc', 'enrol_ucsfsis')));

    $settings->add(new admin_setting_configfile('enrol_ucsfsis/location', get_string('location', 'enrol_ucsfsis'), get_string('location_desc', 'enrol_ucsfsis'), ''));

    $options = core_text::get_encodings();
    $settings->add(new admin_setting_configselect('enrol_ucsfsis/encoding', get_string('encoding', 'enrol_ucsfsis'), '', 'UTF-8', $options));

    $settings->add(new admin_setting_configcheckbox('enrol_ucsfsis/mailstudents', get_string('notifyenrolled', 'enrol_ucsfsis'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_ucsfsis/mailteachers', get_string('notifyenroller', 'enrol_ucsfsis'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_ucsfsis/mailadmins', get_string('notifyadmin', 'enrol_ucsfsis'), '', 0));

    $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                     ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
    $settings->add(new admin_setting_configselect('enrol_ucsfsis/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    //       it describes what should happen when users are not supposed to be enrolled any more.
    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_ucsfsis/expiredaction', get_string('expiredaction', 'enrol_ucsfsis'), get_string('expiredaction_help', 'enrol_ucsfsis'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

    //--- mapping -------------------------------------------------------------------------------------------
    if (!during_initial_install()) {
        $settings->add(new admin_setting_heading('enrol_ucsfsis_mapping', get_string('mapping', 'enrol_ucsfsis'), ''));

        $roles = role_fix_names(get_all_roles());

        foreach ($roles as $role) {
            $settings->add(new enrol_ucsfsis_role_setting($role));
        }
        unset($roles);
    }

    //--- enrol instance defaults ----------------------------------------------------------------------------
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_ucsfsis/roleid',
            get_string('defaultrole', 'role'), '', $student->id, $options));

        $options = array(
            ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
        $settings->add(new admin_setting_configselect('enrol_ucsfsis/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));
    }
}
