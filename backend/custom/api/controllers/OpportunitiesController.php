<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class OpportunitiesController extends BaseController {
    
    public function index(Request $request, Response $response) {
        try {
            // Get query parameters
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $search = $_GET['search'] ?? '';
            $stage = $_GET['stage'] ?? '';
            $assignedUserId = $_GET['assigned_user_id'] ?? '';
            $sortBy = $_GET['sort_by'] ?? 'date_entered';
            $sortOrder = strtoupper($_GET['sort_order'] ?? 'DESC');
            
            // Build query
            $bean = \BeanFactory::newBean('Opportunities');
            $query = new \SugarQuery();
            $query->from($bean);
            $query->select(['*']);
            
            // Add filters
            $query->where()->equals('deleted', 0);
            
            if (!empty($search)) {
                $query->where()->contains('name', $search);
            }
            
            if (!empty($stage)) {
                $query->where()->equals('sales_stage', $stage);
            }
            
            if (!empty($assignedUserId)) {
                $query->where()->equals('assigned_user_id', $assignedUserId);
            }
            
            // Add sorting
            $query->orderBy($sortBy, $sortOrder);
            
            // Get total count
            $countQuery = clone $query;
            $totalCount = $countQuery->getCountQuery()->execute();
            
            // Add pagination
            $offset = ($page - 1) * $limit;
            $query->limit($limit)->offset($offset);
            
            // Execute query
            $results = $query->execute();
            
            // Format opportunities
            $opportunities = [];
            foreach ($results as $row) {
                $opp = \BeanFactory::getBean('Opportunities', $row['id']);
                
                $opportunities[] = [
                    'id' => $opp->id,
                    'name' => $opp->name,
                    'amount' => (float)$opp->amount,
                    'currency' => $opp->currency_id ?? 'USD',
                    'salesStage' => $opp->sales_stage,
                    'probability' => (int)$opp->probability,
                    'closeDate' => $opp->date_closed,
                    'opportunityType' => $opp->opportunity_type,
                    'leadSource' => $opp->lead_source,
                    'nextStep' => $opp->next_step,
                    'description' => $opp->description,
                    'accountId' => $opp->account_id,
                    'accountName' => $opp->account_name,
                    'assignedUserId' => $opp->assigned_user_id,
                    'assignedUserName' => $opp->assigned_user_name,
                    'dateEntered' => $opp->date_entered,
                    'dateModified' => $opp->date_modified,
                    'createdBy' => $opp->created_by,
                    'modifiedUserId' => $opp->modified_user_id
                ];
            }
            
            return $response->json([
                'data' => $opportunities,
                'pagination' => [
                    'page' => $page,
                    'pageSize' => $limit,
                    'totalPages' => ceil($totalCount / $limit),
                    'totalCount' => (int)$totalCount,
                    'hasNext' => $page < ceil($totalCount / $limit),
                    'hasPrevious' => $page > 1
                ]
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'success' => false,
                'error' => 'Failed to fetch opportunities: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show(Request $request, Response $response, $id) {
        try {
            $opp = \BeanFactory::getBean('Opportunities', $id);
            
            if (empty($opp->id)) {
                return $response->json([
                    'success' => false,
                    'error' => 'Opportunity not found'
                ], 404);
            }
            
            // Load relationships
            $opp->load_relationship('contacts');
            $contactIds = $opp->contacts->get();
            $contacts = [];
            
            foreach ($contactIds as $contactId) {
                $contact = \BeanFactory::getBean('Contacts', $contactId);
                $contacts[] = [
                    'id' => $contact->id,
                    'name' => $contact->full_name,
                    'email' => $contact->email1,
                    'phone' => $contact->phone_work
                ];
            }
            
            return $response->json([
                'data' => [
                    'id' => $opp->id,
                    'name' => $opp->name,
                    'amount' => (float)$opp->amount,
                    'currency' => $opp->currency_id ?? 'USD',
                    'salesStage' => $opp->sales_stage,
                    'probability' => (int)$opp->probability,
                    'closeDate' => $opp->date_closed,
                    'opportunityType' => $opp->opportunity_type,
                    'leadSource' => $opp->lead_source,
                    'nextStep' => $opp->next_step,
                    'description' => $opp->description,
                    'accountId' => $opp->account_id,
                    'accountName' => $opp->account_name,
                    'assignedUserId' => $opp->assigned_user_id,
                    'assignedUserName' => $opp->assigned_user_name,
                    'dateEntered' => $opp->date_entered,
                    'dateModified' => $opp->date_modified,
                    'createdBy' => $opp->created_by,
                    'modifiedUserId' => $opp->modified_user_id,
                    'contacts' => $contacts
                ]
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'success' => false,
                'error' => 'Failed to fetch opportunity: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function create(Request $request, Response $response) {
        try {
            $data = $this->getRequestData();
            
            // Validate required fields
            if (empty($data['name'])) {
                return $response->json([
                    'success' => false,
                    'error' => 'Name is required'
                ], 400);
            }
            
            if (empty($data['closeDate'])) {
                return $response->json([
                    'success' => false,
                    'error' => 'Close date is required'
                ], 400);
            }
            
            $opp = \BeanFactory::newBean('Opportunities');
            
            // Set fields
            $opp->name = $data['name'];
            $opp->amount = $data['amount'] ?? 0;
            $opp->currency_id = $data['currency'] ?? 'USD';
            $opp->sales_stage = $data['salesStage'] ?? 'Qualified';
            $opp->probability = $data['probability'] ?? 10;
            $opp->date_closed = $data['closeDate'];
            $opp->opportunity_type = $data['opportunityType'] ?? 'New Business';
            $opp->lead_source = $data['leadSource'] ?? '';
            $opp->next_step = $data['nextStep'] ?? '';
            $opp->description = $data['description'] ?? '';
            $opp->account_id = $data['accountId'] ?? '';
            $opp->account_name = $data['accountName'] ?? '';
            $opp->assigned_user_id = $data['assignedUserId'] ?? $this->getCurrentUserId();
            
            $opp->save();
            
            // Add contacts if provided
            if (!empty($data['contactIds'])) {
                $opp->load_relationship('contacts');
                foreach ($data['contactIds'] as $contactId) {
                    $opp->contacts->add($contactId);
                }
            }
            
            return $response->json([
                'data' => [
                    'id' => $opp->id
                ],
                'message' => 'Opportunity created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return $response->json([
                'success' => false,
                'error' => 'Failed to create opportunity: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request, Response $response, $id) {
        try {
            $data = $this->getRequestData();
            
            $opp = \BeanFactory::getBean('Opportunities', $id);
            
            if (empty($opp->id)) {
                return $response->json([
                    'success' => false,
                    'error' => 'Opportunity not found'
                ], 404);
            }
            
            // Update fields
            if (isset($data['name'])) $opp->name = $data['name'];
            if (isset($data['amount'])) $opp->amount = $data['amount'];
            if (isset($data['currency'])) $opp->currency_id = $data['currency'];
            if (isset($data['salesStage'])) $opp->sales_stage = $data['salesStage'];
            if (isset($data['probability'])) $opp->probability = $data['probability'];
            if (isset($data['closeDate'])) $opp->date_closed = $data['closeDate'];
            if (isset($data['opportunityType'])) $opp->opportunity_type = $data['opportunityType'];
            if (isset($data['leadSource'])) $opp->lead_source = $data['leadSource'];
            if (isset($data['nextStep'])) $opp->next_step = $data['nextStep'];
            if (isset($data['description'])) $opp->description = $data['description'];
            if (isset($data['accountId'])) $opp->account_id = $data['accountId'];
            if (isset($data['accountName'])) $opp->account_name = $data['accountName'];
            if (isset($data['assignedUserId'])) $opp->assigned_user_id = $data['assignedUserId'];
            
            $opp->save();
            
            // Update contacts if provided
            if (isset($data['contactIds'])) {
                $opp->load_relationship('contacts');
                // Remove all existing contacts
                $opp->contacts->removeAll();
                // Add new contacts
                foreach ($data['contactIds'] as $contactId) {
                    $opp->contacts->add($contactId);
                }
            }
            
            return $response->json([
                'data' => [
                    'id' => $opp->id
                ],
                'message' => 'Opportunity updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'success' => false,
                'error' => 'Failed to update opportunity: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function delete(Request $request, Response $response, $id) {
        try {
            $opp = \BeanFactory::getBean('Opportunities', $id);
            
            if (empty($opp->id)) {
                return $response->json([
                    'success' => false,
                    'error' => 'Opportunity not found'
                ], 404);
            }
            
            $opp->mark_deleted($id);
            
            return $response->json([
                'message' => 'Opportunity deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $response->json([
                'success' => false,
                'error' => 'Failed to delete opportunity: ' . $e->getMessage()
            ], 500);
        }
    }
}