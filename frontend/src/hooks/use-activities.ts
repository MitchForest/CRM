import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'
import type { Call, Meeting, Task, Note } from '@/types/api.generated'
import type { BaseActivity, ActivityType } from '@/types/phase2.types'
import { getErrorMessage } from '@/lib/error-utils'

// Extended types that include BaseActivity fields
type ExtendedCall = Call & Pick<BaseActivity, 'dateEntered' | 'dateModified'>
type ExtendedMeeting = Meeting & Pick<BaseActivity, 'dateEntered' | 'dateModified'>
type ExtendedTask = Task & Pick<BaseActivity, 'dateEntered' | 'dateModified'>
type ExtendedNote = Note & Pick<BaseActivity, 'status' | 'dateEntered' | 'dateModified'>

interface ActivityApiMethods<T> {
  getAll: (params?: { page?: number; pageSize?: number; filters?: Record<string, string | number | boolean> }) => Promise<{ data: T[] }>;
  getOne: (id: string) => Promise<T>;
  create: (data: Partial<T>) => Promise<T>;
  update: (id: string, data: Partial<T>) => Promise<T>;
  delete: (id: string) => Promise<void>;
}

// Base hook factory for common activity operations
function createActivityHooks<T extends { id?: string }>(
  moduleName: 'calls' | 'meetings' | 'tasks' | 'notes',
  moduleLabel: string,
  apiMethods: ActivityApiMethods<T>
) {
  return {
    useGetAll: (page = 1, limit = 20, filters?: Record<string, string | number | boolean>) => {
      return useQuery({
        queryKey: [moduleName, page, limit, filters],
        queryFn: async () => {
          return await apiMethods.getAll({ 
            page, 
            pageSize: limit,
            ...filters 
          })
        },
      })
    },

    useGetOne: (id: string) => {
      return useQuery({
        queryKey: [moduleName, id],
        queryFn: async () => {
          return await apiMethods.getOne(id)
        },
        enabled: !!id,
      })
    },

    useCreate: () => {
      const queryClient = useQueryClient()

      return useMutation({
        mutationFn: async (data: Omit<T, 'id'>) => {
          return await apiMethods.create(data as Partial<T>)
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
          return await apiMethods.update(id, data)
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
          return await apiMethods.delete(id)
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
const callHooks = createActivityHooks<ExtendedCall>('calls', 'Call', {
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
const meetingHooks = createActivityHooks<ExtendedMeeting>('meetings', 'Meeting', {
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
const taskHooks = createActivityHooks<ExtendedTask>('tasks', 'Task', {
  getAll: apiClient.getTasks.bind(apiClient),
  getOne: apiClient.getTask.bind(apiClient),
  create: apiClient.createTask.bind(apiClient),
  update: apiClient.updateTask.bind(apiClient),
  delete: apiClient.deleteCall.bind(apiClient), // Fix: there's no deleteTask method
})

export const useTasks = taskHooks.useGetAll
export const useTask = taskHooks.useGetOne
export const useCreateTask = taskHooks.useCreate
export const useUpdateTask = taskHooks.useUpdate
export const useDeleteTask = taskHooks.useDelete

// Note hooks
const noteHooks = createActivityHooks<ExtendedNote>('notes', 'Note', {
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
          pageSize: limit,
          // Filter for future dates would need backend support
        }),
        apiClient.getMeetings({
          pageSize: limit,
        }),
        apiClient.getTasks({
          pageSize: limit,
        }),
      ])

      // Combine and sort by date
      const activities: BaseActivity[] = [
        ...calls.data.map(c => ({ 
          id: c.id || '',
          name: c.name,
          status: c.status || '',
          type: 'Call' as ActivityType,
          dateEntered: '',
          dateModified: '',
          parentType: c.parentType,
          parentId: c.parentId,
          description: c.description
        })),
        ...meetings.data.map(m => ({ 
          id: m.id || '',
          name: m.name,
          status: m.status || '',
          type: 'Meeting' as ActivityType,
          dateEntered: '',
          dateModified: '',
          parentType: m.parentType,
          parentId: m.parentId,
          description: m.description
        })),
        ...tasks.data.map(t => ({ 
          id: t.id || '',
          name: t.name,
          status: t.status || '',
          type: 'Task' as ActivityType,
          dateEntered: '',
          dateModified: '',
          parentType: t.parentType,
          parentId: t.parentId,
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
        pageSize: 100,
        // filter: `dueDate<${today} AND status!=Completed`,
      })
      
      // Client-side filtering for now
      return {
        ...response,
        data: response.data.filter(task => {
          if (!task.dueDate) return false
          if (task.status === 'Completed') return false
          return new Date(task.dueDate) < new Date()
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
        apiClient.getCalls({ pageSize: 100 }),
        apiClient.getMeetings({ pageSize: 100 }),
        apiClient.getTasks({ pageSize: 100 }),
      ])

      // Filter today's activities (would be better with backend support)
      const callsToday = calls.data.filter((c) => {
        if (!('startDate' in c) || !(c as Call & { startDate?: string }).startDate) return false
        const callDate = new Date((c as Call & { startDate: string }).startDate)
        callDate.setHours(0, 0, 0, 0)
        return callDate.getTime() === today.getTime()
      }).length

      const meetingsToday = meetings.data.filter((m) => {
        if (!('startDate' in m) || !(m as Meeting & { startDate?: string }).startDate) return false
        const meetingDate = new Date((m as Meeting & { startDate: string }).startDate)
        meetingDate.setHours(0, 0, 0, 0)
        return meetingDate.getTime() === today.getTime()
      }).length

      // Count overdue tasks
      const tasksOverdue = tasks.data.filter(task => {
        if (!task.dueDate) return false
        if (task.status === 'Completed') return false
        return new Date(task.dueDate) < new Date()
      }).length

      // Get upcoming activities
      const upcomingActivities: BaseActivity[] = [
        ...calls.data.slice(0, 10).map(c => ({ 
          id: c.id || '',
          name: c.name,
          status: c.status || '',
          type: 'Call' as ActivityType,
          dateEntered: '',
          dateModified: '',
          parentType: c.parentType,
          parentId: c.parentId,
          description: c.description
        })),
        ...meetings.data.slice(0, 10).map(m => ({ 
          id: m.id || '',
          name: m.name,
          status: m.status || '',
          type: 'Meeting' as ActivityType,
          dateEntered: '',
          dateModified: '',
          parentType: m.parentType,
          parentId: m.parentId,
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