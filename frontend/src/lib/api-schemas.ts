import { z } from 'zod'

// Base schemas
export const DateStringSchema = z.string().datetime().optional()
export const IdSchema = z.string().optional() // Not enforcing UUID format as backend may use different ID formats

// Common schemas
export const PaginationSchema = z.object({
  page: z.number(),
  limit: z.number(),
  total: z.number(),
  pages: z.number()
})

export const ErrorResponseSchema = z.object({
  error: z.string(),
  message: z.string(),
  statusCode: z.number().optional(),
  details: z.record(z.string(), z.any()).optional()
})

export const ApiResponseSchema = <T extends z.ZodType>(dataSchema: T) => z.object({
  success: z.boolean(),
  data: dataSchema.optional(),
  error: ErrorResponseSchema.optional(),
  pagination: PaginationSchema.optional()
})

export const ListResponseSchema = <T extends z.ZodType>(dataSchema: T) => z.object({
  data: z.array(dataSchema),
  pagination: PaginationSchema
})

// Entity schemas
export const ContactSchema = z.object({
  id: IdSchema,
  firstName: z.string().min(1),
  lastName: z.string().min(1),
  email: z.string().email(),
  phone: z.string().optional(),
  mobile: z.string().optional(),
  title: z.string().optional(),
  department: z.string().optional(),
  company: z.string().optional(),
  industry: z.string().optional(),
  website: z.string().url().optional(),
  source: z.string().optional(),
  status: z.enum(['Active', 'Inactive', 'Prospect']),
  rating: z.enum(['Hot', 'Warm', 'Cold']).optional(),
  ownerId: z.string().optional(),
  ownerName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  tags: z.array(z.string()).optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  lastContactedAt: DateStringSchema,
  preferredContactMethod: z.enum(['Email', 'Phone', 'Mobile']).optional(),
  doNotCall: z.boolean().optional(),
  doNotEmail: z.boolean().optional(),
  customerSince: DateStringSchema,
  lifetimeValue: z.number().optional(),
  totalPurchases: z.number().optional(),
  customerNumber: z.string().optional(),
  loyaltyPoints: z.number().optional(),
  segment: z.enum(['VIP', 'Regular', 'At Risk', 'Churned']).optional(),
  createdAt: DateStringSchema,
  updatedAt: DateStringSchema
})

export const LeadSchema = z.object({
  id: IdSchema,
  firstName: z.string().min(1),
  lastName: z.string().min(1),
  email: z.string().email(),
  phone: z.string().optional(),
  mobile: z.string().optional(),
  title: z.string().optional(),
  company: z.string().optional(),
  website: z.string().optional(),
  description: z.string().optional(),
  status: z.enum(['New', 'Contacted', 'Qualified', 'Converted', 'Dead']),
  source: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  convertedContactId: z.string().optional(),
  convertedAt: DateStringSchema,
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: DateStringSchema,
  updatedAt: DateStringSchema
})

export const OpportunitySchema = z.object({
  id: IdSchema,
  name: z.string().min(1),
  amount: z.number(),
  currency: z.string().optional(),
  probability: z.number().min(0).max(100).optional(),
  salesStage: z.string(),
  closeDate: z.string(),
  description: z.string().optional(),
  nextStep: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  aiInsights: z.string().optional(),
  aiScore: z.number().optional(),
  aiRecommendations: z.array(z.string()).optional(),
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: DateStringSchema,
  updatedAt: DateStringSchema
})

export const TaskSchema = z.object({
  id: IdSchema,
  name: z.string().min(1),
  status: z.enum(['Not Started', 'In Progress', 'Completed', 'Pending Input', 'Deferred']),
  priority: z.enum(['High', 'Medium', 'Low']),
  dueDate: z.string().optional(),
  description: z.string().optional(),
  parentType: z.string().optional(),
  parentId: z.string().optional(),
  parentName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  completedAt: DateStringSchema,
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: DateStringSchema,
  updatedAt: DateStringSchema
})

export const CaseSchema = z.object({
  id: IdSchema,
  caseNumber: z.string(),
  subject: z.string().min(1),
  description: z.string().optional(),
  status: z.enum(['New', 'Assigned', 'In Progress', 'Pending', 'Resolved', 'Closed']),
  priority: z.enum(['Low', 'Medium', 'High', 'Urgent']),
  type: z.string().optional(),
  contactId: z.string().optional(),
  contactName: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  resolution: z.string().optional(),
  resolvedAt: DateStringSchema,
  customFields: z.record(z.string(), z.any()).optional(),
  createdAt: DateStringSchema,
  updatedAt: DateStringSchema
})

export const ActivitySchema = z.object({
  id: IdSchema,
  type: z.enum(['Call', 'Meeting', 'Email', 'Task', 'Note']),
  subject: z.string(),
  description: z.string().optional(),
  date: z.string(),
  duration: z.number().optional(),
  status: z.string().optional(),
  relatedTo: z.string().optional(),
  relatedType: z.string().optional(),
  relatedId: z.string().optional(),
  assignedUserId: z.string().optional(),
  assignedUserName: z.string().optional(),
  assignedToName: z.string().optional(),
  createdAt: DateStringSchema,
  updatedAt: DateStringSchema
})

export const UserSchema = z.object({
  id: z.string(),
  username: z.string(),
  email: z.string().email(),
  firstName: z.string(),
  lastName: z.string(),
  role: z.string(),
  isActive: z.boolean(),
  lastLogin: DateStringSchema,
  createdAt: DateStringSchema,
  updatedAt: DateStringSchema
})

