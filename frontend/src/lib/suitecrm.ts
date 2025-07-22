/**
 * SuiteCRM V4.1 REST API Client  
 * Connects React frontend to SuiteCRM backend via REST API
 */

interface AuthResponse {
  access_token: string;
  token_type: string;
  expires_in: number;
  refresh_token: string;
}

interface SuiteCRMLead {
  type: string;
  id: string;
  attributes: {
    first_name: string;
    last_name: string;
    email1: string;
    phone_work: string;
    account_name: string;
    title: string;
    status: string;
    lead_source: string;
    ai_score_c?: number;
    date_entered: string;
    date_modified: string;
  };
}

interface SuiteCRMResponse<T> {
  data: T[];
  meta: {
    total: number;
  };
}

class SuiteCRMClient {
  private baseURL = import.meta.env.VITE_SUITECRM_URL || 'http://localhost:8080';
  private token: string | null = null;

  /**
   * Authenticate with SuiteCRM using REST API
   */
  async login(username: string, password: string): Promise<{ id: string; error?: any }> {
    try {
      const response = await fetch(`${this.baseURL}/service/v4_1/rest.php`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json'
        },
        body: new URLSearchParams({
          method: 'login',
          input_type: 'JSON',
          response_type: 'JSON',
          rest_data: JSON.stringify({
            user_auth: {
              user_name: username,
              password: password
            },
            application_name: 'Modern CRM Frontend'
          })
        })
      });

      if (!response.ok) {
        throw new Error(`Authentication failed: ${response.statusText}`);
      }

      const data = await response.json();
      
      if (data.id) {
        this.token = data.id; // Session ID
        localStorage.setItem('suitecrm_session', this.token);
        return data;
      } else {
        throw new Error(data.name || 'Login failed');
      }
    } catch (error) {
      console.error('Login error:', error);
      throw error;
    }
  }

  /**
   * Initialize client with stored session
   */
  init() {
    const storedSession = localStorage.getItem('suitecrm_session');
    if (storedSession) {
      this.token = storedSession;
    }
  }

  /**
   * Logout and clear session
   */
  async logout() {
    if (this.token) {
      try {
        await fetch(`${this.baseURL}/service/v4_1/rest.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            method: 'logout',
            input_type: 'JSON',
            response_type: 'JSON',
            rest_data: JSON.stringify({
              session: this.token
            })
          })
        });
      } catch (error) {
        console.error('Logout error:', error);
      }
    }
    
    this.token = null;
    localStorage.removeItem('suitecrm_session');
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated(): boolean {
    return !!this.token;
  }

  /**
   * Make authenticated API request using SuiteCRM V4.1 REST API
   */
  private async apiRequest(method: string, restData: any = {}): Promise<any> {
    if (!this.token) {
      throw new Error('Not authenticated. Please login first.');
    }

    const requestData = {
      session: this.token,
      ...restData
    };

    const response = await fetch(`${this.baseURL}/service/v4_1/rest.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: new URLSearchParams({
        method,
        input_type: 'JSON',
        response_type: 'JSON',
        rest_data: JSON.stringify(requestData)
      })
    });

    if (!response.ok) {
      throw new Error(`API request failed: ${response.statusText}`);
    }

    const data = await response.json();
    
    if (data.name === 'Invalid Session ID') {
      this.logout();
      throw new Error('Session expired. Please login again.');
    }

    return data;
  }

  /**
   * Get all leads from SuiteCRM
   */
  async getLeads(limit = 50, offset = 0): Promise<any> {
    return this.apiRequest('get_entry_list', {
      module_name: 'Leads',
      query: '',
      order_by: 'date_entered DESC',
      offset,
      select_fields: ['first_name', 'last_name', 'email1', 'phone_work', 'account_name', 'title', 'status', 'lead_source', 'date_entered'],
      max_results: limit,
      deleted: 0
    });
  }

  /**
   * Get single lead by ID
   */
  async getLead(id: string): Promise<any> {
    return this.apiRequest('get_entry', {
      module_name: 'Leads',
      id,
      select_fields: ['first_name', 'last_name', 'email1', 'phone_work', 'account_name', 'title', 'status', 'lead_source', 'description', 'date_entered']
    });
  }

  /**
   * Create new lead
   */
  async createLead(leadData: Partial<SuiteCRMLead['attributes']>): Promise<{ data: SuiteCRMLead }> {
    return this.apiRequest('modules/Leads', {
      method: 'POST',
      body: JSON.stringify({
        data: {
          type: 'Leads',
          attributes: leadData
        }
      })
    });
  }

  /**
   * Update existing lead
   */
  async updateLead(id: string, leadData: Partial<SuiteCRMLead['attributes']>): Promise<{ data: SuiteCRMLead }> {
    return this.apiRequest(`modules/Leads/${id}`, {
      method: 'PATCH',
      body: JSON.stringify({
        data: {
          type: 'Leads',
          id,
          attributes: leadData
        }
      })
    });
  }

  /**
   * Delete lead
   */
  async deleteLead(id: string): Promise<void> {
    await this.apiRequest(`modules/Leads/${id}`, {
      method: 'DELETE'
    });
  }

  /**
   * Get accounts
   */
  async getAccounts(limit = 50): Promise<SuiteCRMResponse<any>> {
    return this.apiRequest(`modules/Accounts?page[size]=${limit}`);
  }

  /**
   * Get opportunities
   */
  async getOpportunities(limit = 50): Promise<SuiteCRMResponse<any>> {
    return this.apiRequest(`modules/Opportunities?page[size]=${limit}`);
  }

  /**
   * Health check to verify API connection
   */
  async healthCheck(): Promise<boolean> {
    try {
      if (!this.token) return false;
      
      // Simple request to verify API is working
      const result = await this.apiRequest('get_available_modules', {});
      return !!result.modules;
    } catch {
      return false;
    }
  }
}

// Export singleton instance
export default new SuiteCRMClient();