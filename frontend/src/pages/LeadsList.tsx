import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, Search, Download, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { Badge } from '@/components/ui/badge'
import { useLeads } from '@/hooks/use-leads'
import { formatDate } from '@/lib/utils'
import type { Lead } from '@/types/api.generated'
import type { ColumnDef } from '@tanstack/react-table'

const statusColors = {
  New: 'bg-blue-100 text-blue-700',
  Contacted: 'bg-yellow-100 text-yellow-700',
  Qualified: 'bg-green-100 text-green-700',
  Converted: 'bg-purple-100 text-purple-700',
  Dead: 'bg-gray-100 text-gray-700',
}

const columns: ColumnDef<Lead>[] = [
  {
    accessorKey: 'name',
    header: 'Name',
    cell: ({ row }) => {
      const lead = row.original
      return (
        <Link to={`/leads/${lead.id}`} className="font-medium hover:underline">
          {lead.firstName} {lead.lastName}
        </Link>
      )
    },
  },
  {
    accessorKey: 'company',
    header: 'Company',
  },
  {
    accessorKey: 'email',
    header: 'Email',
    cell: ({ row }) => (
      <a href={`mailto:${row.getValue('email')}`} className="hover:underline">
        {row.getValue('email')}
      </a>
    ),
  },
  {
    accessorKey: 'phone',
    header: 'Phone',
    cell: ({ row }) => {
      const phone = row.getValue('phone') as string
      return phone ? (
        <a href={`tel:${phone}`} className="hover:underline">
          {phone}
        </a>
      ) : (
        '-'
      )
    },
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('status') as keyof typeof statusColors
      return (
        <Badge className={`${statusColors[status]} border-transparent`}>
          {status}
        </Badge>
      )
    },
  },
  {
    accessorKey: 'source',
    header: 'Source',
    cell: ({ row }) => row.getValue('source') || '-',
  },
  {
    accessorKey: 'assignedUserName',
    header: 'Assigned To',
    cell: ({ row }) => row.getValue('assignedUserName') || '-',
  },
  {
    accessorKey: 'createdAt',
    header: 'Created',
    cell: ({ row }) => {
      const date = row.getValue('createdAt') as string
      return date ? formatDate(date) : '-'
    },
  },
  {
    id: 'actions',
    cell: ({ row }) => {
      const lead = row.original
      return (
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/leads/${lead.id}`}>View</Link>
          </Button>
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/leads/${lead.id}/edit`}>Edit</Link>
          </Button>
        </div>
      )
    },
  },
]

export function LeadsListPage() {
  const [page] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const limit = 10

  const filters: Record<string, string | number> = { search }
  if (statusFilter !== 'all') {
    filters.status = statusFilter
  }

  const { data, isLoading } = useLeads(page, limit, filters)

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Leads</h1>
          <p className="text-muted-foreground">
            Manage your sales leads and track conversions
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm">
            <Upload className="mr-2 h-4 w-4" />
            Import
          </Button>
          <Button variant="outline" size="sm">
            <Download className="mr-2 h-4 w-4" />
            Export
          </Button>
          <Button asChild>
            <Link to="/leads/new">
              <Plus className="mr-2 h-4 w-4" />
              Add Lead
            </Link>
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Leads</CardTitle>
          <CardDescription>
            A list of all leads including their status, source, and assigned team member.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search leads..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-8"
              />
            </div>
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Filter by status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem value="New">New</SelectItem>
                <SelectItem value="Contacted">Contacted</SelectItem>
                <SelectItem value="Qualified">Qualified</SelectItem>
                <SelectItem value="Converted">Converted</SelectItem>
                <SelectItem value="Dead">Dead</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {isLoading ? (
            <div className="flex justify-center p-8">
              <div className="text-muted-foreground">Loading...</div>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={data?.data || []}
            />
          )}
        </CardContent>
      </Card>
    </div>
  )
}