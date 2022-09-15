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
 * Leeloocert module core interaction API
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');
require_once($CFG->libdir . '/adminlib.php');

/**
 * The most flexibly setting, user is typing text
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_configleeloocert extends admin_setting {

    /** @var mixed int means PARAM_XXX type, string is a allowed format in regex */
    public $paramtype;
    /** @var int default field size */
    public $size;

    /**
     * Config text constructor
     *
     * @param string $name unique ascii name, either 'mysetting' for settings that in config,
     * or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting
     * @param mixed $paramtype int means PARAM_XXX type, string is a allowed format in regex
     * @param int $size default field size
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, $paramtype = PARAM_RAW, $size = null) {
        $this->paramtype = $paramtype;
        if (!is_null($size)) {
            $this->size = $size;
        } else {
            $this->size = ($paramtype === PARAM_INT) ? 5 : 30;
        }
        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * Return the setting
     *
     * @return mixed returns config if successful else null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Write the setting
     * @param string $data the data
     * @return mixed true if ok string if error found
     */
    public function write_setting($data) {
        if ($this->paramtype === PARAM_INT && $data === '') {
            // Do not complain if '' used instead of 0.
            $data = 0;
        }

        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        return ($this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin'));
    }

    /**
     * Validate data before storage
     * @param string $data the data
     * @return mixed true if ok string if error found
     */
    public function validate($data) {
        // Allow paramtype to be a custom regex if it is the form of /pattern/.
        if (preg_match('#^/.*/$#', $this->paramtype)) {
            if (preg_match($this->paramtype, $data)) {
                return true;
            } else {
                return get_string('validateerror', 'admin');
            }
        } else if ($this->paramtype === PARAM_RAW) {
            return true;
        } else {
            $cleaned = clean_param($data, $this->paramtype);
            if ("$data" === "$cleaned") {
                // Implicit conversion to string is needed to do exact comparison.
                return true;
            } else {
                return get_string('validateerror', 'admin');
            }
        }
    }

    /**
     * Return an XHTML string for the setting
     *
     * @param string $data The data
     * @param string $query The query
     * @return string Returns an XHTML string
     */
    public function output_html($data, $query = '') {
        $default = $this->get_defaultsetting();
        return '<input type="hidden" size="' . $this->size . '" id="' . $this->get_id() . '"
        name="' . $this->get_full_name() . '" value="' . s($data) . '" />';
    }
}

/**
 * Add leeloocert instance.
 *
 * @param stdClass $data
 * @param mod_leeloocert_mod_form $mform
 * @return int new leeloocert instance id
 */
