// Debug script to test API transformation
import { transformToJsonApiDocument } from './api-transformers'
import { mapFrontendToSuiteCRM } from './field-mappers'

// Test data as frontend would send it
const leadData = {
  firstName: 'Test',
  lastName: 'Frontend',
  email: 'test@frontend.com',
  status: 'New'
}

console.log('Original data:', leadData)

// Test field mapping
const mappedData = mapFrontendToSuiteCRM(leadData)
console.log('After field mapping:', mappedData)

// Test full transformation
const jsonApiData = transformToJsonApiDocument('Leads', leadData, false)
console.log('JSON:API format:', JSON.stringify(jsonApiData, null, 2))

// Run with: npx tsx src/lib/debug-api.ts