import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'

// Get dashboard metrics from custom API
export function useDashboardMetrics() {
  return useQuery({
    queryKey: ['dashboard-metrics'],
    queryFn: async () => {
      const response = await apiClient.getDashboardMetrics()
      if (response.success && response.data) {
        // Transform snake_case to camelCase
        const rawData = response.data as any
        return {
          ...response,
          data: {
            totalLeads: rawData.total_leads || rawData.totalLeads || 0,
            totalAccounts: rawData.total_accounts || rawData.totalAccounts || 0,
            newLeadsToday: rawData.new_leads_today || rawData.newLeadsToday || 0,
            pipelineValue: rawData.pipeline_value || rawData.pipelineValue || 0,
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
  return useQuery({
    queryKey: ['dashboard-pipeline'],
    queryFn: async () => {
      const response = await apiClient.getPipelineData()
      if (!response.success || !response.data) return []
      
      // Transform the API response to match the expected format
      return response.data.stages.map(stage => ({
        stage: stage.stage,
        count: stage.count,
        value: stage.value,
        opportunities: [] // Not included in the API response for performance
      }))
    },
  })
}

// Get activity metrics from custom API
export function useActivityMetrics() {
  return useQuery({
    queryKey: ['dashboard-activities'],
    queryFn: async () => {
      const response = await apiClient.getActivityMetrics()
      if (response.success && response.data) {
        // Transform snake_case to camelCase
        const rawData = response.data as any
        return {
          ...response,
          data: {
            callsToday: rawData.calls_today || rawData.callsToday || 0,
            meetingsToday: rawData.meetings_today || rawData.meetingsToday || 0,
            tasksOverdue: rawData.tasks_overdue || rawData.tasksOverdue || 0,
            upcomingActivities: rawData.upcoming_activities || rawData.upcomingActivities || [],
          }
        }
      }
      return response
    },
  })
}

// Get case metrics from custom API
export function useCaseMetrics() {
  return useQuery({
    queryKey: ['dashboard-cases'],
    queryFn: async () => {
      const response = await apiClient.getCaseMetrics()
      if (response.success && response.data) {
        // Transform snake_case to camelCase
        const rawData = response.data as any
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
  return useQuery({
    queryKey: ['dashboard-recent-activities'],
    queryFn: async () => {
      // Get upcoming activities from the custom API
      const response = await apiClient.getActivityMetrics()
      if (!response.success || !response.data) return []
      
      // Transform upcoming activities into the expected format
      const rawData = response.data as any
      const activities = rawData.upcomingActivities || rawData.upcoming_activities || []
      return activities.slice(0, 10).map((activity: any) => ({
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