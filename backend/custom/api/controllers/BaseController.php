<?php
namespace Api\Controllers;

use Api\Response;
use Api\DTO\Base\ErrorDTO;

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
                       'status', 'lead_source', 'date_entered', 'ai_score', 'ai_score_date', 'ai_insights'],
            'Opportunities' => ['id', 'name', 'amount', 'sales_stage', 'probability', 
                               'date_closed', 'description', 'opportunity_type', 'lead_source', 'next_step'],
            'Tasks' => ['id', 'name', 'status', 'priority', 'date_due', 'description', 
                       'contact_id', 'parent_type', 'parent_id', 'date_start'],
            'Emails' => ['id', 'name', 'date_sent', 'status', 'type', 'parent_type', 
                        'parent_id', 'from_addr', 'to_addrs'],
            'Cases' => ['id', 'name', 'case_number', 'status', 'priority', 'type', 
                       'state', 'description', 'resolution', 'date_entered', 'date_modified'],
            'Calls' => ['id', 'name', 'status', 'date_start', 'duration_hours', 'duration_minutes',
                       'parent_type', 'parent_id', 'description'],
            'Meetings' => ['id', 'name', 'status', 'date_start', 'duration_hours', 'duration_minutes',
                          'parent_type', 'parent_id', 'location', 'description'],
            'Notes' => ['id', 'name', 'description', 'date_entered', 'parent_type', 'parent_id']
        ];
        
        return $defaultFields[$module] ?? ['id', 'name', 'date_entered', 'date_modified'];
    }
    
    protected function buildWhereClause($filters) {
        global $db;
        $where = [];
        
        // Whitelist of allowed fields to prevent injection via field names
        $allowedFields = $this->getAllowedFilterFields();
        
        foreach ($filters as $field => $value) {
            // Validate field name against whitelist
            if (!in_array($field, $allowedFields)) {
                continue; // Skip unauthorized fields
            }
            
            // Escape field name for safety
            $safeField = $db->quote($field);
            $safeField = trim($safeField, "'"); // Remove quotes as field names don't need them
            
            if (is_array($value)) {
                // Handle operators
                foreach ($value as $op => $val) {
                    switch ($op) {
                        case 'like':
                            $safeValue = $db->quote('%' . $val . '%');
                            $where[] = "$safeField LIKE $safeValue";
                            break;
                        case 'gt':
                            $safeValue = $db->quote($val);
                            $where[] = "$safeField > $safeValue";
                            break;
                        case 'lt':
                            $safeValue = $db->quote($val);
                            $where[] = "$safeField < $safeValue";
                            break;
                        case 'gte':
                            $safeValue = $db->quote($val);
                            $where[] = "$safeField >= $safeValue";
                            break;
                        case 'lte':
                            $safeValue = $db->quote($val);
                            $where[] = "$safeField <= $safeValue";
                            break;
                        case 'ne':
                            $safeValue = $db->quote($val);
                            $where[] = "$safeField != $safeValue";
                            break;
                        case 'in':
                            if (is_array($val)) {
                                $inValues = array_map(function($v) use ($db) { 
                                    return $db->quote($v); 
                                }, $val);
                                $where[] = "$safeField IN (" . implode(',', $inValues) . ")";
                            }
                            break;
                        case 'between':
                            if (is_array($val) && count($val) == 2) {
                                $min = $db->quote($val[0]);
                                $max = $db->quote($val[1]);
                                $where[] = "$safeField BETWEEN $min AND $max";
                            }
                            break;
                        default:
                            // Default to equals for unknown operators
                            $safeValue = $db->quote($val);
                            $where[] = "$safeField = $safeValue";
                    }
                }
            } else {
                $safeValue = $db->quote($value);
                $where[] = "$safeField = $safeValue";
            }
        }
        
        return implode(' AND ', $where);
    }
    
    /**
     * Get whitelist of allowed filter fields for security
     * Override in child controllers to customize
     */
    protected function getAllowedFilterFields() {
        // Common fields allowed across all modules
        return [
            'id', 'name', 'status', 'date_entered', 'date_modified',
            'created_by', 'modified_user_id', 'assigned_user_id',
            'deleted', 'first_name', 'last_name', 'email1', 
            'phone_mobile', 'description', 'priority', 'type',
            'parent_type', 'parent_id', 'contact_id', 'lead_source',
            'sales_stage', 'amount', 'probability', 'date_due',
            'date_start', 'date_closed', 'case_number'
        ];
    }
    
    protected function getPaginationParams($request) {
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 20);
        $limit = min($limit, 100); // Max 100 records
        
        $offset = ($page - 1) * $limit;
        
        return [$limit, $offset];
    }
    
    /**
     * Return a standardized error response using ErrorDTO
     */
    protected function errorResponse(Response $response, string $message, string $code, int $statusCode = 400, ?array $details = null, ?array $validation = null): Response
    {
        $errorDto = new ErrorDTO();
        $errorDto->setError($message)
                 ->setCode($code)
                 ->setStatusCode($statusCode);
        
        if ($details !== null) {
            $errorDto->setDetails($details);
        }
        
        if ($validation !== null) {
            $errorDto->setValidation($validation);
        }
        
        return $response->json($errorDto->toArray(), $statusCode);
    }
    
    /**
     * Common error response methods
     */
    protected function notFoundResponse(Response $response, string $resource = 'Resource'): Response
    {
        return $this->errorResponse(
            $response,
            "$resource not found",
            ErrorDTO::CODE_NOT_FOUND,
            404
        );
    }
    
    protected function validationErrorResponse(Response $response, string $message, array $validation = []): Response
    {
        return $this->errorResponse(
            $response,
            $message,
            ErrorDTO::CODE_VALIDATION_FAILED,
            400,
            null,
            $validation
        );
    }
    
    protected function unauthorizedResponse(Response $response, string $message = 'Unauthorized'): Response
    {
        return $this->errorResponse(
            $response,
            $message,
            ErrorDTO::CODE_UNAUTHORIZED,
            401
        );
    }
    
    protected function forbiddenResponse(Response $response, string $message = 'Access denied'): Response
    {
        return $this->errorResponse(
            $response,
            $message,
            ErrorDTO::CODE_FORBIDDEN,
            403
        );
    }
    
    protected function serverErrorResponse(Response $response, string $message = 'Internal server error', ?array $details = null): Response
    {
        return $this->errorResponse(
            $response,
            $message,
            ErrorDTO::CODE_INTERNAL_ERROR,
            500,
            $details
        );
    }
    
    /**
     * Sanitize order by clause to prevent SQL injection
     */
    protected function sanitizeOrderBy($orderBy)
    {
        // Define allowed fields for ordering based on common CRM fields
        $allowedFields = [
            'date_start', 'date_end', 'date_entered', 'date_modified',
            'name', 'status', 'priority', 'type', 'amount',
            'sales_stage', 'probability', 'date_closed',
            'first_name', 'last_name', 'email1', 'phone_mobile',
            'case_number', 'resolution', 'date_due', 'date_sent'
        ];
        
        $parts = explode(' ', trim($orderBy));
        
        if (count($parts) >= 1 && in_array($parts[0], $allowedFields)) {
            $field = $parts[0];
            $direction = (count($parts) > 1 && strtoupper($parts[1]) === 'ASC') ? 'ASC' : 'DESC';
            return "$field $direction";
        }
        
        // Default fallback
        return 'date_entered DESC';
    }
}