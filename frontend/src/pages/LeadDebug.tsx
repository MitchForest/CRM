import { useState } from 'react'
import { apiClient } from '@/lib/api-client'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface TestResults {
  createResult?: unknown;
  immediateGet?: unknown;
  delayedGet?: unknown;
  searchResult?: {
    found: boolean;
    totalResults: number;
    results: unknown[];
  };
  listResult?: {
    found: boolean;
    totalResults: number;
    firstFew: unknown[];
  };
  cleanup?: string;
  error?: unknown;
}

export function LeadDebugPage() {
  const [results, setResults] = useState<TestResults>({})
  const [loading, setLoading] = useState(false)

  const testLeadCreation = async () => {
    setLoading(true)
    const testResults: TestResults = {}

    try {
      // 1. Create a test lead
      console.log('Creating test lead...')
      const createResult = await apiClient.createLead({
        firstName: 'Debug',
        lastName: `Test${Date.now()}`,
        email: `debug${Date.now()}@test.com`,
        status: 'New'
      })
      
      testResults.createResult = createResult
      console.log('Create result:', createResult)

      if (createResult.success && createResult.data?.id) {
        const leadId = createResult.data.id

        // 2. Try to fetch it immediately
        console.log('Fetching lead immediately...')
        try {
          const immediateGet = await apiClient.getLead(leadId)
          testResults.immediateGet = immediateGet
          console.log('Immediate get result:', immediateGet)
        } catch (e) {
          testResults.immediateGet = { error: e }
          console.error('Immediate get error:', e)
        }

        // 3. Wait 2 seconds and try again
        await new Promise(resolve => setTimeout(resolve, 2000))
        console.log('Fetching lead after 2s delay...')
        try {
          const delayedGet = await apiClient.getLead(leadId)
          testResults.delayedGet = delayedGet
          console.log('Delayed get result:', delayedGet)
        } catch (e) {
          testResults.delayedGet = { error: e }
          console.error('Delayed get error:', e)
        }

        // 4. Try to search for it
        console.log('Searching for lead...')
        const searchResult = await apiClient.getLeads({
          search: 'Debug',
          page: 1,
          pageSize: 20
        })
        testResults.searchResult = {
          found: searchResult.data.some(l => l.id === leadId),
          totalResults: searchResult.data.length,
          results: searchResult.data
        }
        console.log('Search result:', testResults.searchResult)

        // 5. List all leads without search
        console.log('Listing all leads...')
        const listResult = await apiClient.getLeads({
          page: 1,
          pageSize: 20
        })
        testResults.listResult = {
          found: listResult.data.some(l => l.id === leadId),
          totalResults: listResult.data.length,
          firstFew: listResult.data.slice(0, 5)
        }
        console.log('List result:', testResults.listResult)

        // 6. Clean up
        console.log('Cleaning up test lead...')
        await apiClient.deleteLead(leadId)
        testResults.cleanup = 'Success'
      }

    } catch (error) {
      console.error('Test error:', error)
      testResults.error = error
    }

    setResults(testResults)
    setLoading(false)
  }

  return (
    <div className="space-y-6 p-6">
      <Card>
        <CardHeader>
          <CardTitle>Lead Creation Debug Tool</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p>This tool will help diagnose why created leads might not show up in the list.</p>
          
          <Button onClick={testLeadCreation} disabled={loading}>
            {loading ? 'Running tests...' : 'Run Lead Creation Test'}
          </Button>

          {Object.keys(results).length > 0 && (
            <div className="mt-4 space-y-4">
              <h3 className="font-semibold">Test Results:</h3>
              <pre className="bg-gray-100 p-4 rounded overflow-auto text-sm">
                {JSON.stringify(results, null, 2)}
              </pre>
            </div>
          )}

          <div className="mt-6 space-y-2 text-sm text-gray-600">
            <p><strong>What this test does:</strong></p>
            <ol className="list-decimal list-inside space-y-1">
              <li>Creates a test lead with timestamp</li>
              <li>Immediately tries to fetch it by ID</li>
              <li>Waits 2 seconds and fetches again</li>
              <li>Searches for the lead by name</li>
              <li>Lists all leads to see if it appears</li>
              <li>Cleans up by deleting the test lead</li>
            </ol>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}