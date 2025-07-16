@availability @availability_treasurehunt
Feature: Basic tests for Restriction by Treasurehunt

  @javascript
  Scenario: Plugin availability_treasurehunt appears in the list of installed additional plugins
    Given I log in as "admin"
    When I navigate to "Plugins > Plugins overview" in site administration
    And I follow "Additional plugins"
    Then I should see "Restriction by Treasurehunt"
    And I should see "availability_treasurehunt"
