/**
 * Transformers for converting between JSON:API format and our application format
 */

import { mapSuiteCRMToFrontend, mapFrontendToSuiteCRM } from './field-mappers'

// Types for JSON:API format
interface JsonApiResource {
  type: string
  id?: string
  attributes: Record<string, unknown>
  relationships?: Record<string, JsonApiRelationship>
  links?: Record<string, string>
  meta?: Record<string, unknown>
}

interface JsonApiRelationship {
  data?: JsonApiResourceIdentifier | JsonApiResourceIdentifier[] | null
  links?: Record<string, string>
  meta?: Record<string, unknown>
}

interface JsonApiResourceIdentifier {
  type: string
  id: string
}

interface JsonApiDocument {
  data: JsonApiResource | JsonApiResource[] | null
  included?: JsonApiResource[]
  links?: Record<string, string>
  meta?: Record<string, unknown>
  errors?: JsonApiError[]
}

export interface JsonApiError {
  id?: string
  status?: string
  code?: string
  title?: string
  detail?: string
  source?: {
    pointer?: string
    parameter?: string
  }
  meta?: Record<string, unknown>
}

/**
 * Transform a JSON:API resource to our application format
 */
export function transformFromJsonApi<T>(resource: JsonApiResource): T {
  // First, combine id with attributes
  const combined = {
    id: resource.id,
    ...resource.attributes,
  }

  // Convert snake_case fields from SuiteCRM to camelCase for frontend
  const transformed = mapSuiteCRMToFrontend(combined) as Record<string, unknown>

  // Handle relationships if present
  if (resource.relationships) {
    const relationships: Record<string, unknown> = {}
    
    for (const [key, relationship] of Object.entries(resource.relationships)) {
      if (relationship.data) {
        if (Array.isArray(relationship.data)) {
          relationships[key] = relationship.data.map(item => ({
            type: item.type,
            id: item.id
          }))
        } else {
          relationships[key] = {
            type: relationship.data.type,
            id: relationship.data.id
          }
        }
      }
    }
    
    if (Object.keys(relationships).length > 0) {
      (transformed as Record<string, unknown>)['relationships'] = relationships
    }
  }

  return transformed as T
}

/**
 * Transform multiple JSON:API resources to our application format
 */
export function transformManyFromJsonApi<T>(resources: JsonApiResource[]): T[] {
  return resources.map(resource => transformFromJsonApi<T>(resource))
}

/**
 * Transform a complete JSON:API document to our application format
 */
export function transformDocumentFromJsonApi<T>(document: JsonApiDocument): {
  data: T | T[] | null
  meta?: Record<string, unknown>
  links?: Record<string, string>
  included?: unknown[]
} {
  const result: {
    data: T | T[] | null
    meta?: Record<string, unknown>
    links?: Record<string, string>
    included?: unknown[]
  } = { data: null }

  if (document.data === null) {
    result.data = null
  } else if (Array.isArray(document.data)) {
    result.data = transformManyFromJsonApi<T>(document.data)
  } else {
    result.data = transformFromJsonApi<T>(document.data)
  }

  if (document.meta) {
    result.meta = document.meta
  }

  if (document.links) {
    result.links = document.links
  }

  if (document.included) {
    result.included = document.included.map(resource => transformFromJsonApi(resource))
  }

  return result
}

/**
 * Transform our application data to JSON:API format for requests
 */
export function transformToJsonApi(
  type: string, 
  data: unknown,
  includeId: boolean = true
): JsonApiResource {
  // Convert camelCase fields from frontend to snake_case for SuiteCRM
  const snakeCaseData = mapFrontendToSuiteCRM(data)
  
  const { id, relationships, ...dirtyAttributes } = snakeCaseData as Record<string, unknown>
  
  // Filter out undefined, null, and empty string values
  const attributes: Record<string, unknown> = {}
  Object.entries(dirtyAttributes).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      attributes[key] = value
    }
  })
  
  let resource: JsonApiResource = {
    type,
    id: includeId && id ? String(id) : '',
    attributes
  }

  // Only include id if it's provided and includeId is true
  if (!includeId || !id) {
    // Create a new resource without id instead of deleting
    resource = {
      type: resource.type,
      attributes: resource.attributes
    }
  }

  // Handle relationships
  if (relationships && typeof relationships === 'object') {
    const jsonApiRelationships: Record<string, JsonApiRelationship> = {}
    
    for (const [key, value] of Object.entries(relationships)) {
      if (value === null) {
        jsonApiRelationships[key] = { data: null }
      } else if (Array.isArray(value)) {
        jsonApiRelationships[key] = {
          data: value.map(item => ({
            type: item.type || key,
            id: item.id
          }))
        }
      } else if (typeof value === 'object' && value !== null && 'id' in value && (value as Record<string, unknown>)['id']) {
        jsonApiRelationships[key] = {
          data: {
            type: (value as Record<string, unknown>)['type'] as string || key,
            id: (value as Record<string, unknown>)['id'] as string
          }
        }
      }
    }
    
    if (Object.keys(jsonApiRelationships).length > 0) {
      resource.relationships = jsonApiRelationships
    }
  }

  return resource
}

