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
 * Privacy provider tests.
 *
 * @package    mod_leeloocert
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_leeloocert\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    mod_leeloocert
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_leeloocert_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // The leeloocert activity the user will have an issue from.
        $leeloocert = $this->getDataGenerator()->create_module('leeloocert', ['course' => $course->id]);

        // Another leeloocert activity that has no issued certificates.
        $this->getDataGenerator()->create_module('leeloocert', ['course' => $course->id]);

        // Create a user who will be issued a certificate.
        $user = $this->getDataGenerator()->create_user();

        // Issue the certificate.
        $this->create_certificate_issue($leeloocert->id, $user->id);

        // Check the context supplied is correct.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);

        $contextformodule = $contextlist->current();
        $cmcontext = context_module::instance($leeloocert->cmid);
        $this->assertEquals($cmcontext->id, $contextformodule->id);
    }

    /**
     * Test for provider::get_users_in_context().
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // The leeloocert activity the user will have an issue from.
        $leeloocert1 = $this->getDataGenerator()->create_module('leeloocert', ['course' => $course->id]);
        $leeloocert2 = $this->getDataGenerator()->create_module('leeloocert', ['course' => $course->id]);

        // Call get_users_in_context() when the leeloocert hasn't any user.
        $cm = get_coursemodule_from_instance('leeloocert', $leeloocert1->id);
        $cmcontext = context_module::instance($cm->id);
        $userlist = new \core_privacy\local\request\userlist($cmcontext, 'mod_leeloocert');
        provider::get_users_in_context($userlist);

        // Check no user has been returned.
        $this->assertCount(0, $userlist->get_userids());

        // Create some users who will be issued a certificate.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $this->create_certificate_issue($leeloocert1->id, $user1->id);
        $this->create_certificate_issue($leeloocert1->id, $user2->id);
        $this->create_certificate_issue($leeloocert2->id, $user3->id);

        // Call get_users_in_context() again.
        provider::get_users_in_context($userlist);

        // Check this time there are 2 users.
        $this->assertCount(2, $userlist->get_userids());
        $this->assertContains($user1->id, $userlist->get_userids());
        $this->assertContains($user2->id, $userlist->get_userids());
        $this->assertNotContains($user3->id, $userlist->get_userids());
    }

    /**
     * Test for provider::get_users_in_context() with invalid context type.
     */
    public function test_get_users_in_context_invalid_context_type() {
        $systemcontext = context_system::instance();

        $userlist = new \core_privacy\local\request\userlist($systemcontext, 'mod_leeloocert');
        \mod_leeloocert\privacy\provider::get_users_in_context($userlist);

        $this->assertCount(0, $userlist->get_userids());
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_for_context() {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $leeloocert = $this->getDataGenerator()->create_module('leeloocert', array('course' => $course->id));

        // Create users who will be issued a certificate.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->create_certificate_issue($leeloocert->id, $user1->id);
        $this->create_certificate_issue($leeloocert->id, $user1->id);
        $this->create_certificate_issue($leeloocert->id, $user2->id);

        // Export all of the data for the context for user 1.
        $cmcontext = context_module::instance($leeloocert->cmid);
        $this->export_context_data_for_user($user1->id, $cmcontext, 'mod_leeloocert');
        $writer = \core_privacy\local\request\writer::with_context($cmcontext);

        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data();
        $this->assertCount(2, $data->issues);

        $issues = $data->issues;
        foreach ($issues as $issue) {
            $this->assertArrayHasKey('code', $issue);
            $this->assertArrayHasKey('emailed', $issue);
            $this->assertArrayHasKey('timecreated', $issue);
        }
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $leeloocert = $this->getDataGenerator()->create_module('leeloocert', array('course' => $course->id));
        $leeloocert2 = $this->getDataGenerator()->create_module('leeloocert', array('course' => $course->id));

        // Create users who will be issued a certificate.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->create_certificate_issue($leeloocert->id, $user1->id);
        $this->create_certificate_issue($leeloocert->id, $user2->id);

        $this->create_certificate_issue($leeloocert2->id, $user1->id);
        $this->create_certificate_issue($leeloocert2->id, $user2->id);

        // Before deletion, we should have 2 issued certificates for the first certificate.
        $count = $DB->count_records('leeloocert_issues', ['leeloocertid' => $leeloocert->id]);
        $this->assertEquals(2, $count);

        // Delete data based on context.
        $cmcontext = context_module::instance($leeloocert->cmid);
        provider::delete_data_for_all_users_in_context($cmcontext);

        // After deletion, the issued certificates for the activity should have been deleted.
        $count = $DB->count_records('leeloocert_issues', ['leeloocertid' => $leeloocert->id]);
        $this->assertEquals(0, $count);

        // We should still have the issues for the second certificate.
        $count = $DB->count_records('leeloocert_issues', ['leeloocertid' => $leeloocert2->id]);
        $this->assertEquals(2, $count);
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $leeloocert = $this->getDataGenerator()->create_module('leeloocert', array('course' => $course->id));

        // Create users who will be issued a certificate.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->create_certificate_issue($leeloocert->id, $user1->id);
        $this->create_certificate_issue($leeloocert->id, $user2->id);

        // Before deletion we should have 2 issued certificates.
        $count = $DB->count_records('leeloocert_issues', ['leeloocertid' => $leeloocert->id]);
        $this->assertEquals(2, $count);

        $context = \context_module::instance($leeloocert->cmid);
        $contextlist = new \core_privacy\local\request\approved_contextlist($user1, 'leeloocert',
            [$context->id]);
        provider::delete_data_for_user($contextlist);

        // After deletion, the issued certificates for the first user should have been deleted.
        $count = $DB->count_records('leeloocert_issues', ['leeloocertid' => $leeloocert->id, 'userid' => $user1->id]);
        $this->assertEquals(0, $count);

        // Check the issue for the other user is still there.
        $leeloocertissue = $DB->get_records('leeloocert_issues');
        $this->assertCount(1, $leeloocertissue);
        $lastissue = reset($leeloocertissue);
        $this->assertEquals($user2->id, $lastissue->userid);
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users() {
        global $DB;

        $this->resetAfterTest();

        // Create course, leeloocert and users who will be issued a certificate.
        $course = $this->getDataGenerator()->create_course();
        $leeloocert1 = $this->getDataGenerator()->create_module('leeloocert', array('course' => $course->id));
        $leeloocert2 = $this->getDataGenerator()->create_module('leeloocert', array('course' => $course->id));
        $cm1 = get_coursemodule_from_instance('leeloocert', $leeloocert1->id);
        $cm2 = get_coursemodule_from_instance('leeloocert', $leeloocert2->id);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $this->create_certificate_issue($leeloocert1->id, $user1->id);
        $this->create_certificate_issue($leeloocert1->id, $user2->id);
        $this->create_certificate_issue($leeloocert1->id, $user3->id);
        $this->create_certificate_issue($leeloocert2->id, $user1->id);
        $this->create_certificate_issue($leeloocert2->id, $user2->id);

        // Before deletion we should have 3 + 2 issued certificates.
        $count = $DB->count_records('leeloocert_issues', ['leeloocertid' => $leeloocert1->id]);
        $this->assertEquals(3, $count);
        $count = $DB->count_records('leeloocert_issues', ['leeloocertid' => $leeloocert2->id]);
        $this->assertEquals(2, $count);

        $context1 = context_module::instance($cm1->id);
        $approveduserlist = new \core_privacy\local\request\approved_userlist($context1, 'leeloocert',
                [$user1->id, $user2->id]);
        provider::delete_data_for_users($approveduserlist);

        // After deletion, the leeloocert of the 2 students provided above should have been deleted
        // from the activity. So there should only remain 1 certificate which is for $user3.
        $leeloocertissues1 = $DB->get_records('leeloocert_issues', ['leeloocertid' => $leeloocert1->id]);
        $this->assertCount(1, $leeloocertissues1);
        $lastissue = reset($leeloocertissues1);
        $this->assertEquals($user3->id, $lastissue->userid);

        // Confirm that the certificates issues in the other activity are intact.
        $leeloocertissues1 = $DB->get_records('leeloocert_issues', ['leeloocertid' => $leeloocert2->id]);
        $this->assertCount(2, $leeloocertissues1);
    }

    /**
     * Mimicks the creation of a leeloocert issue.
     *
     * There is no API we can use to insert an leeloocert issue, so we
     * will simply insert directly into the database.
     *
     * @param int $leeloocertid
     * @param int $userid
     */
    protected function create_certificate_issue(int $leeloocertid, int $userid) {
        global $DB;

        static $i = 1;

        $leeloocertissue = new stdClass();
        $leeloocertissue->leeloocertid = $leeloocertid;
        $leeloocertissue->userid = $userid;
        $leeloocertissue->code = \mod_leeloocert\certificate::generate_code();
        $leeloocertissue->timecreated = time() + $i;

        // Insert the record into the database.
        $DB->insert_record('leeloocert_issues', $leeloocertissue);

        $i++;
    }
}
