import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, Search, Download, Upload, Building2, Phone, Globe } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { DataTable } from '@/components/ui/data-table'
import { useAccounts, useDeleteAccount } from '@/hooks/use-accounts'
import { formatDate } from '@/lib/utils'
import type { Account } from '@/types/api.generated'
import type { ColumnDef } from '@tanstack/react-table'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

const columns: ColumnDef<Account>[] = [
  {
    accessorKey: 'name',
    header: 'Name',
    cell: ({ row }) => {
      const account = row.original
      return (
        <Link to={`/accounts/${account.id}`} className="flex items-center gap-2 font-medium hover:underline">
          <Building2 className="h-4 w-4 text-muted-foreground" />
          {account.name}
        </Link>
      )
    },
  },
  {
    accessorKey: 'industry',
    header: 'Industry',
    cell: ({ row }) => row.getValue('industry') || '-',
  },
  {
    accessorKey: 'phone',
    header: 'Phone',
    cell: ({ row }) => {
      const phone = row.getValue('phone') as string
      return phone ? (
        <a href={`tel:${phone}`} className="flex items-center gap-1 hover:underline">
          <Phone className="h-3 w-3" />
          {phone}
        </a>
      ) : (
        '-'
      )
    },
  },
  {
    accessorKey: 'website',
    header: 'Website',
    cell: ({ row }) => {
      const website = row.getValue('website') as string
      if (!website) return '-'
      
      try {
        const url = website.startsWith('http') ? website : `https://${website}`
        const hostname = new URL(url).hostname
        return (
          <a href={url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-1 hover:underline">
            <Globe className="h-3 w-3" />
            {hostname}
          </a>
        )
      } catch {
        return website
      }
    },
  },
  {
    accessorKey: 'annualRevenue',
    header: 'Annual Revenue',
    cell: ({ row }) => {
      const revenue = row.getValue('annualRevenue') as number
      return revenue ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(revenue) : '-'
    },
  },
  {
    accessorKey: 'employees',
    header: 'Employees',
    cell: ({ row }) => row.getValue('employees') || '-',
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
      const account = row.original
      return (
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" asChild>
            <Link to={`/accounts/${account.id}/edit`}>Edit</Link>
          </Button>
        </div>
      )
    },
  },
]

export function AccountsListPage() {
  const [page] = useState(1)
  const [search, setSearch] = useState('')
  const [deleteAccountId, setDeleteAccountId] = useState<string | null>(null)
  const limit = 10

  const { data, isLoading } = useAccounts(page, limit, {
    search,
  })

  const deleteAccount = useDeleteAccount()

  const handleDelete = async () => {
    if (deleteAccountId) {
      await deleteAccount.mutateAsync(deleteAccountId)
      setDeleteAccountId(null)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Accounts</h1>
          <p className="text-muted-foreground">
            Manage your business accounts and track relationships
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
            <Link to="/accounts/new">
              <Plus className="mr-2 h-4 w-4" />
              Add Account
            </Link>
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Accounts</CardTitle>
          <CardDescription>
            A list of all accounts including their industry, contact information, and assigned team member.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex items-center gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search accounts..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-8"
              />
            </div>
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

      <AlertDialog open={!!deleteAccountId} onOpenChange={() => setDeleteAccountId(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Are you sure?</AlertDialogTitle>
            <AlertDialogDescription>
              This action cannot be undone. This will permanently delete the account
              and remove all associated data.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}