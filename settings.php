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

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 *
 * @package    mod_kuet
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $PAGE, $CFG, $ADMIN;

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('modsettingkuet', get_string('configtitle', 'mod_kuet'));
    /*$page = new admin_settingpage('mod_kuet_general', get_string('generalsettings', 'mod_kuet'));

    // Modedit defaults.
    $setting = new admin_setting_heading('jqshow_header',
        get_string('jqshow_header', 'mod_kuet'), '');
    $page->add($setting);


    $settings->add($page);*/

    $page = new admin_settingpage('mod_kuet_socket', get_string('socket', 'mod_kuet'));
    $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes);

    $setting = new admin_setting_configselect(
        'kuet/sockettype',
        get_string('sockettype', 'mod_kuet'),
        get_string('sockettype_desc', 'mod_kuet'),
        'nosocket',
        [
            'nosocket' => get_string('nosocket', 'mod_kuet'),
            'local' => get_string('local', 'mod_kuet'),
            'external' => get_string('external', 'mod_kuet')
        ]
    );
    $page->add($setting);

    $setting = new admin_setting_configtext_with_maxlength(
        'kuet/localport',
        get_string('port', 'mod_kuet'),
        get_string('port_desc', 'mod_kuet'), '8080', PARAM_INT, 4, 4);
    $settings->hide_if('kuet/localport', 'kuet/sockettype', 'neq', 'local');
    $page->add($setting);

    $setting = new admin_setting_configstoredfile (
        'kuet/certificate',
        get_string('certificate', 'mod_kuet'),
        get_string('certificate_desc', 'mod_kuet'),
        'certificate_ssl',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.crt', '.pem'], 'maxbytes' => $maxbytes]
    );
    $settings->hide_if('kuet/certificate', 'kuet/sockettype', 'neq', 'local');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_configstoredfile (
        'kuet/privatekey',
        get_string('privatekey', 'mod_kuet'),
        get_string('privatekey_desc', 'mod_kuet'),
        'privatekey_ssl',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.pem', '.key'], 'maxbytes' => $maxbytes]
    );
    $settings->hide_if('kuet/privatekey', 'kuet/sockettype', 'neq', 'local');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $setting = new admin_setting_configtext(
        'kuet/externalurl',
        get_string('externalurl', 'mod_kuet'),
        get_string('externalurl_desc', 'mod_kuet'), $CFG->wwwroot, PARAM_URL);
    $settings->hide_if('kuet/externalurl', 'kuet/sockettype', 'neq', 'external');
    $page->add($setting);

    $setting = new admin_setting_configtext_with_maxlength(
        'kuet/externalport',
        get_string('port', 'mod_kuet'),
        get_string('port_desc', 'mod_kuet'), '8080', PARAM_INT, 4, 4);
    $settings->hide_if('kuet/externalport', 'kuet/sockettype', 'neq', 'external');
    $page->add($setting);

    $setting = new admin_setting_description('kuet/separator', '', html_writer::tag('hr', ''));
    $page->add($setting);

    $setting = new admin_setting_description(
        'kuet/downloadsocket',
        get_string('downloadsocket', 'mod_kuet'),
        html_writer::div(
            get_string('downloadsocket_desc', 'mod_kuet'),
            'alert alert-info',
            ['role' => 'alert']) .
        html_writer::link(
            new moodle_url('/mod/kuet/unimoodleservercli.php'),
            html_writer::tag('b', get_string('scriptphp', 'mod_kuet')),
            ['download' => 'unimoodleservercli.php']
        ) .
        html_writer::tag('hr', '')
    );
    $settings->hide_if('kuet/downloadsocket', 'kuet/sockettype', 'neq', 'external');
    $page->add($setting);

    $setting = new admin_setting_description(
        'kuet/testssl',
        get_string('testssl', 'mod_kuet'),
        html_writer::div(
            get_string('warningtest', 'mod_kuet'),
            'alert alert-danger',
            ['role' => 'alert']) .
        html_writer::link(
            new moodle_url('/mod/kuet/testssl.php'),
            get_string('testssl_desc', 'mod_kuet'),
            ['target' => '_blank']
        ) .
        html_writer::tag('hr', '')
    );
    $settings->hide_if('kuet/testssl', 'kuet/sockettype', 'eq', 'nosocket');
    $page->add($setting);

    $settings->add($page);
}
