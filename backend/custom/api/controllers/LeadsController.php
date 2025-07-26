<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Api\DTO\Base\PaginationDTO;
use Api\DTO\Base\ErrorDTO;

class LeadsController extends BaseController
{
    protected $module = 'Leads';
    
    /**
     * Get list of leads with pagination and filtering
     */
    public function index(Request $request, Response $response)
    {
        global $db;
        
        try {
            // Ensure SugarEmailAddress is loaded
            require_once('include/SugarEmailAddress/SugarEmailAddress.php');
            
            $bean = \BeanFactory::newBean($this->module);
            
            // Get pagination parameters
            list($limit, $offset) = $this->getPaginationParams($request);
            
            // Build query
            $query = "SELECT * FROM leads WHERE deleted = 0";
            
            // Add filters if provided
            $filters = $request->get('filter', []);
            if (!empty($filters)) {
                $whereClause = $this->buildWhereClause($filters);
                if ($whereClause) {
                    $query .= " AND " . $whereClause;
                }
            }
            
            // Add sorting
            $orderBy = $request->get('orderBy', 'date_entered DESC');
            $query .= " ORDER BY " . $this->sanitizeOrderBy($orderBy);
            
            // Get total count
            $countQuery = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
            $countResult = $db->query($countQuery);
            $totalRow = $db->fetchByAssoc($countResult);
            $total = $totalRow['total'] ?? 0;
            
            // Add pagination
            $query .= " LIMIT $limit OFFSET $offset";
            
            // Execute query
            $result = $db->query($query);
            $leads = [];
            
            while ($row = $db->fetchByAssoc($result)) {
                $lead = \BeanFactory::newBean($this->module);
                $lead->populateFromRow($row);
                
                // Include all fields with AI custom fields
                $leadData = $this->formatLead($lead);
                $leads[] = $leadData;
            }
            
            // Create pagination DTO
            $pagination = new PaginationDTO();
            $pagination->setPage((int)$request->get('page', 1))
                      ->setLimit($limit)
                      ->setTotal($total);
            
            return $response->json([
                'data' => $leads,
                'pagination' => $pagination->toArray()
            ]);
            
        } catch (\Exception $e) {
            return $this->serverErrorResponse($response, 'Failed to retrieve leads', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get single lead by ID
     */
    public function show(Request $request, Response $response, $id)
    {
        try {
            $lead = \BeanFactory::getBean($this->module, $id);
            
            if (empty($lead->id)) {
                return $this->notFoundResponse($response, 'Lead');
            }
            
            $data = $this->formatLead($lead);
            
            return $response->json([
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return $this->serverErrorResponse($response, 'Failed to retrieve lead', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Create new lead
     */
    public function create(Request $request, Response $response)
    {
        try {
            $data = $request->getParsedBody();
            
            // Validate required fields
            $validation = $this->validateLeadData($data);
            if (!empty($validation)) {
                return $this->validationErrorResponse($response, 'Validation failed', $validation);
            }
            
            $lead = \BeanFactory::newBean($this->module);
            
            // Map fields
            $this->mapFieldsToBean($lead, $data);
            
            // Handle AI fields specifically
            if (isset($data['ai_score'])) {
                $lead->ai_score = (int)$data['ai_score'];
                $lead->ai_score_date = date('Y-m-d H:i:s');
            }
            
            if (isset($data['ai_insights'])) {
                $lead->ai_insights = $data['ai_insights'];
            }
            
            // Save the lead
            $lead->save();
            
            // Return created lead
            $responseData = $this->formatLead($lead);
            
            return $response->json([
                'data' => $responseData,
                'message' => 'Lead created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return $this->serverErrorResponse($response, 'Failed to create lead', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Update lead
     */
    public function update(Request $request, Response $response, $id)
    {
        try {
            $lead = \BeanFactory::getBean($this->module, $id);
            
            if (empty($lead->id)) {
                return $this->notFoundResponse($response, 'Lead');
            }
            
            $data = $request->getParsedBody();
            
            // Map fields
            $this->mapFieldsToBean($lead, $data);
            
            // Handle AI fields specifically
            if (isset($data['ai_score'])) {
                $lead->ai_score = (int)$data['ai_score'];
                $lead->ai_score_date = date('Y-m-d H:i:s');
            }
            
            if (isset($data['ai_insights'])) {
                $lead->ai_insights = $data['ai_insights'];
            }
            
            // Save the lead
            $lead->save();
            
            // Return updated lead
            $responseData = $this->formatLead($lead);
            
            return $response->json([
                'data' => $responseData,
                'message' => 'Lead updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->serverErrorResponse($response, 'Failed to update lead', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Partial update (PATCH) lead
     */
    public function patch(Request $request, Response $response, $id)
    {
        // PATCH uses same logic as update but only updates provided fields
        return $this->update($request, $response, $id);
    }
    
    /**
     * Delete lead
     */
    public function delete(Request $request, Response $response, $id)
    {
        try {
            $lead = \BeanFactory::getBean($this->module, $id);
            
            if (empty($lead->id)) {
                return $this->notFoundResponse($response, 'Lead');
            }
            
            // Mark as deleted
            $lead->mark_deleted($id);
            
            return $response->json([
                'message' => 'Lead deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->serverErrorResponse($response, 'Failed to delete lead', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Format lead data for response
     */
    protected function formatLead($lead)
    {
        $fields = [
            'id', 'first_name', 'last_name', 'salutation', 'title',
            'email1', 'email2', 'phone_mobile', 'phone_work', 'phone_home', 'phone_other',
            'primary_address_street', 'primary_address_city', 'primary_address_state',
            'primary_address_postalcode', 'primary_address_country',
            'status', 'status_description', 'lead_source', 'lead_source_description',
            'department', 'do_not_call', 'description',
            'assigned_user_id', 'assigned_user_name',
            'date_entered', 'date_modified', 'modified_user_id', 'created_by',
            // AI custom fields
            'ai_score', 'ai_score_date', 'ai_insights',
            // Conversion fields
            'converted', 'converted_contact_id', 'converted_account_id', 'converted_opportunity_id'
        ];
        
        $data = [];
        foreach ($fields as $field) {
            if (isset($lead->field_defs[$field])) {
                $value = $lead->$field;
                
                // Format datetime fields
                if (in_array($field, ['date_entered', 'date_modified', 'ai_score_date']) && !empty($value)) {
                    $data[$field] = date('c', strtotime($value));
                } else {
                    $data[$field] = $value;
                }
            }
        }
        
        // Ensure AI fields are always present
        if (!isset($data['ai_score'])) {
            $data['ai_score'] = null;
        }
        if (!isset($data['ai_score_date'])) {
            $data['ai_score_date'] = null;
        }
        if (!isset($data['ai_insights'])) {
            $data['ai_insights'] = null;
        }
        
        return $data;
    }
    
    /**
     * Map request data to bean fields
     */
    protected function mapFieldsToBean($bean, $data)
    {
        $mappableFields = [
            'first_name', 'last_name', 'salutation', 'title',
            'email1', 'email2', 'phone_mobile', 'phone_work', 'phone_home', 'phone_other',
            'primary_address_street', 'primary_address_city', 'primary_address_state',
            'primary_address_postalcode', 'primary_address_country',
            'status', 'status_description', 'lead_source', 'lead_source_description',
            'department', 'do_not_call', 'description',
            'assigned_user_id'
        ];
        
        foreach ($mappableFields as $field) {
            if (isset($data[$field])) {
                $bean->$field = $data[$field];
            }
        }
    }
    
    /**
     * Validate lead data
     */
    protected function validateLeadData($data)
    {
        $errors = [];
        
        // Required fields
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        }
        
        // Email validation
        if (!empty($data['email1']) && !filter_var($data['email1'], FILTER_VALIDATE_EMAIL)) {
            $errors['email1'] = 'Invalid email format';
        }
        
        if (!empty($data['email2']) && !filter_var($data['email2'], FILTER_VALIDATE_EMAIL)) {
            $errors['email2'] = 'Invalid email format';
        }
        
        // AI score validation
        if (isset($data['ai_score'])) {
            $score = (int)$data['ai_score'];
            if ($score < 0 || $score > 100) {
                $errors['ai_score'] = 'AI score must be between 0 and 100';
            }
        }
        
        return $errors;
    }
    
    /**
     * Override allowed filter fields to include AI fields
     */
    protected function getAllowedFilterFields()
    {
        $baseFields = parent::getAllowedFilterFields();
        return array_merge($baseFields, [
            'ai_score', 'ai_score_date', 'ai_insights',
            'converted', 'converted_contact_id', 'converted_account_id', 'converted_opportunity_id'
        ]);
    }
}