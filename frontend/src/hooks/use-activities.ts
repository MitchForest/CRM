import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { CallDB, MeetingDB, TaskDB, NoteDB } from '@/types/database.types'
import type { QueryParams, ListResponse, ApiResponse, BaseActivity, ActivityType } from '@/types/api.types'
import { getErrorMessage } from '@/lib/error-utils'

// Extended types - just use the DB types directly
type ExtendedCall = CallDB
type ExtendedMeeting = MeetingDB
type ExtendedTask = TaskDB
type ExtendedNote = NoteDB

interface ActivityApiMethods<T> {
  getAll: (params?: QueryParams) => Promise<ListResponse<T>>;
  getOne: (id: string) => Promise<ApiResponse<T>>;
  create: (data: Partial<T>) => Promise<ApiResponse<T>>;
  update: (id: string, data: Partial<T>) => Promise<ApiResponse<T>>;
  delete: (id: string) => Promise<ApiResponse<void>>;
}

// Base hook factory for common activity operations
function createActivityHooks<T extends { id?: string }, BaseT = Record<string, unknown>>(
  moduleName: 'calls' | 'meetings' | 'tasks' | 'notes',
  moduleLabel: string,
  apiMethods: ActivityApiMethods<BaseT>
) {
  return {
    useGetAll: (page = 1, limit = 20, filters?: Record<string, string | number | boolean>) => {
      return useQuery({
        queryKey: [moduleName, page, limit, filters],
        queryFn: async () => {
          const response = await apiMethods.getAll({ 
            page, 
            limit,
            filter: filters ? Object.entries(filters).map(([field, value]) => ({
              field,
              operator: 'eq' as const,
              value
            })) : undefined
          })
          return {
            data: response.data as unknown as T[],
            pagination: response.pagination
          }
        },
      })
    },

    useGetOne: (id: string) => {
      return useQuery({
        queryKey: [moduleName, id],
        queryFn: async () => {
          const response = await apiMethods.getOne(id)
          if (!response.success || !response.data) {
            throw new Error(response.error?.error || 'Failed to fetch')
          }
          return response.data as unknown as T
        },
        enabled: !!id,
      })
    },

    useCreate: () => {
      const queryClient = useQueryClient()

      return useMutation({
        mutationFn: async (data: Omit<T, 'id'>) => {
          const response = await apiMethods.create(data as unknown as Partial<BaseT>)
          if (!response.success || !response.data) {
            throw new Error(response.error?.error || 'Failed to create')
          }
          return response.data as unknown as T
        },
        onSuccess: async () => {
          await queryClient.invalidateQueries({ queryKey: [moduleName] })
          toast.success(`${moduleLabel} created successfully`)
        },
        onError: (error: unknown) => {
          toast.error(getErrorMessage(error, `Failed to create ${moduleLabel.toLowerCase()}`))
        },
      })
    },

    useUpdate: (id: string) => {
      const queryClient = useQueryClient()

      return useMutation({
        mutationFn: async (data: Partial<T>) => {
          const response = await apiMethods.update(id, data as unknown as Partial<BaseT>)
          if (!response.success || !response.data) {
            throw new Error(response.error?.error || 'Failed to update')
          }
          return response.data as unknown as T
        },
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: [moduleName, id] })
          queryClient.invalidateQueries({ queryKey: [moduleName] })
          toast.success(`${moduleLabel} updated successfully`)
        },
        onError: (error: unknown) => {
          toast.error(getErrorMessage(error, `Failed to update ${moduleLabel.toLowerCase()}`))
        },
      })
    },

    useDelete: () => {
      const queryClient = useQueryClient()

      return useMutation({
        mutationFn: async (id: string) => {
          const response = await apiMethods.delete(id)
          if (!response.success) {
            throw new Error(response.error?.error || 'Failed to delete')
          }
        },
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: [moduleName] })
          toast.success(`${moduleLabel} deleted successfully`)
        },
        onError: (error: unknown) => {
          toast.error(getErrorMessage(error, `Failed to delete ${moduleLabel.toLowerCase()}`))
        },
      })
    },
  }
}

