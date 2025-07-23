<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LeadsController extends BaseController {
    
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $bean = \BeanFactory::newBean('Leads');
        
        $queryParams = $request->getQueryParams();
        $filters = $queryParams['filters'] ?? [];
        $where = $this->buildWhereClause($filters);
        
        $page = (int)($queryParams['page'] ?? 1);
        $limit = min((int)($queryParams['limit'] ?? 20), 100);
        $offset = ($page - 1) * $limit;
        
        $query = $bean->create_new_list_query(
            'date_entered DESC',
            $where
        );
        
        $countResult = $bean->db->query("SELECT COUNT(*) as total FROM ($query) as cnt");
        $total = $bean->db->fetchByAssoc($countResult)['total'];
        
        $query .= " LIMIT $limit OFFSET $offset";
        $result = $bean->db->query($query);
        
        $leads = [];
        while ($row = $bean->db->fetchByAssoc($result)) {
            $lead = \BeanFactory::newBean('Leads');
            $lead->populateFromRow($row);
            $leads[] = $this->formatBean($lead);
        }
        
        return $response->json([
            'data' => $leads,
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
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return $this->notFoundResponse($response, 'Lead');
        }
        
        return $response->json($this->formatBean($lead));
    }
    
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $lead = \BeanFactory::newBean('Leads');
        
        // Set fields
        $data = $request->getParsedBody();
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'status', 'lead_source', 'description'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $lead->$field = $data[$field];
            }
        }
        
        // Validate required fields
        if (empty($lead->last_name)) {
            return $this->validationErrorResponse($response, 'Last name is required', ['last_name' => 'Required field']);
        }
        
        // Set default status if not provided
        if (empty($lead->status)) {
            $lead->status = 'New';
        }
        
        // Save
        $lead->save();
        
        return $response->json($this->formatBean($lead), 201);
    }
    
    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return $this->notFoundResponse($response, 'Lead');
        }
        
        // Update fields
        $data = $request->getParsedBody();
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'status', 'lead_source', 'description'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $lead->$field = $data[$field];
            }
        }
        
        // Save
        $lead->save();
        
        return $response->json($this->formatBean($lead));
    }
    
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return $this->notFoundResponse($response, 'Lead');
        }
        
        $lead->mark_deleted($id);
        
        return $response->json(['message' => 'Lead deleted successfully']);
    }
    
    public function convert(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $id = $request->getAttribute('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return $this->notFoundResponse($response, 'Lead');
        }
        
        // Create contact from lead
        $contact = \BeanFactory::newBean('Contacts');
        $contact->first_name = $lead->first_name;
        $contact->last_name = $lead->last_name;
        $contact->email1 = $lead->email1;
        $contact->phone_mobile = $lead->phone_mobile;
        $contact->description = $lead->description;
        $contact->lead_source = $lead->lead_source;
        $contact->save();
        
        // Create opportunity if requested
        $data = $request->getParsedBody();
        $opportunity = null;
        if (!empty($data['create_opportunity'])) {
            $opportunity = \BeanFactory::newBean('Opportunities');
            $opportunity->name = $data['opportunity_name'] ?? $contact->first_name . ' ' . $contact->last_name . ' - Opportunity';
            $opportunity->amount = $data['opportunity_amount'] ?? 0;
            $opportunity->sales_stage = 'Prospecting';
            $opportunity->probability = 10;
            $opportunity->date_closed = date('Y-m-d', strtotime('+30 days'));
            $opportunity->save();
            
            // Relate to contact
            $opportunity->load_relationship('contacts');
            $opportunity->contacts->add($contact->id);
        }
        
        // Mark lead as converted
        $lead->status = 'Converted';
        $lead->contact_id = $contact->id;
        $lead->converted = 1;
        $lead->save();
        
        return $response->json([
            'contact' => $this->formatBean($contact),
            'opportunity' => $opportunity ? $this->formatBean($opportunity) : null
        ]);
    }
}