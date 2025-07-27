// Field mapping helper - shows common field naming patterns

export const FIELD_MAPPING = {
  "critical_mappings": {
    "leads": {
      "email": "Use email1, not email",
      "company": "Use account_name, not company",
      "phone": "Use phone_work or phone_mobile, not generic phone"
    },
    "contacts": {
      "email": "Use email1, not email",
      "phone": "Use phone_work or phone_mobile"
    },
    "accounts": {
      "company_name": "Use name field",
      "phone": "Use phone_office, not phone_work"
    },
    "users": {
      "email": "Just email, not email1 (different from other modules)"
    }
  },
  "address_patterns": {
    "leads_contacts": "primary_address_*",
    "accounts": "billing_address_*",
    "users": "address_* (no prefix)"
  },
  "common_mistakes": {
    "email_vs_email1": "Most modules use email1, except users table",
    "company_vs_account_name": "Leads use account_name for company",
    "phone_fields": "Always use specific fields like phone_work, phone_mobile",
    "soft_deletes": "Use deleted field (0/1), never actually DELETE records"
  }
} as const;

// Helper to get user-friendly label for a field
export function getFieldLabel(table: string, field: string): string {
  const labels: Record<string, Record<string, string>> = {
    leads: {
      email1: 'Email',
      phone_work: 'Work Phone',
      phone_mobile: 'Mobile Phone',
      account_name: 'Company',
      primary_address_street: 'Street Address',
      primary_address_city: 'City',
      primary_address_state: 'State/Province',
      primary_address_postalcode: 'Postal Code',
      primary_address_country: 'Country',
    },
    contacts: {
      email1: 'Email',
      phone_work: 'Work Phone',
      phone_mobile: 'Mobile Phone',
    },
  };
  
  return labels[table]?.[field] || field;
};
