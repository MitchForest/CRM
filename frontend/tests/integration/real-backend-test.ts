/**
 * Real Backend Integration Test
 * Run this with the backend actually running to verify everything works
 */

const API_URL = 'http://localhost:8080/api';

async function testRealBackend() {
  console.log('🔥 Testing REAL backend integration...\n');

  // Test 1: Backend is running
  console.log('1️⃣ Checking backend health...');
  try {
    const health = await fetch(`${API_URL}/health`);
    const healthData = await health.json();
    console.log('✅ Backend is running:', healthData);
  } catch (e) {
    console.error('❌ BACKEND NOT RUNNING! Start it with: cd backend && php -S localhost:8080 -t public');
    return;
  }

  // Test 2: Login works
  console.log('\n2️⃣ Testing login...');
  let token = '';
  try {
    const loginRes = await fetch(`${API_URL}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: 'john.smith@techflow.com',
        password: 'password123'
      })
    });
    
    if (!loginRes.ok) {
      console.error('❌ Login failed:', await loginRes.text());
      console.log('   Try different credentials or check backend setup');
      return;
    }
    
    const auth = await loginRes.json();
    token = auth.access_token || auth.data?.access_token || '';
    console.log('✅ Login successful, got token');
  } catch (e) {
    console.error('❌ Login error:', e);
    return;
  }

  // Test 3: Can fetch leads
  console.log('\n3️⃣ Testing leads endpoint...');
  try {
    const leadsRes = await fetch(`${API_URL}/crm/leads?page=1&limit=5`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    if (!leadsRes.ok) {
      console.error('❌ Leads fetch failed:', leadsRes.status, await leadsRes.text());
      return;
    }
    
    const leads = await leadsRes.json();
    console.log('✅ Leads response:', {
      total: leads.pagination?.total || leads.total || 0,
      hasData: !!leads.data,
      dataCount: leads.data?.length || 0
    });
    
    // Check field naming
    if (leads.data && leads.data.length > 0) {
      const firstLead = leads.data[0];
      const hasSnakeCase = 'first_name' in firstLead && 'date_entered' in firstLead;
      const hasCamelCase = 'firstName' in firstLead || 'dateEntered' in firstLead;
      
      if (hasCamelCase) {
        console.error('❌ BACKEND RETURNING CAMELCASE! Should be snake_case');
      } else if (hasSnakeCase) {
        console.log('✅ Fields are correctly snake_case');
      }
    }
  } catch (e) {
    console.error('❌ Leads error:', e);
  }

  // Test 4: Dashboard metrics
  console.log('\n4️⃣ Testing dashboard metrics...');
  try {
    const metricsRes = await fetch(`${API_URL}/crm/dashboard/metrics`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    if (!metricsRes.ok) {
      console.error('❌ Metrics failed:', metricsRes.status);
      return;
    }
    
    const metrics = await metricsRes.json();
    console.log('✅ Metrics response:', metrics.data || metrics);
    
    // Check field naming
    const data = metrics.data || metrics;
    if ('total_leads' in data && 'new_leads_today' in data) {
      console.log('✅ Metrics use snake_case');
    } else if ('totalLeads' in data) {
      console.error('❌ Metrics use camelCase - should be snake_case!');
    }
  } catch (e) {
    console.error('❌ Metrics error:', e);
  }

  // Test 5: Create a lead
  console.log('\n5️⃣ Testing lead creation...');
  try {
    const newLead = {
      first_name: 'Test',
      last_name: 'Integration',
      email1: `test${Date.now()}@example.com`,
      phone_work: '555-0000',
      status: 'new',
      lead_source: 'Website'
    };
    
    const createRes = await fetch(`${API_URL}/crm/leads`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(newLead)
    });
    
    if (!createRes.ok) {
      console.error('❌ Create failed:', createRes.status, await createRes.text());
      return;
    }
    
    const created = await createRes.json();
    console.log('✅ Lead created:', created.data?.id || created.id);
  } catch (e) {
    console.error('❌ Create error:', e);
  }

  console.log('\n✨ Integration test complete!\n');
  console.log('Summary:');
  console.log('- If all tests passed, frontend is ready for production');
  console.log('- If tests failed, fix the issues before deploying');
}

// Run it
testRealBackend().catch(console.error);