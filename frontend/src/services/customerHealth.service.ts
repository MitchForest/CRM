import { apiClient } from '@/lib/api-client';
import type { 
  HealthScore, 
  HealthDashboard,
  HealthMetric
} from '@/types/phase3.types';

class CustomerHealthService {
  /**
   * Calculate health score for an account
   */
  async calculateHealthScore(accountId: string): Promise<HealthScore> {
    const response = await apiClient.customPost(`/accounts/${accountId}/health-score`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to calculate health score');
    }
    return response.data;
  }

  /**
   * Get current health score for an account
   */
  async getHealthScore(accountId: string): Promise<HealthScore> {
    const response = await apiClient.customGet(`/accounts/${accountId}/health-score`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch health score');
    }
    return response.data;
  }

  /**
   * Get health score history for an account
   */
  async getHealthHistory(accountId: string, params?: {
    start_date?: string;
    end_date?: string;
    limit?: number;
  }): Promise<HealthScore[]> {
    const response = await apiClient.customGet(`/accounts/${accountId}/health-score-history`, { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch health history');
    }
    return response.data;
  }

  /**
   * Get at-risk accounts
   */
  async getAtRiskAccounts(params?: {
    risk_level?: 'medium' | 'high' | 'critical';
    page?: number;
    limit?: number;
    sort_by?: 'score' | 'trend' | 'mrr' | 'last_activity';
    order?: 'asc' | 'desc';
  }): Promise<{
    data: Array<{
      account_id: string;
      account_name: string;
      health_score: HealthScore;
      mrr?: number;
      last_activity?: string;
      assigned_to?: string;
    }>;
    total: number;
    page: number;
    limit: number;
  }> {
    const response = await apiClient.customGet('/accounts/at-risk', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch at-risk accounts');
    }
    return response.data;
  }

  /**
   * Get health dashboard metrics
   */
  async getHealthDashboard(params?: {
    start_date?: string;
    end_date?: string;
    segment?: string;
  }): Promise<HealthDashboard> {
    const response = await apiClient.customGet('/analytics/health-dashboard', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch health dashboard');
    }
    return response.data;
  }

  /**
   * Update health score factors/weights
   */
  async updateHealthMetrics(metrics: HealthMetric[]): Promise<{
    success: boolean;
    message: string;
  }> {
    const response = await apiClient.customPost('/settings/health-metrics', { metrics });
    if (!response.success) {
      throw new Error(response.error || 'Failed to update health metrics');
    }
    return response.data;
  }

  /**
   * Get health score configuration
   */
  async getHealthConfig(): Promise<{
    metrics: HealthMetric[];
    thresholds: {
      critical: number;
      high: number;
      medium: number;
      low: number;
    };
    calculation_frequency: string;
  }> {
    const response = await apiClient.customGet('/settings/health-config');
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch health configuration');
    }
    return response.data;
  }

  /**
   * Trigger health check webhook
   */
  async triggerHealthCheck(accountIds?: string[]): Promise<{
    success: boolean;
    processed: number;
    message: string;
  }> {
    const response = await apiClient.customPost('/webhooks/health-check', { 
      account_ids: accountIds 
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to trigger health check');
    }
    return response.data;
  }

  /**
   * Get health trends
   */
  async getHealthTrends(params?: {
    period: 'daily' | 'weekly' | 'monthly';
    start_date?: string;
    end_date?: string;
    segment?: string;
  }): Promise<{
    trends: Array<{
      date: string;
      average_score: number;
      healthy_count: number;
      at_risk_count: number;
      critical_count: number;
      improved_count: number;
      declined_count: number;
    }>;
    summary: {
      current_average: number;
      previous_average: number;
      change_percentage: number;
      total_improved: number;
      total_declined: number;
    };
  }> {
    const response = await apiClient.customGet('/analytics/health-trends', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch health trends');
    }
    return response.data;
  }

  /**
   * Get health alerts for an account
   */
  async getHealthAlerts(accountId: string, params?: {
    status?: 'active' | 'resolved' | 'all';
    limit?: number;
  }): Promise<Array<{
    id: string;
    type: 'warning' | 'danger' | 'info';
    message: string;
    created_at: string;
    resolved_at?: string;
    metadata?: Record<string, any>;
  }>> {
    const response = await apiClient.customGet(`/accounts/${accountId}/health-alerts`, { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch health alerts');
    }
    return response.data;
  }

  /**
   * Resolve a health alert
   */
  async resolveAlert(accountId: string, alertId: string, notes?: string): Promise<{
    success: boolean;
    message: string;
  }> {
    const response = await apiClient.customPost(`/accounts/${accountId}/health-alerts/${alertId}/resolve`, { 
      notes 
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to resolve alert');
    }
    return response.data;
  }

  /**
   * Get recommended actions for an account
   */
  async getRecommendedActions(accountId: string): Promise<{
    immediate_actions: string[];
    preventive_actions: string[];
    growth_opportunities: string[];
  }> {
    const response = await apiClient.customGet(`/accounts/${accountId}/health-recommendations`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch recommendations');
    }
    return response.data;
  }

  /**
   * Export health data
   */
  async exportHealthData(format: 'csv' | 'xlsx' | 'pdf', params?: {
    type: 'dashboard' | 'at-risk' | 'trends';
    start_date?: string;
    end_date?: string;
    account_ids?: string[];
  }): Promise<Blob> {
    const response = await apiClient.customGet('/analytics/health-export', {
      params: { format, ...params },
      responseType: 'blob'
    });
    return response;
  }

  /**
   * Calculate health score impact of an event
   */
  calculateEventImpact(currentScore: number, event: {
    type: 'support_ticket' | 'payment_failed' | 'user_churn' | 'feature_adoption';
    severity: 'low' | 'medium' | 'high';
  }): {
    new_score: number;
    impact: number;
    description: string;
  } {
    const impactMap = {
      support_ticket: { low: -2, medium: -5, high: -10 },
      payment_failed: { low: -5, medium: -10, high: -20 },
      user_churn: { low: -3, medium: -7, high: -15 },
      feature_adoption: { low: 2, medium: 5, high: 10 }
    };

    const impact = impactMap[event.type][event.severity];
    const newScore = Math.max(0, Math.min(100, currentScore + impact));

    return {
      new_score: newScore,
      impact,
      description: `${event.type.replace(/_/g, ' ')} (${event.severity} severity)`
    };
  }

  /**
   * Get health score color and label
   */
  getHealthStatus(score: number): {
    color: string;
    label: string;
    icon: string;
  } {
    if (score >= 80) {
      return { color: 'green', label: 'Healthy', icon: 'check-circle' };
    } else if (score >= 60) {
      return { color: 'yellow', label: 'Needs Attention', icon: 'alert-circle' };
    } else if (score >= 40) {
      return { color: 'orange', label: 'At Risk', icon: 'alert-triangle' };
    } else {
      return { color: 'red', label: 'Critical', icon: 'x-circle' };
    }
  }
}

export const customerHealthService = new CustomerHealthService();