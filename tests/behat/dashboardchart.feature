@block @block_dashboardchart
Feature: Add the Dashboard Chart block
  In order to display site statistics on a dashboard
  As an admin
  I need to be able to add the Dashboard Chart block

  Scenario: Add the Dashboard Chart block to the dashboard
    Given I log in as "admin"
    And I turn editing mode on
    When I add the "Dashboard Chart" block
    Then I should see "Dashboard Chart"
