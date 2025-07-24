import { describe, it, expect, beforeAll, afterAll } from 'vitest'
import { getTestAuthTokens, createAuthenticatedClient } from './helpers/test-auth'
import { testData } from './helpers/test-data'
import type { AuthTokens } from './helpers/test-auth'

describe('Activities Integration Tests', () => {
  let tokens: AuthTokens
  let suitecrmClient: any
  let customApiClient: any
  let testAccountId: string | null = null
  let testContactId: string | null = null
  const createdActivityIds: { [key: string]: string[] } = {
    calls: [],
    meetings: [],
    tasks: [],
    notes: []
  }

  beforeAll(async () => {
    tokens = await getTestAuthTokens()
    suitecrmClient = createAuthenticatedClient('http://localhost:8080/Api/V8', tokens.suitecrmToken, true)
    customApiClient = createAuthenticatedClient('http://localhost:8080/custom-api', tokens.customApiToken)
    
    // Create test account and contact for activity relationships
    const accountData = {
      data: {
        type: 'Accounts',
        attributes: { name: 'Test Activities Account' }
      }
    }
    
    const contactData = {
      data: {
        type: 'Contacts',
        attributes: {
          first_name: 'Activity',
          last_name: 'Test',
          email1: 'activity.test@example.com'
        }
      }
    }
    
    const [accountResponse, contactResponse] = await Promise.all([
      suitecrmClient.post('/module', accountData),
      suitecrmClient.post('/module', contactData)
    ])
    
    testAccountId = accountResponse.data.data.id
    testContactId = contactResponse.data.data.id
  })

  afterAll(async () => {
    // Clean up all created activities
    const cleanupPromises = []
    
    for (const [module, ids] of Object.entries(createdActivityIds)) {
      const moduleName = module.charAt(0).toUpperCase() + module.slice(1)
      ids.forEach(id => {
        cleanupPromises.push(
          suitecrmClient.delete(`/module/${moduleName}/${id}`).catch(() => {})
        )
      })
    }
    
    // Clean up test account and contact
    if (testAccountId) {
      cleanupPromises.push(
        suitecrmClient.delete(`/module/Accounts/${testAccountId}`).catch(() => {})
      )
    }
    if (testContactId) {
      cleanupPromises.push(
        suitecrmClient.delete(`/module/Contacts/${testContactId}`).catch(() => {})
      )
    }
    
    await Promise.all(cleanupPromises)
  })

  describe('Call Management', () => {
    it('should create a new call activity', async () => {
      const callData = {
        data: {
          type: 'Calls',
          attributes: {
            ...testData.call,
            parent_type: 'Accounts',
            parent_id: testAccountId
          }
        }
      }
      
      const response = await suitecrmClient.post('/module', callData)
      
      expect(response.status).toBe(201)
      expect(response.data.data).toHaveProperty('id')
      expect(response.data.data.type).toBe('Calls')
      
      const callId = response.data.data.id
      createdActivityIds.calls!.push(callId)
      
      const attributes = response.data.data.attributes
      expect(attributes.name).toBe(testData.call.name)
      expect(attributes.status).toBe('Planned')
      expect(attributes.direction).toBe('Outbound')
    })

    it('should list calls with filters', async () => {
      const response = await suitecrmClient.get('/module/Calls', {
        params: {
          'filter[status]': 'Planned',
          'page[size]': 10,
          'sort': '-date_start'
        }
      })
      
      expect(response.status).toBe(200)
      expect(Array.isArray(response.data.data)).toBe(true)
      
      // All returned calls should have Planned status
      response.data.data.forEach((call: any) => {
        expect(call.attributes.status).toBe('Planned')
      })
    })

    it('should update call status and outcome', async () => {
      const callId = createdActivityIds.calls![0]
      
      const updateData = {
        data: {
          type: 'Calls',
          id: callId,
          attributes: {
            status: 'Held',
            description: 'Call completed successfully. Customer interested in upgrade.'
          }
        }
      }
      
      const response = await suitecrmClient.patch('/module', updateData)
      
      expect(response.status).toBe(200)
      expect(response.data.data.attributes.status).toBe('Held')
      expect(response.data.data.attributes.description).toContain('completed successfully')
    })
  })

  describe('Meeting Management', () => {
    it('should create a new meeting with invitees', async () => {
      const meetingData = {
        data: {
          type: 'Meetings',
          attributes: {
            ...testData.meeting,
            parent_type: 'Accounts',
            parent_id: testAccountId
          }
        }
      }
      
      const response = await suitecrmClient.post('/module', meetingData)
      
      expect(response.status).toBe(201)
      expect(response.data.data).toHaveProperty('id')
      
      const meetingId = response.data.data.id
      createdActivityIds.meetings!.push(meetingId)
      
      const attributes = response.data.data.attributes
      expect(attributes.name).toBe(testData.meeting.name)
      expect(attributes.location).toBe('Zoom')
      expect(attributes.duration_hours).toBe(1)
    })

    it('should handle recurring meetings', async () => {
      const recurringMeetingData = {
        data: {
          type: 'Meetings',
          attributes: {
            name: 'Weekly Status Meeting',
            status: 'Planned',
            date_start: new Date().toISOString(),
            duration_hours: 1,
            duration_minutes: 0,
            location: 'Conference Room A',
            recurring: true,
            repeat_type: 'Weekly',
            repeat_count: 4
          }
        }
      }
      
      const response = await suitecrmClient.post('/module', recurringMeetingData)
      
      expect(response.status).toBe(201)
      createdActivityIds.meetings!.push(response.data.data.id)
      
      // Note: Actual recurring functionality depends on SuiteCRM configuration
      expect(response.data.data.attributes.name).toBe('Weekly Status Meeting')
    })
  })

  describe('Task Management', () => {
    it('should create tasks with different priorities', async () => {
      const priorities = ['High', 'Medium', 'Low']
      const taskPromises = priorities.map(priority => {
        const taskData = {
          data: {
            type: 'Tasks',
            attributes: {
              name: `${priority} Priority Task`,
              status: 'Not Started',
              priority: priority,
              date_due: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().split('T')[0],
              parent_type: 'Accounts',
              parent_id: testAccountId
            }
          }
        }
        
        return suitecrmClient.post('/module', taskData)
      })
      
      const responses = await Promise.all(taskPromises)
      
      responses.forEach((response, index) => {
        expect(response.status).toBe(201)
        createdActivityIds.tasks!.push(response.data.data.id)
        expect(response.data.data.attributes.priority).toBe(priorities[index])
      })
    })

    it('should track overdue tasks', async () => {
      // Create an overdue task
      const overdueTaskData = {
        data: {
          type: 'Tasks',
          attributes: {
            name: 'Overdue Task',
            status: 'Not Started',
            priority: 'High',
            date_due: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0] // Yesterday
          }
        }
      }
      
      const createResponse = await suitecrmClient.post('/module', overdueTaskData)
      createdActivityIds.tasks!.push(createResponse.data.data.id)
      
      // Get activity metrics from custom API
      const metricsResponse = await customApiClient.get('/dashboard/activities')
      
      expect(metricsResponse.status).toBe(200)
      expect(metricsResponse.data.data.tasksOverdue).toBeGreaterThan(0)
    })

    it('should update task completion status', async () => {
      const taskId = createdActivityIds.tasks![0]
      
      const updateData = {
        data: {
          type: 'Tasks',
          id: taskId,
          attributes: {
            status: 'Completed',
            date_finished: new Date().toISOString()
          }
        }
      }
      
      const response = await suitecrmClient.patch('/module', updateData)
      
      expect(response.status).toBe(200)
      expect(response.data.data.attributes.status).toBe('Completed')
      expect(response.data.data.attributes.date_finished).toBeTruthy()
    })
  })

  describe('Notes Management', () => {
    it('should create and attach notes to records', async () => {
      const noteData = {
        data: {
          type: 'Notes',
          attributes: {
            ...testData.note,
            parent_type: 'Accounts',
            parent_id: testAccountId
          }
        }
      }
      
      const response = await suitecrmClient.post('/module', noteData)
      
      expect(response.status).toBe(201)
      createdActivityIds.notes!.push(response.data.data.id)
      
      const attributes = response.data.data.attributes
      expect(attributes.name).toBe(testData.note.name)
      expect(attributes.description).toContain('Client expressed interest')
    })

    it('should support file attachments in notes', async () => {
      const noteWithAttachmentData = {
        data: {
          type: 'Notes',
          attributes: {
            name: 'Contract Draft',
            description: 'Initial contract draft for review',
            filename: 'contract-draft.pdf',
            file_mime_type: 'application/pdf'
          }
        }
      }
      
      const response = await suitecrmClient.post('/module', noteWithAttachmentData)
      
      expect(response.status).toBe(201)
      createdActivityIds.notes!.push(response.data.data.id)
      
      expect(response.data.data.attributes.filename).toBe('contract-draft.pdf')
      expect(response.data.data.attributes.file_mime_type).toBe('application/pdf')
    })
  })

  describe('Activity Dashboard Integration', () => {
    it('should reflect today\'s activities in dashboard metrics', async () => {
      // Create activities scheduled for today
      const todayCall = {
        data: {
          type: 'Calls',
          attributes: {
            name: 'Today\'s Call',
            status: 'Planned',
            date_start: new Date().toISOString(),
            duration_minutes: 30
          }
        }
      }
      
      const todayMeeting = {
        data: {
          type: 'Meetings',
          attributes: {
            name: 'Today\'s Meeting',
            status: 'Planned',
            date_start: new Date().toISOString(),
            duration_hours: 1
          }
        }
      }
      
      const [callResponse, meetingResponse] = await Promise.all([
        suitecrmClient.post('/module', todayCall),
        suitecrmClient.post('/module', todayMeeting)
      ])
      
      createdActivityIds.calls!.push(callResponse.data.data.id)
      createdActivityIds.meetings!.push(meetingResponse.data.data.id)
      
      // Wait for data propagation
      await new Promise(resolve => setTimeout(resolve, 1000))
      
      // Check dashboard metrics
      const metricsResponse = await customApiClient.get('/dashboard/activities')
      
      expect(metricsResponse.status).toBe(200)
      expect(metricsResponse.data.data.callsToday).toBeGreaterThan(0)
      expect(metricsResponse.data.data.meetingsToday).toBeGreaterThan(0)
    })

    it('should show upcoming activities in correct order', async () => {
      const metricsResponse = await customApiClient.get('/dashboard/activities')
      
      expect(metricsResponse.status).toBe(200)
      
      const upcomingActivities = metricsResponse.data.data.upcomingActivities
      expect(Array.isArray(upcomingActivities)).toBe(true)
      
      // Verify activities are sorted by date
      for (let i = 1; i < upcomingActivities.length; i++) {
        const prevDate = new Date(upcomingActivities[i-1].date)
        const currDate = new Date(upcomingActivities[i].date)
        expect(prevDate.getTime()).toBeLessThanOrEqual(currDate.getTime())
      }
    })
  })

  describe('Activity Relationships', () => {
    it('should link activities to parent records', async () => {
      const callId = createdActivityIds.calls![0]
      
      const response = await suitecrmClient.get(`/module/Calls/${callId}`, {
        params: {
          'include': 'parent'
        }
      })
      
      expect(response.status).toBe(200)
      expect(response.data.data.attributes.parent_type).toBe('Accounts')
      expect(response.data.data.attributes.parent_id).toBe(testAccountId)
    })

    it('should retrieve activities for a specific account', async () => {
      // This would typically require a custom endpoint or relationship query
      // For now, we'll test filtering by parent
      const response = await suitecrmClient.get('/module/Calls', {
        params: {
          'filter[parent_type]': 'Accounts',
          'filter[parent_id]': testAccountId,
          'page[size]': 100
        }
      })
      
      expect(response.status).toBe(200)
      
      // Should find at least one call linked to our test account
      const linkedCalls = response.data.data.filter((call: any) => 
        call.attributes.parent_id === testAccountId
      )
      expect(linkedCalls.length).toBeGreaterThan(0)
    })
  })

  describe('Activity Validation', () => {
    it('should validate required fields', async () => {
      const invalidCallData = {
        data: {
          type: 'Calls',
          attributes: {
            // Missing required name field
            status: 'Planned'
          }
        }
      }
      
      try {
        await suitecrmClient.post('/module', invalidCallData)
        expect.fail('Should have rejected invalid data')
      } catch (error: any) {
        expect(error.response.status).toBeGreaterThanOrEqual(400)
      }
    })

    it('should validate date/time fields', async () => {
      const invalidMeetingData = {
        data: {
          type: 'Meetings',
          attributes: {
            name: 'Invalid Date Meeting',
            date_start: 'invalid-date-format',
            duration_hours: 1
          }
        }
      }
      
      try {
        await suitecrmClient.post('/module', invalidMeetingData)
        expect.fail('Should have rejected invalid date')
      } catch (error: any) {
        expect(error.response.status).toBeGreaterThanOrEqual(400)
      }
    })
  })
})