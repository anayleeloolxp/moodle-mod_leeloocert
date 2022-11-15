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
 * A scheduled task for emailing certificates.
 *
 * @package    mod_leeloolxpcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leeloolxpcert\task;

use curl;

defined('MOODLE_INTERNAL') || die();
require(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/../../lib.php');
/**
 * A scheduled task for emailing certificates.
 *
 * @package    mod_leeloolxpcert
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_certificate_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskemailcertificate', 'leeloolxpcert');
    }
    /**
     * Execute.
     */
    public function execute() {
        global $DB, $PAGE, $CFG;
        // Get all the certificates that have requested someone get emailed.
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
                $url = $leeloolxpurl . '/admin/theme_setup/get_template_data_for_email';
                $postdata = [
                    'license_key' => $leeloolxplicense,
                ];
                $curl = new curl;
                $options = array(
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_HEADER' => false,
                    'CURLOPT_POST' => count($postdata),
                );
                $outputresult = $curl->post($url, $postdata, $options);
                $result = json_decode($outputresult);
                if ((!empty($result->data)) && (is_array($result->data) || is_object($result->data))) {
                    $leeloolxpcerts = [];
                    foreach ($result->data as $key_template => $value_template) {
                        $sql = "SELECT lc.*
                    FROM {leeloolxpcert} lc
                        JOIN {course_modules} cm
                            ON lc.course = cm.course AND
                            lc.id = cm.instance
                        WHERE cm.id = :activityid";
                        $leeloolxpcert = $DB->get_record_sql($sql, array('activityid' => $value_template->activity_id));
                        $arr_arfulldata = json_decode(base64_decode($value_template->arfulldata));
                        $leeloolxpcert->templateid = $value_template->id;
                        $leeloolxpcert->requiredtime = $arr_arfulldata->requiredtime;
                        $leeloolxpcert->verifyany = $arr_arfulldata->verifyany;
                        $leeloolxpcert->deliveryoption = $arr_arfulldata->deliveryoption;
                        $leeloolxpcert->emailstudents = $arr_arfulldata->emailstudents;
                        $leeloolxpcert->emailteachers = $arr_arfulldata->emailteachers;
                        $leeloolxpcert->emailothers = $arr_arfulldata->emailothers;
                        $leeloolxpcert->protection = $arr_arfulldata->protection;
                        $leeloolxpcert->templatename = $value_template->name;
                        $leeloolxpcert->courseid = $value_template->courseid;
                        $leeloolxpcert->coursefullname = $value_template->coursefullname;
                        $leeloolxpcert->courseshortname = $value_template->courseshortname;
                        $leeloolxpcert->activityid = $value_template->activity_id;
                        $contextdata = $DB->get_record('context', array('instanceid' => $value_template->activity_id, 'contextlevel' => '70', 'depth' => '4'), '*', MUST_EXIST);
                        $leeloolxpcert->contextid = $contextdata->id;
                        $leeloolxpcerts[] = $leeloolxpcert;
                    }
                }
            }
        }
        if (!$leeloolxpcerts) {
            return;
        }
        // The renderers used for sending emails.
        $htmlrenderer = $PAGE->get_renderer('mod_leeloolxpcert', 'email', 'htmlemail');
        $textrenderer = $PAGE->get_renderer('mod_leeloolxpcert', 'email', 'textemail');
        foreach ($leeloolxpcerts as $leeloolxpcert) {
            // Get the context.
            $context = \context::instance_by_id($leeloolxpcert->contextid);
            // Set the $PAGE context - this ensure settings, such as language, are kept and don't default to the site settings.
            $PAGE->set_context($context);
            // Get the person we are going to send this email on behalf of.
            $userfrom = \core_user::get_noreply_user();
            // Store teachers for later.
            $teachers = get_enrolled_users($context, 'moodle/course:update');
            $courseshortname = format_string($leeloolxpcert->courseshortname, true, array('context' => $context));
            $coursefullname = format_string($leeloolxpcert->coursefullname, true, array('context' => $context));
            $certificatename = format_string($leeloolxpcert->name, true, array('context' => $context));
            // Used to create the email subject.
            $info = new \stdClass;
            $info->coursename = $courseshortname; // Added for BC, so users who have edited the string don't lose this value.
            $info->courseshortname = $courseshortname;
            $info->coursefullname = $coursefullname;
            $info->certificatename = $certificatename;
            // Get a list of all the issues.
            $userfields = get_all_user_name_fields(true, 'u');
            $sql = "SELECT u.id, u.username, $userfields, u.email, ci.id as issueid, ci.emailed
                  FROM {leeloolxpcert_issues} ci
                  JOIN {user} u
                    ON ci.userid = u.id
                 WHERE ci.leeloolxpcertid = :leeloolxpcertid";
            $issuedusers = $DB->get_records_sql($sql, array('leeloolxpcertid' => $leeloolxpcert->id));
            // Now, get a list of users who can access the certificate but have not yet.
            $enrolledusers = get_enrolled_users(\context_course::instance($leeloolxpcert->courseid), 'mod/leeloolxpcert:view');
            foreach ($enrolledusers as $enroluser) {
                // Check if the user has already been issued.
                if (in_array($enroluser->id, array_keys((array) $issuedusers))) {
                    continue;
                }
                // Now check if the certificate is not visible to the current user.
                $cm = get_fast_modinfo($leeloolxpcert->courseid, $enroluser->id)->instances['leeloolxpcert'][$leeloolxpcert->id];
                if (!$cm->uservisible) {
                    continue;
                }
                // Don't want to email those with the capability to manage the certificate.
                if (has_capability('mod/leeloolxpcert:manage', $context, $enroluser->id)) {
                    continue;
                }
                // Only email those with the capability to receive the certificate.
                if (!has_capability('mod/leeloolxpcert:receiveissue', $context, $enroluser->id)) {
                    continue;
                }
                // Check that they have passed the required time.
                if (!empty($leeloolxpcert->requiredtime)) {
                    if (\mod_leeloolxpcert\certificate::get_course_time(
                        $leeloolxpcert->courseid,
                        $enroluser->id
                    ) < ($leeloolxpcert->requiredtime * 60)) {
                        continue;
                    }
                }
                // Ensure the cert hasn't already been issued, e.g via the UI (view.php) - a race condition.
                $issueid = $DB->get_field(
                    'leeloolxpcert_issues',
                    'id',
                    array('userid' => $enroluser->id, 'leeloolxpcertid' => $leeloolxpcert->id),
                    IGNORE_MULTIPLE
                );
                if (empty($issueid)) {
                    // Ok, issue them the certificate.
                    $issueid = \mod_leeloolxpcert\certificate::issue_certificate($leeloolxpcert->id, $enroluser->id);
                }
                // Add them to the array so we email them.
                $enroluser->issueid = $issueid;
                $enroluser->emailed = 0;
                $issuedusers[] = $enroluser;
            }
            // Remove all the users who have already been emailed.
            foreach ($issuedusers as $key => $issueduser) {
                if ($issueduser->emailed) {
                    unset($issuedusers[$key]);
                }
            }
            // If there are no users to email we can return early.
            if (!$issuedusers) {
                continue;
            }
            // Create a directory to store the PDF we will be sending.
            $tempdir = make_temp_directory('certificate/attachment');
            if (!$tempdir) {
                return;
            }
            // Now, email the people we need to.
            foreach ($issuedusers as $user) {
                // Set up the user.
                cron_setup_user($user);
                $userfullname = fullname($user);
                $info->userfullname = $userfullname;
                // Now, get the PDF.
                /*$template = new \stdClass();
            $template->id = $leeloolxpcert->templateid;
            $template->name = $leeloolxpcert->templatename;
            $template->contextid = $leeloolxpcert->contextid;*/
                $template = new \stdClass();
                $pagesdata = [];
                $elementsdata = [];
                $aroptions = '';
                if (!empty($infoleeloolxp) && $infoleeloolxp->status != 'false') {
                    $leeloolxpurl = $infoleeloolxp->data->install_url;
                    $url = $leeloolxpurl . '/admin/theme_setup/get_template_data';
                    $postdata = [
                        'license_key' => $leeloolxplicense,
                        'activity_id' => $leeloolxpcert->activityid,
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
                        $aroptions->contextid = $leeloolxpcert->contextid;
                        $aroptions->activityid = $leeloolxpcert->activityid;

                        $issuedata = $DB->get_record('leeloolxpcert_issues', array('userid' => $user->id, 'leeloolxpcertid' => $leeloolxpcert->id), '*', MUST_EXIST);
                        $userdata = $DB->get_record('user', array('id' => $user->id));
                        $templatename = $template->name;
                        $certnamepdf = "$templatename-$userfullname.pdf";
                        $templategdrive = new \mod_leeloolxpcert\template($template);
                        $datatemp = $templategdrive->generate_pdf(false, $user->id, 1, $pagesdata, $elementsdata, $aroptions);

                        $leeloolxpurl = $infoleeloolxp->data->install_url;
                        $url = $leeloolxpurl . '/admin/theme_setup/save_delete_issues_certificate';
                        $postdata = [
                            'license_key' => $leeloolxplicense,
                            'activity_id' => $leeloolxpcert->activityid,
                            'issuedata' => json_encode($issuedata),
                            'email' => base64_encode($userdata->email),
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
                }

                $template = new \mod_leeloolxpcert\template($template);
                $filecontents = $template->generate_pdf(false, $user->id, true, $pagesdata, $elementsdata, $aroptions);
                // Set the name of the file we are going to send.
                $filename = $courseshortname . '_' . $certificatename;
                $filename = \core_text::entities_to_utf8($filename);
                $filename = strip_tags($filename);
                $filename = rtrim($filename, '.');
                $filename = str_replace('&', '_', $filename) . '.pdf';
                // Create the file we will be sending.
                $tempfile = $tempdir . '/' . md5(microtime() . $user->id) . '.pdf';
                file_put_contents($tempfile, $filecontents);
                if ($leeloolxpcert->emailstudents) {
                    $renderable = new \mod_leeloolxpcert\output\email_certificate(
                        true,
                        $userfullname,
                        $courseshortname,
                        $coursefullname,
                        $certificatename,
                        $leeloolxpcert->contextid
                    );
                    $subject = get_string('emailstudentsubject', 'leeloolxpcert', $info);
                    $message = $textrenderer->render($renderable);
                    $messagehtml = $htmlrenderer->render($renderable);
                    email_to_user($user, fullname($userfrom), $subject, $message, $messagehtml, $tempfile, $filename);
                }
                if ($leeloolxpcert->emailteachers) {
                    $renderable = new \mod_leeloolxpcert\output\email_certificate(
                        false,
                        $userfullname,
                        $courseshortname,
                        $coursefullname,
                        $certificatename,
                        $leeloolxpcert->contextid
                    );
                    $subject = get_string('emailnonstudentsubject', 'leeloolxpcert', $info);
                    $message = $textrenderer->render($renderable);
                    $messagehtml = $htmlrenderer->render($renderable);
                    foreach ($teachers as $teacher) {
                        email_to_user(
                            $teacher,
                            fullname($userfrom),
                            $subject,
                            $message,
                            $messagehtml,
                            $tempfile,
                            $filename
                        );
                    }
                }
                if (!empty($leeloolxpcert->emailothers)) {
                    $others = explode(',', $leeloolxpcert->emailothers);
                    foreach ($others as $email) {
                        $email = trim($email);
                        if (validate_email($email)) {
                            $renderable = new \mod_leeloolxpcert\output\email_certificate(
                                false,
                                $userfullname,
                                $courseshortname,
                                $coursefullname,
                                $certificatename,
                                $leeloolxpcert->contextid
                            );
                            $subject = get_string('emailnonstudentsubject', 'leeloolxpcert', $info);
                            $message = $textrenderer->render($renderable);
                            $messagehtml = $htmlrenderer->render($renderable);
                            $emailuser = new \stdClass();
                            $emailuser->id = -1;
                            $emailuser->email = $email;
                            email_to_user(
                                $emailuser,
                                fullname($userfrom),
                                $subject,
                                $message,
                                $messagehtml,
                                $tempfile,
                                $filename
                            );
                        }
                    }
                }
                // Set the field so that it is emailed.
                $DB->set_field('leeloolxpcert_issues', 'emailed', 1, array('id' => $user->issueid));
            }
        }
    }
}
