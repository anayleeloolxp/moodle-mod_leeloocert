<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
/**
 * Plugin administration pages are defined here.
 *
 * @package     mod_leeloolxpcert
 * @category    admin
 * @copyright   2022 Leeloo LXP <info@leeloolxp.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    if ($ADMIN->fulltree) {
        // TODO: Define the plugin settings page - {@link https://docs.moodle.org/dev/Admin_settings}.
        require_once($CFG->dirroot . '/mod/leeloolxpcert/lib.php');
        $ADMIN->add('modsettings', new admin_category('leeloolxpcert', get_string('pluginname', 'mod_leeloolxpcert')));
        $settings = new admin_settingpage('modsettingleeloolxpcert', new lang_string('leeloolxpcertsettings', 'mod_leeloolxpcert'));
        $settings->add(new admin_setting_configtext(
            'leeloolxpcert/license',
            get_string('license', 'leeloolxpcert'),
            get_string('license', 'leeloolxpcert'),
            0
        ));
        $ADMIN->add('leeloolxpcert', $settings);
        // Element plugin settings.
        $ADMIN->add('leeloolxpcert', new admin_category('leeloolxpcertelements', get_string('elementplugins', 'leeloolxpcert')));
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('leeloolxpcertelement');
        foreach ($plugins as $plugin) {
            $plugin->load_settings($ADMIN, 'leeloolxpcertelements', $hassiteconfig);
        }
        // Tell core we already added the settings structure.
        $settings = null;
    }
}
