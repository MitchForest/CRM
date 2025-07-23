import { useParams, Link, useNavigate } from 'react-router-dom'
import { ArrowLeft, Edit, Trash2, DollarSign, Calendar, User, Target, TrendingUp, FileText } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { Separator } from '@/components/ui/separator'
import { useOpportunity } from '@/hooks/use-opportunities'
import { formatDate, formatDateTime, formatCurrency } from '@/lib/utils'
import { ActivityTimeline } from '@/components/activities/ActivityTimeline'
import { EmptyState } from '@/components/ui/empty-state'

// Sales stages for badge styling
const SALES_STAGES = [
  { id: 'prospecting', label: 'Prospecting', color: 'bg-slate-100' },
  { id: 'qualification', label: 'Qualification', color: 'bg-blue-100' },
  { id: 'proposal', label: 'Proposal', color: 'bg-yellow-100' },
  { id: 'negotiation', label: 'Negotiation', color: 'bg-orange-100' },
  { id: 'closed-won', label: 'Closed Won', color: 'bg-green-100' },
  { id: 'closed-lost', label: 'Closed Lost', color: 'bg-red-100' },
]

export function OpportunityDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { data: opportunityData, isLoading } = useOpportunity(id!)

  if (isLoading) {
    return (
      <div className="p-8 space-y-6">
        <div className="flex items-center gap-4">
          <Skeleton className="h-10 w-32" />
        </div>
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-96 w-full" />
      </div>
    )
  }

  if (!opportunityData?.success || !opportunityData.data) {
    return (
      <div className="p-8 space-y-6">
        <div className="flex items-center gap-4">
          <Link to="/opportunities">
            <Button variant="ghost" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Opportunities
            </Button>
          </Link>
        </div>
        <EmptyState
          title="Opportunity not found"
          description="The opportunity you're looking for doesn't exist or has been removed."
          action={
            <Button onClick={() => navigate('/opportunities')}>
              View all opportunities
            </Button>
          }
        />
      </div>
    )
  }

  const opportunity = opportunityData.data
  const stage = SALES_STAGES.find(s => s.id === opportunity.salesStage)

  return (
    <div className="p-8 space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link to="/opportunities">
            <Button variant="ghost" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Opportunities
            </Button>
          </Link>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" size="sm" asChild>
            <Link to={`/opportunities/${id}/edit`}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Link>
          </Button>
          <Button variant="outline" size="sm" className="text-destructive">
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-6">
          {/* Main Info Card */}
          <Card>
            <CardHeader>
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle className="text-2xl">{opportunity.name}</CardTitle>
                  <CardDescription className="mt-1">
                    Opportunity ID: {opportunity.id}
                  </CardDescription>
                </div>
                <Badge variant="secondary" className={stage?.color}>
                  {stage?.label || opportunity.salesStage}
                </Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                  <p className="text-sm text-muted-foreground">Amount</p>
                  <div className="flex items-center gap-2">
                    <DollarSign className="h-4 w-4 text-muted-foreground" />
                    <p className="text-lg font-semibold">
                      {formatCurrency(opportunity.amount)}
                    </p>
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm text-muted-foreground">Close Date</p>
                  <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-muted-foreground" />
                    <p className="font-medium">{formatDate(opportunity.closeDate)}</p>
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm text-muted-foreground">Probability</p>
                  <div className="flex items-center gap-2">
                    <Target className="h-4 w-4 text-muted-foreground" />
                    <p className="font-medium">{opportunity.probability || 0}%</p>
                  </div>
                </div>
                <div className="space-y-1">
                  <p className="text-sm text-muted-foreground">Expected Revenue</p>
                  <div className="flex items-center gap-2">
                    <TrendingUp className="h-4 w-4 text-muted-foreground" />
                    <p className="font-medium">
                      {formatCurrency(opportunity.amount * (opportunity.probability || 0) / 100)}
                    </p>
                  </div>
                </div>
              </div>

              {opportunity.description && (
                <>
                  <Separator />
                  <div className="space-y-2">
                    <div className="flex items-center gap-2">
                      <FileText className="h-4 w-4 text-muted-foreground" />
                      <h3 className="font-semibold">Description</h3>
                    </div>
                    <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                      {opportunity.description}
                    </p>
                  </div>
                </>
              )}

              {opportunity.nextStep && (
                <>
                  <Separator />
                  <div className="space-y-2">
                    <h3 className="font-semibold">Next Step</h3>
                    <p className="text-sm text-muted-foreground">
                      {opportunity.nextStep}
                    </p>
                  </div>
                </>
              )}
            </CardContent>
          </Card>

          {/* Tabs for Activities, Notes, etc. */}
          <Tabs defaultValue="activities" className="w-full">
            <TabsList className="grid w-full grid-cols-3">
              <TabsTrigger value="activities">Activities</TabsTrigger>
              <TabsTrigger value="notes">Notes</TabsTrigger>
              <TabsTrigger value="files">Files</TabsTrigger>
            </TabsList>

            <TabsContent value="activities" className="space-y-4">
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle>Activity Timeline</CardTitle>
                    <Button size="sm">
                      Add Activity
                    </Button>
                  </div>
                </CardHeader>
                <CardContent>
                  <ActivityTimeline 
                    activities={[]} 
                    emptyMessage="No activities recorded yet"
                  />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="notes" className="space-y-4">
              <Card>
                <CardContent className="p-6">
                  <EmptyState
                    title="No notes yet"
                    description="Add notes to keep track of important information"
                    action={
                      <Button size="sm">
                        Add Note
                      </Button>
                    }
                  />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="files" className="space-y-4">
              <Card>
                <CardContent className="p-6">
                  <EmptyState
                    title="No files attached"
                    description="Upload documents, contracts, or proposals"
                    action={
                      <Button size="sm">
                        Upload File
                      </Button>
                    }
                  />
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Contact Info */}
          {opportunity.contactName && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Contact Information</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex items-center gap-3">
                  <div className="h-10 w-10 rounded-full bg-muted flex items-center justify-center">
                    <User className="h-5 w-5 text-muted-foreground" />
                  </div>
                  <div>
                    <p className="font-medium">{opportunity.contactName}</p>
                    {opportunity.contactId && (
                      <Link 
                        to={`/contacts/${opportunity.contactId}`}
                        className="text-sm text-primary hover:underline"
                      >
                        View Contact
                      </Link>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* AI Insights */}
          {(opportunity.aiScore || opportunity.aiInsights) && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">AI Insights</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                {opportunity.aiScore && (
                  <div>
                    <p className="text-sm text-muted-foreground">AI Score</p>
                    <p className="text-2xl font-bold">{opportunity.aiScore}/100</p>
                  </div>
                )}
                {opportunity.aiInsights && (
                  <div>
                    <p className="text-sm text-muted-foreground">Insights</p>
                    <p className="text-sm mt-1">{opportunity.aiInsights}</p>
                  </div>
                )}
              </CardContent>
            </Card>
          )}

          {/* System Info */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">System Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {opportunity.assignedUserName && (
                <div>
                  <p className="text-sm text-muted-foreground">Assigned To</p>
                  <p className="text-sm font-medium">{opportunity.assignedUserName}</p>
                </div>
              )}
              <div>
                <p className="text-sm text-muted-foreground">Created</p>
                <p className="text-sm font-medium">
                  {opportunity.createdAt ? formatDateTime(opportunity.createdAt) : 'Unknown'}
                </p>
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Last Updated</p>
                <p className="text-sm font-medium">
                  {opportunity.updatedAt ? formatDateTime(opportunity.updatedAt) : 'Unknown'}
                </p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}