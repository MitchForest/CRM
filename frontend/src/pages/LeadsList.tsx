import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, Download, Upload, RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { Badge } from '@/components/ui/badge'
import { TablePageLayout } from '@/components/ui/table-page-layout'
import { TableToolbar } from '@/components/ui/table-toolbar'
import { TableActions } from '@/components/ui/table-actions'
import { useLeads } from '@/hooks/use-leads'
import { formatDate } from '@/lib/utils'
import { usePermissions } from '@/hooks/use-permissions'
import { PermissionGuard } from '@/components/auth/PermissionGuard'
import type { LeadDB } from '@/types/database.types'
import type { ColumnDef } from '@tanstack/react-table'

const statusColors = {
  new: 'bg-blue-100 text-blue-700',
  contacted: 'bg-yellow-100 text-yellow-700',
  qualified: 'bg-green-100 text-green-700',
  converted: 'bg-purple-100 text-purple-700',
  dead: 'bg-gray-100 text-gray-700',
}

const columns: ColumnDef<LeadDB>[] = [
  {
    accessorKey: 'name',
    header: 'Name',
    cell: ({ row }) => {
      const lead = row.original
      return (
        <Link to={`/app/leads/${lead.id}`} className="font-medium hover:underline">
          {lead.first_name} {lead.last_name}
        </Link>
      )
    },
  },
  {
    accessorKey: 'account_name',
    header: 'Company',
  },
  {
    accessorKey: 'email1',
    header: 'Email',
    cell: ({ row }) => (
      <a href={`mailto:${row.getValue('email1')}`} className="hover:underline">
        {row.getValue('email1')}
      </a>
    ),
  },
  {
    accessorKey: 'phone_work',
    header: 'Phone',
    cell: ({ row }) => {
      const phone = row.getValue('phone_work') as string
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
    accessorKey: 'lead_source',
    header: 'Source',
    cell: ({ row }) => row.getValue('lead_source') || '-',
  },
  {
    accessorKey: 'ai_score',
    header: 'AI Score',
    cell: ({ row }) => {
      const aiScore = row.original.ai_score
      if (!aiScore && aiScore !== 0) return '-'
      const score = typeof aiScore === 'number' ? aiScore : parseInt(aiScore)
      let color = 'text-gray-600'
      if (score >= 80) color = 'text-green-600 font-semibold'
      else if (score >= 60) color = 'text-yellow-600'
      else if (score >= 40) color = 'text-orange-600'
      else color = 'text-red-600'
      return <span className={color}>{score}</span>
    },
  },
  {
    accessorKey: 'assigned_user_name',
    header: 'Assigned To',
    cell: ({ row }) => row.getValue('assigned_user_name') || '-',
  },
  {
    accessorKey: 'date_entered',
    header: 'Created',
    cell: ({ row }) => {
      const date = row.getValue('date_entered') as string
      return date ? formatDate(date) : '-'
    },
  },
  {
    id: 'actions',
    cell: function ActionsCell({ row }) {
      const lead = row.original
      const { canEdit } = usePermissions()
      
      const actions = [
        {
          label: 'View',
          onClick: () => window.location.href = `/leads/${lead.id}`,
        },
      ]
      
      if (canEdit('Leads')) {
        actions.push({
          label: 'Edit',
          onClick: () => window.location.href = `/leads/${lead.id}/edit`,
        })
      }
      
      return <TableActions actions={actions} />
    },
  },
]

export function LeadsListPage() {
  const [page] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const limit = 10

  const filters: Record<string, string | number> = {}
  if (search) {
    filters['search'] = search
  }
  if (statusFilter !== 'all') {
    filters['status'] = statusFilter
  }

  const { data, isLoading, refetch } = useLeads(page, limit, filters)

  return (
    <TablePageLayout
      title="Leads"
      description="Manage your sales leads and track conversions"
      actions={
        <>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            <RefreshCw className="mr-2 h-4 w-4" />
            Refresh
          </Button>
          <Button variant="outline" size="sm">
            <Upload className="mr-2 h-4 w-4" />
            Import
          </Button>
          <Button variant="outline" size="sm">
            <Download className="mr-2 h-4 w-4" />
            Export
          </Button>
          <PermissionGuard module="Leads" action="create">
            <Button asChild>
              <Link to="/leads/new">
                <Plus className="mr-2 h-4 w-4" />
                Add Lead
              </Link>
            </Button>
          </PermissionGuard>
        </>
      }
    >

      <Card>
        <CardContent className="pt-6">
          <TableToolbar
            searchValue={search}
            onSearchChange={setSearch}
            searchPlaceholder="Search leads..."
            filters={
              <Select value={statusFilter} onValueChange={setStatusFilter}>
                <SelectTrigger className="w-[180px]">
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Statuses</SelectItem>
                  <SelectItem value="new">New</SelectItem>
                  <SelectItem value="contacted">Contacted</SelectItem>
                  <SelectItem value="qualified">Qualified</SelectItem>
                  <SelectItem value="converted">Converted</SelectItem>
                  <SelectItem value="dead">Dead</SelectItem>
                </SelectContent>
              </Select>
            }
          />

          <div className="mt-4">
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
          </div>
        </CardContent>
      </Card>
    </TablePageLayout>
  )
}