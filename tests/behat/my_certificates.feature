@mod @mod_leeloolxpcert
Feature: Being able to view the certificates you have been issued
  In order to ensure that a user can view the certificates they have been issued
  As a student
  I need to view the certificates I have been issued

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
    And the following "activities" exist:
      | activity   | name                 | intro                      | course | idnumber    |
      | leeloolxpcert | Leeloo certificate 1 | Leeloo certificate 1 intro | C1     | leeloolxpcert1 |
      | leeloolxpcert | Leeloo certificate 2 | Leeloo certificate 2 intro | C2     | leeloolxpcert2 |

  Scenario: View your issued certificates on the my certificates page
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Leeloo certificate 1"
    And I press "View certificate"
    And I follow "Profile" in the user menu
    And I follow "My certificates"
    And I should see "Leeloo certificate 1"
    And I should not see "Leeloo certificate 2"
    And I am on "Course 2" course homepage
    And I follow "Leeloo certificate 2"
    And I press "View certificate"
    And I follow "Profile" in the user menu
    And I follow "My certificates"
    And I should see "Leeloo certificate 1"
    And I should see "Leeloo certificate 2"
