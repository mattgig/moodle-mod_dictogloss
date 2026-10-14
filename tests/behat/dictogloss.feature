@mod @mod_dictogloss
Feature: Using the Dictogloss activity
  In order to run a dictogloss lesson
  As a teacher and student
  I need to create the activity and move through its stages

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Terry | Teacher | teacher1@example.com |
      | student1 | Sam | Student | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: A student notes keywords, reconstructs, and the source is revealed on submit
    Given the following dictogloss activities exist:
      | course | name | source | shownotes | revealmode |
      | C1 | Lesson | The quick brown fox. | 1 | 1 |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Lesson"
    When I set the field "Keywords and notes" to "quick brown fox"
    And I press "Save keywords"
    And I set the field "Reconstruct the text" to "The quick brown fox."
    And I press "Submit reconstruction"
    Then I should see "Compare your reconstruction with the original"
    And I should see "The quick brown fox."

  Scenario: The original text stays hidden in manual reveal mode
    Given the following dictogloss activities exist:
      | course | name | source | revealmode |
      | C1 | Manual | The early bird catches the worm. | 2 |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Manual"
    Then I should not see "The early bird catches the worm."

  Scenario: The reconstruction is held until the teacher opens it
    Given the following dictogloss activities exist:
      | course | name | source | holdreconstruction |
      | C1 | Held | The lazy dog sleeps. | 1 |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Held"
    Then I should see "Your teacher will open the reconstruction shortly"

  Scenario: The teacher opens the held reconstruction from the live controls
    Given the following dictogloss activities exist:
      | course | name | source | holdreconstruction |
      | C1 | Held | The lazy dog sleeps. | 1 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Held"
    And I follow "Live controls"
    And I set the field "Open the reconstruction" to "1"
    And I press "Save"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Held"
    Then I should see "Reconstruct the text"
