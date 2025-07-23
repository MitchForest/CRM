<?php
// AI Score fields for Leads module
$dictionary['Lead']['fields']['ai_score'] = array(
    'name' => 'ai_score',
    'vname' => 'LBL_AI_SCORE',
    'type' => 'int',
    'len' => 3,
    'comment' => 'AI-generated lead score (0-100)',
    'default' => 0,
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'importable' => true,
    'duplicate_merge' => 'enabled',
    'unified_search' => false,
    'merge_filter' => 'disabled',
    'calculated' => false,
    'min' => 0,
    'max' => 100,
);

$dictionary['Lead']['fields']['ai_score_date'] = array(
    'name' => 'ai_score_date',
    'vname' => 'LBL_AI_SCORE_DATE',
    'type' => 'datetime',
    'comment' => 'Date when AI score was last calculated',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => true,
    'duplicate_merge' => 'enabled',
);

$dictionary['Lead']['fields']['ai_insights'] = array(
    'name' => 'ai_insights',
    'vname' => 'LBL_AI_INSIGHTS',
    'type' => 'text',
    'comment' => 'AI-generated insights about the lead',
    'required' => false,
    'reportable' => false,
    'audited' => true,
    'importable' => false,
    'duplicate_merge' => 'disabled',
);