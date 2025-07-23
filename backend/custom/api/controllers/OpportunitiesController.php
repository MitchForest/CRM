<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class OpportunitiesController extends BaseController {
    
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $bean = \BeanFactory::newBean('Opportunities');
        
        // Get filters
        $queryParams = $request->getQueryParams();
        $filters = $queryParams['filters'] ?? [];
        $where = $this->buildWhereClause($filters);
        
        // Get sorting
        $sortField = $queryParams['sort'] ?? 'date_entered';
        $sortOrder = $queryParams['order'] ?? 'DESC';
        
        // Get pagination
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        
        // Build query
        $query = $bean->create_new_list_query(
            "$sortField $sortOrder",
            $where,
            [],
            [],
            0,
            '',
            true,
            $bean,
            true
        );
        
        // Get total count
        $countResult = $bean->db->query("SELECT COUNT(*) as total FROM ($query) as cnt");
        $total = $bean->db->fetchByAssoc($countResult)['total'];
        
        // Add limit and offset
        $query .= " LIMIT $limit OFFSET $offset";
        
        // Execute query
        $result = $bean->db->query($query);
        $opportunities = [];
        
        while ($row = $bean->db->fetchByAssoc($result)) {
            $opportunity = \BeanFactory::newBean('Opportunities');
            $opportunity->populateFromRow($row);
            $opportunities[] = $this->formatBean($opportunity);
        }
        
        return $response->json([
            'data' => $opportunities,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function get(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $opportunity = \BeanFactory::getBean('Opportunities', $id);
        
        if (empty($opportunity->id)) {
            return $this->notFoundResponse($response, 'Opportunity');
        }
        
        $data = $this->formatBean($opportunity);
        
        // Add related contact information
        $opportunity->load_relationship('contacts');
        $contacts = $opportunity->contacts->getBeans();
        $data['contacts'] = [];
        foreach ($contacts as $contact) {
            $data['contacts'][] = [
                'id' => $contact->id,
                'name' => $contact->first_name . ' ' . $contact->last_name,
                'email' => $contact->email1
            ];
        }
        
        return $response->json($data);
    }
    
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $opportunity = \BeanFactory::newBean('Opportunities');
        
        // Set fields
        $data = $request->getParsedBody();
        $fields = ['name', 'amount', 'sales_stage', 'probability', 'date_closed', 'description', 'opportunity_type', 'lead_source', 'next_step'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $opportunity->$field = $data[$field];
            }
        }
        
        // Validate required fields
        if (empty($opportunity->name)) {
            return $this->validationErrorResponse($response, 'Opportunity name is required', ['name' => 'Required field']);
        }
        
        // Set defaults
        if (empty($opportunity->sales_stage)) {
            $opportunity->sales_stage = 'Prospecting';
        }
        if (empty($opportunity->probability)) {
            $opportunity->probability = 10;
        }
        if (empty($opportunity->date_closed)) {
            $opportunity->date_closed = date('Y-m-d', strtotime('+30 days'));
        }
        
        // Save
        $opportunity->save();
        
        // Link to contact if provided
        if (!empty($data['contact_id'])) {
            $opportunity->load_relationship('contacts');
            $opportunity->contacts->add($data['contact_id']);
        }
        
        return $response->json($this->formatBean($opportunity), 201);
    }
    
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $opportunity = \BeanFactory::getBean('Opportunities', $id);
        
        if (empty($opportunity->id)) {
            return $this->notFoundResponse($response, 'Opportunity');
        }
        
        // Update fields
        $data = $request->getParsedBody();
        $fields = ['name', 'amount', 'sales_stage', 'probability', 'date_closed', 'description', 'opportunity_type', 'lead_source', 'next_step'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $opportunity->$field = $data[$field];
            }
        }
        
        // Save
        $opportunity->save();
        
        return $response->json($this->formatBean($opportunity));
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $opportunity = \BeanFactory::getBean('Opportunities', $id);
        
        if (empty($opportunity->id)) {
            return $this->notFoundResponse($response, 'Opportunity');
        }
        
        $opportunity->mark_deleted($id);
        
        return $response->json(['message' => 'Opportunity deleted successfully']);
    }
    
    public function analyze(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $opportunity = \BeanFactory::getBean('Opportunities', $id);
        
        if (empty($opportunity->id)) {
            return $this->notFoundResponse($response, 'Opportunity');
        }
        
        // Calculate win probability based on various factors
        $analysis = [
            'current_probability' => (int)$opportunity->probability,
            'days_in_pipeline' => $this->calculateDaysInPipeline($opportunity),
            'days_until_close' => $this->calculateDaysUntilClose($opportunity),
            'stage_duration' => $this->calculateStageDuration($opportunity),
            'recommended_actions' => $this->getRecommendedActions($opportunity)
        ];
        
        // Add AI insights here in the future
        $analysis['ai_insights'] = [
            'win_probability' => $this->calculateWinProbability($opportunity),
            'risk_factors' => $this->identifyRiskFactors($opportunity),
            'next_best_action' => $this->suggestNextBestAction($opportunity)
        ];
        
        return $response->json($analysis);
    }
    
    private function calculateDaysInPipeline($opportunity) {
        $created = strtotime($opportunity->date_entered);
        $now = time();
        return floor(($now - $created) / (60 * 60 * 24));
    }
    
    private function calculateDaysUntilClose($opportunity) {
        $close = strtotime($opportunity->date_closed);
        $now = time();
        $days = floor(($close - $now) / (60 * 60 * 24));
        return max(0, $days);
    }
    
    private function calculateStageDuration($opportunity) {
        // This would track how long in current stage
        // For now, return placeholder
        return 0;
    }
    
    private function calculateWinProbability($opportunity) {
        // Simple calculation based on stage and amount
        $probability = (int)$opportunity->probability;
        
        // Adjust based on days in pipeline
        $daysInPipeline = $this->calculateDaysInPipeline($opportunity);
        if ($daysInPipeline > 90) {
            $probability = $probability * 0.8; // Reduce by 20% if over 90 days
        }
        
        return min(100, max(0, round($probability)));
    }
    
    private function identifyRiskFactors($opportunity) {
        $risks = [];
        
        // Check if overdue
        if (strtotime($opportunity->date_closed) < time()) {
            $risks[] = 'Opportunity is past close date';
        }
        
        // Check if stalled
        $daysInPipeline = $this->calculateDaysInPipeline($opportunity);
        if ($daysInPipeline > 60 && $opportunity->sales_stage === 'Prospecting') {
            $risks[] = 'Stuck in early stage for too long';
        }
        
        // Check if no amount
        if (empty($opportunity->amount) || $opportunity->amount == 0) {
            $risks[] = 'No opportunity value specified';
        }
        
        return $risks;
    }
    
    private function getRecommendedActions($opportunity) {
        $actions = [];
        
        // Based on stage
        switch ($opportunity->sales_stage) {
            case 'Prospecting':
                $actions[] = 'Schedule initial meeting';
                $actions[] = 'Qualify opportunity requirements';
                break;
            case 'Qualification':
                $actions[] = 'Identify decision makers';
                $actions[] = 'Confirm budget availability';
                break;
            case 'Needs Analysis':
                $actions[] = 'Document specific requirements';
                $actions[] = 'Prepare solution proposal';
                break;
            case 'Value Proposition':
                $actions[] = 'Present proposal';
                $actions[] = 'Address objections';
                break;
            case 'Id. Decision Makers':
                $actions[] = 'Map decision process';
                $actions[] = 'Build relationships with stakeholders';
                break;
            case 'Perception Analysis':
                $actions[] = 'Gather competitive intelligence';
                $actions[] = 'Strengthen value proposition';
                break;
            case 'Proposal/Price Quote':
                $actions[] = 'Send formal proposal';
                $actions[] = 'Schedule follow-up meeting';
                break;
            case 'Negotiation/Review':
                $actions[] = 'Address final concerns';
                $actions[] = 'Prepare contract';
                break;
        }
        
        return $actions;
    }
    
    private function suggestNextBestAction($opportunity) {
        // Simple logic for now
        if (empty($opportunity->next_step)) {
            return 'Update next step field with planned action';
        }
        
        $daysUntilClose = $this->calculateDaysUntilClose($opportunity);
        if ($daysUntilClose < 7) {
            return 'Follow up immediately - close date approaching';
        }
        
        return $opportunity->next_step;
    }
}