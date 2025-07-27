import { apiClient } from '@/lib/api-client';
import type { 
  KBArticle, 
  KBCategory,
  KBPublicCategory,
  KBSearchResult
} from '@/types/api.types';

class KnowledgeBaseService {
  // Category Management
  
  /**
   * Get all categories (admin)
   */
  async getCategories(): Promise<KBCategory[]> {
    const response = await apiClient.customGet('/admin/knowledge-base/categories');
    // The response IS the data, not wrapped in success/data
    if (!response || !response.data) {
      throw new Error('Failed to fetch categories');
    }
    return response.data;
  }

  /**
   * Get public categories
   */
  async getPublicCategories(): Promise<KBPublicCategory[]> {
    const response = await apiClient.publicGet('/public/kb/categories');
    if (!response || !response.data) {
      throw new Error('Failed to fetch public categories');
    }
    return response.data;
  }

  /**
   * Get category by ID
   */
  async getCategory(id: string): Promise<KBCategory> {
    const response = await apiClient.customGet(`/admin/knowledge-base/categories/${id}`);
    if (!response) {
      throw new Error('Failed to fetch category');
    }
    return response.data;
  }

  /**
   * Create a new category
   */
  async createCategory(data: Partial<KBCategory>): Promise<KBCategory> {
    const response = await apiClient.customPost('/admin/knowledge-base/categories', data);
    if (!response) {
      throw new Error('Failed to create category');
    }
    return response;
  }

  /**
   * Update a category
   */
  async updateCategory(id: string, data: Partial<KBCategory>): Promise<KBCategory> {
    const response = await apiClient.customPut(`/admin/knowledge-base/categories/${id}`, data);
    if (!response) {
      throw new Error('Failed to update category');
    }
    return response;
  }

  /**
   * Delete a category
   */
  async deleteCategory(id: string): Promise<void> {
    const response = await apiClient.customDelete(`/admin/knowledge-base/categories/${id}`);
    if (response === null || response === undefined) {
      throw new Error('Failed to delete category');
    }
  }

  // Article Management

  /**
   * Get all articles with filters
   */
  async getArticles(params?: {
    page?: number;
    limit?: number;
    category_id?: string;
    is_public?: boolean;
    is_featured?: boolean;
    search?: string;
    tags?: string[];
  }): Promise<{ data: KBArticle[]; meta: any }> {
    // Transform params to match backend expectations
    const backendParams = {
      ...params,
      category: params?.category_id,
      is_published: params?.is_public,
    };
    delete backendParams.category_id;
    delete backendParams.is_public;
    
    const response = await apiClient.customGet('/admin/knowledge-base/articles', { params: backendParams });
    if (!response || !response.data) {
      throw new Error('Failed to fetch articles');
    }
    return response;
  }

  /**
   * Get article by ID (admin)
   */
  async getArticle(id: string): Promise<KBArticle> {
    const response = await apiClient.customGet(`/admin/knowledge-base/articles/${id}`);
    if (!response || !response.data) {
      throw new Error('Failed to fetch article');
    }
    return response.data;
  }

  /**
   * Get public article by slug (no auth required)
   */
  async getPublicArticle(slug: string): Promise<KBArticle> {
    const response = await apiClient.publicGet(`/public/kb/articles/${slug}`);
    if (!response || !response.data) {
      throw new Error('Article not found');
    }
    return response.data;
  }

  /**
   * Create a new article
   */
  async createArticle(data: Partial<KBArticle>): Promise<KBArticle> {
    const response = await apiClient.customPost('/admin/knowledge-base/articles', data);
    if (!response || !response.data) {
      throw new Error('Failed to create article');
    }
    return response.data;
  }

  /**
   * Update an article
   */
  async updateArticle(id: string, data: Partial<KBArticle>): Promise<KBArticle> {
    const response = await apiClient.customPut(`/admin/knowledge-base/articles/${id}`, data);
    if (!response || !response.data) {
      throw new Error('Failed to update article');
    }
    return response.data;
  }

  /**
   * Delete an article
   */
  async deleteArticle(id: string): Promise<void> {
    const response = await apiClient.customDelete(`/admin/knowledge-base/articles/${id}`);
    if (response === null || response === undefined) {
      throw new Error('Failed to delete article');
    }
  }

  /**
   * Duplicate an article
   */
  async duplicateArticle(id: string, newTitle?: string): Promise<KBArticle> {
    // First get the original article
    const original = await this.getArticle(id);
    
    // Create a copy with a new title
    const copy = {
      ...original,
      id: undefined,  // Remove ID so a new one is generated
      title: newTitle || `${original.title} (Copy)`,
      slug: undefined,  // Let backend generate new slug
      is_published: false,  // Start as draft
      view_count: 0,
      helpful_count: 0,
      not_helpful_count: 0
    };
    
    // Create the new article
    return this.createArticle(copy);
  }

  /**
   * Rate an article
   */
  async rateArticle(id: string, helpful: boolean): Promise<{
    helpful_yes: number;
    helpful_no: number;
  }> {
    const response = await apiClient.customPost(`/knowledge-base/articles/${id}/rate`, { helpful });
    if (!response) {
      throw new Error('Failed to rate article');
    }
    return response;
  }

