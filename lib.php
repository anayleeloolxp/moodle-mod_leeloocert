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
 * Library of interface functions and constants.
 *
 * @package     mod_leeloolxpcert
 * @copyright   2022 Leeloo LXP <info@leeloolxp.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing the feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function leeloolxpcert_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        default:
            return null;
    }
}
/**
 * Saves a new instance of the mod_leeloolxpcert into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_leeloolxpcert_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function leeloolxpcert_add_instance($moduleinstance, $mform = null) {
    global $DB;
    $moduleinstance->timecreated = time();
    $id = $DB->insert_record('leeloolxpcert', $moduleinstance);
    return $id;
}
/**
 * Updates an instance of the mod_leeloolxpcert in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_leeloolxpcert_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function leeloolxpcert_update_instance($moduleinstance, $mform = null) {
    global $DB;
    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;
    return $DB->update_record('leeloolxpcert', $moduleinstance);
}
/**
 * Removes an instance of the mod_leeloolxpcert from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function leeloolxpcert_delete_instance($id) {
    global $DB;
    $exists = $DB->get_record('leeloolxpcert', array('id' => $id));
    if (!$exists) {
        return false;
    }
    $DB->delete_records('leeloolxpcert', array('id' => $id));
    return true;
}
/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 * @return bool
 */
function mod_leeloolxpcert_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    $url = new moodle_url('/mod/leeloolxpcert/my_certificates.php', array('userid' => $user->id));
    $node = new core_user\output\myprofile\node(
        'miscellaneous',
        'myleeloolxpcerts',
        get_string('mycertificates', 'leeloolxpcert'),
        null,
        $url
    );
    $tree->add_node($node);
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called.
 *
 * @param settings_navigation $settings
 * @param navigation_node $leeloolxpcertnode
 */
function leeloolxpcert_extend_settings_navigation(settings_navigation $settings, navigation_node $leeloolxpcertnode) {
    global $DB, $PAGE;

    $keys = $leeloolxpcertnode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/leeloolxpcert:verifycertificate', $PAGE->cm->context)) {
        $node = navigation_node::create(
            get_string('verifycertificate', 'leeloolxpcert'),
            new moodle_url('/mod/leeloolxpcert/verify_certificate.php', array('contextid' => $PAGE->cm->context->id)),
            navigation_node::TYPE_SETTING,
            null,
            'mod_leeloolxpcert_verify_certificate',
            new pix_icon('t/check', '')
        );
        $leeloolxpcertnode->add_node($node, $beforekey);
    }

    return $leeloolxpcertnode->trim_if_empty();
}
