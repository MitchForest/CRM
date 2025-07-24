import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import userEvent from '@testing-library/user-event'
import { ActivitiesList } from '@/pages/activities/ActivitiesList'
import { useUpcomingActivities, useOverdueTasks, useCalls, useMeetings } from '@/hooks/use-activities'
import type { Call, Meeting, Task } from '@/types/api.generated'
import type { BaseActivity } from '@/types/phase2.types'

// Mock the hooks
vi.mock('@/hooks/use-activities')

const mockCalls: Call[] = [
  {
    id: '1',
    name: 'Call with John',
    status: 'Scheduled',
    startDate: new Date().toISOString(),
    duration: 30,
    direction: 'Outbound',
    parentType: 'Leads',
    parentId: 'lead1',
    parentName: 'John Doe',
    assignedUserId: 'user1',
    assignedUserName: 'Sales Rep',
    description: 'Follow up call',
  },
]

const mockMeetings: Meeting[] = [
  {
    id: '2',
    name: 'Strategy Meeting',
    status: 'Scheduled',
    startDate: new Date().toISOString(),
    endDate: new Date(Date.now() + 3600000).toISOString(),
    location: 'Conference Room A',
    parentType: 'Opportunities',
    parentId: 'opp1',
    parentName: 'Big Deal',
    assignedUserId: 'user1',
    assignedUserName: 'Sales Manager',
    description: 'Quarterly planning',
  },
]

const mockOverdueTasks: Task[] = [
  {
    id: '3',
    name: 'Complete proposal',
    status: 'In Progress',
    priority: 'High',
    dueDate: new Date(Date.now() - 86400000).toISOString(), // Yesterday
    parentType: 'Opportunities',
    parentId: 'opp2',
    parentName: 'Important Deal',
    assignedUserId: 'user1',
    assignedUserName: 'Sales Rep',
    description: 'Needs immediate attention',
  },
]

const mockUpcomingActivities: BaseActivity[] = [
  {
    id: '1',
    name: 'Call with John',
    status: 'Scheduled',
    type: 'Call',
    dateModified: new Date().toISOString(),
    dateEntered: new Date().toISOString(),
    parentType: 'Leads',
    parentId: 'lead1',
    parentName: 'John Doe',
    assignedUserId: 'user1',
    assignedUserName: 'Sales Rep',
  },
  {
    id: '2',
    name: 'Strategy Meeting',
    status: 'Scheduled',
    type: 'Meeting',
    dateModified: new Date().toISOString(),
    dateEntered: new Date().toISOString(),
    parentType: 'Opportunities',
    parentId: 'opp1',
    parentName: 'Big Deal',
    assignedUserId: 'user1',
    assignedUserName: 'Sales Manager',
  },
]

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  )
}

describe('ActivitiesList', () => {
  const mockUseUpcomingActivities = vi.mocked(useUpcomingActivities)
  const mockUseOverdueTasks = vi.mocked(useOverdueTasks)
  const mockUseCalls = vi.mocked(useCalls)
  const mockUseMeetings = vi.mocked(useMeetings)

  beforeEach(() => {
    vi.clearAllMocks()
    
    mockUseUpcomingActivities.mockReturnValue({
      data: mockUpcomingActivities,
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    mockUseOverdueTasks.mockReturnValue({
      data: { data: mockOverdueTasks },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    mockUseCalls.mockReturnValue({
      data: { data: mockCalls },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    mockUseMeetings.mockReturnValue({
      data: { data: mockMeetings },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)
  })

  it('renders activities page with header', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Activities')).toBeInTheDocument()
    })
  })

  it('displays quick create buttons for each activity type', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByRole('link', { name: /call/i })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: /meeting/i })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: /task/i })).toBeInTheDocument()
      expect(screen.getByRole('link', { name: /note/i })).toBeInTheDocument()
    })
  })

  it('shows overdue tasks card', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Overdue Tasks')).toBeInTheDocument()
      expect(screen.getByText('Complete proposal')).toBeInTheDocument()
      expect(screen.getByText(/yesterday|1 day ago/i)).toBeInTheDocument()
    })
  })

  it('shows today\'s activities', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Today\'s Activities')).toBeInTheDocument()
      // Since we mock activities with today's date, they should appear
      expect(screen.getByText('Call with John')).toBeInTheDocument()
      expect(screen.getByText('Strategy Meeting')).toBeInTheDocument()
    })
  })

  it('shows upcoming activities card', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Upcoming Activities')).toBeInTheDocument()
    })
  })

  it('displays activity tabs', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: 'All Activities' })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: 'Calls' })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: 'Meetings' })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: 'Tasks' })).toBeInTheDocument()
      expect(screen.getByRole('tab', { name: 'Notes' })).toBeInTheDocument()
    })
  })

  it('switches between activity tabs', async () => {
    const user = userEvent.setup()
    render(<ActivitiesList />, { wrapper: createWrapper() })

    // Click on Calls tab
    const callsTab = await screen.findByRole('tab', { name: 'Calls' })
    await user.click(callsTab)

    await waitFor(() => {
      expect(screen.getByText('Call with John')).toBeInTheDocument()
    })

    // Click on Meetings tab
    const meetingsTab = screen.getByRole('tab', { name: 'Meetings' })
    await user.click(meetingsTab)

    await waitFor(() => {
      expect(screen.getByText('Strategy Meeting')).toBeInTheDocument()
    })
  })

  it('shows empty state when no activities', async () => {
    mockUseUpcomingActivities.mockReturnValue({
      data: [],
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    mockUseOverdueTasks.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('No overdue tasks')).toBeInTheDocument()
      expect(screen.getByText('No activities scheduled for today')).toBeInTheDocument()
      expect(screen.getByText('No upcoming activities')).toBeInTheDocument()
    })
  })

  it('displays activity timeline in All Activities tab', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      // The timeline should show upcoming activities
      const allTab = screen.getByRole('tab', { name: 'All Activities' })
      expect(allTab).toHaveAttribute('aria-selected', 'true')
      
      // Both activities should be visible in the timeline
      expect(screen.getByText('Call with John')).toBeInTheDocument()
      expect(screen.getByText('Strategy Meeting')).toBeInTheDocument()
    })
  })

  it('links to parent records', async () => {
    render(<ActivitiesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      // Check that parent names are displayed and linked
      const johnDoeLink = screen.getByText('John Doe')
      expect(johnDoeLink.closest('a')).toHaveAttribute('href', expect.stringContaining('/leads/'))
      
      const bigDealLink = screen.getByText('Big Deal')
      expect(bigDealLink.closest('a')).toHaveAttribute('href', expect.stringContaining('/opportunities/'))
    })
  })
})