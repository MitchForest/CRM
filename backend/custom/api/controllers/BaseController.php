<?php
namespace Api\Controllers;

use Api\Response;

abstract class BaseController {
    
    protected function formatBean($bean, $fields = []) {
        $data = [];
        
        if (empty($fields)) {
            // Default fields based on module
            $fields = $this->getDefaultFields($bean->module_name);
        }
        
        foreach ($fields as $field) {
            if (isset($bean->field_defs[$field])) {
                $data[$field] = $bean->$field;
            }
        }
        
        return $data;
    }
    
    protected function getDefaultFields($module) {
        $defaultFields = [
            'Contacts' => ['id', 'first_name', 'last_name', 'email1', 'phone_mobile', 
                          'date_entered', 'date_modified', 'description'],
            'Leads' => ['id', 'first_name', 'last_name', 'email1', 'phone_mobile', 
                       'status', 'lead_source', 'date_entered'],
            'Opportunities' => ['id', 'name', 'amount', 'sales_stage', 'probability', 
                               'date_closed', 'description'],
            'Tasks' => ['id', 'name', 'status', 'priority', 'date_due', 'description', 
                       'contact_id', 'parent_type', 'parent_id'],
            'Emails' => ['id', 'name', 'date_sent', 'status', 'type', 'parent_type', 
                        'parent_id', 'from_addr', 'to_addrs']
        ];
        
        return $defaultFields[$module] ?? ['id', 'name', 'date_entered', 'date_modified'];
    }
    
    protected function buildWhereClause($filters) {
        $where = [];
        
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                // Handle operators
                foreach ($value as $op => $val) {
                    switch ($op) {
                        case 'like':
                            $where[] = "$field LIKE '%$val%'";
                            break;
                        case 'gt':
                            $where[] = "$field > '$val'";
                            break;
                        case 'lt':
                            $where[] = "$field < '$val'";
                            break;
                        case 'in':
                            $inValues = array_map(function($v) { return "'$v'"; }, $val);
                            $where[] = "$field IN (" . implode(',', $inValues) . ")";
                            break;
                        default:
                            $where[] = "$field = '$val'";
                    }
                }
            } else {
                $where[] = "$field = '$value'";
            }
        }
        
        return implode(' AND ', $where);
    }
    
    protected function getPaginationParams($request) {
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);
        $limit = min($limit, 100); // Max 100 records
        
        $offset = ($page - 1) * $limit;
        
        return [$limit, $offset];
    }
}