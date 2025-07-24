import axios from 'axios'

export interface AuthTokens {
  suitecrmToken: string
  customApiToken: string
}

export async function getTestAuthTokens(): Promise<AuthTokens> {
  // Get SuiteCRM V8 API token
  const oauthParams = new URLSearchParams()
  oauthParams.append('grant_type', 'password')
  oauthParams.append('client_id', 'suitecrm_client')
  oauthParams.append('client_secret', 'secret123')
  oauthParams.append('username', 'admin')
  oauthParams.append('password', 'admin123')
  
  const suitecrmResponse = await axios.post(
    'http://localhost:8080/Api/access_token',
    oauthParams,
    {
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      }
    }
  )
  
  // Get Custom API token
  const customApiResponse = await axios.post(
    'http://localhost:8080/custom-api/auth/login',
    {
      username: 'admin',
      password: 'admin123'
    },
    {
      headers: {
        'Content-Type': 'application/json'
      }
    }
  )
  
  return {
    suitecrmToken: suitecrmResponse.data.access_token,
    customApiToken: customApiResponse.data.data.token
  }
}

export function createAuthenticatedClient(baseURL: string, token: string, isJsonApi = false) {
  return axios.create({
    baseURL,
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': isJsonApi ? 'application/vnd.api+json' : 'application/json',
      'Accept': isJsonApi ? 'application/vnd.api+json' : 'application/json',
    }
  })
}