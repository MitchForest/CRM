<?php
// Health Score and MRR fields for Accounts module
$dictionary['Account']['fields']['health_score'] = array(
    'name' => 'health_score',
    'vname' => 'LBL_HEALTH_SCORE',
    'type' => 'int',
    'len' => 3,
    'comment' => 'Customer health score (0-100)',
    'default' => 100,
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

$dictionary['Account']['fields']['mrr'] = array(
    'name' => 'mrr',
    'vname' => 'LBL_MRR',
    'type' => 'currency',
    'comment' => 'Monthly Recurring Revenue',
    'required' => false,
    'reportable' => true,
    'audited' => true,
    'duplicate_merge' => 'enabled',
    'importable' => true,
);

$dictionary['Account']['fields']['last_activity'] = array(
    'name' => 'last_activity',
    'vname' => 'LBL_LAST_ACTIVITY',
    'type' => 'datetime',
    'comment' => 'Date of last customer activity',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => true,
    'duplicate_merge' => 'enabled',
);