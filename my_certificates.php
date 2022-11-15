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
 * Handles viewing the certificates for a certain user.
 *
 * @package    mod_leeloolxpcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
$userid = optional_param('userid', $USER->id, PARAM_INT);
$download = optional_param('download', null, PARAM_ALPHA);
$downloadcert = optional_param('downloadcert', '', PARAM_BOOL);
if ($downloadcert) {
    $certificateid = required_param('certificateid', PARAM_INT);
    $leeloolxpcert = $DB->get_record('leeloolxpcert', array('id' => $certificateid), '*', MUST_EXIST);
    // Check there exists an issued certificate for this user.
    if (!$issue = $DB->get_record('leeloolxpcert_issues', ['userid' => $userid, 'leeloolxpcertid' => $leeloolxpcert->id])) {
        throw new moodle_exception('You have not been issued a certificate');
    }
}
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', \mod_leeloolxpcert\certificate::LEELOOLXPCERT_PER_PAGE, PARAM_INT);
$pageurl = $url = new moodle_url('/mod/leeloolxpcert/my_certificates.php', array(
    'userid' => $userid,
    'page' => $page, 'perpage' => $perpage
));
// Requires a login.
require_login();
// Check that we have a valid user.
$user = \core_user::get_user($userid, '*', MUST_EXIST);
// If we are viewing certificates that are not for the currently logged in user then do a capability check.
if (($userid != $USER->id) && !has_capability('mod/leeloolxpcert:viewallcertificates', context_system::instance())) {
    throw new moodle_exception('You are not allowed to view these certificates');
}
$PAGE->set_url($pageurl);
$PAGE->set_context(context_user::instance($userid));
$PAGE->set_title(get_string('mycertificates', 'leeloolxpcert'));
$PAGE->set_pagelayout('standard');
$PAGE->navigation->extend_for_user($user);
// Check if we requested to download a certificate.
if ($downloadcert) {
    $template = new stdClass();
    $pagesdata = [];
    $elementsdata = [];
    $aroptions = '';
    $sql = "SELECT id FROM {modules} WHERE name = :namee";
    $module = $DB->get_record_sql($sql, array('namee' => 'leeloolxpcert'));
    $sql = "SELECT cm.id
                    FROM {leeloolxpcert} lc
                    JOIN {course_modules} cm
                    ON lc.id = cm.instance
                    AND lc.course = cm.course
                    WHERE lc.id = :leeloolxpcertid
                    AND cm.module = :module";
    $activitydata = $DB->get_record_sql($sql, array('leeloolxpcertid' => $leeloolxpcert->id, 'module' => $module->id));

    $activityid = $activitydata->id;
    if (isset(get_config('leeloolxpcert')->license) && !empty($activityid)) {
        $leeloolxplicense = get_config('leeloolxpcert')->license;
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
        if (!empty($infoleeloolxp) && $infoleeloolxp->status != 'false') {
            $leeloolxpurl = $infoleeloolxp->data->install_url;
            $url = $leeloolxpurl . '/admin/theme_setup/get_template_data';
            $postdata = [
                'license_key' => $leeloolxplicense,
                'activity_id' => $activityid,
            ];
            $curl = new curl;
            $options = array(
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_HEADER' => false,
                'CURLOPT_POST' => count($postdata),
            );
            $outputresult = $curl->post($url, $postdata, $options);
            $result = json_decode($outputresult);
            if (is_array($result->data) || is_object($result->data)) {
                $template = $result->data->template_data;
                $pagesdata = $result->data->pages_data;
                $elementsdata = $result->data->elements_data;
                $aroptions = $result->data->aroptions;
                $aroptions = json_decode(base64_decode($aroptions));
                $aroptions->leeloolxpcertid = $leeloolxpcert->id;
                $contextdata = $DB->get_record('context', array('instanceid' => $activityid, 'contextlevel' => '70', 'depth' => '4'), '*', MUST_EXIST);
                $aroptions->contextid = $contextdata->id;
                $aroptions->activityid = $activityid;
            }
        }
    }
    $template = new \mod_leeloolxpcert\template($template);
    $template->generate_pdf(false, $userid, false, $pagesdata, $elementsdata, $aroptions);
    exit();
}
$table = new \mod_leeloolxpcert\my_certificates_table($userid, $download);
$table->define_baseurl($pageurl);
if ($table->is_downloading()) {
    $table->download();
    exit();
}
// Additional page setup.
$PAGE->navbar->add(get_string('profile'), new moodle_url('/user/profile.php', array('id' => $userid)));
$PAGE->navbar->add(get_string('mycertificates', 'leeloolxpcert'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('mycertificates', 'leeloolxpcert'));
echo html_writer::div(get_string('mycertificatesdescription', 'leeloolxpcert'));
$table->out($perpage, false);
echo $OUTPUT->footer();
