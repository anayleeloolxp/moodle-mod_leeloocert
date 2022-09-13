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
 * Defines mobile handlers.
 *
 * @package   mod_leeloocert
 * @copyright 2018 Mark Nelson <markn@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_leeloocert' => [ // Plugin identifier.
        'handlers' => [ // Different places where the plugin will display content.
            'issueview' => [ // Handler unique name.
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/mod/leeloocert/pix/icon.png',
                    'class' => 'core-course-module-leeloocert-handler',
                ],
                'delegate' => 'CoreCourseModuleDelegate', // Delegate (where to display the link to the plugin).
                'method' => 'mobile_view_activity', // Main function in \mod_leeloocert\output\mobile.
                'styles' => [
                    'url' => '/mod/leeloocert/mobile/styles.css',
                    'version' => 1
                ]
            ]
        ],
        'lang' => [ // Language strings that are used in all the handlers.
            ['deleteissueconfirm', 'leeloocert'],
            ['getleeloocert', 'leeloocert'],
            ['listofissues', 'leeloocert'],
            ['nothingtodisplay', 'moodle'],
            ['notissued', 'leeloocert'],
            ['pluginname', 'leeloocert'],
            ['receiveddate', 'leeloocert'],
            ['requiredtimenotmet', 'leeloocert'],
            ['selectagroup', 'moodle']
        ]
    ]
];
