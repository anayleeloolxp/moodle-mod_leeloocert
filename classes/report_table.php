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
 * The report that displays issued certificates.
 *
 * @package    mod_leeloolxpcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_leeloolxpcert;

use curl;

defined('MOODLE_INTERNAL') || die;
global $CFG;

require_once($CFG->libdir . '/tablelib.php');
/**
 * Class for the report that displays issued certificates.
 *
 * @package    mod_leeloolxpcert
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends \table_sql {
    /**
     * @var int $leeloolxpcertid The custom certificate id
     */
    protected $leeloolxpcertid;
    /**
     * @var \stdClass $cm The course module.
     */
    protected $cm;
    /**
     * @var bool $groupmode are we in group mode?
     */
    protected $groupmode;

    private $text;
    private $categoryname;
    private $coursefield;
    private $coursename;
    private $dateinfo;
    private $valid_thru;
    private $courseid;
    private $studentname;
    private $teachername;
    private $userfield;


    /**
     * Sets up the table.
     *
     * @param int $leeloolxpcertid
     * @param \stdClass $cm the course module
     * @param bool $groupmode are we in group mode?
     * @param string|null $download The file type, null if we are not downloading
     */
    public function __construct($leeloolxpcertid, $cm, $groupmode, $download = null) {
        parent::__construct('mod_leeloolxpcert_report_table');
        $context = \context_module::instance($cm->id);
        $extrafields = get_extra_user_fields($context);
        $columns = [];
        $columns[] = 'fullname';
        $columns[] = 'text';
        $columns[] = 'categoryname';
        $columns[] = 'coursefield';
        $columns[] = 'coursename';
        $columns[] = 'date';
        $columns[] = 'studentname';
        $columns[] = 'teachername';
        $columns[] = 'userfield';



        foreach ($extrafields as $extrafield) {
            $columns[] = $extrafield;
        }
        $columns[] = 'timecreated';
        $columns[] = 'code';
        $headers = [];
        $headers[] = get_string('fullname');
        $headers[] = 'Text';
        $headers[] = 'Category';
        $headers[] = 'Course field';
        $headers[] = 'Course name';
        $headers[] = 'Date';
        $headers[] = 'Student name';
        $headers[] = 'Teacher name';
        $headers[] = 'User field';

        foreach ($extrafields as $extrafield) {
            $headers[] = get_user_field_name($extrafield);
        }
        $headers[] = get_string('receiveddate', 'leeloolxpcert');
        $headers[] = get_string('code', 'leeloolxpcert');
        // Check if we were passed a filename, which means we want to download it.
        if ($download) {
            $this->is_downloading($download, 'leeloolxpcert-report');
        }
        if (!$this->is_downloading()) {
            $columns[] = 'download';
            $headers[] = get_string('file');
        }
        if (!$this->is_downloading() && has_capability('mod/leeloolxpcert:manage', $context)) {
            $columns[] = 'actions';
            $headers[] = '';
        }


        global $CFG;
        global $DB;
        global $USER;

        //get data from Leeloo
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
                    $this->valid_thru = $aroptions->valid_thru;
                    $contextdata = $DB->get_record('context', array('instanceid' => $leeloolxpcertid, 'contextlevel' => '70', 'depth' => '4'), '*', MUST_EXIST);

                    foreach ($elementsdata as $element) {
                        if ($element->element == 'text') {
                            $this->text = $element->data;
                        }
                        if ($element->element == 'categoryname') {
                            $leeloolxpcertdata = $DB->get_record('leeloolxpcert', array('id' => $leeloolxpcertid), '*', MUST_EXIST);
                            $course = get_course($leeloolxpcertdata->course);
                            $categoryname = $DB->get_field('course_categories', 'name', array('id' => $course->category), MUST_EXIST);
                            $this->categoryname = $categoryname;
                        }

                        if ($element->element == 'coursefield') {
                            $field = $element->data;
                            $leeloolxpcertdata = $DB->get_record('leeloolxpcert', array('id' => $leeloolxpcertid), '*', MUST_EXIST);
                            $course = get_course($leeloolxpcertdata->course);
                            if (is_number($field)) { // Must be a leeloo course profile field.
                                $handler = \core_course\customfield\course_handler::create();
                                $data = $handler->get_instance_data($course->id, true);
                                if (!empty($data[$field])) {
                                    $valuetemp = $data[$field]->export_value();
                                }
                            } else if (!empty($course->$field)) { // Field in the course table.
                                $valuetemp = $course->$field;
                            } else {
                                $valuetemp = '';
                            }
                            $this->coursefield = $valuetemp;
                        }
                        if ($element->element == 'coursename') {
                            $leeloolxpcertdata = $DB->get_record('leeloolxpcert', array('id' => $leeloolxpcertid), '*', MUST_EXIST);
                            $course = get_course($leeloolxpcertdata->course);
                            $this->coursename = $course->fullname;
                        }
                        if ($element->element == 'date') {
                            $this->dateinfo = $element->data;
                            $leeloolxpcertdata = $DB->get_record('leeloolxpcert', array('id' => $leeloolxpcertid), '*', MUST_EXIST);
                            $this->courseid = $leeloolxpcertdata->course;
                        }
                        if ($element->element == 'studentname') {
                            $this->studentname = fullname($USER);
                        }
                        if ($element->element == 'teachername') {
                            $userdatatemp = explode(',', $element->data);
                            $useremailtemp = base64_decode($userdatatemp[1]);
                            $teacher = $DB->get_record('user', array('email' => $useremailtemp));
                            $this->teachername = fullname($teacher);
                        }
                        if ($element->element == 'userfield') {
                            $this->userfield = $element->data;
                        }
                    }
                }
            }
        }
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->collapsible(false);
        $this->sortable(true);
        $this->no_sorting('code');
        $this->no_sorting('download');
        $this->no_sorting('text');
        $this->no_sorting('categoryname');
        $this->no_sorting('coursefield');
        $this->no_sorting('coursename');
        $this->no_sorting('date');
        $this->no_sorting('studentname');
        $this->no_sorting('teachername');
        $this->no_sorting('userfield');
        $this->is_downloadable(true);
        $this->leeloolxpcertid = $leeloolxpcertid;
        $this->cm = $cm;
        $this->groupmode = $groupmode;
    }
    /**
     * Generate the fullname column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_fullname($user) {
        global $OUTPUT;
        if (!$this->is_downloading()) {
            return $OUTPUT->user_picture($user) . ' ' . fullname($user);
        } else {
            return fullname($user);
        }
    }

    /**
     * Generate the text column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_text($user) {
        return $this->text;
    }

    /**
     * Generate the category column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_categoryname($user) {
        return $this->categoryname;
    }

    /**
     * Generate the coursefield column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_coursefield($user) {
        return $this->coursefield;
    }

    /**
     * Generate the coursename column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_coursename($user) {
        return $this->coursename;
    }

    /**
     * Generate the date column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_date($user) {

        global $DB;
        $activityid = $this->cm->id;
        $dateinfo = json_decode($this->dateinfo);
        $dateitem = $dateinfo->dateitem;

        $dateformat = $dateinfo->dateformat;

        $issue = $DB->get_record(

            'leeloolxpcert_issues',

            array('userid' => $user->id, 'leeloolxpcertid' => $this->leeloolxpcertid),

            '*',

            IGNORE_MULTIPLE

        );
        if ($dateitem == '-1') {

            $date = $issue->timecreated;
        } else if ($dateitem == '-8') {
            $date = strtotime(" +  $this->valid_thru month", $issue->timecreated);
        } else if ($dateitem == '-5') {

            $date = time();
        } else if ($dateitem == '-2') {

            // Get the last completion date.

            $sql = "SELECT MAX(c.timecompleted) as timecompleted

                      FROM {course_completions} c

                     WHERE c.userid = :userid

                       AND c.course = :courseid";

            if ($timecompleted = $DB->get_record_sql($sql, array('userid' => $issue->userid, 'courseid' => $this->courseid))) {

                if (!empty($timecompleted->timecompleted)) {

                    $date = $timecompleted->timecompleted;
                }
            }
        } else if ($dateitem == '-6') {

            // Get the enrolment start date.

            $sql = "SELECT ue.timestart FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id

                     WHERE e.courseid = :courseid

                       AND ue.userid = :userid";

            if ($timestart = $DB->get_record_sql($sql, array('userid' => $issue->userid, 'courseid' => $this->courseid))) {

                if (!empty($timestart->timestart)) {

                    $date = $timestart->timestart;
                }
            }
        } else if ($dateitem == '-7') {

            // Get the enrolment end date.

            $sql = "SELECT ue.timeend FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id

                     WHERE e.courseid = :courseid

                       AND ue.userid = :userid";

            if ($timeend = $DB->get_record_sql($sql, array('userid' => $issue->userid, 'courseid' => $this->courseid))) {

                if (!empty($timeend->timeend)) {

                    $date = $timeend->timeend;
                }
            }
        } else if ($dateitem == '-3') {

            $date = $DB->get_field('course', 'startdate', array('id' => $this->courseid));
        } else if ($dateitem == '-4') {

            $date = $DB->get_field('course', 'enddate', array('id' => $this->courseid));
        } else {

            if ($dateitem == '0') {

                $grade = \mod_leeloolxpcert\element_helper::get_course_grade_info(

                    $this->courseid,

                    GRADE_DISPLAY_TYPE_DEFAULT,

                    $user->id

                );
            } else if (strpos($dateitem, 'gradeitem:') === 0) {

                $gradeitemid = substr($dateitem, 10);

                $grade = \mod_leeloolxpcert\element_helper::get_grade_item_info(

                    $gradeitemid,

                    $dateitem,

                    $user->id

                );
            } else {

                $grade = \mod_leeloolxpcert\element_helper::get_mod_grade_info(

                    $dateitem,

                    GRADE_DISPLAY_TYPE_DEFAULT,

                    $user->id

                );
            }

            if ($grade && !empty($grade->get_dategraded())) {

                $date = $grade->get_dategraded();
            }
        }

        if (is_number($dateformat)) {

            switch ($dateformat) {

                case 1:

                    $certificatedate = userdate($date, '%B %d, %Y');

                    break;

                case 2:

                    $day = userdate($date, '%d');
                    $suffix = 'th';
                    if (!in_array(($day % 100), array(11, 12, 13))) {
                        switch ($day % 10) {
                                // Handle 1st, 2nd, 3rd.
                            case 1:
                                $suffix = get_string('numbersuffix_st_as_in_first', 'customcertelement_date');
                            case 2:
                                $suffix = get_string('numbersuffix_nd_as_in_second', 'customcertelement_date');
                            case 3:
                                $suffix = get_string('numbersuffix_rd_as_in_third', 'customcertelement_date');
                        }
                    }


                    $certificatedate = userdate($date, '%B %d' . $suffix . ', %Y');

                    break;

                case 3:

                    $certificatedate = userdate($date, '%d %B %Y');

                    break;

                case 4:

                    $certificatedate = userdate($date, '%B %Y');

                    break;

                default:

                    $certificatedate = userdate($date, get_string('strftimedate', 'langconfig'));
            }
        }

        if (!isset($certificatedate)) {

            if ($dateformat == 'strftimedatefullshortwleadingzero') {

                $certificatedate = userdate($date, get_string('strftimedatefullshort', 'langconfig'), 99, false);
            } else if ($dateformat == 'strftimedatetimeshortwleadingzero') {

                $certificatedate = userdate($date, get_string('strftimedatetimeshort', 'langconfig'), 99, false);
            } else {

                $certificatedate = userdate($date, get_string($dateformat, 'langconfig'));
            }
        }
        return $certificatedate;
    }


    /**
     * Generate the studentname column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_studentname($user) {
        return $this->studentname;
    }

    /**
     * Generate the teachername column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_teachername($user) {
        return $this->teachername;
    }

    /**
     * Generate the userfield column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_userfield($user) {
        $field = $this->userfield;
        if (is_number($field)) { // Must be a leeloo user profile field.
            if ($field = $DB->get_record('user_info_field', array('id' => $field))) {
                // Found the field name, let's update the value to display.
                $valuee = $field->name;
                $file = $CFG->dirroot . '/user/profile/field/' . $field->datatype . '/field.class.php';
                if (file_exists($file)) {
                    require_once($CFG->dirroot . '/user/profile/lib.php');
                    require_once($file);
                    $class = "profile_field_{$field->datatype}";
                    $field = new $class($field->id, $user->id);
                    return $valuee = $field->display_data();
                }
            }
        } else { // Field in the user table.
            return $user->$field;
        }
        return '';
    }



    /**
     * Generate the certificate time created column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_timecreated($user) {
        return userdate($user->timecreated);
    }
    /**
     * Generate the code column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_code($user) {
        return $user->code;
    }
    /**
     * Generate the download column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_download($user) {
        global $OUTPUT;
        $icon = new \pix_icon('download', get_string('download'), 'leeloolxpcert');
        $link = new \moodle_url(
            '/mod/leeloolxpcert/view.php',
            [
                'id' => $this->cm->id,
                'downloadissue' => $user->id
            ]
        );
        return $OUTPUT->action_link($link, '', null, null, $icon);
    }
    /**
     * Generate the actions column.
     *
     * @param \stdClass $user
     * @return string
     */
    public function col_actions($user) {
        global $OUTPUT;
        $icon = new \pix_icon('i/delete', get_string('delete'));
        $link = new \moodle_url(
            '/mod/leeloolxpcert/view.php',
            [
                'id' => $this->cm->id,
                'deleteissue' => $user->issueid,
                'sesskey' => sesskey()
            ]
        );
        return $OUTPUT->action_icon($link, $icon, null, ['class' => 'action-icon delete-icon']);
    }
    /**
     * Query the reader.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $total = \mod_leeloolxpcert\certificate::get_number_of_issues($this->leeloolxpcertid, $this->cm, $this->groupmode);
        $this->pagesize($pagesize, $total);
        $this->rawdata = \mod_leeloolxpcert\certificate::get_issues(
            $this->leeloolxpcertid,
            $this->groupmode,
            $this->cm,
            $this->get_page_start(),
            $this->get_page_size(),
            $this->get_sql_sort()
        );
        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }
    /**
     * Download the data.
     */
    public function download() {
        \core\session\manager::write_close();
        $total = \mod_leeloolxpcert\certificate::get_number_of_issues($this->leeloolxpcertid, $this->cm, $this->groupmode);
        $this->out($total, false);
        exit;
    }
}