// Call hooks
const callHooks = createActivityHooks<ExtendedCall, CallDB>('calls', 'call', {
  getAll: apiClient.getCalls.bind(apiClient),
  getOne: apiClient.getCall.bind(apiClient),
  create: apiClient.createCall.bind(apiClient),
  update: apiClient.updateCall.bind(apiClient),
  delete: apiClient.deleteCall.bind(apiClient),
})

export const useCalls = callHooks.useGetAll
export const useCall = callHooks.useGetOne
export const useCreateCall = callHooks.useCreate
export const useUpdateCall = callHooks.useUpdate
export const useDeleteCall = callHooks.useDelete

// Meeting hooks
const meetingHooks = createActivityHooks<ExtendedMeeting, MeetingDB>('meetings', 'meeting', {
  getAll: apiClient.getMeetings.bind(apiClient),
  getOne: apiClient.getMeeting.bind(apiClient),
  create: apiClient.createMeeting.bind(apiClient),
  update: apiClient.updateMeeting.bind(apiClient),
  delete: apiClient.deleteMeeting.bind(apiClient),
})

export const useMeetings = meetingHooks.useGetAll
export const useMeeting = meetingHooks.useGetOne
export const useCreateMeeting = meetingHooks.useCreate
export const useUpdateMeeting = meetingHooks.useUpdate
export const useDeleteMeeting = meetingHooks.useDelete

// Task hooks
const taskHooks = createActivityHooks<ExtendedTask, TaskDB>('tasks', 'task', {
  getAll: apiClient.getTasks.bind(apiClient),
  getOne: apiClient.getTask.bind(apiClient),
  create: apiClient.createTask.bind(apiClient),
  update: apiClient.updateTask.bind(apiClient),
  delete: apiClient.deleteTask.bind(apiClient),
})

export const useTasks = taskHooks.useGetAll
export const useTask = taskHooks.useGetOne
export const useCreateTask = taskHooks.useCreate
export const useUpdateTask = taskHooks.useUpdate
export const useDeleteTask = taskHooks.useDelete

// Note hooks
const noteHooks = createActivityHooks<ExtendedNote, NoteDB>('notes', 'Note', {
  getAll: apiClient.getNotes.bind(apiClient),
  getOne: apiClient.getNote.bind(apiClient),
  create: apiClient.createNote.bind(apiClient),
  update: apiClient.updateNote.bind(apiClient),
  delete: apiClient.deleteNote.bind(apiClient),
})

export const useNotes = noteHooks.useGetAll
export const useNote = noteHooks.useGetOne
export const useCreateNote = noteHooks.useCreate
export const useUpdateNote = noteHooks.useUpdate
export const useDeleteNote = noteHooks.useDelete

// Combined activity hooks
export function useUpcomingActivities(limit = 10) {
  return useQuery({
    queryKey: ['activities', 'upcoming', limit],
    queryFn: async () => {
      const [calls, meetings, tasks] = await Promise.all([
        apiClient.getCalls({
          limit,
          // Filter for future dates would need backend support
        }),
        apiClient.getMeetings({
          limit,
        }),
        apiClient.getTasks({
          limit,
        }),
      ])

      // Combine and sort by date
      const activities: BaseActivity[] = [
        ...calls.data.map(c => ({ 
          id: c.id || '',
          name: c.name,
          status: c.status || '',
          type: 'call' as ActivityType,
          date_entered: '',
          date_modified: '',
          parent_type: c.parent_type,
          parent_id: c.parent_id,
          description: c.description
        })),
        ...meetings.data.map(m => ({ 
          id: m.id || '',
          name: m.name,
          status: m.status || '',
          type: 'meeting' as ActivityType,
          date_entered: '',
          date_modified: '',
          parent_type: m.parent_type,
          parent_id: m.parent_id,
          description: m.description
        })),
        ...tasks.data.map(t => ({ 
          id: t.id || '',
          name: t.name,
          status: t.status || '',
          type: 'task' as ActivityType,
          date_entered: '',
          date_modified: '',
          parent_type: t.parent_type,
          parent_id: t.parent_id,
          description: t.description,
          priority: t.priority
        })),
      ].sort((_a, _b) => {
        // Sort by relevant date field
        const dateA = new Date('')
        const dateB = new Date('')
        return dateA.getTime() - dateB.getTime()
      })

      return activities.slice(0, limit)
    },
  })
}

