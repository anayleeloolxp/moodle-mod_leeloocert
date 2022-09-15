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
 * Handles viewing a leeloocert.
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$downloadown = optional_param('downloadown', false, PARAM_BOOL);
$downloadtable = optional_param('download', null, PARAM_ALPHA);
$downloadissue = optional_param('downloadissue', 0, PARAM_INT);
$deleteissue = optional_param('deleteissue', 0, PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', \mod_leeloocert\certificate::CUSTOMCERT_PER_PAGE, PARAM_INT);

$cm = get_coursemodule_from_id('leeloocert', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$leeloocert = $DB->get_record('leeloocert', array('id' => $cm->instance), '*', MUST_EXIST);
$template = $DB->get_record('leeloocert_templates', array('id' => $leeloocert->templateid), '*', MUST_EXIST);

// Ensure the user is allowed to view this page.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/leeloocert:view', $context);

$canreceive = has_capability('mod/leeloocert:receiveissue', $context);
$canmanage = has_capability('mod/leeloocert:manage', $context);
$canviewreport = has_capability('mod/leeloocert:viewreport', $context);

// Initialise $PAGE.
$pageurl = new moodle_url('/mod/leeloocert/view.php', array('id' => $cm->id));
\mod_leeloocert\page_helper::page_setup($pageurl, $context, format_string($leeloocert->name));

// Check if the user can view the certificate based on time spent in course.
if ($leeloocert->requiredtime && !$canmanage) {
    if (\mod_leeloocert\certificate::get_course_time($course->id) < ($leeloocert->requiredtime * 60)) {
        $a = new stdClass;
        $a->requiredtime = $leeloocert->requiredtime;
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        notice(get_string('requiredtimenotmet', 'leeloocert', $a), $url);
        die;
    }
}

// Check if we are deleting an issue.
if ($deleteissue && $canmanage && confirm_sesskey()) {
    if (!$confirm) {
        $nourl = new moodle_url('/mod/leeloocert/view.php', ['id' => $id]);
        $yesurl = new moodle_url(
            '/mod/leeloocert/view.php',
            [
                'id' => $id,
                'deleteissue' => $deleteissue,
                'confirm' => 1,
                'sesskey' => sesskey()
            ]
        );

        // Show a confirmation page.
        $PAGE->navbar->add(get_string('deleteconfirm', 'leeloocert'));
        $message = get_string('deleteissueconfirm', 'leeloocert');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($leeloocert->name));
        echo $OUTPUT->confirm($message, $yesurl, $nourl);
        echo $OUTPUT->footer();
        exit();
    }

    // Delete the issue.
    $DB->delete_records('leeloocert_issues', array('id' => $deleteissue, 'leeloocertid' => $leeloocert->id));

    // Redirect back to the manage templates page.
    redirect(new moodle_url('/mod/leeloocert/view.php', array('id' => $id)));
}

$event = \mod_leeloocert\event\course_module_viewed::create(array(
    'objectid' => $leeloocert->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('leeloocert', $leeloocert);
$event->trigger();

// Check that we are not downloading a certificate PDF.
if (!$downloadown && !$downloadissue) {
    // Get the current groups mode.
    if ($groupmode = groups_get_activity_groupmode($cm)) {
        groups_get_activity_group($cm, true);
    }

    // Generate the table to the report if there are issues to display.
    if ($canviewreport) {
        // Get the total number of issues.
        $reporttable = new \mod_leeloocert\report_table($leeloocert->id, $cm, $groupmode, $downloadtable);
        $reporttable->define_baseurl($pageurl);

        if ($reporttable->is_downloading()) {
            $reporttable->download();
            exit();
        }
    }

    // Generate the intro content if it exists.
    $intro = '';
    if (!empty($leeloocert->intro)) {
        $intro = $OUTPUT->box(format_module_intro('leeloocert', $leeloocert, $cm->id), 'generalbox', 'intro');
    }

    // If the current user has been issued a leeloocert generate HTML to display the details.
    $issuehtml = '';
    $issues = $DB->get_records('leeloocert_issues', array('userid' => $USER->id, 'leeloocertid' => $leeloocert->id));
    if ($issues && !$canmanage) {
        // Get the most recent issue (there should only be one).
        $issue = reset($issues);
        $issuestring = get_string('receiveddate', 'leeloocert') . ': ' . userdate($issue->timecreated);
        $issuehtml = $OUTPUT->box($issuestring);
    }

    // Create the button to download the leeloocert.
    $downloadbutton = '';
    if ($canreceive) {
        $linkname = get_string('getleeloocert', 'leeloocert');
        $link = new moodle_url('/mod/leeloocert/view.php', array('id' => $cm->id, 'downloadown' => true));
        $downloadbutton = new single_button($link, $linkname, 'get', true);
        $downloadbutton->class .= ' m-b-1';  // Seems a bit hackish, ahem.
        $downloadbutton = $OUTPUT->render($downloadbutton);
    }

    // Output all the page data.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($leeloocert->name));
    echo $intro;
    echo $issuehtml;
    echo $downloadbutton;
    if (isset($reporttable)) {
        $numissues = \mod_leeloocert\certificate::get_number_of_issues($leeloocert->id, $cm, $groupmode);
        echo $OUTPUT->heading(get_string('listofissues', 'leeloocert', $numissues), 3);
        groups_print_activity_menu($cm, $pageurl);
        echo $reporttable->out($perpage, false);
    }
    echo $OUTPUT->footer($course);
    exit();
} else if ($canreceive || $canmanage) { // Output to pdf.
    // Set the userid value of who we are downloading the certificate for.
    $userid = $USER->id;
    if ($downloadown) {
        // Create new leeloocert issue record if one does not already exist.
        if (!$DB->record_exists('leeloocert_issues', array('userid' => $USER->id, 'leeloocertid' => $leeloocert->id))) {
            \mod_leeloocert\certificate::issue_certificate($leeloocert->id, $USER->id);
            $userdata = $DB->get_record('user', array('id' => $userid));
            $userfullname = $userdata->firstname . ' ' . $userdata->lastname;
            $templatename = $template->name;
            $settingsjson = get_config('leeloocert')->settingsjson;
            $resposedata = json_decode(base64_decode($settingsjson));
            $settingleeloolxp = $resposedata->data->certificate_settings;

            if (!empty($settingleeloolxp->google_drive_json_file) && !empty($settingleeloolxp->folder_id) && !empty($settingleeloolxp->upload_on_google_drive)) {
                $certnamepdf = "$templatename-$userfullname.pdf";
                if (empty(file_exists("path.json"))) {
                    file_put_contents("path.json", $settingleeloolxp->google_drive_json_file);
                }
            }
        }

        // Set the leeloo certificate as viewed.
        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);
    } else if ($downloadissue && $canviewreport) {
        $userid = $downloadissue;
    }

    // Hack alert - don't initiate the download when running Behat.
    if (defined('BEHAT_SITE_RUNNING')) {
        redirect(new moodle_url('/mod/leeloocert/view.php', array('id' => $cm->id)));
    }

    \core\session\manager::write_close();
    // Now we want to generate the PDF.
    $template = new \mod_leeloocert\template($template);
    $template->generate_pdf(false, $userid);
    if (!empty($certnamepdf)) {

        $datatemp = $template->generate_pdf(false, $userid, 1);

        require __DIR__ . '/vendor/autoload.php';

        $client = new Google_Client();
        $client->setAuthConfig('path.json');
        $client->addScope('https://www.googleapis.com/auth/drive');

        $service = new Google_Service_Drive($client);

        $fileMetadata = new Google_Service_Drive_DriveFile(array('name' => $certnamepdf, 'parents' => ['1amWEOHX_ZoVtNSxO4aFR3YCkSEkr17z_']));
        $content = $datatemp;
        $file = $service->files->create($fileMetadata, array('data' => $content, 'mimeType' => 'application/pdf', 'uploadType' => 'multipart', 'fields' => 'id'));
    }
    exit();
}
