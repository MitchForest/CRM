import { apiClient } from '@/lib/api-client';
import type { 
  Form, 
  FormSubmission, 
  FormField
} from '@/types/api.types';

class FormBuilderService {
  /**
   * Get all forms with pagination
   */
  async getAllForms(params?: { 
    page?: number; 
    limit?: number;
    search?: string;
    is_active?: boolean;
  }): Promise<{
    data: Form[];
    total: number;
    page: number;
    limit: number;
  }> {
    const response = await apiClient.customGet('/admin/forms', { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch forms');
    }
    return response.data;
  }

  /**
   * Get a single form by ID
   */
  async getForm(id: string): Promise<Form> {
    const response = await apiClient.customGet(`/admin/forms/${id}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch form');
    }
    return response.data;
  }

  /**
   * Create a new form
   */
  async createForm(data: Partial<Form>): Promise<Form> {
    const response = await apiClient.customPost('/admin/forms', data);
    if (!response.success) {
      throw new Error(response.error || 'Failed to create form');
    }
    return response.data;
  }

  /**
   * Update an existing form
   */
  async updateForm(id: string, data: Partial<Form>): Promise<Form> {
    const response = await apiClient.customPut(`/admin/forms/${id}`, data);
    if (!response.success) {
      throw new Error(response.error || 'Failed to update form');
    }
    return response.data;
  }

  /**
   * Delete a form
   */
  async deleteForm(id: string): Promise<void> {
    const response = await apiClient.customDelete(`/admin/forms/${id}`);
    if (!response.success) {
      throw new Error(response.error || 'Failed to delete form');
    }
  }

  /**
   * Duplicate a form
   */
  async duplicateForm(id: string, newName: string): Promise<Form> {
    const response = await apiClient.customPost(`/admin/forms/${id}/duplicate`, { name: newName });
    if (!response.success) {
      throw new Error(response.error || 'Failed to duplicate form');
    }
    return response.data;
  }

  /**
   * Get form submissions
   */
  async getFormSubmissions(
    formId: string, 
    params?: { 
      page?: number; 
      limit?: number;
      start_date?: string;
      end_date?: string;
    }
  ): Promise<{
    data: FormSubmission[];
    total: number;
    page: number;
    limit: number;
  }> {
    const response = await apiClient.customGet(`/admin/forms/${formId}/submissions`, { params });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch submissions');
    }
    return response.data;
  }

  /**
   * Submit a form (public endpoint - no auth required)
   */
  async submitForm(formId: string, data: Record<string, string | number | boolean | string[]>): Promise<{
    success: boolean;
    message: string;
    lead_id?: string;
    submission_id: string;
  }> {
    const response = await apiClient.publicPost(`/public/forms/${formId}/submit`, data);
    if (!response.success) {
      throw new Error(response.error || 'Form submission failed');
    }
    return response.data;
  }

  /**
   * Generate embed code for a form
   */
  async generateEmbedCode(
    formId: string, 
    options?: { 
      theme?: 'light' | 'dark' | 'auto';
      container?: string;
      height?: string;
      onSuccess?: string;
    }
  ): Promise<string> {
    const response = await apiClient.customPost(`/admin/forms/${formId}/embed-code`, options);
    if (!response.success) {
      throw new Error(response.error || 'Failed to generate embed code');
    }
    return response.data.embed_code;
  }

  /**
   * Get form analytics
   */
  async getFormAnalytics(formId: string, dateRange?: {
    start_date: string;
    end_date: string;
  }): Promise<{
    total_submissions: number;
    conversion_rate: number;
    abandonment_rate: number;
    average_completion_time: number;
    field_analytics: {
      field_name: string;
      completion_rate: number;
      error_rate: number;
    }[];
    submission_trends: {
      date: string;
      count: number;
    }[];
  }> {
    const response = await apiClient.customGet(`/admin/forms/${formId}/analytics`, { 
      params: dateRange 
    });
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch form analytics');
    }
    return response.data;
  }

  /**
   * Export form submissions
   */
  async exportSubmissions(
    formId: string, 
    format: 'csv' | 'xlsx' | 'json',
    params?: {
      start_date?: string;
      end_date?: string;
    }
  ): Promise<Blob> {
    const response = await apiClient.customGet(`/admin/forms/${formId}/export`, {
      params: { format, ...params },
      responseType: 'blob'
    });
    return response;
  }

  /**
   * Validate form field
   */
  async validateField(
    field: FormField, 
    value: string | number | boolean | string[]
  ): Promise<{
    valid: boolean;
    errors?: string[];
  }> {
    // Client-side validation
    const errors: string[] = [];

    if (field.required && !value) {
      errors.push(`${field.label} is required`);
    }

    if (field.validation) {
      if (field.validation.pattern && value && typeof value === 'string') {
        const regex = new RegExp(field.validation.pattern);
        if (!regex.test(value)) {
          errors.push(`${field.label} format is invalid`);
        }
      }

      if (field.type === 'email' && value && typeof value === 'string') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          errors.push('Please enter a valid email address');
        }
      }

      if (field.validation.minLength && value && typeof value === 'string' && value.length < field.validation.minLength) {
        errors.push(`${field.label} must be at least ${field.validation.minLength} characters`);
      }

      if (field.validation.maxLength && value && typeof value === 'string' && value.length > field.validation.maxLength) {
        errors.push(`${field.label} must be no more than ${field.validation.maxLength} characters`);
      }
    }

    return {
      valid: errors.length === 0,
      errors: errors.length > 0 ? errors : undefined
    };
  }

  /**
   * Get form templates
   */
  async getFormTemplates(): Promise<{
    id: string;
    name: string;
    description: string;
    category: string;
    preview_url: string;
    fields: FormField[];
  }[]> {
    const response = await apiClient.customGet('/admin/forms/templates');
    if (!response.success) {
      throw new Error(response.error || 'Failed to fetch form templates');
    }
    return response.data;
  }
}

export const formBuilderService = new FormBuilderService();