/**
 * Transform our application data to a JSON:API document for requests
 */
export function transformToJsonApiDocument(
  type: string,
  data: unknown | unknown[],
  includeId: boolean = true
): { data: JsonApiResource | JsonApiResource[] } {
  if (Array.isArray(data)) {
    return {
      data: data.map(item => transformToJsonApi(type, item, includeId))
    }
  }
  
  return {
    data: transformToJsonApi(type, data, includeId)
  }
}

/**
 * Extract pagination metadata from JSON:API response
 */
export function extractPaginationMeta(document: JsonApiDocument): {
  page: number
  pageSize: number
  totalPages: number
  totalCount: number
  hasNext: boolean
  hasPrevious: boolean
} {
  const meta = document.meta || {}
  const links = document.links || {}
  
  return {
    page: Number(meta['page-number'] || meta['page'] || 1),
    pageSize: Number(meta['page-size'] || meta['pageSize'] || 20),
    totalPages: Number(meta['total-pages'] || meta['totalPages'] || 1),
    totalCount: Number(meta['total-count'] || meta['totalCount'] || 0),
    hasNext: !!links['next'],
    hasPrevious: !!links['prev'] || !!links['previous']
  }
}

/**
 * Transform JSON:API errors to our error format
 */
export function transformJsonApiErrors(errors: JsonApiError[]): {
  message: string
  code?: string
  details?: unknown
} {
  if (!errors || errors.length === 0) {
    return {
      message: 'An unknown error occurred'
    }
  }

  const firstError = errors[0]
  
  return {
    message: firstError?.title || firstError?.detail || 'An error occurred',
    ...(firstError?.code && { code: firstError.code }),
    ...(firstError?.status && { code: firstError.status }),
    ...(errors.length > 1 ? { details: errors } : firstError?.meta ? { details: firstError.meta } : {})
  }
}

/**
 * Build JSON:API filter parameters
 */
export function buildJsonApiFilters(filters: Record<string, unknown>): Record<string, string> {
  const params: Record<string, string> = {}
  
  // Convert filter keys to snake_case for SuiteCRM
  const snakeCaseFilters = mapFrontendToSuiteCRM(filters)
  
  for (const [key, value] of Object.entries(snakeCaseFilters as Record<string, unknown>)) {
    if (value !== undefined && value !== null && value !== '') {
      if (typeof value === 'object' && value !== null && 'operator' in value && (value as Record<string, unknown>).operator && (value as Record<string, unknown>).value !== undefined) {
        // Handle complex filters like { operator: 'like', value: 'test' }
        const filterValue = value as Record<string, unknown>;
        params[`filter[${key}][${filterValue.operator}]`] = String(filterValue.value)
      } else {
        // Handle simple filters
        params[`filter[${key}]`] = String(value)
      }
    }
  }
  
  return params
}

/**
 * Build JSON:API sort parameter
 */
export function buildJsonApiSort(sortBy?: string, sortOrder?: 'asc' | 'desc'): string | undefined {
  if (!sortBy) return undefined
  
  // Convert camelCase field name to snake_case for SuiteCRM
  const snakeCaseSortBy = mapFrontendToSuiteCRM({ [sortBy]: true }) as Record<string, unknown>
  const fieldName = Object.keys(snakeCaseSortBy)[0]
  
  // In JSON:API, descending sort is prefixed with a minus sign
  return sortOrder === 'desc' ? `-${fieldName}` : fieldName
}

/**
 * Build JSON:API pagination parameters
 */
export function buildJsonApiPagination(page?: number, pageSize?: number): Record<string, string> {
  const params: Record<string, string> = {}
  
  if (page !== undefined) {
    params['page[number]'] = String(page)
  }
  
  if (pageSize !== undefined) {
    params['page[size]'] = String(pageSize)
  }
  
  return params
}