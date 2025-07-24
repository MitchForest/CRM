import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { Layout } from '@/components/layout/Layout'
import { LoginPage } from '@/pages/Login'
import { DashboardPage } from '@/pages/Dashboard'
import { ContactsPage } from '@/pages/Contacts'
import { ContactDetailPage } from '@/pages/ContactDetail'
import { ContactFormPage } from '@/pages/ContactForm'
import { LeadsListPage } from '@/pages/LeadsList'
import { LeadDetailPage } from '@/pages/LeadDetail'
import { LeadFormPage } from '@/pages/LeadForm'
import { AccountsListPage } from '@/pages/AccountsList'
import { AccountFormPage } from '@/pages/AccountForm'
import { LeadDebugPage } from '@/pages/LeadDebug'

// Opportunities
import { OpportunitiesPipeline } from '@/pages/opportunities/OpportunitiesPipeline'
import { OpportunityForm } from '@/pages/opportunities/OpportunityForm'

// Activities
import { ActivitiesList } from '@/pages/activities/ActivitiesList'
import { CallForm } from '@/pages/activities/CallForm'
import { MeetingForm } from '@/pages/activities/MeetingForm'
import { TaskForm } from '@/pages/activities/TaskForm'
import { NoteForm } from '@/pages/activities/NoteForm'

// Cases
import { CasesList } from '@/pages/cases/CasesList'
import { CaseForm } from '@/pages/cases/CaseForm'
import { CaseDetail } from '@/pages/cases/CaseDetail'

import { Toaster } from '@/components/ui/sonner'

// Create a client
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      gcTime: 10 * 60 * 1000, // 10 minutes
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <Router>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          
          <Route element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }>
            <Route path="/" element={<DashboardPage />} />
            
            {/* Contacts Routes */}
            <Route path="/contacts" element={<ContactsPage />} />
            <Route path="/contacts/new" element={<ContactFormPage />} />
            <Route path="/contacts/:id" element={<ContactDetailPage />} />
            <Route path="/contacts/:id/edit" element={<ContactFormPage />} />
            
            {/* Leads Routes */}
            <Route path="/leads" element={<LeadsListPage />} />
            <Route path="/leads/new" element={<LeadFormPage />} />
            <Route path="/leads/:id" element={<LeadDetailPage />} />
            <Route path="/leads/:id/edit" element={<LeadFormPage />} />
            
            {/* Accounts Routes */}
            <Route path="/accounts" element={<AccountsListPage />} />
            <Route path="/accounts/new" element={<AccountFormPage />} />
            <Route path="/accounts/:id/edit" element={<AccountFormPage />} />
            
            {/* Opportunities Routes */}
            <Route path="/opportunities" element={<OpportunitiesPipeline />} />
            <Route path="/opportunities/new" element={<OpportunityForm />} />
            <Route path="/opportunities/:id" element={<OpportunityForm />} />
            
            {/* Activities Routes */}
            <Route path="/activities" element={<ActivitiesList />} />
            <Route path="/activities/calls/new" element={<CallForm />} />
            <Route path="/activities/calls/:id" element={<CallForm />} />
            <Route path="/activities/meetings/new" element={<MeetingForm />} />
            <Route path="/activities/meetings/:id" element={<MeetingForm />} />
            <Route path="/activities/tasks/new" element={<TaskForm />} />
            <Route path="/activities/tasks/:id" element={<TaskForm />} />
            <Route path="/activities/notes/new" element={<NoteForm />} />
            <Route path="/activities/notes/:id" element={<NoteForm />} />
            
            {/* Cases Routes */}
            <Route path="/cases" element={<CasesList />} />
            <Route path="/cases/new" element={<CaseForm />} />
            <Route path="/cases/:id" element={<CaseDetail />} />
            <Route path="/cases/:id/edit" element={<CaseForm />} />
            
            <Route path="/debug/leads" element={<LeadDebugPage />} />
            <Route path="/settings" element={<div>Settings Page (Coming Soon)</div>} />
          </Route>
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Router>
      <Toaster position="top-right" />
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  )
}

export default App