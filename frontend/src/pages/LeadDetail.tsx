import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { ArrowLeft, Mail, Phone, Edit, Trash2, UserPlus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { useLead, useConvertLead, useDeleteLead } from '@/hooks/use-leads'
import { formatDateTime } from '@/lib/utils'
import { ActivityTimeline } from '@/components/activities/ActivityTimeline'
import { useActivitiesByParent } from '@/hooks/use-activities'

const statusColors = {
  New: 'bg-blue-100 text-blue-700',
  Contacted: 'bg-yellow-100 text-yellow-700',
  Qualified: 'bg-green-100 text-green-700',
  Converted: 'bg-purple-100 text-purple-700',
  Dead: 'bg-gray-100 text-gray-700',
}

export function LeadDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [showConvertDialog, setShowConvertDialog] = useState(false)

  const { data: leadData, isLoading: leadLoading } = useLead(id!)
  const { data: activities } = useActivitiesByParent('Lead', id!)
  const convertLead = useConvertLead()
  const deleteLead = useDeleteLead()

  const handleConvert = async () => {
    if (!id) return

    await convertLead.mutateAsync(id)

    setShowConvertDialog(false)
    navigate('/contacts')
  }

  const handleDelete = async () => {
    if (!id || !confirm('Are you sure you want to delete this lead?')) return

    await deleteLead.mutateAsync(id)
    navigate('/leads')
  }

  if (leadLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Skeleton className="h-10 w-32" />
        </div>
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-96 w-full" />
      </div>
    )
  }

  if (!leadData?.success || !leadData.data) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Link to="/leads">
            <Button variant="ghost" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Leads
            </Button>
          </Link>
        </div>
        <Card>
          <CardContent className="p-6">
            <p className="text-center text-muted-foreground">Lead not found</p>
          </CardContent>
        </Card>
      </div>
    )
  }

  const lead = leadData.data
  // const activities = activitiesData?.data || []

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link to="/leads">
            <Button variant="ghost" size="sm">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Leads
            </Button>
          </Link>
        </div>
        <div className="flex gap-2">
          {lead.status !== 'Converted' && lead.status !== 'Dead' && (
            <Button onClick={() => setShowConvertDialog(true)}>
              <UserPlus className="mr-2 h-4 w-4" />
              Convert to Contact
            </Button>
          )}
          <Button variant="outline" size="sm" asChild>
            <Link to={`/leads/${id}/edit`}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Link>
          </Button>
          <Button variant="outline" size="sm" className="text-destructive" onClick={handleDelete}>
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <div className="md:col-span-2 space-y-6">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="text-2xl">
                    {lead.first_name} {lead.last_name}
                  </CardTitle>
                  <CardDescription>
                    {lead.title && `${lead.title} at `}
                    {lead.account_name || 'No company'}
                  </CardDescription>
                </div>
                <Badge className={`${statusColors[lead.status as keyof typeof statusColors] || ''} border-transparent`}>
                  {lead.status}
                </Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <p className="text-sm font-medium text-muted-foreground">Email</p>
                  <a href={`mailto:${lead.email1}`} className="flex items-center gap-2 text-sm hover:underline">
                    <Mail className="h-4 w-4" />
                    {lead.email1}
                  </a>
                </div>
                {lead.phone_work && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Phone</p>
                    <a href={`tel:${lead.phone_work}`} className="flex items-center gap-2 text-sm hover:underline">
                      <Phone className="h-4 w-4" />
                      {lead.phone_work}
                    </a>
                  </div>
                )}
                {lead.phone_mobile && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Mobile</p>
                    <a href={`tel:${lead.phone_mobile}`} className="flex items-center gap-2 text-sm hover:underline">
                      <Phone className="h-4 w-4" />
                      {lead.phone_mobile}
                    </a>
                  </div>
                )}
                {lead.website && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Website</p>
                    <a 
                      href={lead.website.startsWith('http') ? lead.website : `https://${lead.website}`} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="text-sm hover:underline"
                    >
                      {lead.website}
                    </a>
                  </div>
                )}
                {lead.lead_source && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Source</p>
                    <p className="text-sm">{lead.lead_source}</p>
                  </div>
                )}
                {lead.account_name && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Account Name</p>
                    <p className="text-sm">{lead.account_name}</p>
                  </div>
                )}
                {lead.assigned_user_id && (
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Assigned To</p>
                    <p className="text-sm">{lead.assigned_user_id}</p>
                  </div>
                )}
              </div>

              {lead.description && (
                <div>
                  <p className="text-sm font-medium text-muted-foreground mb-2">Description</p>
                  <p className="text-sm whitespace-pre-wrap">{lead.description}</p>
                </div>
              )}
            </CardContent>
          </Card>

          <Tabs defaultValue="activities" className="w-full">
            <TabsList>
              <TabsTrigger value="activities">
                Activities {activities && `(${activities.length})`}
              </TabsTrigger>
              <TabsTrigger value="history">History</TabsTrigger>
            </TabsList>
            
            <TabsContent value="activities" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Recent Activities</CardTitle>
                  <CardDescription>
                    All interactions and activities with this lead
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <ActivityTimeline parentType="Lead" parentId={id!} />
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="history">
              <Card>
                <CardContent className="p-6">
                  <p className="text-center text-muted-foreground">
                    Lead history coming soon
                  </p>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Quick Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button className="w-full" size="sm">
                <Mail className="mr-2 h-4 w-4" />
                Send Email
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                <Phone className="mr-2 h-4 w-4" />
                Log Call
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                Create Task
              </Button>
              <Button className="w-full" size="sm" variant="outline">
                Schedule Meeting
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div>
                <p className="text-muted-foreground">Created</p>
                <p>{lead.date_entered ? formatDateTime(lead.date_entered) : 'Unknown'}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Last Updated</p>
                <p>{lead.date_modified ? formatDateTime(String(lead.date_modified)) : 'Unknown'}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Convert Lead Dialog */}
      <Dialog open={showConvertDialog} onOpenChange={setShowConvertDialog}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle>Convert Lead to Contact</DialogTitle>
            <DialogDescription>
              Convert {lead.first_name} {lead.last_name} to a contact.
            </DialogDescription>
          </DialogHeader>
          <div className="py-4">
            <p className="text-sm text-muted-foreground">
              This will create a new contact with the lead's information.
            </p>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowConvertDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleConvert} disabled={convertLead.isPending}>
              {convertLead.isPending ? 'Converting...' : 'Convert Lead'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}