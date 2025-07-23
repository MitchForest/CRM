<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;

class LeadsController extends BaseController {
    
    public function list(Request $request) {
        $bean = \BeanFactory::newBean('Leads');
        
        $filters = $request->get('filters', []);
        $where = $this->buildWhereClause($filters);
        
        list($limit, $offset) = $this->getPaginationParams($request);
        
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
        
        return Response::success([
            'data' => $leads,
            'pagination' => [
                'page' => (int)$request->get('page', 1),
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    public function get(Request $request) {
        $id = $request->getParam('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return Response::notFound('Lead not found');
        }
        
        return Response::success($this->formatBean($lead));
    }
    
    public function create(Request $request) {
        $lead = \BeanFactory::newBean('Leads');
        
        // Set fields
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'status', 'lead_source', 'description'];
        foreach ($fields as $field) {
            if ($request->get($field)) {
                $lead->$field = $request->get($field);
            }
        }
        
        // Validate required fields
        if (empty($lead->last_name)) {
            return Response::error('Last name is required', 400);
        }
        
        // Set default status if not provided
        if (empty($lead->status)) {
            $lead->status = 'New';
        }
        
        // Save
        $lead->save();
        
        return Response::created($this->formatBean($lead));
    }
    
    public function update(Request $request) {
        $id = $request->getParam('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return Response::notFound('Lead not found');
        }
        
        // Update fields
        $fields = ['first_name', 'last_name', 'email1', 'phone_mobile', 'status', 'lead_source', 'description'];
        foreach ($fields as $field) {
            if ($request->get($field) !== null) {
                $lead->$field = $request->get($field);
            }
        }
        
        // Save
        $lead->save();
        
        return Response::success($this->formatBean($lead));
    }
    
    public function delete(Request $request) {
        $id = $request->getParam('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return Response::notFound('Lead not found');
        }
        
        $lead->mark_deleted($id);
        
        return Response::success(['message' => 'Lead deleted successfully']);
    }
    
    public function convert(Request $request) {
        $id = $request->getParam('id');
        $lead = \BeanFactory::getBean('Leads', $id);
        
        if (empty($lead->id)) {
            return Response::notFound('Lead not found');
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
        $opportunity = null;
        if ($request->get('create_opportunity')) {
            $opportunity = \BeanFactory::newBean('Opportunities');
            $opportunity->name = $request->get('opportunity_name', $contact->first_name . ' ' . $contact->last_name . ' - Opportunity');
            $opportunity->amount = $request->get('opportunity_amount', 0);
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
        
        return Response::success([
            'contact' => $this->formatBean($contact),
            'opportunity' => $opportunity ? $this->formatBean($opportunity) : null
        ]);
    }
}