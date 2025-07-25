<?php
// Define B2B-specific sales stages
$app_list_strings['sales_stage_dom'] = array(
    'Qualification' => 'Qualification',
    'Needs Analysis' => 'Needs Analysis',
    'Value Proposition' => 'Value Proposition',
    'Decision Makers' => 'Decision Makers',
    'Proposal' => 'Proposal',
    'Negotiation' => 'Negotiation',
    'Closed Won' => 'Closed Won',
    'Closed Lost' => 'Closed Lost',
);

// Define probability mapping for stages
$app_list_strings['sales_probability_dom'] = array(
    'Qualification' => '10',
    'Needs Analysis' => '20',
    'Value Proposition' => '40',
    'Decision Makers' => '60',
    'Proposal' => '75',
    'Negotiation' => '90',
    'Closed Won' => '100',
    'Closed Lost' => '0',
);

// Add custom fields for B2B sales
$dictionary['Opportunity']['fields']['competitors'] = array(
    'name' => 'competitors',
    'vname' => 'LBL_COMPETITORS',
    'type' => 'text',
    'comment' => 'Competitors being evaluated by the prospect',
    'rows' => 3,
    'cols' => 60,
);

$dictionary['Opportunity']['fields']['decision_criteria'] = array(
    'name' => 'decision_criteria',
    'vname' => 'LBL_DECISION_CRITERIA',
    'type' => 'text',
    'comment' => 'Key decision criteria for this opportunity',
    'rows' => 3,
    'cols' => 60,
);

$dictionary['Opportunity']['fields']['champion_contact_id'] = array(
    'name' => 'champion_contact_id',
    'vname' => 'LBL_CHAMPION_CONTACT',
    'type' => 'id',
    'comment' => 'Internal champion contact ID',
);

$dictionary['Opportunity']['fields']['subscription_type'] = array(
    'name' => 'subscription_type',
    'vname' => 'LBL_SUBSCRIPTION_TYPE',
    'type' => 'enum',
    'options' => 'subscription_type_list',
    'comment' => 'Type of subscription (Monthly, Annual, etc)',
);