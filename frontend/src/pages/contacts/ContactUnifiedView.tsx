import { useParams, Link } from 'react-router-dom'
import { ArrowLeft, Phone, Mail, Building, Calendar, MessageSquare, FileText, Activity } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Skeleton } from '@/components/ui/skeleton'
import { format } from 'date-fns'
import { useQuery } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'

interface UnifiedContactData {
  contact: {
    id: string
    first_name: string
    last_name: string
    email1: string
    phone_work?: string
    phone_mobile?: string
    title?: string
    department?: string
    account_id?: string
    account_name?: string
    is_company?: boolean
    assigned_user_id?: string
    assigned_user_name?: string
    description?: string
  }
  activities: Array<{
    type: 'website_visit' | 'ai_chat' | 'call' | 'meeting' | 'email' | 'note'
    date: string
    data: Record<string, unknown>
  }>
  tickets: Array<{
    id: string
    name: string
    status: string
    priority: string
    type: string
    date_entered: string
  }>
  opportunities: Array<{
    id: string
    name: string
    amount: number
    sales_stage: string
    probability: number
    date_closed: string
  }>
  score?: {
    type: 'health' | 'lead'
    value: number
    risk_level?: string
    factors?: Array<{ name: string; impact: string; description: string }>
    calculated_at: string
  }
  stats: {
    total_activities: number
    open_tickets: number
    total_opportunities: number
  }
}

function useUnifiedContact(id: string) {
  return useQuery({
    queryKey: ['contacts', id, 'unified'],
    queryFn: async () => {
      const response = await apiClient.customGet(`/contacts/${id}/unified`)
      return response.data
    },
    enabled: !!id,
  })
}

