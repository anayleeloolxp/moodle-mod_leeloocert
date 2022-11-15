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
 * This is the external API for this tool.
 *
 * @package    mod_leeloolxpcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leeloolxpcert;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

/**
 * This is the external API for this tool.
 *
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * Returns the save_element() parameters.
     *
     * @return \external_function_parameters
     */
    public static function save_element_parameters() {
        return new \external_function_parameters(
            array(
                'templateid' => new \external_value(PARAM_INT, 'The template id'),
                'elementid' => new \external_value(PARAM_INT, 'The element id'),
                'values' => new \external_multiple_structure(
                    new \external_single_structure(
                        array(
                            'name' => new \external_value(PARAM_ALPHANUMEXT, 'The field to update'),
                            'value' => new \external_value(PARAM_RAW, 'The value of the field'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Handles saving element data.
     *
     * @param int $templateid The template id.
     * @param int $elementid The element id.
     * @param array $values The values to save
     * @return array
     */
    public static function save_element($templateid, $elementid, $values) {
        global $DB;

        $params = array(
            'templateid' => $templateid,
            'elementid' => $elementid,
            'values' => $values
        );
        self::validate_parameters(self::save_element_parameters(), $params);

        $template = $DB->get_record('leeloolxpcert_templates', array('id' => $templateid), '*', MUST_EXIST);
        $element = $DB->get_record('leeloolxpcert_elements', array('id' => $elementid), '*', MUST_EXIST);

        // Set the template.
        $template = new \mod_leeloolxpcert\template($template);

        // Perform checks.
        if ($cm = $template->get_cm()) {
            self::validate_context(\context_module::instance($cm->id));
        } else {
            self::validate_context(\context_system::instance());
        }
        // Make sure the user has the required capabilities.
        $template->require_manage();

        // Set the values we are going to save.
        $data = new \stdClass();
        $data->id = $element->id;
        $data->name = $element->name;
        foreach ($values as $value) {
            $field = $value['name'];
            $data->$field = $value['value'];
        }

        // Get an instance of the element class.
        if ($e = \mod_leeloolxpcert\element_factory::get_element_instance($element)) {
            return $e->save_form_elements($data);
        }

        return false;
    }

    /**
     * Returns the save_element result value.
     *
     * @return \external_value
     */
    public static function save_element_returns() {
        return new \external_value(PARAM_BOOL, 'True if successful, false otherwise');
    }

    /**
     * Returns get_element() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_element_html_parameters() {
        return new \external_function_parameters(
            array(
                'templateid' => new \external_value(PARAM_INT, 'The template id'),
                'elementid' => new \external_value(PARAM_INT, 'The element id'),
            )
        );
    }

    /**
     * Handles return the element's HTML.
     *
     * @param int $templateid The template id
     * @param int $elementid The element id.
     * @return string
     */
    public static function get_element_html($templateid, $elementid) {
        global $DB;

        $params = array(
            'templateid' => $templateid,
            'elementid' => $elementid
        );
        self::validate_parameters(self::get_element_html_parameters(), $params);

        $template = $DB->get_record('leeloolxpcert_templates', array('id' => $templateid), '*', MUST_EXIST);
        $element = $DB->get_record('leeloolxpcert_elements', array('id' => $elementid), '*', MUST_EXIST);

        // Set the template.
        $template = new \mod_leeloolxpcert\template($template);

        // Perform checks.
        if ($cm = $template->get_cm()) {
            self::validate_context(\context_module::instance($cm->id));
        } else {
            self::validate_context(\context_system::instance());
        }

        // Get an instance of the element class.
        if ($e = \mod_leeloolxpcert\element_factory::get_element_instance($element)) {
            return $e->render_html();
        }

        return '';
    }

    /**
     * Returns the get_element result value.
     *
     * @return \external_value
     */
    public static function get_element_html_returns() {
        return new \external_value(PARAM_RAW, 'The HTML');
    }

    /**
     * Returns the delete_issue() parameters.
     *
     * @return \external_function_parameters
     */
    public static function delete_issue_parameters() {
        return new \external_function_parameters(
            array(
                'certificateid' => new \external_value(PARAM_INT, 'The certificate id'),
                'issueid' => new \external_value(PARAM_INT, 'The issue id'),
            )
        );
    }

    /**
     * Handles deleting a leeloolxpcert issue.
     *
     * @param int $certificateid The certificate id.
     * @param int $issueid The issue id.
     * @return bool
     */
    public static function delete_issue($certificateid, $issueid) {
        global $DB;

        $params = [
            'certificateid' => $certificateid,
            'issueid' => $issueid
        ];
        self::validate_parameters(self::delete_issue_parameters(), $params);

        $certificate = $DB->get_record('leeloolxpcert', ['id' => $certificateid], '*', MUST_EXIST);
        $issue = $DB->get_record('leeloolxpcert_issues', ['id' => $issueid, 'leeloolxpcertid' => $certificateid], '*', MUST_EXIST);

        $cm = get_coursemodule_from_instance('leeloolxpcert', $certificate->id, 0, false, MUST_EXIST);

        // Make sure the user has the required capabilities.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/leeloolxpcert:manage', $context);

        // Delete the issue.
        return $DB->delete_records('leeloolxpcert_issues', ['id' => $issue->id]);
    }

    /**
     * Returns the delete_issue result value.
     *
     * @return \external_value
     */
    public static function delete_issue_returns() {
        return new \external_value(PARAM_BOOL, 'True if successful, false otherwise');
    }
}
