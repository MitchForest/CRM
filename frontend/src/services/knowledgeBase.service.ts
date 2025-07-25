import { apiClient } from '@/lib/api-client';
import type { 
  KBArticle, 
  KBCategory,
  KBSearchResult
} from '@/types/phase3.types';

class KnowledgeBaseService {
  // Category Management
  
  /**
   * Get all categories
   */
  async getCategories(): Promise<KBCategory[]> {
    const response = await apiClient.customGet('/knowledge-base/categories');
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch categories');
    }
    return response.data;
  }

  /**
   * Get category by ID
   */
  async getCategory(id: string): Promise<KBCategory> {
    const response = await apiClient.customGet(`/knowledge-base/categories/${id}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch category');
    }
    return response.data;
  }

  /**
   * Create a new category
   */
  async createCategory(data: Partial<KBCategory>): Promise<KBCategory> {
    const response = await apiClient.customPost('/knowledge-base/categories', data);
    if (!response.success) {
      throw new Error(response.error || 'Failed to create category');
    }
    return response.data;
  }

  /**
   * Update a category
   */
  async updateCategory(id: string, data: Partial<KBCategory>): Promise<KBCategory> {
    const response = await apiClient.customPut(`/knowledge-base/categories/${id}`, data);
    if (!response.success) {
      throw new Error(response.error || 'Failed to update category');
    }
    return response.data;
  }

  /**
   * Delete a category
   */
  async deleteCategory(id: string): Promise<void> {
    const response = await apiClient.customDelete(`/knowledge-base/categories/${id}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to delete category');
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
    author_id?: string;
  }): Promise<{ 
    data: KBArticle[]; 
    total: number;
    page: number;
    limit: number;
  }> {
    const response = await apiClient.customGet('/knowledge-base/articles', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch articles');
    }
    return response.data;
  }

  /**
   * Get article by ID
   */
  async getArticle(id: string): Promise<KBArticle> {
    const response = await apiClient.customGet(`/knowledge-base/articles/${id}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch article');
    }
    return response.data;
  }

  /**
   * Get public article by slug (no auth required)
   */
  async getPublicArticle(slug: string): Promise<KBArticle> {
    const response = await apiClient.publicGet(`/knowledge-base/public/${slug}`);
    if (!response.success) {
      throw new Error(response.error || 'Article not found');
    }
    return response.data;
  }

  /**
   * Create a new article
   */
  async createArticle(data: Partial<KBArticle>): Promise<KBArticle> {
    const response = await apiClient.customPost('/knowledge-base/articles', data);
    if (!response.success) {
      throw new Error(response.error || 'Failed to create article');
    }
    return response.data;
  }

  /**
   * Update an article
   */
  async updateArticle(id: string, data: Partial<KBArticle>): Promise<KBArticle> {
    const response = await apiClient.customPut(`/knowledge-base/articles/${id}`, data);
    if (!response.success) {
      throw new Error(response.error || 'Failed to update article');
    }
    return response.data;
  }

  /**
   * Delete an article
   */
  async deleteArticle(id: string): Promise<void> {
    const response = await apiClient.customDelete(`/knowledge-base/articles/${id}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to delete article');
    }
  }

  /**
   * Duplicate an article
   */
  async duplicateArticle(id: string, newTitle: string): Promise<KBArticle> {
    const response = await apiClient.customPost(`/knowledge-base/articles/${id}/duplicate`, { 
      title: newTitle 
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to duplicate article');
    }
    return response.data;
  }

  /**
   * Rate an article
   */
  async rateArticle(id: string, helpful: boolean): Promise<{
    helpful_yes: number;
    helpful_no: number;
  }> {
    const response = await apiClient.customPost(`/knowledge-base/articles/${id}/rate`, { helpful });
    if (!response.success) {
      throw new Error(response.error || 'Failed to rate article');
    }
    return response.data;
  }

  /**
   * Track article view (public endpoint)
   */
  async trackView(id: string): Promise<void> {
    await apiClient.publicPost(`/knowledge-base/articles/${id}/view`);
  }

  /**
   * Search articles using semantic search
   */
  async searchArticles(query: string, params?: {
    limit?: number;
    category_id?: string;
    is_public?: boolean;
  }): Promise<KBSearchResult[]> {
    const response = await apiClient.customPost('/knowledge-base/search', { 
      query,
      ...params 
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to search articles');
    }
    return response.data;
  }

  /**
   * Get related articles
   */
  async getRelatedArticles(articleId: string, limit = 5): Promise<KBArticle[]> {
    const response = await apiClient.customGet(`/knowledge-base/articles/${articleId}/related`, {
      params: { limit }
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch related articles');
    }
    return response.data;
  }

  /**
   * Get popular articles
   */
  async getPopularArticles(params?: {
    limit?: number;
    days?: number;
    category_id?: string;
  }): Promise<KBArticle[]> {
    const response = await apiClient.customGet('/knowledge-base/articles/popular', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch popular articles');
    }
    return response.data;
  }

  /**
   * Get featured articles
   */
  async getFeaturedArticles(limit = 10): Promise<KBArticle[]> {
    const response = await apiClient.customGet('/knowledge-base/articles/featured', {
      params: { limit }
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch featured articles');
    }
    return response.data;
  }

  /**
   * Export articles
   */
  async exportArticles(format: 'pdf' | 'docx' | 'json', params?: {
    category_id?: string;
    article_ids?: string[];
  }): Promise<Blob> {
    const response = await apiClient.customPost('/knowledge-base/export', {
      format,
      ...params
    }, {
      responseType: 'blob'
    });
    return response;
  }

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
   * Validate article slug
   */
  async validateSlug(slug: string, excludeId?: string): Promise<boolean> {
    const response = await apiClient.customPost('/knowledge-base/validate-slug', {
      slug,
      exclude_id: excludeId
    });
    return response.data.available;
  }
}

export const knowledgeBaseService = new KnowledgeBaseService();