export function useOverdueTasks() {
  return useQuery({
    queryKey: ['tasks', 'overdue'],
    queryFn: async () => {
      // This would need backend support for date filtering
      const response = await apiClient.getTasks({
        limit: 100,
        // filter: `date_due<${today} AND status!=Completed`,
      })
      
      // Client-side filtering for now
      return {
        ...response,
        data: response.data.filter(t => {
          if (!t.date_due) return false
          if (t.status === 'Completed') return false
          return new Date(t.date_due) < new Date()
        })
      }
    },
  })
}

export function useActivityMetrics() {
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  return useQuery({
    queryKey: ['activities', 'metrics'],
    queryFn: async () => {
      const [calls, meetings, tasks] = await Promise.all([
        apiClient.getCalls({ limit: 100 }),
        apiClient.getMeetings({ limit: 100 }),
        apiClient.getTasks({ limit: 100 }),
      ])

      // Filter today's activities (would be better with backend support)
      const callsToday = calls.data.filter((c) => {
        if (!c.date_start) return false
        const callDate = new Date(c.date_start)
        callDate.setHours(0, 0, 0, 0)
        return callDate.getTime() === today.getTime()
      }).length

      const meetingsToday = meetings.data.filter((m) => {
        if (!m.date_start) return false
        const meetingDate = new Date(m.date_start)
        meetingDate.setHours(0, 0, 0, 0)
        return meetingDate.getTime() === today.getTime()
      }).length

      // Count overdue tasks
      const tasksOverdue = tasks.data.filter(task => {
        if (!task.date_due) return false
        if (task.status === 'Completed') return false
        return new Date(task.date_due) < new Date()
      }).length

      // Get upcoming activities
      const upcomingActivities: BaseActivity[] = [
        ...calls.data.slice(0, 10).map(c => ({ 
          id: c.id || '',
          name: c.name,
          status: c.status || '',
          type: 'call' as ActivityType,
          date_entered: '',
          date_modified: '',
          parent_type: c.parent_type,
          parent_id: c.parent_id,
          description: c.description
        })),
        ...meetings.data.slice(0, 10).map(m => ({ 
          id: m.id || '',
          name: m.name,
          status: m.status || '',
          type: 'meeting' as ActivityType,
          date_entered: '',
          date_modified: '',
          parent_type: m.parent_type,
          parent_id: m.parent_id,
          description: m.description
        }))
      ]

      return {
        callsToday,
        meetingsToday,
        tasksOverdue,
        upcomingActivities,
      }
    },
  })
}

// Hook to get activities filtered by parent entity
export function useActivitiesByParent(parent_type: string, parent_id: string) {
  return useQuery({
    queryKey: ['activities', 'byParent', parent_type, parent_id],
    queryFn: async () => {
      // Fetch all activity types with parent filters
      // Fetch all activity types with parent filters
      const filters = [
        { field: 'parent_type', operator: 'eq' as const, value: parent_type },
        { field: 'parent_id', operator: 'eq' as const, value: parent_id }
      ]
      
      const [callsResponse, meetingsResponse, tasksResponse, notesResponse] = await Promise.all([
        apiClient.getCalls({ limit: 50, filter: filters }),
        apiClient.getMeetings({ limit: 50, filter: filters }),
        apiClient.getTasks({ limit: 50, filter: filters }),
        apiClient.getNotes({ limit: 50, filter: filters }),
      ])

      // Combine all activities into a single array with type tag
      const activities = [
        ...callsResponse.data.map(call => ({
          ...call,
          type: 'call' as const,
        })),
        ...meetingsResponse.data.map(meeting => ({
          ...meeting,
          type: 'meeting' as const,
        })),
        ...tasksResponse.data.map(task => ({
          ...task,
          type: 'task' as const,
        })),
        ...notesResponse.data.map(note => ({
          ...note,
          type: 'note' as const,
          status: 'completed' as const, // Notes don't have status, so we default to completed
        })),
      ]

      // Sort by date, most recent first
      return activities.sort((a, b) => {
        const dateA = new Date(a.date_entered || 0)
        const dateB = new Date(b.date_entered || 0)
        return dateB.getTime() - dateA.getTime()
      })
    },
    enabled: !!parent_type && !!parent_id,
  })
}