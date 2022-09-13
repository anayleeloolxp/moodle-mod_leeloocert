@mod @mod_leeloocert
Feature: Being able to correctly display options on the certificate activity edit form

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | manager1 | Manager   | 1        | Manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
    And the following "activities" exist:
      | activity   | name                 | intro                      | course | idnumber    |
      | leeloocert | Leeloo certificate 1 | Leeloo certificate 1 intro | C1     | leeloocert1 |

  Scenario: Edit an activity as an Editing Teacher I can see all leeloo certificate options
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I follow "Leeloo certificate 1"
    And I navigate to "Edit settings" in current page administration
    And I should see "Email students"
    And I should see "Email teachers"
    And I should see "Email others"
    And I should see "Allow anyone to verify a certificate"
    And I should see "Required minutes in course"
    And I should see "Set protection"

  Scenario: Create an activity as an Editing Teacher I can see all leeloo certificate options
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Leeloo certificate" to section "1"
    And I should see "Email students"
    And I should see "Email teachers"
    And I should see "Email others"
    And I should see "Allow anyone to verify a certificate"
    And I should see "Required minutes in course"
    And I should see "Set protection"

  Scenario: Add an activity as a Manager I can see all leeloo certificate options
    And I log in as "manager1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Leeloo certificate" to section "1"
    And I should see "Email students"
    And I should see "Email teachers"
    And I should see "Email others"
    And I should see "Allow anyone to verify a certificate"
    And I should see "Required minutes in course"
    And I should see "Set protection"

  Scenario: Edit an activity as a Manager Teacher I can see all leeloo certificate options
    And I log in as "manager1"
    And I am on "Course 1" course homepage with editing mode on
    And I follow "Leeloo certificate 1"
    And I navigate to "Edit settings" in current page administration
    And I should see "Email students"
    And I should see "Email teachers"
    And I should see "Email others"
    And I should see "Allow anyone to verify a certificate"
    And I should see "Required minutes in course"
    And I should see "Set protection"

  Scenario: Create an activity as an Editing Teacher without required capabilities I can't see all leeloo certificate options
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability                         | permission |
      | mod/leeloocert:manageemailstudents | Prevent    |
      | mod/leeloocert:manageemailteachers | Prevent    |
      | mod/leeloocert:manageemailothers   | Prevent    |
      | mod/leeloocert:managecertificatevalidthru   | Prevent    |
      | mod/leeloocert:manageverifyany     | Prevent    |
      | mod/leeloocert:managerequiredtime  | Prevent    |
      | mod/leeloocert:manageprotection    | Prevent    |
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Leeloo certificate" to section "1"
    And I should not see "Email students"
    And I should not see "Email teachers"
    And I should not see "Email others"
    And I should not see "Allow anyone to verify a certificate"
    And I should not see "Required minutes in course"
    And I should not see "Set protection"

  Scenario: Edit an activity as an Editing Teacher without required capabilities I can't see all leeloo certificate options
    And I log in as "admin"
    And I set the following system permissions of "Teacher" role:
      | capability                         | permission |
      | mod/leeloocert:manageemailstudents | Prevent    |
      | mod/leeloocert:manageemailteachers | Prevent    |
      | mod/leeloocert:manageemailothers   | Prevent    |
      | mod/leeloocert:managecertificatevalidthru   | Prevent    |
      | mod/leeloocert:manageverifyany     | Prevent    |
      | mod/leeloocert:managerequiredtime  | Prevent    |
      | mod/leeloocert:manageprotection    | Prevent    |
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I follow "Leeloo certificate 1"
    And I navigate to "Edit settings" in current page administration
    And I should not see "Email students"
    And I should not see "Email teachers"
    And I should not see "Email others"
    And I should not see "Allow anyone to verify a certificate"
    And I should not see "Required minutes in course"
    And I should not see "Set protection"

  Scenario: Add an activity using default leeloo certificate options
    And I log in as "manager1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Leeloo certificate" to section "0"
    And the field "emailstudents" matches value "0"
    And the field "emailteachers" matches value "0"
    And the field "emailothers" matches value ""
    And the field "certificatevalidthru" matches value ""
    And the field "verifyany" matches value "0"
    And the field "requiredtime" matches value "0"
    And the field "protection_print" matches value "0"
    And the field "protection_modify" matches value "0"
    And the field "protection_copy" matches value "0"

  Scenario: Add an activity using configured leeloo certificate options
    And the following config values are set as admin:
      | emailstudents     | 1               | leeloocert |
      | emailteachers     | 1               | leeloocert |
      | emailothers       | test@moodle.com | leeloocert |
      | certificatevalidthru       | 1 | leeloocert |
      | verifyany         | 1               | leeloocert |
      | requiredtime      | 5               | leeloocert |
      | protection_print  | 1               | leeloocert |
      | protection_modify | 1               | leeloocert |
      | protection_copy   | 1               | leeloocert |
    And I log in as "manager1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Leeloo certificate" to section "1"
    And the field "emailstudents" matches value "1"
    And the field "emailteachers" matches value "1"
    And the field "emailothers" matches value "test@moodle.com"
    And the field "certificatevalidthru" matches value ""
    And the field "verifyany" matches value "1"
    And the field "requiredtime" matches value "5"
    And the field "protection_print" matches value "1"
    And the field "protection_modify" matches value "1"
    And the field "protection_copy" matches value "1"