function leeloocert_add_instance($data, $mform) {
    global $DB;

    // Create a template for this leeloocert to use.
    $context = context_module::instance($data->coursemodule);
    $template = \mod_leeloocert\template::create($data->name, $context->id);

    // Add the data to the DB.
    $data->templateid = $template->get_id();
    $data->protection = \mod_leeloocert\certificate::set_protection($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('leeloocert', $data);

    // Add a page to this leeloocert.
    $template->add_page();

    return $data->id;
}

/**
 * Update leeloocert instance.
 *
 * @param stdClass $data
 * @param mod_leeloocert_mod_form $mform
 * @return bool true
 */
function leeloocert_update_instance($data, $mform) {
    global $DB;

    $data->protection = \mod_leeloocert\certificate::set_protection($data);
    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('leeloocert', $data);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id
 * @return bool true if successful
 */
function leeloocert_delete_instance($id) {
    global $CFG, $DB;

    // Ensure the leeloocert exists.
    if (!$leeloocert = $DB->get_record('leeloocert', array('id' => $id))) {
        return false;
    }

    // Get the course module as it is used when deleting files.
    if (!$cm = get_coursemodule_from_instance('leeloocert', $id)) {
        return false;
    }

    // Delete the leeloocert instance.
    if (!$DB->delete_records('leeloocert', array('id' => $id))) {
        return false;
    }

    // Now, delete the template associated with this certificate.
    if ($template = $DB->get_record('leeloocert_templates', array('id' => $leeloocert->templateid))) {
        $template = new \mod_leeloocert\template($template);
        $template->delete();
    }

    // Delete the leeloocert issues.
    if (!$DB->delete_records('leeloocert_issues', array('leeloocertid' => $id))) {
        return false;
    }

    // Delete any files associated with the leeloocert.
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    return true;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified leeloocert
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array status array
 */
function leeloocert_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'leeloocert');
    $status = array();

    if (!empty($data->reset_leeloocert)) {
        $sql = "SELECT cert.id
                  FROM {leeloocert} cert
                 WHERE cert.course = :courseid";
        $DB->delete_records_select('leeloocert_issues', "leeloocertid IN ($sql)", array('courseid' => $data->courseid));
        $status[] = array(
            'component' => $componentstr, 'item' => get_string('deleteissuedcertificates', 'leeloocert'),
            'error' => false
        );
    }

    return $status;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the leeloocert.
 *
 * @param mod_leeloocert_mod_form $mform form passed by reference
 */
function leeloocert_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'leeloocertheader', get_string('modulenameplural', 'leeloocert'));
    $mform->addElement('advcheckbox', 'reset_leeloocert', get_string('deleteissuedcertificates', 'leeloocert'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course
 * @return array
 */
function leeloocert_reset_course_form_defaults($course) {
    return array('reset_leeloocert' => 1);
}

/**
 * Returns information about received leeloocert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $leeloocert
 * @return stdClass the user outline object
 */
function leeloocert_user_outline($course, $user, $mod, $leeloocert) {
    global $DB;

    $result = new stdClass();
    if ($issue = $DB->get_record('leeloocert_issues', array('leeloocertid' => $leeloocert->id, 'userid' => $user->id))) {
        $result->info = get_string('receiveddate', 'leeloocert');
        $result->time = $issue->timecreated;
    } else {
        $result->info = get_string('notissued', 'leeloocert');
    }

    return $result;
}

/**
 * Returns information about received leeloocert.
 * Used for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $leeloocert
 * @return string the user complete information
 */
function leeloocert_user_complete($course, $user, $mod, $leeloocert) {
    global $DB, $OUTPUT;

    if ($issue = $DB->get_record('leeloocert_issues', array('leeloocertid' => $leeloocert->id, 'userid' => $user->id))) {
        echo $OUTPUT->box_start();
        echo get_string('receiveddate', 'leeloocert') . ": ";
        echo userdate($issue->timecreated);
        echo $OUTPUT->box_end();
    } else {
        print_string('notissued', 'leeloocert');
    }
}

/**
 * Serves certificate issues and other files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool|null false if file not found, does not return anything if found - just send the file
 */
function leeloocert_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    // We are positioning the elements.
    if ($filearea === 'image') {
        if ($context->contextlevel == CONTEXT_MODULE) {
            require_login($course, false, $cm);
        } else if ($context->contextlevel == CONTEXT_SYSTEM && !has_capability('mod/leeloocert:manage', $context)) {
            return false;
        }

        $relativepath = implode('/', $args);
        $fullpath = '/' . $context->id . '/mod_leeloocert/image/' . $relativepath;

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, $forcedownload);
    }
}

/**
 * The features this activity supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function leeloocert_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Used for course participation report (in case leeloocert is added).
 *
 * @return array
 */
function leeloocert_get_view_actions() {
    return array('view', 'view all', 'view report');
}

/**
 * Used for course participation report (in case leeloocert is added).
 *
 * @return array
 */
function leeloocert_get_post_actions() {
    return array('received');
}

/**
 * Function to be run periodically according to the moodle cron.
 */
function leeloocert_cron() {
    return true;
}

/**
 * Serve the edit element as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function mod_leeloocert_output_fragment_editelement($args) {
    global $DB;

    // Get the element.
    $element = $DB->get_record('leeloocert_elements', array('id' => $args['elementid']), '*', MUST_EXIST);

    $pageurl = new moodle_url('/mod/leeloocert/rearrange.php', array('pid' => $element->pageid));
    $form = new \mod_leeloocert\edit_element_form($pageurl, array('element' => $element));

    return $form->render();
}

/**
 * This function extends the settings navigation block for the site.
 *
 * It is safe to rely on PAGE here as we will only ever be within the module
 * context when this is called.
 *
 * @param settings_navigation $settings
 * @param navigation_node $leeloocertnode
 */
function leeloocert_extend_settings_navigation(settings_navigation $settings, navigation_node $leeloocertnode) {
    global $DB, $PAGE;

    $keys = $leeloocertnode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (has_capability('mod/leeloocert:manage', $PAGE->cm->context)) {
        // Get the template id.
        $templateid = $DB->get_field('leeloocert', 'templateid', array('id' => $PAGE->cm->instance));
        $node = navigation_node::create(
            get_string('editleeloocert', 'leeloocert'),
            new moodle_url('/mod/leeloocert/edit.php', array('tid' => $templateid)),
            navigation_node::TYPE_SETTING,
            null,
            'mod_leeloocert_edit',
            new pix_icon('t/edit', '')
        );
        $leeloocertnode->add_node($node, $beforekey);
    }

    if (has_capability('mod/leeloocert:verifycertificate', $PAGE->cm->context)) {
        $node = navigation_node::create(
            get_string('verifycertificate', 'leeloocert'),
            new moodle_url('/mod/leeloocert/verify_certificate.php', array('contextid' => $PAGE->cm->context->id)),
            navigation_node::TYPE_SETTING,
            null,
            'mod_leeloocert_verify_certificate',
            new pix_icon('t/check', '')
        );
        $leeloocertnode->add_node($node, $beforekey);
    }

    return $leeloocertnode->trim_if_empty();
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 * @return bool
 */
function mod_leeloocert_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    $url = new moodle_url('/mod/leeloocert/my_certificates.php', array('userid' => $user->id));
    $node = new core_user\output\myprofile\node(
        'miscellaneous',
        'myleeloocerts',
        get_string('mycertificates', 'leeloocert'),
        null,
        $url
    );
    $tree->add_node($node);
}

/**
 * Handles editing the 'name' of the element in a list.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param string $newvalue
 * @return \core\output\inplace_editable
 */
function mod_leeloocert_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $PAGE;

    if ($itemtype === 'elementname') {
        $element = $DB->get_record('leeloocert_elements', array('id' => $itemid), '*', MUST_EXIST);
        $page = $DB->get_record('leeloocert_pages', array('id' => $element->pageid), '*', MUST_EXIST);
        $template = $DB->get_record('leeloocert_templates', array('id' => $page->templateid), '*', MUST_EXIST);

        // Set the template object.
        $template = new \mod_leeloocert\template($template);
        // Perform checks.
        if ($cm = $template->get_cm()) {
            require_login($cm->course, false, $cm);
        } else {
            $PAGE->set_context(context_system::instance());
            require_login();
        }
        // Make sure the user has the required capabilities.
        $template->require_manage();

        // Clean input and update the record.
        $updateelement = new stdClass();
        $updateelement->id = $element->id;
        $updateelement->name = clean_param($newvalue, PARAM_TEXT);
        $DB->update_record('leeloocert_elements', $updateelement);

        return new \core\output\inplace_editable(
            'mod_leeloocert',
            'elementname',
            $element->id,
            true,
            $updateelement->name,
            $updateelement->name
        );
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_leeloocert_get_fontawesome_icon_map() {
    return [
        'mod_leeloocert:download' => 'fa-download'
    ];
}

/**
 * File browsing support for leeloocert module content area.
 *
 * @package  mod_leeloocert
 * @category files
 * @param stdClass $browser file browser instance
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function leeloocert_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
        // Students can not peak here!
        return null;
    }

    $fs = get_file_storage();

    if ($filearea === 'content') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_leeloocert', 'content', 0, $filepath, $filename)) {
            if ($filepath === '/' && $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_leeloocert', 'content', 0);
            } else {
                // Not found.
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/leeloocert/locallib.php");
        return new leeloocert_content_file_info(
            $browser,
            $context,
            $storedfile,
            $urlbase,
            $areas[$filearea],
            true,
            true,
            true,
            false
        );
    }

    // Note: leeloocert_intro handled in file_browser automatically.

    return null;
}