export function ContactUnifiedView() {
  const { id } = useParams()
  const { data, isLoading, error } = useUnifiedContact(id!)

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-32" />
        <Skeleton className="h-[600px] w-full" />
      </div>
    )
  }

  if (error || !data) {
    return (
      <div className="space-y-6">
        <Link to="/contacts">
          <Button variant="ghost" size="sm">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Contacts
          </Button>
        </Link>
        <Card>
          <CardContent className="p-6">
            <p className="text-center text-muted-foreground">Contact not found</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  const { contact, activities, tickets, opportunities, score, stats } = data

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'website_visit':
        return <Activity className="h-4 w-4" />
      case 'ai_chat':
        return <MessageSquare className="h-4 w-4" />
      case 'call':
        return <Phone className="h-4 w-4" />
      case 'meeting':
        return <Calendar className="h-4 w-4" />
      case 'email':
        return <Mail className="h-4 w-4" />
      case 'note':
        return <FileText className="h-4 w-4" />
      default:
        return <Activity className="h-4 w-4" />
    }
  }

  const getActivityTitle = (activity: UnifiedContactData['activities'][0]) => {
    switch (activity.type) {
      case 'website_visit':
        return `Website visit - ${activity.data.totalPageViews} pages viewed`
      case 'ai_chat':
        return `AI Chat conversation - ${activity.data.messageCount} messages`
      case 'call':
        return activity.data.name
      case 'meeting':
        return activity.data.name
      case 'email':
        return activity.data.name
      case 'note':
        return activity.data.name
      default:
        return 'Activity'
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link to="/contacts">
            <Button variant="ghost" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Contacts
            </Button>
          </Link>
        </div>
      </div>

      {/* Contact Info Card */}
      <Card>
        <CardHeader>
          <div className="flex items-start justify-between">
            <div>
              <CardTitle className="text-2xl">
                {contact.is_company ? contact.account_name : `${contact.first_name} ${contact.last_name}`}
              </CardTitle>
              {contact.title && <CardDescription>{contact.title}</CardDescription>}
              {contact.account_name && !contact.is_company && (
                <p className="text-sm text-muted-foreground mt-1">
                  <Building className="inline h-3 w-3 mr-1" />
                  {contact.account_name}
                </p>
              )}
            </div>
            {score && (
              <div className="text-right">
                <p className="text-sm text-muted-foreground">{score.type === 'health' ? 'Health Score' : 'Lead Score'}</p>
                <p className="text-3xl font-bold">{score.value}</p>
                {score.risk_level && (
                  <Badge variant={score.risk_level === 'high' ? 'destructive' : score.risk_level === 'medium' ? 'secondary' : 'default'}>
                    {score.risk_level} risk
                  </Badge>
                )}
              </div>
            )}
          </div>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 md:grid-cols-3">
            {contact.email1 && (
              <div>
                <p className="text-sm text-muted-foreground">Email</p>
                <a href={`mailto:${contact.email1}`} className="text-sm font-medium hover:underline">
                  {contact.email1}
                </a>
              </div>
            )}
            {contact.phone_work && (
              <div>
                <p className="text-sm text-muted-foreground">Work Phone</p>
                <a href={`tel:${contact.phone_work}`} className="text-sm font-medium hover:underline">
                  {contact.phone_work}
                </a>
              </div>
            )}
            {contact.phone_mobile && (
              <div>
                <p className="text-sm text-muted-foreground">Mobile</p>
                <a href={`tel:${contact.phone_mobile}`} className="text-sm font-medium hover:underline">
                  {contact.phone_mobile}
                </a>
              </div>
            )}
          </div>
          
          {/* Quick Stats */}
          <div className="grid gap-4 md:grid-cols-3 mt-6 pt-6 border-t">
            <div className="text-center">
              <p className="text-2xl font-semibold">{stats.total_activities}</p>
              <p className="text-sm text-muted-foreground">Total Activities</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-semibold">{stats.open_tickets}</p>
              <p className="text-sm text-muted-foreground">Open Tickets</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-semibold">{stats.total_opportunities}</p>
              <p className="text-sm text-muted-foreground">Opportunities</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Main Content Tabs */}
      <Tabs defaultValue="timeline" className="space-y-4">
        <TabsList>
          <TabsTrigger value="timeline">Timeline</TabsTrigger>
          <TabsTrigger value="opportunities">Opportunities</TabsTrigger>
          <TabsTrigger value="tickets">Support Tickets</TabsTrigger>
          <TabsTrigger value="details">Details</TabsTrigger>
        </TabsList>

        {/* Timeline Tab */}
        <TabsContent value="timeline" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Activity Timeline</CardTitle>
              <CardDescription>All interactions and activities in one place</CardDescription>
            </CardHeader>
            <CardContent>
              <ScrollArea className="h-[600px] pr-4">
                <div className="space-y-4">
                  {activities.length === 0 ? (
                    <p className="text-center text-muted-foreground py-8">No activities recorded</p>
                  ) : (
                    activities.map((activity: UnifiedContactData['activities'][0], index: number) => (
                      <div key={index} className="flex gap-4 pb-4 border-b last:border-0">
                        <div className="flex-shrink-0 w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                          {getActivityIcon(activity.type)}
                        </div>
                        <div className="flex-1 space-y-1">
                          <p className="text-sm font-medium">{getActivityTitle(activity)}</p>
                          {activity.data.description && (
                            <p className="text-sm text-muted-foreground">{String(activity.data.description)}</p>
                          )}
                          <p className="text-xs text-muted-foreground">
                            {format(new Date(activity.date), 'PPp')}
                            {activity.data.assignedTo && ` • ${String(activity.data.assignedTo)}`}
                          </p>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </ScrollArea>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Opportunities Tab */}
        <TabsContent value="opportunities" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Opportunities</CardTitle>
              <CardDescription>Active deals and pipeline</CardDescription>
            </CardHeader>
            <CardContent>
              {opportunities.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">No opportunities</p>
              ) : (
                <div className="space-y-4">
                  {opportunities.map((opp: UnifiedContactData['opportunities'][0]) => (
                    <div key={opp.id} className="flex items-center justify-between p-4 border rounded-lg">
                      <div>
                        <Link to={`/opportunities/${opp.id}`} className="font-medium hover:underline">
                          {opp.name}
                        </Link>
                        <p className="text-sm text-muted-foreground">
                          {opp.sales_stage} • {opp.probability}% • Closes {format(new Date(opp.date_closed), 'PP')}
                        </p>
                      </div>
                      <p className="font-semibold">${opp.amount.toLocaleString()}</p>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Support Tickets Tab */}
        <TabsContent value="tickets" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Support Tickets</CardTitle>
              <CardDescription>Customer support history</CardDescription>
            </CardHeader>
            <CardContent>
              {tickets.length === 0 ? (
                <p className="text-center text-muted-foreground py-8">No support tickets</p>
              ) : (
                <div className="space-y-4">
                  {tickets.map((ticket: UnifiedContactData['tickets'][0]) => (
                    <div key={ticket.id} className="flex items-center justify-between p-4 border rounded-lg">
                      <div>
                        <Link to={`/cases/${ticket.id}`} className="font-medium hover:underline">
                          {ticket.name}
                        </Link>
                        <p className="text-sm text-muted-foreground">
                          {ticket.type} • Created {format(new Date(ticket.date_entered), 'PP')}
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        <Badge variant={ticket.priority === 'High' ? 'destructive' : 'secondary'}>
                          {ticket.priority}
                        </Badge>
                        <Badge>{ticket.status}</Badge>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        {/* Details Tab */}
        <TabsContent value="details" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Contact Details</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <p className="text-sm text-muted-foreground">Department</p>
                  <p className="text-sm">{contact.department || 'Not specified'}</p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Type</p>
                  <p className="text-sm">{contact.isCompany ? 'Company' : 'Person'}</p>
                </div>
                {contact.description && (
                  <div className="md:col-span-2">
                    <p className="text-sm text-muted-foreground">Description</p>
                    <p className="text-sm whitespace-pre-wrap">{contact.description}</p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}