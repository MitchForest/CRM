<?php
$hook_version = 1;
$hook_array = array();

$hook_array['before_save'] = array();
$hook_array['before_save'][] = array(
    1,
    'Update probability based on stage',
    'custom/modules/Opportunities/OpportunityHooks.php',
    'OpportunityHooks',
    'updateProbability'
);