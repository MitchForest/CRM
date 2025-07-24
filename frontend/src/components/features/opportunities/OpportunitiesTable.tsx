import { Link } from 'react-router-dom'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import type { Opportunity } from '@/types/api.generated'
import { OPPORTUNITY_STATUS_COLORS } from '@/types/phase2.types'
import { cn } from '@/lib/utils'

interface OpportunitiesTableProps {
  opportunities: Opportunity[]
}

export function OpportunitiesTable({ opportunities }: OpportunitiesTableProps) {
  const formatCurrency = (amount: number, currency = 'USD') => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount)
  }

  const formatDate = (dateString: string) => {
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }).format(date)
  }

  const getProbabilityColor = (probability: number) => {
    if (probability >= 70) return 'text-green-600'
    if (probability >= 40) return 'text-yellow-600'
    return 'text-red-600'
  }

  return (
    <div className="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Account</TableHead>
            <TableHead>Stage</TableHead>
            <TableHead className="text-right">Amount</TableHead>
            <TableHead>Probability</TableHead>
            <TableHead>Close Date</TableHead>
            <TableHead>Assigned To</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {opportunities.map((opportunity) => (
            <TableRow key={opportunity.id}>
              <TableCell className="font-medium">
                <Link
                  to={`/opportunities/${opportunity.id}`}
                  className="hover:underline"
                >
                  {opportunity.name}
                </Link>
              </TableCell>
              <TableCell>{opportunity.contactName || '-'}</TableCell>
              <TableCell>
                <Badge 
                  variant="outline" 
                  className={cn(
                    OPPORTUNITY_STATUS_COLORS[opportunity.salesStage as keyof typeof OPPORTUNITY_STATUS_COLORS] || 
                    'bg-gray-100 text-gray-800'
                  )}
                >
                  {opportunity.salesStage}
                </Badge>
              </TableCell>
              <TableCell className="text-right">
                {formatCurrency(opportunity.amount, opportunity.currency)}
              </TableCell>
              <TableCell>
                <span className={cn(
                  'font-medium',
                  getProbabilityColor(opportunity.probability || 0)
                )}>
                  {opportunity.probability}%
                </span>
              </TableCell>
              <TableCell>{formatDate(opportunity.closeDate)}</TableCell>
              <TableCell>{opportunity.assignedUserName || '-'}</TableCell>
              <TableCell className="text-right">
                <Button variant="ghost" size="sm" asChild>
                  <Link to={`/opportunities/${opportunity.id}`}>
                    View
                  </Link>
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}