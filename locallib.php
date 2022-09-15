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
 * Private leeloocert module utility functions
 *
 * @package leeloocert
 * @copyright  2020 Leeloo LXP (https://leeloolxp.com)
 * @author     Leeloo LXP <info@leeloolxp.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resourcelib.php");
require_once("$CFG->dirroot/mod/leeloocert/lib.php");

/**
 * File browsing support class
 */
class leeloocert_content_file_info extends file_info_stored {
    /**
     * Get parent.
     * @return object true
     */
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }
    /**
     * Get name.
     * @return object true
     */
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

/**
 * Get options.
 * @param object $context
 * @return array true
 */
function leeloocert_get_editor_options($context) {
    global $CFG;
    return array(
        'subdirs' => 1,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => -1,
        'changeformat' => 1,
        'context' => $context,
        'noclean' => 1,
        'trusttext' => 0
    );
}

/**
 * Fetch and Update Configration From L
 */
function leeloocert_updateconf() {
    if (isset(get_config('leeloocert')->license)) {
        $leeloolxplicense = get_config('leeloocert')->license;
    } else {
        file_put_contents(dirname(__FILE__) . "/test_point.txt", print_r('reached else 1', true));
        return;
    }
    global $CFG;
    require_once($CFG->dirroot . '/lib/filelib.php');

    $url = 'https://leeloolxp.com/api_moodle.php/?action=page_info';
    $postdata = [
        'license_key' => $leeloolxplicense,
    ];
    $curl = new curl;
    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => count($postdata),
    );
    if (!$output = $curl->post($url, $postdata, $options)) {
        return;
    }
    $infoleeloolxp = json_decode($output);
    if ($infoleeloolxp->status != 'false') {
        $leeloolxpurl = $infoleeloolxp->data->install_url;
    } else {
        set_config('settingsjson', base64_encode($output), 'leeloocert');
        return;
    }
    $url = $leeloolxpurl . '/admin/Theme_setup/get_certificate_settings';
    $postdata = [
        'license_key' => $leeloolxplicense,
    ];
    $curl = new curl;
    $options = array(
        'CURLOPT_RETURNTRANSFER' => true,
        'CURLOPT_HEADER' => false,
        'CURLOPT_POST' => count($postdata),
    );
    if (!$output = $curl->post($url, $postdata, $options)) {
        return;
    }
    set_config('settingsjson', base64_encode($output), 'leeloocert');
}
