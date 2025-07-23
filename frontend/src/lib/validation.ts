import { z } from 'zod'

// Common regex patterns
const phoneRegex = /^[\d\s()+-]+$/
const urlRegex = /^https?:\/\/(www\.)?[-a-zA-Z0-9@:%._+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_+.~#?&//=]*)$/

// Common validation messages
const messages = {
  required: (field: string) => `${field} is required`,
  email: 'Please enter a valid email address',
  url: 'Please enter a valid URL (include http:// or https://)',
  phone: 'Please enter a valid phone number',
  min: (field: string, length: number) => `${field} must be at least ${length} characters`,
  max: (field: string, length: number) => `${field} must be at most ${length} characters`,
  number: (field: string) => `${field} must be a valid number`,
  positive: (field: string) => `${field} must be a positive number`,
}

// Helper functions for common validations
const optionalUrl = () => z.union([
  z.literal(''),
  z.string().url(messages.url)
]).optional()

const optionalPhone = () => z.string().refine(
  (val) => !val || phoneRegex.test(val),
  { message: messages.phone }
).optional()

const requiredPhone = () => z.string().min(1, messages.required('Phone number')).refine(
  (val) => phoneRegex.test(val),
  { message: messages.phone }
)

// Login form validation
export const loginSchema = z.object({
  username: z.string()
    .min(1, messages.required('Username'))
    .min(3, messages.min('Username', 3))
    .max(50, messages.max('Username', 50)),
  password: z.string()
    .min(1, messages.required('Password'))
    .min(6, messages.min('Password', 6))
    .max(100, messages.max('Password', 100)),
})

export type LoginFormData = z.infer<typeof loginSchema>

// Lead form validation
export const leadSchema = z.object({
  firstName: z.string()
    .min(1, messages.required('First name'))
    .max(50, messages.max('First name', 50)),
  lastName: z.string()
    .min(1, messages.required('Last name'))
    .max(50, messages.max('Last name', 50)),
  email: z.string()
    .min(1, messages.required('Email'))
    .email(messages.email)
    .max(100, messages.max('Email', 100)),
  phone: optionalPhone(),
  mobile: optionalPhone(),
  title: z.string()
    .max(100, messages.max('Title', 100))
    .optional(),
  company: z.string()
    .max(100, messages.max('Company', 100))
    .optional(),
  accountName: z.string()
    .max(100, messages.max('Account name', 100))
    .optional(),
  website: optionalUrl(),
  description: z.string()
    .max(5000, messages.max('Description', 5000))
    .optional(),
  status: z.enum(['New', 'Contacted', 'Qualified', 'Converted', 'Dead']),
  source: z.enum(['Website', 'Referral', 'Campaign', 'Social Media', 'Other']).optional(),
})

export type LeadFormData = z.infer<typeof leadSchema>

// Account form validation
export const accountSchema = z.object({
  name: z.string()
    .min(1, messages.required('Account name'))
    .max(100, messages.max('Account name', 100)),
  phone: optionalPhone(),
  website: optionalUrl(),
  industry: z.string()
    .max(100, messages.max('Industry', 100))
    .optional(),
  annualRevenue: z.string().optional(),
  employees: z.string().optional(),
  billingStreet: z.string()
    .max(255, messages.max('Street', 255))
    .optional(),
  billingCity: z.string()
    .max(100, messages.max('City', 100))
    .optional(),
  billingState: z.string()
    .max(100, messages.max('State/Province', 100))
    .optional(),
  billingPostalCode: z.string()
    .max(20, messages.max('Postal code', 20))
    .optional(),
  billingCountry: z.string()
    .max(100, messages.max('Country', 100))
    .optional(),
  shippingStreet: z.string()
    .max(255, messages.max('Street', 255))
    .optional(),
  shippingCity: z.string()
    .max(100, messages.max('City', 100))
    .optional(),
  shippingState: z.string()
    .max(100, messages.max('State/Province', 100))
    .optional(),
  shippingPostalCode: z.string()
    .max(20, messages.max('Postal code', 20))
    .optional(),
  shippingCountry: z.string()
    .max(100, messages.max('Country', 100))
    .optional(),
  description: z.string()
    .max(5000, messages.max('Description', 5000))
    .optional(),
})

export type AccountFormData = z.infer<typeof accountSchema>

// Contact form validation
export const contactSchema = z.object({
  firstName: z.string()
    .min(1, messages.required('First name'))
    .max(50, messages.max('First name', 50)),
  lastName: z.string()
    .min(1, messages.required('Last name'))
    .max(50, messages.max('Last name', 50)),
  email: z.string()
    .min(1, messages.required('Email'))
    .email(messages.email)
    .max(100, messages.max('Email', 100)),
  phone: optionalPhone(),
  mobile: optionalPhone(),
  title: z.string()
    .max(100, messages.max('Title', 100))
    .optional(),
  preferredContactMethod: z.enum(['email', 'phone', 'sms']).optional(),
  customerNumber: z.string()
    .max(50, messages.max('Customer number', 50))
    .optional(),
  tags: z.string()
    .max(500, messages.max('Tags', 500))
    .optional(),
})

export type ContactFormData = z.infer<typeof contactSchema>

// Opportunity form validation (for future use)
export const opportunitySchema = z.object({
  name: z.string()
    .min(1, messages.required('Opportunity name'))
    .max(100, messages.max('Opportunity name', 100)),
  accountId: z.string()
    .min(1, messages.required('Account')),
  amount: z.coerce.number()
    .positive(messages.positive('Amount'))
    .optional(),
  closeDate: z.string()
    .min(1, messages.required('Close date')),
  stage: z.enum(['Prospecting', 'Qualification', 'Needs Analysis', 'Value Proposition', 'Decision Makers', 'Perception Analysis', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost']),
  probability: z.coerce.number()
    .min(0, 'Probability must be between 0 and 100')
    .max(100, 'Probability must be between 0 and 100')
    .optional(),
  description: z.string()
    .max(5000, messages.max('Description', 5000))
    .optional(),
})

export type OpportunityFormData = z.infer<typeof opportunitySchema>

// Activity form validation (for future use)
export const activitySchema = z.object({
  subject: z.string()
    .min(1, messages.required('Subject'))
    .max(200, messages.max('Subject', 200)),
  type: z.enum(['Call', 'Email', 'Meeting', 'Task', 'Note']),
  relatedTo: z.string()
    .min(1, messages.required('Related to')),
  relatedType: z.enum(['Lead', 'Contact', 'Account', 'Opportunity']),
  dueDate: z.string().optional(),
  status: z.enum(['Planned', 'In Progress', 'Completed', 'Cancelled']).default('Planned'),
  priority: z.enum(['High', 'Medium', 'Low']).default('Medium'),
  description: z.string()
    .max(5000, messages.max('Description', 5000))
    .optional(),
})

export type ActivityFormData = z.infer<typeof activitySchema>

// User form validation (for future use)
export const userSchema = z.object({
  username: z.string()
    .min(1, messages.required('Username'))
    .min(3, messages.min('Username', 3))
    .max(50, messages.max('Username', 50))
    .regex(/^[a-zA-Z0-9_-]+$/, 'Username can only contain letters, numbers, underscores, and hyphens'),
  email: z.string()
    .min(1, messages.required('Email'))
    .email(messages.email)
    .max(100, messages.max('Email', 100)),
  password: z.string()
    .min(1, messages.required('Password'))
    .min(8, messages.min('Password', 8))
    .max(100, messages.max('Password', 100))
    .regex(/[A-Z]/, 'Password must contain at least one uppercase letter')
    .regex(/[a-z]/, 'Password must contain at least one lowercase letter')
    .regex(/[0-9]/, 'Password must contain at least one number')
    .regex(/[^A-Za-z0-9]/, 'Password must contain at least one special character'),
  confirmPassword: z.string()
    .min(1, messages.required('Confirm password')),
  firstName: z.string()
    .min(1, messages.required('First name'))
    .max(50, messages.max('First name', 50)),
  lastName: z.string()
    .min(1, messages.required('Last name'))
    .max(50, messages.max('Last name', 50)),
  role: z.enum(['Admin', 'User', 'Manager']),
  phone: optionalPhone(),
  isActive: z.boolean().default(true),
}).refine((data) => data.password === data.confirmPassword, {
  message: "Passwords don't match",
  path: ["confirmPassword"],
})

export type UserFormData = z.infer<typeof userSchema>

// Settings form validation (for future use)
export const settingsSchema = z.object({
  companyName: z.string()
    .min(1, messages.required('Company name'))
    .max(100, messages.max('Company name', 100)),
  companyEmail: z.string()
    .min(1, messages.required('Company email'))
    .email(messages.email)
    .max(100, messages.max('Company email', 100)),
  companyPhone: requiredPhone(),
  companyWebsite: z.string()
    .min(1, messages.required('Company website'))
    .refine((val) => urlRegex.test(val), { message: messages.url }),
  timezone: z.string()
    .min(1, messages.required('Timezone')),
  dateFormat: z.enum(['MM/DD/YYYY', 'DD/MM/YYYY', 'YYYY-MM-DD']),
  currency: z.string()
    .min(1, messages.required('Currency'))
    .max(3, messages.max('Currency', 3)),
  emailNotifications: z.boolean().default(true),
  smsNotifications: z.boolean().default(false),
})

export type SettingsFormData = z.infer<typeof settingsSchema>