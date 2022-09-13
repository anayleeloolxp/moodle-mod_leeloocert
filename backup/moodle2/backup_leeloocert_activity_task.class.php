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
 * This file contains the backup tasks that provides all the settings and steps to perform a backup of the activity.
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/mod/leeloocert/backup/moodle2/backup_leeloocert_stepslib.php');

/**
 * Handles creating tasks to peform in order to create the backup.
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_leeloocert_activity_task extends backup_activity_task {

    /**
     * Define particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define particular steps this activity can have.
     */
    protected function define_my_steps() {
        // The leeloocert only has one structure step.
        $this->add_step(new backup_leeloocert_activity_structure_step('leeloocert_structure', 'leeloocert.xml'));
    }

    /**
     * Code the transformations to perform in the activity in order to get transportable (encoded) links.
     *
     * @param string $content
     * @return mixed|string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of leeloocerts.
        $search = "/(".$base."\/mod\/leeloocert\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CUSTOMCERTINDEX*$2@$', $content);

        // Link to leeloocert view by moduleid.
        $search = "/(".$base."\/mod\/leeloocert\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CUSTOMCERTVIEWBYID*$2@$', $content);

        return $content;
    }
}