  /**
   * Track article view (public endpoint)
   */
  async trackView(_id: string): Promise<void> {
    // The view tracking is handled automatically by the backend when fetching the article
    // No need for a separate API call
  }

  /**
   * Search articles using semantic search
   */
  async searchArticles(query: string, params?: {
    limit?: number;
    category_id?: string;
    is_public?: boolean;
  }): Promise<KBSearchResult[]> {
    const response = await apiClient.customGet('/kb/search', { 
      params: { q: query, ...params }
    });
    if (!response || !response.data) {
      throw new Error('Failed to search articles');
    }
    // Response format: { data: { results: [...], search_type: '...', query: '...' } }
    return response.data.results || [];
  }

  /**
   * Search articles (public)
   */
  async searchPublicArticles(query: string, options?: {
    category?: string;
    limit?: number;
  }): Promise<KBSearchResult[]> {
    const response = await apiClient.publicGet('/public/kb/search', {
      params: {
        q: query,
        category: options?.category,
        limit: options?.limit || 20
      }
    });
    
    if (!response || !response.data) {
      throw new Error('Failed to search articles');
    }
    return response.data;
  }

  /**
   * Get related articles (uses same category)
   */
  async getRelatedArticles(_articleId: string, _limit = 5): Promise<KBArticle[]> {
    // For now, we'll return an empty array since there's no related articles endpoint
    // In the future, this could fetch articles from the same category
    return [];
  }

  /**
   * Get popular articles (uses regular articles sorted by view count)
   */
  async getPopularArticles(params?: {
    limit?: number;
    days?: number;
    category_id?: string;
  }): Promise<KBArticle[]> {
    // Since there's no dedicated popular endpoint, use regular articles
    const response = await this.getArticles({
      limit: params?.limit || 10,
      category_id: params?.category_id
    });
    
    // Sort by view_count if available
    const articles = response.data.sort((a, b) => (b.view_count || 0) - (a.view_count || 0));
    return articles.slice(0, params?.limit || 10);
  }

  /**
   * Get featured articles (public)
   */
  async getFeaturedArticles(limit = 10): Promise<KBArticle[]> {
    const response = await apiClient.publicGet('/public/kb/articles', {
      params: { limit, is_featured: true }
    });
    // The response has data and meta fields
    if (!response || !response.data) {
      throw new Error('Failed to fetch featured articles');
    }
    return response.data || [];
  }

  /**
   * Get public articles
   */
  async getPublicArticles(options?: {
    category?: string;
    limit?: number;
    page?: number;
  }): Promise<{ data: KBArticle[]; meta: any }> {
    const response = await apiClient.publicGet('/public/kb/articles', {
      params: {
        category: options?.category,
        limit: options?.limit || 10,
        page: options?.page || 1
      }
    });
    
    if (!response || !response.data) {
      throw new Error('Failed to fetch public articles');
    }
    return response;
  }

  /**
   * Submit article feedback
   */
  async submitFeedback(articleId: string, helpful: boolean): Promise<void> {
    await apiClient.publicPost(`/public/kb/articles/${articleId}/feedback`, {
      helpful
    });
  }

  /**
   * Export articles (NOT IMPLEMENTED)
   */
  // async exportArticles(format: 'pdf' | 'docx' | 'json', params?: {
  //   category_id?: string;
  //   article_ids?: string[];
  // }): Promise<Blob> {
  //   const response = await apiClient.customPost('/admin/knowledge-base/export', {
  //     format,
  //     ...params
  //   }, {
  //     responseType: 'blob'
  //   });
  //   return response;
  // }

  /**
   * Generate table of contents for an article
   */
  generateTableOfContents(content: string): {
    id: string;
    text: string;
    level: number;
  }[] {
    const headings: { id: string; text: string; level: number }[] = [];
    const regex = /<h([1-6])(?:\s+id="([^"]*)")?[^>]*>([^<]+)<\/h[1-6]>/gi;
    let match;

    while ((match = regex.exec(content)) !== null) {
      const level = parseInt(match[1] || '1');
      const id = match[2] || (match[3] ? match[3].toLowerCase().replace(/\s+/g, '-') : '');
      const text = match[3] || '';
      
      if (text) {
        headings.push({ id, text, level });
      }
    }

    return headings;
  }

  /**
   * Validate slug uniqueness
   */
  async validateSlug(slug: string, excludeId?: string): Promise<boolean> {
    // Check if slug is unique by searching for articles with this slug
    try {
      const response = await this.getArticles({
        search: slug,
        limit: 10
      });
      
      // Check if any article has this exact slug (excluding the current article)
      const exists = response.data.some(article => 
        article.slug === slug && article.id !== excludeId
      );
      
      return !exists;  // Return true if slug is valid (doesn't exist)
    } catch (error) {
      // If there's an error, assume slug is valid
      return true;
    }
  }
}

export const knowledgeBaseService = new KnowledgeBaseService();