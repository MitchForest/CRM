import { useEffect, lazy, Suspense } from 'react'
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { Layout } from '@/components/layout/Layout'
import { LoginPage } from '@/pages/Login'
import { useAuthStore } from '@/stores/auth-store'
import { DashboardPage } from '@/pages/Dashboard'
import { ContactsPage } from '@/pages/Contacts'
import { ContactDetailPage } from '@/pages/ContactDetail'
import { ContactFormPage } from '@/pages/ContactForm'
import { LeadsListPage } from '@/pages/LeadsList'
import { LeadDetailPage } from '@/pages/LeadDetail'
import { LeadFormPage } from '@/pages/LeadForm'
import { AccountsListPage } from '@/pages/AccountsList'
import { AccountFormPage } from '@/pages/AccountForm'
import { AccountDetailPage } from '@/pages/AccountDetail'
import { LeadDebugPage } from '@/pages/LeadDebug'
import { SettingsPage } from '@/pages/Settings'

// Opportunities
import { OpportunitiesPipeline } from '@/pages/opportunities/OpportunitiesPipeline'
import { OpportunityForm } from '@/pages/opportunities/OpportunityForm'
import { OpportunityDetailPage } from '@/pages/opportunities/OpportunityDetail'

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

// Phase 3 - Lazy load for better performance
const LeadScoringDashboard = lazy(() => import('@/pages/leads/LeadScoringDashboard').then(m => ({ default: m.LeadScoringDashboard })))
const FormBuilderPage = lazy(() => import('@/pages/forms/FormBuilderPage').then(m => ({ default: m.FormBuilderPage })))
const FormsList = lazy(() => import('@/pages/forms/FormsList').then(m => ({ default: m.FormsList })))
const KnowledgeBaseAdmin = lazy(() => import('@/pages/kb/KnowledgeBaseAdmin').then(m => ({ default: m.KnowledgeBaseAdmin })))
const ArticleEditor = lazy(() => import('@/pages/kb/ArticleEditor').then(m => ({ default: m.ArticleEditor })))
const KnowledgeBasePublic = lazy(() => import('@/pages/kb/KnowledgeBasePublic').then(m => ({ default: m.KnowledgeBasePublic })))
// Phase 3 - Activity Tracking, Chatbot, and Health
const ActivityTrackingDashboard = lazy(() => import('@/pages/tracking/ActivityTrackingDashboard').then(m => ({ default: m.ActivityTrackingDashboard })))
const SessionDetail = lazy(() => import('@/pages/tracking/SessionDetail').then(m => ({ default: m.SessionDetail })))
const ChatbotSettings = lazy(() => import('@/pages/chatbot/ChatbotSettings').then(m => ({ default: m.ChatbotSettings })))
const CustomerHealthDashboard = lazy(() => import('@/pages/health/CustomerHealthDashboard').then(m => ({ default: m.CustomerHealthDashboard })))

import { Toaster } from '@/components/ui/sonner'
import { ChatWidget } from '@/components/features/chatbot/ChatWidget'
import { activityTrackingService } from '@/services/activityTracking.service'

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

// Loading component for lazy loaded pages
const PageLoader = () => (
  <div className="flex items-center justify-center h-full">
    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
  </div>
)

