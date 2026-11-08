@block @block_gradingqueue
Feature: See everything waiting for grading on the Dashboard
  In order to grade work in time across all my courses
  As a teacher
  I need one list of submissions and attempts waiting for me, oldest first

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tess      | Teacher  |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname |
      | Biology  | BIO       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | BIO    | editingteacher |
      | student1 | BIO    | student        |
    And the following "activities" exist:
      | activity | course | name    | assignsubmission_onlinetext_enabled | submissiondrafts |
      | assign   | BIO    | Essay 1 | 1                                   | 0                |
    And the following "mod_assign > submissions" exist:
      | assign  | user     | onlinetext |
      | Essay 1 | student1 | My answer  |

  @javascript @accessibility
  Scenario: A teacher adds the block and opens the grading page from it
    Given I log in as "teacher1"
    And I turn editing mode on
    When I add the "Needs grading" block
    Then I should see "Essay 1" in the "Needs grading" "block"
    And I should see "1 waiting" in the "Needs grading" "block"
    And I should see "Biology" in the "Needs grading" "block"
    And I should see "1 to grade" in the "Needs grading" "block"
    # Checked with editing off: Moodle 5.0's own block editing controls fail the touch target rule.
    And I turn editing mode off
    And the page should meet accessibility standards
    When I click on "Essay 1" "link" in the "Needs grading" "block"
    Then I should see "Sam Student"

  Scenario: A student sees nothing to grade
    Given I log in as "student1"
    And I turn editing mode on
    When I add the "Needs grading" block
    Then I should see "Nothing waiting for grading." in the "Needs grading" "block"
