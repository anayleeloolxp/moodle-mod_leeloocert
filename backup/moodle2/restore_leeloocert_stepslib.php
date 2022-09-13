<?php
// This file is part of the leeloocert module for Moodle - http://moodle.org/
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
 * Define all the restore steps that will be used by the restore_leeloocert_activity_task.
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Define the complete leeloocert structure for restore, with file and id annotations.
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_leeloocert_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the different items to restore.
     *
     * @return array the restore paths
     */
    protected function define_structure() {
        // The array used to store the path to the items we want to restore.
        $paths = array();

        // The leeloocert instance.
        $paths[] = new restore_path_element('leeloocert', '/activity/leeloocert');

        // The templates.
        $paths[] = new restore_path_element('leeloocert_template', '/activity/leeloocert/template');

        // The pages.
        $paths[] = new restore_path_element('leeloocert_page', '/activity/leeloocert/template/pages/page');

        // The elements.
        $paths[] = new restore_path_element('leeloocert_element', '/activity/leeloocert/template/pages/page/element');

        // Check if we want the issues as well.
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('leeloocert_issue', '/activity/leeloocert/issues/issue');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Handles restoring the leeloocert activity.
     *
     * @param stdClass $data the leeloocert data
     */
    protected function process_leeloocert($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the leeloocert record.
        $newitemid = $DB->insert_record('leeloocert', $data);

        // Immediately after inserting record call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Handles restoring a leeloocert page.
     *
     * @param stdClass $data the leeloocert data
     */
    protected function process_leeloocert_template($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->contextid = $this->task->get_contextid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('leeloocert_templates', $data);
        $this->set_mapping('leeloocert_template', $oldid, $newitemid);

        // Update the template id for the leeloocert.
        $leeloocert = new stdClass();
        $leeloocert->id = $this->get_new_parentid('leeloocert');
        $leeloocert->templateid = $newitemid;
        $DB->update_record('leeloocert', $leeloocert);
    }

    /**
     * Handles restoring a leeloocert template.
     *
     * @param stdClass $data the leeloocert data
     */
    protected function process_leeloocert_page($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->templateid = $this->get_new_parentid('leeloocert_template');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('leeloocert_pages', $data);
        $this->set_mapping('leeloocert_page', $oldid, $newitemid);
    }

    /**
     * Handles restoring a leeloocert element.
     *
     * @param stdclass $data the leeloocert data
     */
    protected function process_leeloocert_element($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->pageid = $this->get_new_parentid('leeloocert_page');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('leeloocert_elements', $data);
        $this->set_mapping('leeloocert_element', $oldid, $newitemid);
    }

    /**
     * Handles restoring a leeloocert issue.
     *
     * @param stdClass $data the leeloocert data
     */
    protected function process_leeloocert_issue($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->leeloocertid = $this->get_new_parentid('leeloocert');
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record('leeloocert_issues', $data);
        $this->set_mapping('leeloocert_issue', $oldid, $newitemid);
    }

    /**
     * Called immediately after all the other restore functions.
     */
    protected function after_execute() {
        parent::after_execute();

        // Add the files.
        $this->add_related_files('mod_leeloocert', 'intro', null);

        // Note - we can't use get_old_contextid() as it refers to the module context.
        $this->add_related_files('mod_leeloocert', 'image', null, $this->get_task()->get_info()->original_course_contextid);
    }
}
