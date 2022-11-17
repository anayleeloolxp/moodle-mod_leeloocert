<?php
// This file is part of the leeloolxpcert module for Moodle - http://moodle.org/
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
 * This file contains the leeloolxpcert element coursefield's core interaction API.
 *
 * @package    leeloolxpcertelement_coursefield
 * @copyright  2019 Catalyst IT
 * @author     Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace leeloolxpcertelement_coursefield;

defined('MOODLE_INTERNAL') || die();
/**
 * The leeloolxpcert element coursefield's core interaction API.
 *
 * @package    leeloolxpcertelement_coursefield
 * @copyright  2019 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_leeloolxpcert\element {
    /**
     * This function renders the form elements when adding a leeloolxpcert element.
     *
     * @param \MoodleQuickForm $mform the edit form instance
     */
    public function render_form_elements($mform) {
        // Get the user profile fields.
        $coursefields = array(
            'fullname' => get_string('fullnamecourse'),
            'shortname' => get_string('shortnamecourse'),
            'idnumber' => get_string('idnumbercourse')
        );
        // Get the course leeloo fields.
        $arrcustomfields = array();
        $handler = \core_course\customfield\course_handler::create();
        $customfields = $handler->get_fields();
        foreach ($customfields as $field) {
            $arrcustomfields[$field->get('id')] = $field->get_formatted_name();
        }
        // Combine the two.
        $fields = $coursefields + $arrcustomfields;
        \core_collator::asort($fields);
        // Create the select box where the user field is selected.
        $mform->addElement('select', 'coursefield', get_string('coursefield', 'leeloolxpcertelement_coursefield'), $fields);
        $mform->setType('coursefield', PARAM_ALPHANUM);
        $mform->addHelpButton('coursefield', 'coursefield', 'leeloolxpcertelement_coursefield');
        parent::render_form_elements($mform);
    }
    /**
     * This will handle how form data will be saved into the data column in the
     * leeloolxpcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the text
     */
    public function save_unique_data($data) {
        return $data->coursefield;
    }
    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        $activityid = $this->id;

        if (!empty($user->aroptions)) {

            if (!empty($user->aroptions->activityid)) {
                $activityid = $user->aroptions->activityid;
            }
        }
        $courseid = \mod_leeloolxpcert\element_helper::get_courseid($activityid);
        $course = get_course($courseid);
        \mod_leeloolxpcert\element_helper::render_content($pdf, $this, $this->get_course_field_value($course, $preview, $activityid, $user->aroptions->contextid));
    }
    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     */
    public function render_html() {
        global $COURSE;
        return \mod_leeloolxpcert\element_helper::render_html_content($this, $this->get_course_field_value($COURSE, true));
    }
    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit form instance
     */
    public function definition_after_data($mform) {
        if (!empty($this->get_data())) {
            $element = $mform->getElement('coursefield');
            $element->setValue($this->get_data());
        }
        parent::definition_after_data($mform);
    }
    /**
     * Helper function that returns the field value in a human-readable format.
     *
     * @param \stdClass $course the course we are rendering this for
     * @param bool $preview Is this a preview?
     * @return string
     */
    protected function get_course_field_value(\stdClass $course, bool $preview, $activityid = 0, $contextid = 0): string {
        // The user field to display.
        $field = $this->get_data();
        // The value to display - we always want to show a value here so it can be repositioned.
        if ($preview) {
            $value = $field;
        } else {
            $value = '';
        }
        if (!empty($activityid)) {
            $id = $activityid;
        } else {
            $id = $this->get_id();
        }
        if (is_number($field)) { // Must be a leeloo course profile field.

            $handler = \core_course\customfield\course_handler::create();
            $data = $handler->get_instance_data($course->id, true);

            if (!empty($data[$field])) {
                $value = $data[$field]->export_value();
            }
        } else if (!empty($course->$field)) { // Field in the course table.
            $value = $course->$field;
        }

        $context = \mod_leeloolxpcert\element_helper::get_context($contextid);
        return format_string($value, true, ['context' => $context]);
    }
}
