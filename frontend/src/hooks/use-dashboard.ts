import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import type { DashboardMetrics, ActivityMetrics } from '@/types/api.types'
import { useAuthStore } from '@/stores/auth-store'

// Get dashboard metrics from custom API
export function useDashboardMetrics() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-metrics'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getDashboardMetrics()
      if (response.success && response.data) {
        // Transform snake_case to camelCase
        const rawData = response.data as DashboardMetrics & Record<string, unknown>
        return {
          ...response,
          data: {
            totalLeads: rawData.totalLeads || 0,
            totalAccounts: rawData.totalAccounts || 0,
            newLeadsToday: rawData.newLeadsToday || 0,
            pipelineValue: rawData.pipelineValue || 0,
          }
        }
      }
      return response
    },
  })
}

// Legacy wrapper for backward compatibility
export function useDashboardStats() {
  return useDashboardMetrics()
}

// Get pipeline data from custom API
export function usePipelineData() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-pipeline'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getPipelineData()
      if (!response.success || !response.data) return []
      
      // The API returns an array of pipeline data directly
      return response.data
    },
  })
}

// Get activity metrics from custom API
export function useActivityMetrics() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-activities'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getActivityMetrics()
      if (response.success && response.data) {
        // Transform snake_case to camelCase
        const rawData = response.data as ActivityMetrics & Record<string, unknown>
        return {
          ...response,
          data: {
            callsToday: rawData.callsToday || 0,
            meetingsToday: rawData.meetingsToday || 0,
            tasksOverdue: rawData.tasksOverdue || 0,
            upcomingActivities: rawData.upcomingActivities || [],
          }
        }
      }
      return response
    },
  })
}

// Get case metrics from custom API
export function useCaseMetrics() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-cases'],
    enabled: isAuthenticated,
    queryFn: async () => {
      const response = await apiClient.getCaseMetrics()
      if (response.success && response.data) {
        // Transform snake_case to camelCase
        interface CaseMetricsRaw {
          open_cases?: number;
          openCases?: number;
          critical_cases?: number;
          criticalCases?: number;
          avg_resolution_time?: number;
          avgResolutionTime?: number;
          cases_by_priority?: Array<{ priority: string; count: number }>;
          casesByPriority?: Array<{ priority: string; count: number }>;
        }
        const rawData = response.data as CaseMetricsRaw
        return {
          ...response,
          data: {
            openCases: rawData.open_cases || rawData.openCases || 0,
            criticalCases: rawData.critical_cases || rawData.criticalCases || 0,
            avgResolutionTime: rawData.avg_resolution_time || rawData.avgResolutionTime || 0,
            casesByPriority: rawData.cases_by_priority || rawData.casesByPriority || [],
          }
        }
      }
      return response
    },
  })
}

// Get recent activity data
export function useRecentActivities() {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  
  return useQuery({
    queryKey: ['dashboard-recent-activities'],
    enabled: isAuthenticated,
    queryFn: async () => {
      // Get upcoming activities from the custom API
      const response = await apiClient.getActivityMetrics()
      if (!response.success || !response.data) return []
      
      // Transform upcoming activities into the expected format
      const rawData = response.data as ActivityMetrics
      interface Activity {
        id: string;
        type?: string;
        name: string;
        status?: string;
        priority?: string;
        date_start?: string;
        dateEntered?: string;
        date_entered?: string;
      }
      const activities = (rawData.upcomingActivities || []) as Activity[]
      return activities.slice(0, 10).map((activity) => ({
        id: activity.id,
        type: activity.type || 'Task' as const,
        name: activity.name,
        description: `${activity.status} - ${activity.priority || 'Normal'} priority`,
        date: activity.date_start || activity.dateEntered || activity.date_entered,
        icon: activity.type === 'Call' ? 'Phone' : 
              activity.type === 'Meeting' ? 'Calendar' : 
              activity.type === 'Note' ? 'FileText' : 'CheckCircle2',
      }))
    },
  })
}

// Combine all dashboard data
export function useDashboardData() {
  const metrics = useDashboardMetrics()
  const pipeline = usePipelineData()
  const activityMetrics = useActivityMetrics()
  const caseMetrics = useCaseMetrics()
  const recentActivities = useRecentActivities()
  
  return {
    stats: metrics, // Keep as 'stats' for backward compatibility
    metrics,
    pipeline,
    activityMetrics,
    caseMetrics,
    recentActivities,
    isLoading: metrics.isLoading || pipeline.isLoading || activityMetrics.isLoading || caseMetrics.isLoading,
  }
}