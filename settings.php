<?php
// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * Creates a link to the upload form on the settings page.
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$url = $CFG->wwwroot . '/mod/leeloocert/verify_certificate.php';

require_once($CFG->dirroot . '/mod/leeloocert/lib.php');

$ADMIN->add('modsettings', new admin_category('leeloocert', get_string('pluginname', 'mod_leeloocert')));
$settings = new admin_settingpage('modsettingleeloocert', new lang_string('leeloocertsettings', 'mod_leeloocert'));

$settings->add(new admin_setting_configtext(
    'leeloocert/license',
    get_string('license', 'leeloocert'),
    get_string('license', 'leeloocert'),
    0
));


$settings->add(new \mod_leeloocert\admin_setting_link(
    'leeloocert/verifycertificate',
    get_string('verifycertificate', 'leeloocert'),
    get_string('verifycertificatedesc', 'leeloocert'),
    get_string('verifycertificate', 'leeloocert'),
    new moodle_url('/mod/leeloocert/verify_certificate.php'),
    ''
));

$settings->add(new \mod_leeloocert\admin_setting_link(
    'leeloocert/managetemplates',
    get_string('managetemplates', 'leeloocert'),
    get_string('managetemplatesdesc', 'leeloocert'),
    get_string('managetemplates', 'leeloocert'),
    new moodle_url('/mod/leeloocert/manage_templates.php'),
    ''
));

$settings->add(new \mod_leeloocert\admin_setting_link(
    'leeloocert/uploadimage',
    get_string('uploadimage', 'leeloocert'),
    get_string('uploadimagedesc', 'leeloocert'),
    get_string('uploadimage', 'leeloocert'),
    new moodle_url('/mod/leeloocert/upload_image.php'),
    ''
));

$setting = new admin_setting_configleeloocert('leeloocert/settingsjson', '', '', '', PARAM_RAW);
$settings->add($setting);

$ADMIN->add('leeloocert', $settings);

// Element plugin settings.
$ADMIN->add('leeloocert', new admin_category('leeloocertelements', get_string('elementplugins', 'leeloocert')));
$plugins = \core_plugin_manager::instance()->get_plugins_of_type('leeloocertelement');
foreach ($plugins as $plugin) {
    $plugin->load_settings($ADMIN, 'leeloocertelements', $hassiteconfig);
}

// Tell core we already added the settings structure.
$settings = null;