function AppContent() {
  const { accessToken, refreshToken } = useAuthStore()

  useEffect(() => {
    // Sync auth tokens with API client when they change
    if (accessToken) {
      // The API client will pick up the token from localStorage via getStoredAuth
      // Force a re-read by updating the axios default headers
      // Update local storage to sync tokens
      // Store in expected format for getStoredAuth
      const stored = localStorage.getItem('auth-storage')
      if (stored) {
        try {
          const parsed = JSON.parse(stored)
          // Ensure the token is in the right place
          if (parsed.state && parsed.state.accessToken !== accessToken) {
            parsed.state.accessToken = accessToken
            parsed.state.refreshToken = refreshToken
            localStorage.setItem('auth-storage', JSON.stringify(parsed))
          }
        } catch (e) {
          console.error('Failed to sync auth storage:', e)
        }
      }
    }
  }, [accessToken, refreshToken])

  // Initialize activity tracking
  useEffect(() => {
    const handleRouteChange = () => {
      activityTrackingService.trackPageView({
        page_url: window.location.pathname + window.location.search,
        title: document.title,
      })
    }

    // Track initial page view
    handleRouteChange()

    // Listen for route changes
    window.addEventListener('popstate', handleRouteChange)
    
    return () => {
      window.removeEventListener('popstate', handleRouteChange)
    }
  }, [])

  return (
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
            <Route path="/accounts/:id" element={<AccountDetailPage />} />
            <Route path="/accounts/:id/edit" element={<AccountFormPage />} />
            
            {/* Opportunities Routes */}
            <Route path="/opportunities" element={<OpportunitiesPipeline />} />
            <Route path="/opportunities/new" element={<OpportunityForm />} />
            <Route path="/opportunities/:id" element={<OpportunityDetailPage />} />
            <Route path="/opportunities/:id/edit" element={<OpportunityForm />} />
            
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
            
            {/* Phase 3 Routes */}
            {/* AI Lead Scoring */}
            <Route path="/leads/scoring" element={
              <Suspense fallback={<PageLoader />}>
                <LeadScoringDashboard />
              </Suspense>
            } />
            
            {/* Form Builder - TODO: Create these pages */}
            <Route path="/forms" element={
              <Suspense fallback={<PageLoader />}>
                <FormsList />
              </Suspense>
            } />
            <Route path="/forms/new" element={
              <Suspense fallback={<PageLoader />}>
                <FormBuilderPage />
              </Suspense>
            } />
            <Route path="/forms/:id" element={
              <Suspense fallback={<PageLoader />}>
                <FormBuilderPage />
              </Suspense>
            } />
            
            {/* Knowledge Base - TODO: Create these pages */}
            <Route path="/kb" element={
              <Suspense fallback={<PageLoader />}>
                <KnowledgeBaseAdmin />
              </Suspense>
            } />
            <Route path="/kb/new" element={
              <Suspense fallback={<PageLoader />}>
                <ArticleEditor />
              </Suspense>
            } />
            <Route path="/kb/edit/:id" element={
              <Suspense fallback={<PageLoader />}>
                <ArticleEditor />
              </Suspense>
            } />
            
            {/* Activity Tracking */}
            <Route path="/tracking" element={
              <Suspense fallback={<PageLoader />}>
                <ActivityTrackingDashboard />
              </Suspense>
            } />
            <Route path="/tracking/sessions/:id" element={
              <Suspense fallback={<PageLoader />}>
                <SessionDetail />
              </Suspense>
            } />
            
            {/* Chatbot Settings */}
            <Route path="/chatbot" element={
              <Suspense fallback={<PageLoader />}>
                <ChatbotSettings />
              </Suspense>
            } />
            
            {/* Customer Health */}
            <Route path="/health" element={
              <Suspense fallback={<PageLoader />}>
                <CustomerHealthDashboard />
              </Suspense>
            } />
            
            <Route path="/debug/leads" element={<LeadDebugPage />} />
            <Route path="/settings" element={<SettingsPage />} />
          </Route>
          
          {/* Public KB Route - TODO: Create this page */}
          <Route path="/kb/public/:slug" element={
            <Suspense fallback={<PageLoader />}>
              <KnowledgeBasePublic />
            </Suspense>
          } />
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
        
        {/* Global Chat Widget */}
        <ChatWidget 
          position="bottom-right"
          theme="auto"
          onLeadCapture={(_leadInfo) => {
            // Handle lead capture from chat
            queryClient.invalidateQueries({ queryKey: ['leads'] })
          }}
        />
      </Router>
  )
}

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AppContent />
      <Toaster position="top-right" />
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  )
}

export default App