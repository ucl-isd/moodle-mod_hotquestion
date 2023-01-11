@mod @mod_hotquestion
Feature: Set entry visibility after close time for HotQuestion
  In order to control if a student can HotQuestion entries after closing time
  As a teacher
  I need to be able to set availability dates and viewaftertimeclose flag for a hotquestion.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity            | hotquestion                  |
      | course              | C1                           |
      | idnumber            | hotquestion1                 |
      | name                | Test hotquestion name        |
      | intro               | Test hotquestion description |
      | grade               | 0                            |
      | timeopen            | 0                            |
      | timeclose           | 0                            |
      | viewaftertimeclose  | 0                            |
  Scenario: Student doesn't see questions after close time
    #Teacher 1 posts an entry
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here | First question |
    And I press "Click to post"
    And I should not see "No entries yet."
    And I should see "First question"
    Then I log out
	#Student 1 views and posts the questions
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I should see "First question"
    Then I log out
    #Teacher 1 set time close
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I open course or activity settings page
    And I set the following fields to these values:
      | timeclose[enabled] | 1 |
      | timeclose[day] | 1 |
      | timeclose[month] | 2 |
      | timeclose[year] | 2017 |
    And I press "Save and return to course"
    Then I log out
    #Student 1 cannot view questions
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I should see "Not currently available!"
    Then I log out
  Scenario: Student do see questions after close time when "View after close time" is set
    #Teacher 1 posts an entry, set close time and enables "View after close time"
    Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here | First question |
    And I press "Click to post"
    And I should not see "No entries yet."
    And I should see "First question"
    Then I open course or activity settings page
    And I set the following fields to these values:
      | timeclose[enabled] | 1 |
      | timeclose[day] | 1 |
      | timeclose[month] | 2 |
      | timeclose[year] | 2017 |
      | viewaftertimeclose | 1 |
    And I press "Save and return to course"
    Then I log out
    #Student 1 views questions
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I should see "First question"
    And I should not see "Submit your question here"
    Then I log out