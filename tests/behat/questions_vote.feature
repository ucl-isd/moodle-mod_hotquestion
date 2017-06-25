@mod @mod_hotquestion
Feature: Users can vote on named or anonymous entries to hotquestion
  In order to vote on a question
  As a user
  I need to see a hotquestion post

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |	  
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
	  | teacher2 | Teacher   | 2        | teacher2@asd.com |
	  | manager1 | Manager   | 1        | manager@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
	  | teacher2 | C1     | teacher        |
	  | manager1 | C1     | manager        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity     | name                   | intro             | course | idnumber     | submitdirections           |
      | hotquestion  | Test hotquestion name  | Hotquestion intro | C1     | hotquestion1 | Submit your question here: |
  Scenario: A user follows vote to increase heat
    # Student 1 adds posts and votes.
	Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | First question by student 1 |
    And I press "Post"
    And I set the following fields to these values:
      | Submit your question here: | Second question by student 1 |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
    #And I wait "2" seconds
	Then I should see "Second question by student 1"
	And I should see "Posted by Anonymous"
	Then I should see "First question by student 1"
    And I should see "Posted by Student 1"
	And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "1" in the "Anonymous" "table_row"
    Then I log out
    # Admin 1 votes.
	Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "2" in the "Anonymous" "table_row"
	And I click on "Vote" "link" in the "Student 1" "table_row"
	Then I should see "1" in the "Student 1" "table_row"
    Then I log out
	# Teacher 1 votes.
	Given I log in as "teacher1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "3" in the "Anonymous" "table_row"
	And I click on "Vote" "link" in the "Student 1" "table_row"
	Then I should see "2" in the "Student 1" "table_row"
    Then I log out
	#Teacher 2 votes.
	Given I log in as "teacher2"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "4" in the "Anonymous" "table_row"
	And I click on "Vote" "link" in the "Student 1" "table_row"
	Then I should see "3" in the "Student 1" "table_row"
    Then I log out
	#Manager 1 votes.
	Given I log in as "manager1"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I click on "Vote" "link" in the "Anonymous" "table_row"
    Then I should see "5" in the "Anonymous" "table_row"
	And I click on "Vote" "link" in the "Student 1" "table_row"
	Then I should see "4" in the "Student 1" "table_row"
    Then I log out