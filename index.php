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
 * This page lists all the instances of leeloocert in a particular course.
 *
 * @package    mod_leeloocert
 * @copyright  2013 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

// Requires a login.
require_login($course);

// Set up the page variables.
$pageurl = new moodle_url('/mod/leeloocert/index.php', array('id' => $course->id));
\mod_leeloocert\page_helper::page_setup(
    $pageurl,
    context_course::instance($id),
    get_string('modulenameplural', 'leeloocert')
);

// Additional page setup needed.
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add(get_string('modulenameplural', 'leeloocert'));

// Add the page view to the Moodle log.
$event = \mod_leeloocert\event\course_module_instance_list_viewed::create(array(
    'context' => context_course::instance($course->id)
));
$event->add_record_snapshot('course', $course);
$event->trigger();

// Get the leeloocerts, if there are none display a notice.
if (!$leeloocerts = get_all_instances_in_course('leeloocert', $course)) {
    echo $OUTPUT->header();
    notice(get_string('noleeloocerts', 'leeloocert'), new moodle_url('/course/view.php', array('id' => $course->id)));
    echo $OUTPUT->footer();
    exit();
}

// Create the table to display the different leeloo certificates.
$table = new html_table();

if ($usesections = course_format_uses_sections($course->format)) {
    $table->head = array(
        get_string('sectionname', 'format_' . $course->format), get_string('name'),
        get_string('receiveddate', 'leeloocert')
    );
} else {
    $table->head = array(get_string('name'), get_string('receiveddate', 'leeloocert'));
}

$currentsection = '';
foreach ($leeloocerts as $leeloocert) {
    // Check if the leeloocert is visible, if so show text as normal, else show it as dimmed.
    if ($leeloocert->visible) {
        $link = html_writer::tag('a', $leeloocert->name, array('href' => new moodle_url(
            '/mod/leeloocert/view.php',
            array('id' => $leeloocert->coursemodule)
        )));
    } else {
        $link = html_writer::tag('a', $leeloocert->name, array(
            'class' => 'dimmed',
            'href' => new moodle_url('/mod/leeloocert/view.php', array('id' => $leeloocert->coursemodule))
        ));
    }
    // If we are at a different section then print a horizontal rule.
    if ($leeloocert->section !== $currentsection) {
        if ($currentsection !== '') {
            $table->data[] = 'hr';
        }
        $currentsection = $leeloocert->section;
    }
    // Check if there is was an issue provided for this user.
    if ($certrecord = $DB->get_record('leeloocert_issues', array('userid' => $USER->id, 'leeloocertid' => $leeloocert->id))) {
        $issued = userdate($certrecord->timecreated);
    } else {
        $issued = get_string('notissued', 'leeloocert');
    }
    // Only display the section column if the course format uses sections.
    if ($usesections) {
        $table->data[] = array($leeloocert->section, $link, $issued);
    } else {
        $table->data[] = array($link, $issued);
    }
}

echo $OUTPUT->header();
echo html_writer::table($table);
echo $OUTPUT->footer();
