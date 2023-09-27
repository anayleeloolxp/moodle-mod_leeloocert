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
 * Handles verifying the code for a certificate.
 *
 * @package   mod_leeloolxpcert
 * @copyright 2017 Mark Nelson <markn@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// This file does not need require_login because capability to verify can be granted to guests, skip codechecker here.
// @codingStandardsIgnoreLine
require_once('../../config.php');
$contextid = optional_param('contextid', context_system::instance()->id, PARAM_INT);
$code = optional_param('code', '', PARAM_ALPHANUM); // The code for the certificate we are verifying.
$qrcode = optional_param('qrcode', false, PARAM_BOOL);
$context = context::instance_by_id($contextid);
// Set up the page.
$pageurl = new moodle_url('/mod/leeloolxpcert/verify_certificate.php', array('contextid' => $contextid));
$verifyany = 0;
if ($code) {
    $pageurl->param('code', $code);
    $sql = "SELECT id FROM {modules} WHERE name = :namee";
    $module = $DB->get_record_sql($sql, array('namee' => 'leeloolxpcert'));

    $sql = "SELECT cm.id
                    FROM {leeloolxpcert_issues} li
                    JOIN {leeloolxpcert} lc
                    ON li.leeloolxpcertid = lc.id
                    JOIN {course_modules} cm
                    ON lc.id = cm.instance
                    AND lc.course = cm.course
                    WHERE li.code = :code
                    AND cm.module = :module";
    $activitydata = $DB->get_record_sql($sql, array('code' => $code, 'module' => $module->id));

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

            $result_temp = json_decode($outputresult);

            if (is_array($result_temp->data) || is_object($result_temp->data)) {

                $aroptions = $result_temp->data->aroptions;
                $aroptions = json_decode(base64_decode($aroptions));

                $verifyany = $leeloolxpcert->verifyany = $aroptions->verifyany;
            }
        }
    }
}

// Ok, a certificate was specified.
if ($context->contextlevel != CONTEXT_SYSTEM) {
    $cm = get_coursemodule_from_id('leeloolxpcert', $context->instanceid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $leeloolxpcert = $DB->get_record('leeloolxpcert', array('id' => $cm->instance), '*', MUST_EXIST);

    // Check if we are allowing anyone to verify, if so, no need to check login, or permissions.
    if (!$verifyany) {
        // Need to be logged in.
        require_login($course, false, $cm);
        // Ok, now check the user has the ability to verify certificates.
        require_capability('mod/leeloolxpcert:verifycertificate', $context);
    } else {
        $PAGE->set_cm($cm, $course);
    }
    $title = $leeloolxpcert->name;
    $heading = format_string($title);
    $checkallofsite = false;
} else {
    $title = $SITE->fullname;
    $heading = $title;
    $checkallofsite = true;
}
\mod_leeloolxpcert\page_helper::page_setup($pageurl, $context, $title);
// Additional page setup.
if ($context->contextlevel == CONTEXT_SYSTEM) {
    $PAGE->navbar->add(get_string('verifycertificate', 'leeloolxpcert'));
}


if ($checkallofsite) {
    // If the 'verifyallcertificates' is not set and the user does not have the capability 'mod/leeloolxpcert:verifyallcertificates'
    // then show them a message letting them know they can not proceed.
    $settingsjson = get_config('leeloolxpcert')->settingsjson;
    $resposedata = json_decode(base64_decode($settingsjson));
    $settingleeloolxp = $resposedata->data->certificate_settings;

    $verifyallcertificates = $settingleeloolxp->allow_verification_certificates;
    $canverifyallcertificates = has_capability('mod/leeloolxpcert:verifyallcertificates', $context);

    if (!$verifyallcertificates && !$canverifyallcertificates) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);
        echo $OUTPUT->notification(get_string('cannotverifyallcertificates', 'leeloolxpcert'));
        echo $OUTPUT->footer();
        exit();
    }
}
// The form we are using to verify these codes.
$form = new \mod_leeloolxpcert\verify_certificate_form($pageurl);
if ($code) {
    $result = new stdClass();
    $result->issues = array();
    // Ok, now check if the code is valid.
    $userfields = get_all_user_name_fields(true, 'u');
    $sql = "SELECT ci.id, u.id as userid, $userfields, co.id as courseid,
                   co.fullname as coursefullname, c.id as certificateid,
                   c.name as certificatename
              FROM {leeloolxpcert} c
              JOIN {leeloolxpcert_issues} ci
                ON c.id = ci.leeloolxpcertid
              JOIN {course} co
                ON c.course = co.id
              JOIN {user} u
                ON ci.userid = u.id
             WHERE ci.code = :code";
    if ($checkallofsite) {
        // Only people with the capability to verify all the certificates can verify any.
        if (!$canverifyallcertificates) {

            $sql .= " AND $verifyany = 1";
        }
        $params = ['code' => $code];
    } else {
        $sql .= " AND c.id = :leeloolxpcertid";
        $params = ['code' => $code, 'leeloolxpcertid' => $leeloolxpcert->id];
    }
    // It is possible (though unlikely) that there is the same code for issued certificates.
    if ($issues = $DB->get_records_sql($sql, $params)) {
        $result->success = true;
        $result->issues = $issues;
    } else {
        // Can't find it, let's say it's not verified.
        $result->success = false;
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
// Don't show the form if we are coming from a QR code.
if (!$qrcode) {
    echo $form->display();
}
if (isset($result)) {
    $renderer = $PAGE->get_renderer('mod_leeloolxpcert');
    $result = new \mod_leeloolxpcert\output\verify_certificate_results($result);
    echo $renderer->render($result);
}
echo $OUTPUT->footer();
