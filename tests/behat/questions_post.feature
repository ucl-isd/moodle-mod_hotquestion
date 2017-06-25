@mod @mod_hotquestion
Feature: Users can post named or anonymous entries to hotquestion
  In order to post questions
  As a user
  I need to post a hotquestion entry

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
      | activity     | name                   | intro             | course | idnumber     | submitdirections           |
      | hotquestion  | Test hotquestion name  | Hotquestion intro | C1     | hotquestion1 | Submit your question here: |
  Scenario: A user posts named and anonymous entries
    # Admin 1 adds posts.
	Given I log in as "admin"
    When I am on "Course 1" course homepage
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | First question |
    And I press "Post"
    And I set the following fields to these values:
      | Submit your question here: | Second question |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
    Then I log out
    #Teacher 1 posts an entry
	Given I log in as "teacher1"
	And I am on homepage
    And I follow "Course 1"
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Third question |
    And I press "Post"
    And I set the following fields to these values:
      | Submit your question here: | Fourth question |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
    Then I log out
    #Non-editing teacher 2 posts an entry
	Given I log in as "teacher2"
	And I am on homepage
    And I follow "Course 1"
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Fifth question |
    And I press "Post"
    And I set the following fields to these values:
      | Submit your question here: | Sixth question |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
    Then I log out
	#Student 1 posts an entry
	Given I log in as "student1"
	And I am on homepage
    And I follow "Course 1"
    And I follow "Test hotquestion name"
    And I set the following fields to these values:
      | Submit your question here: | Seventh question |
    And I press "Post"
	Then I should see "Seventh question"
    And I set the following fields to these values:
      | Submit your question here: | Eighth question |
	And I set the field "Display as anonymous" to "1"
    And I press "Post"
    Then I should see "Eighth question"
    And I should see "Posted by Anonymous"
    And I should see "Seventh question"
    And I should see "Posted by Student 1"
    And I should see "Sixth question"
	And I should see "Posted by Anonymous"
	And I should see "Fifth question"
    And I should see "Posted by Teacher 2"
	And I should see "Fourth question"
	And I should see "Posted by Anonymous"
    And I should see "Third question"
    And I should see "Posted by Teacher 1"
	And I should see "Second question"
	And I should see "Posted by Anonymous"
	And I should see "First question"
    And I should see "Posted by Admin User"
    Then I log out