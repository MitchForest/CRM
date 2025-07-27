import { apiClient } from '@/lib/api-client';
import type { 
  AIScoreResult, 
  AIScoreHistory, 
  ChatSession,
  KBSearchResult
} from '@/types/api.types';

class AIService {
  /**
   * Start a public chat session (for visitors)
   */
  async startPublicChat(visitorId: string): Promise<{
    conversation_id: string;
    visitor_id: string;
    status: string;
  }> {
    const response = await apiClient.publicPost('/public/chat/start', {
      visitor_id: visitorId
    });
    
    if (!response.success) {
      throw new Error(response.error || 'Failed to start chat');
    }
    return response.data;
  }

  /**
   * Send a message in public chat (for visitors)
   */
  async sendPublicChatMessage(
    conversationId: string,
    message: string
  ): Promise<{
    message: string;
    handoff_required: boolean;
    confidence: number;
  }> {
    const response = await apiClient.publicPost('/public/chat/message', {
      conversation_id: conversationId,
      message
    });
    
    if (!response.success) {
      throw new Error(response.error || 'Failed to send message');
    }
    return response.data;
  }

  /**
   * Get public chat conversation history
   */
  async getPublicChatHistory(sessionId: string): Promise<ChatSession> {
    const response = await apiClient.publicGet(`/public/chat/conversation/${sessionId}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to get chat history');
    }
    return response.data;
  }

  /**
   * Calculate AI score for a single lead
   */
  async scoreLead(leadId: string): Promise<AIScoreResult> {
    const response = await apiClient.customPost(`/leads/${leadId}/ai-score`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to score lead');
    }
    return response.data;
  }

  /**
   * Batch score multiple leads
   */
  async batchScoreLeads(leadIds: string[]): Promise<Record<string, AIScoreResult>> {
    const response = await apiClient.customPost('/leads/ai-score-batch', { lead_ids: leadIds });
    if (!response.success) {
      throw new Error(response.error || 'Failed to batch score leads');
    }
    return response.data;
  }

  /**
   * Get AI score history for a lead
   */
  async getScoreHistory(leadId: string): Promise<AIScoreHistory[]> {
    const response = await apiClient.customGet(`/leads/${leadId}/score-history`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to get score history');
    }
    return response.data;
  }

  /**
   * Send a chat message to the AI chatbot
   */
  async sendChatMessage(
    conversationId: string | null, 
    message: string, 
    visitorId?: string
  ): Promise<{
    conversation_id: string;
    message: string;
    intent?: string;
    sentiment?: string;
    confidence?: number;
    suggested_actions?: string[];
    metadata?: Record<string, unknown>;
  }> {
    const response = await apiClient.customPost('/crm/ai/chat', {
      conversation_id: conversationId,
      message,
      visitor_id: visitorId || localStorage.getItem('crm_visitor_id')
    });
    
    if (!response.success) {
      throw new Error(response.error || 'Failed to send chat message');
    }
    // The API returns the data at the top level, not nested in .data
    return response as {
      conversation_id: string;
      message: string;
      response?: string;
      intent?: string;
      sentiment?: string;
      confidence?: number;
      suggested_actions?: string[];
      metadata?: Record<string, unknown>;
    };
  }

  /**
   * Get chat conversation history
   */
  async getChatHistory(conversationId: string): Promise<ChatSession> {
    const response = await apiClient.customGet(`/crm/ai/chat/${conversationId}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to get chat history');
    }
    return response.data;
  }

  /**
   * Search knowledge base using AI
   */
  async searchKnowledgeBase(query: string, limit = 5): Promise<KBSearchResult[]> {
    const response = await apiClient.customPost('/knowledge-base/search', {
      query,
      limit
    });
    
    if (!response.success) {
      throw new Error(response.error || 'Failed to search knowledge base');
    }
    return response.data;
  }

  /**
   * Get AI recommendations for a lead
   */
  async getLeadRecommendations(leadId: string): Promise<{
    next_actions: string[];
    talking_points: string[];
    risk_factors: string[];
  }> {
    const response = await apiClient.customGet(`/leads/${leadId}/ai-recommendations`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to get recommendations');
    }
    return response.data;
  }

  /**
   * Analyze sentiment of customer interaction
   */
  async analyzeSentiment(text: string, context?: string): Promise<{
    sentiment: 'positive' | 'neutral' | 'negative';
    confidence: number;
    keywords: string[];
  }> {
    // Note: Sentiment analysis endpoint not implemented in backend yet
    throw new Error('Sentiment analysis not available');
    const response = await apiClient.customPost('/crm/ai/sentiment', {
      text,
      context
    });
    
    if (!response.success) {
      throw new Error(response.error || 'Failed to analyze sentiment');
    }
    return response.data;
  }

  /**
   * Create a support ticket through AI
   */
  async createSupportTicket(issue: string, userInfo?: {
    name?: string;
    email?: string;
    company?: string;
  }): Promise<{
    ticketId: string;
    message: string;
    ticket: {
      id: string;
      case_number: string;
      name: string;
      status: string;
      priority: string;
    };
  }> {
    // Note: Create ticket endpoint not implemented in backend routes yet
    throw new Error('Ticket creation through AI not available');
    const response = await apiClient.customPost('/crm/ai/create-ticket', {
      issue,
      userInfo
    });
    
    if (!response.success) {
      throw new Error(response.error || 'Failed to create support ticket');
    }
    return response.data;
  }
}

export const aiService = new AIService();