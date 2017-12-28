@mod @mod_hotquestion
Feature: Users can post named or anonymous entries to hotquestion
  In order to use HotQuestion
  As a user
  I need to be able to post a hotquestion entry.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1 | 0 | 1 |
    And the following "users" exist:
      | username | firstname | lastname | email            |	  
      | teacher1 | Teacher   | 1        | teacher1@asd.com |
	  | teacher2 | Teacher   | 2        | teacher2@asd.com |
      | student1 | Student   | 1        | student1@asd.com |
      | student2 | Student   | 2        | student2@asd.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
	  | teacher2 | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity     | name                   | intro             | course | idnumber     | submitdirections           | anonymouspost | approval |
      | hotquestion  | Test hotquestion name  | Hotquestion intro | C1     | hotquestion1 | Submit your question here: | 1             | 1        |
  Scenario: A user posts named and anonymous entries
    # Admin User adds and approves posts.
	Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | First question |
    And I press "Post"
	And I follow "Not approved"
    And I set the following fields to these values:
      | Submit your question here: | Second question |
    And I set the following fields to these values:
      | Submit your question here: | Second question |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
	And I follow "Not approved"
    Then I log out
    #Teacher 1 adds and approves posts
	Given I log in as "teacher1"
	And I am on homepage
    And I follow "Course 1"
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Third question |
    And I press "Post"
	And I follow "Not approved"
    And I set the following fields to these values:
      | Submit your question here: | Fourth question |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
	And I follow "Not approved"
    Then I log out
	#Student 1 posts an entry
	Given I log in as "student1"
	And I am on homepage
    And I follow "Course 1"
    And I follow "Test hotquestion name"
	And I should see "Fourth question"
	And I should see "Posted by Anonymous"
    And I should see "Third question"
    And I should see "Posted by Teacher 1"
	And I should see "Second question"
	And I should see "Posted by Anonymous"
	And I should see "First question"
    And I should see "Posted by Admin User"
    And I set the following fields to these values:
      | Submit your question here: | Seventh question |
    And I press "Post"
	Then I should see "This entry is not currently approved for viewing."
    And I set the following fields to these values:
      | Submit your question here: | Eighth question |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
    Then I should see "This entry is not currently approved for viewing."
    Then I log out
     #Teacher 1 approves a students entries
	Given I log in as "teacher1"
	And I am on homepage
    And I follow "Course 1"
    And I follow "Test hotquestion name"
	And I follow "Not approved"
	And I follow "Not approved"
    Then I log out
	#Student 1 views his approved entries
	Given I log in as "student1"
	And I am on homepage
    And I follow "Course 1"
    And I follow "Test hotquestion name"
	And I should see "Eighth question"
	And I should see "Posted by Anonymous"
    And I should see "Seventh question"
    And I should see "Posted by Student 1"
    Then I log out