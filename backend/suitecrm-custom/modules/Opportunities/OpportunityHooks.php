<?php
class OpportunityHooks
{
    public function updateProbability($bean, $event, $arguments)
    {
        global $app_list_strings;
        
        if (!empty($bean->sales_stage) && 
            isset($app_list_strings['sales_probability_dom'][$bean->sales_stage])) {
            
            // Only update if probability hasn't been manually set
            if (empty($bean->fetched_row['id']) || 
                $bean->fetched_row['sales_stage'] != $bean->sales_stage) {
                
                $bean->probability = $app_list_strings['sales_probability_dom'][$bean->sales_stage];
            }
        }
    }
}