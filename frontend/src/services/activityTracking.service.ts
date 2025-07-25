import { apiClient } from '@/lib/api-client';
import type { 
  WebsiteSession, 
  ActivityHeatmap,
  TrackingEvent
} from '@/types/phase3.types';

class ActivityTrackingService {
  private visitorId: string | null = null;
  private sessionId: string | null = null;
  private trackingEnabled: boolean = true;
  private pageStartTime: number = Date.now();
  private scrollDepth: number = 0;
  private clickCount: number = 0;

  constructor() {
    // Initialize visitor and session IDs
    this.visitorId = localStorage.getItem('crm_visitor_id');
    this.sessionId = sessionStorage.getItem('crm_session_id');
    
    // Set up tracking listeners if enabled
    if (this.trackingEnabled) {
      this.initializeTracking();
    }
  }

  /**
   * Initialize tracking listeners
   */
  private initializeTracking() {
    // Track scroll depth
    let scrollTimer: NodeJS.Timeout;
    window.addEventListener('scroll', () => {
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(() => {
        const scrollPercent = Math.round(
          (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
        );
        this.scrollDepth = Math.max(this.scrollDepth, scrollPercent);
      }, 100);
    });

    // Track clicks
    document.addEventListener('click', () => {
      this.clickCount++;
    });

    // Track page unload
    window.addEventListener('beforeunload', () => {
      this.trackPageExit();
    });
  }

  /**
   * Track a page view
   */
  async trackPageView(data?: {
    page_url?: string;
    title?: string;
    referrer?: string;
    custom_data?: Record<string, any>;
  }): Promise<void> {
    try {
      // End previous page tracking
      if (this.sessionId) {
        await this.trackPageExit();
      }

      // Reset metrics
      this.pageStartTime = Date.now();
      this.scrollDepth = 0;
      this.clickCount = 0;

      const response = await apiClient.publicPost('/track/pageview', {
        visitor_id: this.visitorId || undefined,
        session_id: this.sessionId || undefined,
        page_url: data?.page_url || window.location.pathname + window.location.search,
        title: data?.title || document.title,
        referrer: data?.referrer || document.referrer,
        user_agent: navigator.userAgent,
        screen_resolution: `${window.screen.width}x${window.screen.height}`,
        viewport_size: `${window.innerWidth}x${window.innerHeight}`,
        custom_data: data?.custom_data
      });
      
      // Store visitor/session IDs if new
      if (response.data?.visitor_id) {
        this.visitorId = response.data.visitor_id;
        localStorage.setItem('crm_visitor_id', this.visitorId!);
      }
      if (response.data?.session_id) {
        this.sessionId = response.data.session_id;
        sessionStorage.setItem('crm_session_id', this.sessionId!);
      }
    } catch (error) {
      console.error('Failed to track page view:', error);
    }
  }

  /**
   * Track page exit with metrics
   */
  private async trackPageExit(): Promise<void> {
    if (!this.sessionId) return;

    const duration = Math.round((Date.now() - this.pageStartTime) / 1000);
    
    try {
      await apiClient.publicPost('/track/page-exit', {
        visitor_id: this.visitorId || undefined,
        session_id: this.sessionId || undefined,
        duration,
        scroll_depth: this.scrollDepth,
        clicks: this.clickCount
      });
    } catch {
      // Use sendBeacon as fallback for page unload
      const data = JSON.stringify({
        visitor_id: this.visitorId || undefined,
        session_id: this.sessionId || undefined,
        duration,
        scroll_depth: this.scrollDepth,
        clicks: this.clickCount
      });
      navigator.sendBeacon('/api/track/page-exit', data);
    }
  }

  /**
   * Track a conversion event
   */
  async trackConversion(event: string, value?: any, metadata?: Record<string, any>): Promise<void> {
    try {
      await apiClient.publicPost('/track/conversion', {
        visitor_id: this.visitorId || undefined,
        session_id: this.sessionId || undefined,
        event,
        value,
        metadata,
        page_url: window.location.pathname,
        timestamp: new Date().toISOString()
      });
    } catch (error) {
      console.error('Failed to track conversion:', error);
    }
  }

  /**
   * Track a custom event
   */
  async trackEvent(event: TrackingEvent): Promise<void> {
    try {
      await apiClient.publicPost('/track/event', {
        ...event,
        visitor_id: event.visitor_id || this.visitorId,
        session_id: event.session_id || this.sessionId
      });
    } catch {
      console.error('Failed to track event');
    }
  }

  /**
   * Identify visitor with lead/contact
   */
  async identifyVisitor(leadId?: string, contactId?: string, customData?: Record<string, any>): Promise<void> {
    if (!this.visitorId) return;
    
    try {
      await apiClient.customPost('/track/identify', {
        visitor_id: this.visitorId!,
        lead_id: leadId,
        contact_id: contactId,
        custom_data: customData
      });
    } catch (error) {
      console.error('Failed to identify visitor:', error);
    }
  }

  // Analytics Methods (Authenticated)

  /**
   * Get website sessions
   */
  async getSessions(params?: {
    page?: number;
    limit?: number;
    lead_id?: string;
    contact_id?: string;
    active_only?: boolean;
    start_date?: string;
    end_date?: string;
  }): Promise<{ 
    data: WebsiteSession[]; 
    total: number;
    page: number;
    limit: number;
  }> {
    const response = await apiClient.customGet('/analytics/visitors', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch sessions');
    }
    return response.data;
  }

  /**
   * Get session details
   */
  async getSession(id: string): Promise<WebsiteSession> {
    const response = await apiClient.customGet(`/analytics/visitors/${id}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch session');
    }
    return response.data;
  }

  /**
   * Get live visitors
   */
  async getLiveVisitors(): Promise<WebsiteSession[]> {
    const response = await apiClient.customGet('/analytics/visitors/live');
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch live visitors');
    }
    return response.data;
  }

  /**
   * Get visitor timeline
   */
  async getVisitorTimeline(visitorId: string): Promise<{
    visitor_id: string;
    first_seen: string;
    last_seen: string;
    total_sessions: number;
    total_page_views: number;
    average_session_duration: number;
    sessions: WebsiteSession[];
  }> {
    const response = await apiClient.customGet(`/analytics/visitors/${visitorId}/timeline`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch visitor timeline');
    }
    return response.data;
  }

  /**
   * Get heatmap data for a page
   */
  async getHeatmapData(pageUrl: string, params?: { 
    start_date?: string; 
    end_date?: string;
    device_type?: 'all' | 'desktop' | 'mobile' | 'tablet';
  }): Promise<ActivityHeatmap> {
    const response = await apiClient.customGet('/analytics/heatmap', {
      params: { page_url: pageUrl, ...params }
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch heatmap data');
    }
    return response.data;
  }

  /**
   * Get page analytics
   */
  async getPageAnalytics(params?: {
    page_url?: string;
    start_date?: string;
    end_date?: string;
  }): Promise<{
    total_views: number;
    unique_visitors: number;
    average_time_on_page: number;
    bounce_rate: number;
    exit_rate: number;
    average_scroll_depth: number;
    top_referrers: { source: string; count: number }[];
    device_breakdown: { type: string; count: number; percentage: number }[];
  }> {
    const response = await apiClient.customGet('/analytics/pages', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch page analytics');
    }
    return response.data;
  }

  /**
   * Get conversion funnel analytics
   */
  async getFunnelAnalytics(steps: string[], params?: {
    start_date?: string;
    end_date?: string;
  }): Promise<{
    steps: {
      name: string;
      visitors: number;
      conversion_rate: number;
      drop_off_rate: number;
    }[];
    overall_conversion_rate: number;
  }> {
    const response = await apiClient.customPost('/analytics/funnel', {
      steps,
      ...params
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch funnel analytics');
    }
    return response.data;
  }

  /**
   * Export tracking data
   */
  async exportData(format: 'csv' | 'xlsx' | 'json', params?: {
    type: 'sessions' | 'events' | 'conversions';
    start_date?: string;
    end_date?: string;
    lead_id?: string;
  }): Promise<Blob> {
    const response = await apiClient.customGet('/analytics/export', {
      params: { format, ...params },
      responseType: 'blob'
    });
    return response;
  }

  /**
   * Enable/disable tracking
   */
  setTrackingEnabled(enabled: boolean): void {
    this.trackingEnabled = enabled;
    if (enabled) {
      this.initializeTracking();
    }
  }

  /**
   * Get current visitor ID
   */
  getVisitorId(): string | null {
    return this.visitorId;
  }

  /**
   * Get current session ID
   */
  getSessionId(): string | null {
    return this.sessionId;
  }
}

export const activityTrackingService = new ActivityTrackingService();