export const LoginRequestSchema = z.object({
  username: z.string().min(1),
  password: z.string().min(1)
})

export const LoginResponseSchema = z.object({
  success: z.boolean(),
  data: z.object({
    accessToken: z.string(),
    refreshToken: z.string(),
    expiresIn: z.number(),
    user: UserSchema
  })
})

export const RefreshTokenRequestSchema = z.object({
  refreshToken: z.string()
})

export const RefreshTokenResponseSchema = z.object({
  success: z.boolean(),
  data: z.object({
    accessToken: z.string(),
    expiresIn: z.number()
  })
})

// API endpoint schemas
export const ApiEndpointSchemas = {
  // Auth
  '/auth/login': {
    method: 'POST' as const,
    request: LoginRequestSchema,
    response: LoginResponseSchema
  },
  '/auth/refresh': {
    method: 'POST' as const,
    request: RefreshTokenRequestSchema,
    response: RefreshTokenResponseSchema
  },
  '/auth/logout': {
    method: 'POST' as const,
    request: z.object({}),
    response: ApiResponseSchema(z.object({ message: z.string() }))
  },

  // Contacts
  '/contacts': {
    method: 'GET' as const,
    request: z.object({
      page: z.string().optional(),
      limit: z.string().optional(),
      search: z.string().optional(),
      status: z.string().optional()
    }),
    response: ListResponseSchema(ContactSchema)
  },
  '/contacts/:id': {
    method: 'GET' as const,
    request: z.object({}),
    response: ApiResponseSchema(ContactSchema)
  },
  '/contacts/create': {
    method: 'POST' as const,
    request: ContactSchema.omit({ id: true, createdAt: true, updatedAt: true }),
    response: ApiResponseSchema(ContactSchema)
  },
  '/contacts/update': {
    method: 'PUT' as const,
    request: ContactSchema.partial(),
    response: ApiResponseSchema(ContactSchema)
  },
  '/contacts/:id/activities': {
    method: 'GET' as const,
    request: z.object({}),
    response: ApiResponseSchema(z.array(ActivitySchema))
  },

  // Leads
  '/leads': {
    method: 'GET' as const,
    request: z.object({
      page: z.string().optional(),
      limit: z.string().optional(),
      search: z.string().optional(),
      status: z.string().optional()
    }),
    response: ListResponseSchema(LeadSchema)
  },
  '/leads/:id': {
    method: 'GET' as const,
    request: z.object({}),
    response: ApiResponseSchema(LeadSchema)
  },
  '/leads/create': {
    method: 'POST' as const,
    request: LeadSchema.omit({ id: true, createdAt: true, updatedAt: true }),
    response: ApiResponseSchema(LeadSchema)
  },
  '/leads/update': {
    method: 'PUT' as const,
    request: LeadSchema.partial(),
    response: ApiResponseSchema(LeadSchema)
  },
  '/leads/:id/convert': {
    method: 'POST' as const,
    request: z.object({
      createOpportunity: z.boolean().optional(),
      opportunityData: z.object({
        name: z.string(),
        amount: z.number(),
        closeDate: z.string(),
        salesStage: z.string()
      }).optional()
    }),
    response: ApiResponseSchema(z.object({
      contactId: z.string(),
      opportunityId: z.string().optional()
    }))
  },

  // Opportunities
  '/opportunities': {
    method: 'GET' as const,
    request: z.object({
      page: z.string().optional(),
      limit: z.string().optional(),
      stage: z.string().optional()
    }),
    response: ListResponseSchema(OpportunitySchema)
  },
  '/opportunities/:id': {
    method: 'GET' as const,
    request: z.object({}),
    response: ApiResponseSchema(OpportunitySchema)
  },
  '/opportunities/create': {
    method: 'POST' as const,
    request: OpportunitySchema.omit({ id: true, createdAt: true, updatedAt: true }),
    response: ApiResponseSchema(OpportunitySchema)
  },

  // Tasks
  '/tasks': {
    method: 'GET' as const,
    request: z.object({
      page: z.string().optional(),
      limit: z.string().optional(),
      status: z.string().optional()
    }),
    response: ListResponseSchema(TaskSchema)
  },
  '/tasks/upcoming': {
    method: 'GET' as const,
    request: z.object({}),
    response: ApiResponseSchema(z.array(TaskSchema))
  },
  '/tasks/overdue': {
    method: 'GET' as const,
    request: z.object({}),
    response: ApiResponseSchema(z.array(TaskSchema))
  },

  // Activities
  '/activities': {
    method: 'GET' as const,
    request: z.object({
      page: z.string().optional(),
      limit: z.string().optional(),
      type: z.string().optional()
    }),
    response: ListResponseSchema(ActivitySchema)
  },
  '/activities/upcoming': {
    method: 'GET' as const,
    request: z.object({}),
    response: ApiResponseSchema(z.array(ActivitySchema))
  },
  '/activities/recent': {
    method: 'GET' as const,
    request: z.object({
      limit: z.string().optional()
    }),
    response: ApiResponseSchema(z.array(ActivitySchema))
  }
} as const

// Type helpers
export type ApiEndpoint = keyof typeof ApiEndpointSchemas
export type ApiEndpointConfig<T extends ApiEndpoint> = typeof ApiEndpointSchemas[T]
export type ApiRequest<T extends ApiEndpoint> = z.infer<ApiEndpointConfig<T>['request']>
export type ApiResponse<T extends ApiEndpoint> = z.infer<ApiEndpointConfig<T>['response']>