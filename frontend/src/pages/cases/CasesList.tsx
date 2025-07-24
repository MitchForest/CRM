import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, Search, AlertCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useCases, useCriticalCases } from '@/hooks/use-cases'
import { formatDate } from '@/lib/utils'
import { PriorityBadge } from '@/components/ui/priority-badge'

export function CasesList() {
  const [searchTerm, setSearchTerm] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [priorityFilter, setPriorityFilter] = useState<string>('all')
  const [page, setPage] = useState(1)

  const { data, isLoading, error } = useCases(page, 20, {
    status: statusFilter === 'all' ? undefined : statusFilter,
    priority: priorityFilter === 'all' ? undefined : priorityFilter,
    search: searchTerm || undefined,
  })

  const { data: criticalCases } = useCriticalCases()

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      'New': 'bg-blue-100 text-blue-800',
      'Assigned': 'bg-yellow-100 text-yellow-800',
      'In Progress': 'bg-purple-100 text-purple-800',
      'Pending Input': 'bg-orange-100 text-orange-800',
      'Closed': 'bg-gray-100 text-gray-800',
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Support Cases</h1>
          {criticalCases && criticalCases.length > 0 && (
            <div className="mt-1 flex items-center gap-2 text-sm text-red-600">
              <AlertCircle className="h-4 w-4" />
              <span>{criticalCases.length} critical cases require attention</span>
            </div>
          )}
        </div>
        <Button asChild>
          <Link to="/cases/new">
            <Plus className="mr-2 h-4 w-4" />
            New Case
          </Link>
        </Button>
      </div>

      <div className="mb-4 flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            placeholder="Search cases..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
        <Select
          value={statusFilter}
          onValueChange={setStatusFilter}
        >
          <SelectTrigger className="w-[180px]">
            <SelectValue placeholder="All statuses" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="New">New</SelectItem>
            <SelectItem value="Assigned">Assigned</SelectItem>
            <SelectItem value="In Progress">In Progress</SelectItem>
            <SelectItem value="Pending Input">Pending Input</SelectItem>
            <SelectItem value="Closed">Closed</SelectItem>
          </SelectContent>
        </Select>
        <Select
          value={priorityFilter}
          onValueChange={setPriorityFilter}
        >
          <SelectTrigger className="w-[180px]">
            <SelectValue placeholder="All priorities" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All priorities</SelectItem>
            <SelectItem value="High">P1 - High</SelectItem>
            <SelectItem value="Medium">P2 - Medium</SelectItem>
            <SelectItem value="Low">P3 - Low</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {isLoading && <div>Loading cases...</div>}

      {error && (
        <div className="rounded-md border border-red-200 bg-red-50 p-4">
          <div className="flex items-center gap-2 text-red-800">
            <AlertCircle className="h-5 w-5" />
            <p className="font-medium">Failed to load cases</p>
          </div>
          <p className="mt-1 text-sm text-red-700">
            Please check if the backend server is running and try again.
          </p>
        </div>
      )}

      {data && (
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Case #</TableHead>
                <TableHead>Subject</TableHead>
                <TableHead>Account</TableHead>
                <TableHead>Priority</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Assigned To</TableHead>
                <TableHead>Created</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.data.map((caseItem) => (
                <TableRow key={caseItem.id}>
                  <TableCell className="font-medium">
                    {caseItem.caseNumber}
                  </TableCell>
                  <TableCell>{caseItem.name}</TableCell>
                  <TableCell>-</TableCell>
                  <TableCell>
                    <PriorityBadge priority={caseItem.priority} />
                  </TableCell>
                  <TableCell>
                    <Badge className={getStatusColor(caseItem.status)}>
                      {caseItem.status}
                    </Badge>
                  </TableCell>
                  <TableCell>{caseItem.assignedUserName || '-'}</TableCell>
                  <TableCell>{formatDate(caseItem.createdAt || caseItem.updatedAt || '')}</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="sm" asChild>
                      <Link to={`/cases/${caseItem.id}`}>View</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}

      {data && data.pagination && (
        <div className="mt-4 flex items-center justify-between">
          <div className="text-sm text-muted-foreground">
            Showing {(page - 1) * 20 + 1} to {Math.min(page * 20, data.data.length)} of {data.data.length} cases
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage(page - 1)}
              disabled={page === 1}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage(page + 1)}
              disabled={!data.pagination.hasNext}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}