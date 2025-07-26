import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter } from 'react-router-dom'
import userEvent from '@testing-library/user-event'
import { CasesList } from '@/pages/cases/CasesList'
import { useCases } from '@/hooks/use-cases'
import type { Case } from '@/types/api.generated'

// Mock the cases hook
vi.mock('@/hooks/use-cases')

const mockCases: Case[] = [
  {
    id: '1',
    caseNumber: 'CASE-001',
    name: 'Login issue',
    status: 'Open',
    priority: 'High',
    type: 'Technical',
    // accountId: 'acc1',
    // accountName: 'Acme Corp',
    contactId: 'cont1',
    contactName: 'John Doe',
    description: 'Cannot login to the system',
    resolution: undefined,
    assignedUserId: 'user1',
    assignedUserName: 'Support Agent',
    // dateEntered: '2024-01-15T10:00:00Z',
    // dateModified: '2024-01-15T14:30:00Z',
  },
  {
    id: '2',
    caseNumber: 'CASE-002',
    name: 'Feature request',
    status: 'In Progress',
    priority: 'Medium',
    type: 'Enhancement',
    // accountId: 'acc2',
    // accountName: 'Tech Solutions',
    contactId: 'cont2',
    contactName: 'Jane Smith',
    description: 'Need export functionality',
    resolution: undefined,
    assignedUserId: 'user2',
    assignedUserName: 'Product Manager',
    // dateEntered: '2024-01-14T09:00:00Z',
    // dateModified: '2024-01-16T11:00:00Z',
  },
  {
    id: '3',
    caseNumber: 'CASE-003',
    name: 'Data sync problem',
    status: 'Closed',
    priority: 'Low',
    type: 'Bug',
    // accountId: 'acc1',
    // accountName: 'Acme Corp',
    contactId: 'cont3',
    contactName: 'Bob Wilson',
    description: 'Data not syncing properly',
    resolution: 'Fixed sync service',
    assignedUserId: 'user1',
    assignedUserName: 'Support Agent',
    // dateEntered: '2024-01-10T08:00:00Z',
    // dateModified: '2024-01-12T16:00:00Z',
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

describe('CasesList', () => {
  const mockUseCases = vi.mocked(useCases)

  beforeEach(() => {
    vi.clearAllMocks()
    
    mockUseCases.mockReturnValue({
      data: { data: mockCases },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)
  })

  it('renders cases list page', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('Support Cases')).toBeInTheDocument()
    })
  })

  it('displays critical cases alert', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText('1 critical cases require attention')).toBeInTheDocument()
    })
  })

  it('shows new case button', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      const newButton = screen.getByRole('link', { name: /new case/i })
      expect(newButton).toBeInTheDocument()
      expect(newButton).toHaveAttribute('href', '/cases/new')
    })
  })

  it('displays search input', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByPlaceholderText('Search cases...')).toBeInTheDocument()
    })
  })

  it('renders cases table with all columns', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      // Table headers
      expect(screen.getByText('Case #')).toBeInTheDocument()
      expect(screen.getByText('Subject')).toBeInTheDocument()
      expect(screen.getByText('Account')).toBeInTheDocument()
      expect(screen.getByText('Priority')).toBeInTheDocument()
      expect(screen.getByText('Status')).toBeInTheDocument()
      expect(screen.getByText('Assigned To')).toBeInTheDocument()
      expect(screen.getByText('Created')).toBeInTheDocument()
      expect(screen.getByText('Actions')).toBeInTheDocument()
    })
  })

  it('displays case data in table rows', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      // Case 1
      expect(screen.getByText('CASE-001')).toBeInTheDocument()
      expect(screen.getByText('Login issue')).toBeInTheDocument()
      expect(screen.getByText('Acme Corp')).toBeInTheDocument()
      expect(screen.getAllByText('P1')[0]).toBeInTheDocument()
      expect(screen.getByText('New')).toBeInTheDocument()

      // Case 2
      expect(screen.getByText('CASE-002')).toBeInTheDocument()
      expect(screen.getByText('Feature request')).toBeInTheDocument()
      expect(screen.getByText('Tech Solutions')).toBeInTheDocument()
      expect(screen.getAllByText('P2')[0]).toBeInTheDocument()
      expect(screen.getByText('In Progress')).toBeInTheDocument()
    })
  })

  it('applies correct priority badge colors', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      const p1Badge = screen.getAllByText('High')[0]
      expect(p1Badge!.className).toContain('red')

      const p2Badge = screen.getAllByText('Medium')[0]
      expect(p2Badge!.className).toContain('yellow')

      const p3Badge = screen.getAllByText('Low')[0]
      expect(p3Badge!.className).toContain('green')
    })
  })

  it('applies correct status badge colors', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      const newBadge = screen.getByText('New')
      expect(newBadge.className).toContain('blue')

      const inProgressBadge = screen.getByText('In Progress')
      expect(inProgressBadge.className).toContain('purple')

      const closedBadge = screen.getByText('Closed')
      expect(closedBadge.className).toContain('gray')
    })
  })

  it('filters cases by search term', async () => {
    const user = userEvent.setup()
    const mockRefetch = vi.fn()
    
    mockUseCases.mockReturnValue({
      data: { data: mockCases },
      isLoading: false,
      error: null,
      refetch: mockRefetch,
    } as any)

    render(<CasesList />, { wrapper: createWrapper() })

    const searchInput = await screen.findByPlaceholderText('Search cases...')
    await user.type(searchInput, 'login')

    // The component should update its state and potentially refetch
    expect(searchInput).toHaveValue('login')
  })

  it('filters cases by status', async () => {
    const user = userEvent.setup()
    render(<CasesList />, { wrapper: createWrapper() })

    // Find and click status filter
    const statusSelect = await screen.findByRole('combobox')
    await user.click(statusSelect)

    // Select "New" status
    const newOption = await screen.findByText('New', { selector: '[role="option"]' })
    await user.click(newOption)

    // Component should update to show filtered results
    expect(mockUseCases).toHaveBeenCalled()
  })

  it('shows loading state', () => {
    mockUseCases.mockReturnValue({
      data: null,
      isLoading: true,
      error: null,
      refetch: vi.fn(),
    } as any)

    render(<CasesList />, { wrapper: createWrapper() })

    expect(screen.getByText('Loading cases...')).toBeInTheDocument()
  })

  it('shows empty state when no cases', async () => {
    mockUseCases.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.getByText(/no cases found/i)).toBeInTheDocument()
    })
  })

  it('shows no critical cases when all are low priority', async () => {
    const lowPriorityCases = mockCases.map(c => ({ ...c, priority: 'P3' as const }))
    
    mockUseCases.mockReturnValue({
      data: { data: lowPriorityCases },
      isLoading: false,
      error: null,
      refetch: vi.fn(),
    } as any)

    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      expect(screen.queryByText(/critical cases require attention/)).not.toBeInTheDocument()
    })
  })

  it('provides view action for each case', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      const viewButtons = screen.getAllByRole('link', { name: /view/i })
      expect(viewButtons).toHaveLength(3)
      expect(viewButtons[0]).toHaveAttribute('href', '/cases/1')
      expect(viewButtons[1]).toHaveAttribute('href', '/cases/2')
      expect(viewButtons[2]).toHaveAttribute('href', '/cases/3')
    })
  })

  it('formats dates correctly', async () => {
    render(<CasesList />, { wrapper: createWrapper() })

    await waitFor(() => {
      // Check that dates are formatted (exact format depends on formatDate util)
      expect(screen.getByText(/Jan 15, 2024|15\/01\/2024|2024-01-15/)).toBeInTheDocument()
      expect(screen.getByText(/Jan 14, 2024|14\/01\/2024|2024-01-14/)).toBeInTheDocument()
    })
  })
})