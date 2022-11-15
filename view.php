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
 * Prints an instance of mod_leeloolxpcert.
 *
 * @package     mod_leeloolxpcert
 * @copyright   2022 Leeloo LXP <info@leeloolxp.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
global $CFG;
// Course module id.
$id = optional_param('id', 0, PARAM_INT);
$downloadown = optional_param('downloadown', false, PARAM_BOOL);
$downloadtable = optional_param('download', null, PARAM_ALPHA);
$downloadissue = optional_param('downloadissue', 0, PARAM_INT);
$deleteissue = optional_param('deleteissue', 0, PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', \mod_leeloolxpcert\certificate::LEELOOLXPCERT_PER_PAGE, PARAM_INT);
$cm = get_coursemodule_from_id('leeloolxpcert', $id, 0, false, MUST_EXIST);
$leeloolxpcert = $DB->get_record('leeloolxpcert', array('id' => $cm->instance), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$template = new stdClass();
// Ensure the user is allowed to view this page.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/leeloolxpcert:view', $context);
$canreceive = has_capability('mod/leeloolxpcert:receiveissue', $context);
$canmanage = has_capability('mod/leeloolxpcert:manage', $context);
$canviewreport = has_capability('mod/leeloolxpcert:viewreport', $context);
// Initialise $PAGE.
$pageurl = new moodle_url('/mod/leeloolxpcert/view.php', array('id' => $cm->id));
// Check if the user can view the certificate based on time spent in course.
$pagesdata = [];
$elementsdata = [];
$aroptions = '';
if (isset(get_config('leeloolxpcert')->license)) {
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
            'activity_id' => $cm->id,
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
            $contextdata = $DB->get_record('context', array('instanceid' => $id, 'contextlevel' => '70', 'depth' => '4'), '*', MUST_EXIST);
            $aroptions->contextid = $contextdata->id;
            $aroptions->activityid = $id;
            $leeloolxpcert->deliveryoption = $aroptions->deliveryoption;
            $leeloolxpcert->emailstudents = $aroptions->emailstudents;
            $leeloolxpcert->emailteachers = $aroptions->emailteachers;
            $leeloolxpcert->emailothers = $aroptions->emailothers;
            $leeloolxpcert->verifyany = $aroptions->verifyany;
            $leeloolxpcert->requiredtime = $aroptions->requiredtime;
            $leeloolxpcert->valid_thru = $aroptions->valid_thru;
            $leeloolxpcert->protection = $aroptions->protection;
        }
    }
}
/*echo '<pre>';
print_r($leeloolxpcert);
die;*/
if ($leeloolxpcert->requiredtime && !$canmanage) {
    if (\mod_leeloolxpcert\certificate::get_course_time($course->id) < ($leeloolxpcert->requiredtime * 60)) {
        $a = new stdClass;
        $a->requiredtime = $leeloolxpcert->requiredtime;
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        notice(get_string('requiredtimenotmet', 'leeloolxpcert', $a), $url);
        die;
    }
}
// Check if we are deleting an issue.
if ($deleteissue && $canmanage && confirm_sesskey()) {
    if (!$confirm) {
        $nourl = new moodle_url('/mod/leeloolxpcert/view.php', ['id' => $id]);
        $yesurl = new moodle_url(
            '/mod/leeloolxpcert/view.php',
            [
                'id' => $id,
                'deleteissue' => $deleteissue,
                'confirm' => 1,
                'sesskey' => sesskey()
            ]
        );
        // Show a confirmation page.
        $PAGE->navbar->add(get_string('deleteconfirm', 'leeloolxpcert'));
        $message = get_string('deleteissueconfirm', 'leeloolxpcert');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($leeloolxpcert->name));
        echo $OUTPUT->confirm($message, $yesurl, $nourl);
        echo $OUTPUT->footer();
        exit();
    }

    $issuestemp = $DB->get_record('leeloolxpcert_issues', array('id' => $deleteissue, 'leeloolxpcertid' => $leeloolxpcert->id));
    $useriddeleted = $issuestemp->userid;
    // Delete the issue.
    $DB->delete_records('leeloolxpcert_issues', array('id' => $deleteissue, 'leeloolxpcertid' => $leeloolxpcert->id));
    if (!empty($infoleeloolxp) && $infoleeloolxp->status != 'false') {
        $userdata = $DB->get_record('user', array('id' => $useriddeleted));
        $leeloolxpurl = $infoleeloolxp->data->install_url;
        $url = $leeloolxpurl . '/admin/theme_setup/save_delete_issues_certificate/1';
        $postdata = [
            'license_key' => $leeloolxplicense,
            'activity_id' => $cm->id,
            'email' => base64_encode($userdata->email),
        ];
        $curl = new curl;
        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HEADER' => false,
            'CURLOPT_POST' => count($postdata),
        );
        $outtemp = $curl->post($url, $postdata, $options);
    }
    // Redirect back to the manage templates page.
    redirect(new moodle_url('/mod/leeloolxpcert/view.php', array('id' => $id)));
}
// Activity instance id.
$l = optional_param('l', 0, PARAM_INT);
if ($id) {
    $cm = get_coursemodule_from_id('leeloolxpcert', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('leeloolxpcert', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $moduleinstance = $DB->get_record('leeloolxpcert', array('id' => $l), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('leeloolxpcert', $moduleinstance->id, $course->id, false, MUST_EXIST);
}
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);
$event = \mod_leeloolxpcert\event\course_module_viewed::create(array(
    'objectid' => $moduleinstance->id,
    'context' => $modulecontext
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('leeloolxpcert', $moduleinstance);
$event->trigger();
if (!$downloadown && !$downloadissue) {
    // Get the current groups mode.
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        groups_get_activity_group($cm, true);
    }
    // Generate the table to the report if there are issues to display.
    if ($canviewreport) {
        // Get the total number of issues.
        $reporttable = new \mod_leeloolxpcert\report_table($leeloolxpcert->id, $cm, $groupmode, $downloadtable);
        $reporttable->define_baseurl($pageurl);
        if ($reporttable->is_downloading()) {
            $reporttable->download();
            exit();
        }
    }
    $PAGE->set_url('/mod/leeloolxpcert/view.php', array('id' => $cm->id));
    $PAGE->set_title(format_string($moduleinstance->name));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($modulecontext);
    // Generate the intro content if it exists.
    $intro = '';
    if (!empty($leeloolxpcert->intro)) {
        $intro = $OUTPUT->box(format_module_intro('leeloolxpcert', $leeloolxpcert, $cm->id), 'generalbox', 'intro');
    }
    // If the current user has been issued a leeloolxpcert generate HTML to display the details.
    $issuehtml = '';
    $issues = $DB->get_records('leeloolxpcert_issues', array('userid' => $USER->id, 'leeloolxpcertid' => $leeloolxpcert->id));
    if ($issues && !$canmanage) {
        // Get the most recent issue (there should only be one).
        $issue = reset($issues);
        $issuestring = get_string('receiveddate', 'leeloolxpcert') . ': ' . userdate($issue->timecreated);
        $issuehtml = $OUTPUT->box($issuestring);
    }
    $downloadbutton = '';
    if ($canreceive) {
        $linkname = get_string('getleeloolxpcert', 'leeloolxpcert');
        $link = new moodle_url('/mod/leeloolxpcert/view.php', array('id' => $cm->id, 'downloadown' => true));
        $downloadbutton = new single_button($link, $linkname, 'get', true);
        $downloadbutton->class .= ' m-b-1';  // Seems a bit hackish, ahem.
        $downloadbutton = $OUTPUT->render($downloadbutton);
    }
    echo $OUTPUT->header();
    echo $intro;
    echo $downloadbutton;
    if (isset($reporttable)) {
        $numissues = \mod_leeloolxpcert\certificate::get_number_of_issues($leeloolxpcert->id, $cm, $groupmode);
        echo $OUTPUT->heading(get_string('listofissues', 'leeloolxpcert', $numissues), 3);
        groups_print_activity_menu($cm, $pageurl);
        echo $reporttable->out($perpage, false);
    }
    echo $OUTPUT->footer();
} else if ($canreceive || $canmanage) {
    $userid = $USER->id;
    \core\session\manager::write_close();
    if (isset(get_config('leeloolxpcert')->license)) {
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
    }
    // Create new leeloolxpcert issue record if one does not already exist.
    if (!$DB->record_exists('leeloolxpcert_issues', array('userid' => $USER->id, 'leeloolxpcertid' => $leeloolxpcert->id))) {
        \mod_leeloolxpcert\certificate::issue_certificate($leeloolxpcert->id, $USER->id);
        $issuedata = $DB->get_record('leeloolxpcert_issues', array('userid' => $USER->id, 'leeloolxpcertid' => $leeloolxpcert->id), '*', MUST_EXIST);
        if (!empty($infoleeloolxp) && $infoleeloolxp->status != 'false') {

            $userdata = $DB->get_record('user', array('id' => $userid));
            $userfullname = $userdata->firstname . ' ' . $userdata->lastname;
            $templatename = $template->name;
            $certnamepdf = "$templatename-$userfullname.pdf";
            $templategdrive = new \mod_leeloolxpcert\template($template);
            $datatemp = $templategdrive->generate_pdf(false, $userid, 1, $pagesdata, $elementsdata, $aroptions);

            $leeloolxpurl = $infoleeloolxp->data->install_url;
            $url = $leeloolxpurl . '/admin/theme_setup/save_delete_issues_certificate';
            $postdata = [
                'license_key' => $leeloolxplicense,
                'activity_id' => $cm->id,
                'issuedata' => json_encode($issuedata),
                'email' => base64_encode($USER->email),
                'certnamepdf' => json_encode($certnamepdf),
                'content' => $datatemp,
            ];
            $curl = new curl;
            $options = array(
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_HEADER' => false,
                'CURLOPT_POST' => count($postdata),
            );
            $tempoutput = $curl->post($url, $postdata, $options);
        }
    } else if ($downloadissue && $canviewreport) {
        $userid = $downloadissue;
    }
    // Now we want to generate the PDF.
    $template = new \mod_leeloolxpcert\template($template);
    $template->generate_pdf(false, $userid, false, $pagesdata, $elementsdata, $aroptions);
    exit();
}
