import { useState } from 'react'
import { Link } from 'react-router-dom'
import { type ColumnDef } from '@tanstack/react-table'
import { Plus, MoreHorizontal, ArrowUpDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { DataTable } from '@/components/ui/data-table'
import { useContacts } from '@/hooks/use-contacts'
import { formatDate } from '@/lib/utils'
import type { ContactDB } from '@/types/database.types'
import { Skeleton } from '@/components/ui/skeleton'

// Define columns for the contacts table
const columns: ColumnDef<ContactDB>[] = [
  {
    accessorKey: 'name',
    header: ({ column }) => {
      return (
        <Button
          variant="ghost"
          onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
        >
          Name
          <ArrowUpDown className="ml-2 h-4 w-4" />
        </Button>
      )
    },
    cell: ({ row }) => {
      const contact = row.original
      return (
        <Link 
          to={`/contacts/${contact.id}`}
          className="font-medium hover:underline"
        >
          {contact.first_name} {contact.last_name}
        </Link>
      )
    },
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
    accessorKey: 'account_name',
    header: 'Company',
    cell: ({ row }) => row.getValue('account_name') || '-',
  },
  {
    accessorKey: 'lead_source',
    header: 'Source',
    cell: ({ row }) => row.getValue('lead_source') || '-',
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
    cell: ({ row }) => {
      const contact = row.original

      return (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="h-8 w-8 p-0">
              <span className="sr-only">Open menu</span>
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuLabel>Actions</DropdownMenuLabel>
            <DropdownMenuItem asChild>
              <Link to={`/contacts/${contact.id}`}>View details</Link>
            </DropdownMenuItem>
            <DropdownMenuItem asChild>
              <Link to={`/contacts/${contact.id}/edit`}>Edit contact</Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem className="text-destructive">
              Delete contact
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      )
    },
  },
]

export function ContactsPage() {
  const [page] = useState(1)
  const { data, isLoading } = useContacts({ page, pageSize: 20 })

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Contacts</h1>
            <p className="text-muted-foreground">Manage your customer relationships</p>
          </div>
          <Skeleton className="h-10 w-32" />
        </div>
        <div className="space-y-4">
          {[...Array(5)].map((_, i) => (
            <Skeleton key={i} className="h-16 w-full" />
          ))}
        </div>
      </div>
    )
  }

  const contacts = data?.data || []

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Contacts</h1>
          <p className="text-muted-foreground">Manage your customer relationships</p>
        </div>
        <Button asChild>
          <Link to="/contacts/new">
            <Plus className="mr-2 h-4 w-4" />
            Add Contact
          </Link>
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={contacts}
        searchKey="email1"
      />
    </div>
  )
}