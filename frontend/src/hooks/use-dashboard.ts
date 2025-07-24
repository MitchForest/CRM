import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'

// Get dashboard metrics from custom API
export function useDashboardMetrics() {
  return useQuery({
    queryKey: ['dashboard-metrics'],
    queryFn: () => apiClient.getDashboardMetrics(),
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
    queryFn: () => apiClient.getActivityMetrics(),
  })
}

// Get case metrics from custom API
export function useCaseMetrics() {
  return useQuery({
    queryKey: ['dashboard-cases'],
    queryFn: () => apiClient.getCaseMetrics(),
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
      return response.data.upcomingActivities.slice(0, 10).map(activity => ({
        id: activity.id,
        type: activity.type || 'Task' as const,
        name: activity.name,
        description: `${activity.status} - ${activity.priority || 'Normal'} priority`,
        date: activity.dateEntered,
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