export const testData = {
  lead: {
    first_name: 'John',
    last_name: 'Doe',
    email1: 'john.doe@example.com',
    phone_mobile: '555-1234',
    title: 'CEO',
    account_name: 'Acme Corp',
    lead_source: 'Web Site',
    status: 'New',
    industry: 'Technology',
    website: 'https://example.com'
  },
  
  account: {
    name: 'Test Account Inc',
    website: 'https://testaccount.com',
    phone_office: '555-5678',
    email1: 'info@testaccount.com',
    industry: 'Technology',
    annual_revenue: '5000000',
    employees: '100',
    billing_address_street: '123 Main St',
    billing_address_city: 'San Francisco',
    billing_address_state: 'CA',
    billing_address_postalcode: '94105',
    billing_address_country: 'USA'
  },
  
  opportunity: {
    name: 'Big Deal Q4',
    account_name: 'Test Account Inc',
    sales_stage: 'Qualification',
    amount: 50000,
    probability: 10,
    date_closed: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 90 days from now
    lead_source: 'Partner',
    description: 'Large enterprise deal for Q4'
  },
  
  contact: {
    first_name: 'Jane',
    last_name: 'Smith',
    email1: 'jane.smith@testaccount.com',
    phone_mobile: '555-9876',
    title: 'VP Sales',
    department: 'Sales'
  },
  
  call: {
    name: 'Follow-up Call',
    status: 'Planned',
    direction: 'Outbound',
    date_start: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(), // Tomorrow
    duration_hours: 0,
    duration_minutes: 30,
    description: 'Follow up on proposal'
  },
  
  meeting: {
    name: 'Product Demo',
    status: 'Planned',
    date_start: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString(), // 2 days from now
    duration_hours: 1,
    duration_minutes: 0,
    location: 'Zoom',
    description: 'Product demonstration for stakeholders'
  },
  
  task: {
    name: 'Send Proposal',
    status: 'Not Started',
    priority: 'High',
    date_due: new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 3 days from now
    description: 'Send detailed proposal with pricing'
  },
  
  note: {
    name: 'Meeting Notes',
    description: 'Client expressed interest in our enterprise features. Budget approval expected by end of quarter.'
  },
  
  case: {
    name: 'Login Issue',
    type: 'User',
    priority: 'P2',
    status: 'New',
    description: 'Customer unable to login to the portal'
  }
}

export const opportunityStages = [
  'Qualification',
  'Needs Analysis',
  'Value Proposition',
  'Decision Makers',
  'Proposal',
  'Negotiation',
  'Closed Won',
  'Closed Lost'
]

export const casePriorities = ['P1', 'P2', 'P3']
export const caseStatuses = ['New', 'Assigned', 'Pending Input', 'Resolved', 'Closed']