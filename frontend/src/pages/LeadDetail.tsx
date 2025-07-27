import { useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { ArrowLeft, Mail, Phone, Edit, Trash2, UserPlus, CheckSquare, Calendar } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Skeleton } from '@/components/ui/skeleton'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useLead, useConvertLead, useDeleteLead } from '@/hooks/use-leads'
import { formatDateTime } from '@/lib/utils'
import { UnifiedActivityTimeline } from '@/components/activities/UnifiedActivityTimeline'
import { useActivitiesByParent } from '@/hooks/use-activities'
import { apiClient } from '@/lib/api-client'
import { toast } from 'sonner'

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
  const [showEmailDialog, setShowEmailDialog] = useState(false)
  const [showCallDialog, setShowCallDialog] = useState(false)
  const [showTaskDialog, setShowTaskDialog] = useState(false)
  const [showMeetingDialog, setShowMeetingDialog] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  
  // Form states
  const [emailForm, setEmailForm] = useState({ subject: '', body: '' })
  const [callForm, setCallForm] = useState({ 
    direction: 'outbound', 
    status: 'completed',
    duration_minutes: 15,
    description: '' 
  })
  const [taskForm, setTaskForm] = useState({ 
    name: '', 
    description: '',
    priority: 'medium',
    due_date: new Date().toISOString().split('T')[0]
  })
  const [meetingForm, setMeetingForm] = useState({ 
    name: '', 
    description: '',
    date_start: new Date().toISOString().split('T')[0],
    time_start: '09:00',
    duration_hours: 1
  })

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

  const handleSendEmail = async () => {
    if (!emailForm.subject || !emailForm.body) {
      toast.error('Please fill in all fields')
      return
    }
    
    setIsSubmitting(true)
    try {
      // For now, just log the email as a note
      await apiClient.createNote({
        name: `Email: ${emailForm.subject}`,
        description: `Subject: ${emailForm.subject}\n\nBody:\n${emailForm.body}\n\nSent at: ${new Date().toLocaleString()}`,
        parent_type: 'Leads',
        parent_id: id!
      })
      
      toast.success('Email logged successfully')
      setShowEmailDialog(false)
      setEmailForm({ subject: '', body: '' })
      // Refresh the timeline
      window.location.reload()
    } catch (error) {
      toast.error('Failed to log email')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleLogCall = async () => {
    if (!callForm.description) {
      toast.error('Please add call notes')
      return
    }
    
    setIsSubmitting(true)
    try {
      await apiClient.createCall({
        name: `Call with ${lead?.first_name} ${lead?.last_name}`,
        direction: callForm.direction,
        status: callForm.status,
        duration_minutes: callForm.duration_minutes,
        description: callForm.description,
        parent_type: 'Leads',
        parent_id: id!,
        date_start: new Date().toISOString()
      })
      
      toast.success('Call logged successfully')
      setShowCallDialog(false)
      setCallForm({ direction: 'outbound', status: 'completed', duration_minutes: 15, description: '' })
      window.location.reload()
    } catch (error) {
      toast.error('Failed to log call')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleCreateTask = async () => {
    if (!taskForm.name) {
      toast.error('Please enter a task name')
      return
    }
    
    setIsSubmitting(true)
    try {
      await apiClient.createTask({
        name: taskForm.name,
        description: taskForm.description,
        priority: taskForm.priority,
        status: 'Not Started',
        date_due: taskForm.due_date,
        parent_type: 'Leads',
        parent_id: id!
      })
      
      toast.success('Task created successfully')
      setShowTaskDialog(false)
      setTaskForm({ name: '', description: '', priority: 'medium', due_date: new Date().toISOString().split('T')[0] })
      window.location.reload()
    } catch (error) {
      toast.error('Failed to create task')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleScheduleMeeting = async () => {
    if (!meetingForm.name) {
      toast.error('Please enter a meeting subject')
      return
    }
    
    setIsSubmitting(true)
    try {
      const dateTime = `${meetingForm.date_start}T${meetingForm.time_start}:00`
      await apiClient.createMeeting({
        name: meetingForm.name,
        description: meetingForm.description,
        status: 'Planned',
        date_start: dateTime,
        duration_hours: meetingForm.duration_hours,
        duration_minutes: 0,
        parent_type: 'Leads',
        parent_id: id!
      })
      
      toast.success('Meeting scheduled successfully')
      setShowMeetingDialog(false)
      setMeetingForm({ name: '', description: '', date_start: new Date().toISOString().split('T')[0], time_start: '09:00', duration_hours: 1 })
      window.location.reload()
    } catch (error) {
      toast.error('Failed to schedule meeting')
    } finally {
      setIsSubmitting(false)
    }
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
                  <UnifiedActivityTimeline entityType="lead" entityId={id!} />
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
              <Button className="w-full" size="sm" onClick={() => setShowEmailDialog(true)}>
                <Mail className="mr-2 h-4 w-4" />
                Send Email
              </Button>
              <Button className="w-full" size="sm" variant="outline" onClick={() => setShowCallDialog(true)}>
                <Phone className="mr-2 h-4 w-4" />
                Log Call
              </Button>
              <Button className="w-full" size="sm" variant="outline" onClick={() => setShowTaskDialog(true)}>
                <CheckSquare className="mr-2 h-4 w-4" />
                Create Task
              </Button>
              <Button className="w-full" size="sm" variant="outline" onClick={() => setShowMeetingDialog(true)}>
                <Calendar className="mr-2 h-4 w-4" />
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

      {/* Email Dialog */}
      <Dialog open={showEmailDialog} onOpenChange={setShowEmailDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Send Email</DialogTitle>
            <DialogDescription>
              Log an email sent to {lead?.first_name} {lead?.last_name}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label htmlFor="email-subject">Subject</Label>
              <Input
                id="email-subject"
                value={emailForm.subject}
                onChange={(e) => setEmailForm({ ...emailForm, subject: e.target.value })}
                placeholder="Email subject..."
              />
            </div>
            <div>
              <Label htmlFor="email-body">Message</Label>
              <Textarea
                id="email-body"
                value={emailForm.body}
                onChange={(e) => setEmailForm({ ...emailForm, body: e.target.value })}
                placeholder="Email content..."
                rows={6}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowEmailDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleSendEmail} disabled={isSubmitting}>
              {isSubmitting ? 'Sending...' : 'Send Email'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Call Dialog */}
      <Dialog open={showCallDialog} onOpenChange={setShowCallDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Log Call</DialogTitle>
            <DialogDescription>
              Record a call with {lead?.first_name} {lead?.last_name}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="call-direction">Direction</Label>
                <Select value={callForm.direction} onValueChange={(value) => setCallForm({ ...callForm, direction: value })}>
                  <SelectTrigger id="call-direction">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="inbound">Inbound</SelectItem>
                    <SelectItem value="outbound">Outbound</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label htmlFor="call-duration">Duration (minutes)</Label>
                <Input
                  id="call-duration"
                  type="number"
                  value={callForm.duration_minutes}
                  onChange={(e) => setCallForm({ ...callForm, duration_minutes: parseInt(e.target.value) || 0 })}
                />
              </div>
            </div>
            <div>
              <Label htmlFor="call-notes">Call Notes</Label>
              <Textarea
                id="call-notes"
                value={callForm.description}
                onChange={(e) => setCallForm({ ...callForm, description: e.target.value })}
                placeholder="What was discussed..."
                rows={4}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowCallDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleLogCall} disabled={isSubmitting}>
              {isSubmitting ? 'Logging...' : 'Log Call'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Task Dialog */}
      <Dialog open={showTaskDialog} onOpenChange={setShowTaskDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create Task</DialogTitle>
            <DialogDescription>
              Add a new task for {lead?.first_name} {lead?.last_name}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label htmlFor="task-name">Task Name</Label>
              <Input
                id="task-name"
                value={taskForm.name}
                onChange={(e) => setTaskForm({ ...taskForm, name: e.target.value })}
                placeholder="Follow up on proposal..."
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="task-priority">Priority</Label>
                <Select value={taskForm.priority} onValueChange={(value) => setTaskForm({ ...taskForm, priority: value })}>
                  <SelectTrigger id="task-priority">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="low">Low</SelectItem>
                    <SelectItem value="medium">Medium</SelectItem>
                    <SelectItem value="high">High</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label htmlFor="task-due">Due Date</Label>
                <Input
                  id="task-due"
                  type="date"
                  value={taskForm.due_date}
                  onChange={(e) => setTaskForm({ ...taskForm, due_date: e.target.value })}
                />
              </div>
            </div>
            <div>
              <Label htmlFor="task-description">Description</Label>
              <Textarea
                id="task-description"
                value={taskForm.description}
                onChange={(e) => setTaskForm({ ...taskForm, description: e.target.value })}
                placeholder="Task details..."
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowTaskDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleCreateTask} disabled={isSubmitting}>
              {isSubmitting ? 'Creating...' : 'Create Task'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Meeting Dialog */}
      <Dialog open={showMeetingDialog} onOpenChange={setShowMeetingDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Schedule Meeting</DialogTitle>
            <DialogDescription>
              Schedule a meeting with {lead?.first_name} {lead?.last_name}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label htmlFor="meeting-subject">Subject</Label>
              <Input
                id="meeting-subject"
                value={meetingForm.name}
                onChange={(e) => setMeetingForm({ ...meetingForm, name: e.target.value })}
                placeholder="Product demo..."
              />
            </div>
            <div className="grid grid-cols-3 gap-4">
              <div className="col-span-2">
                <Label htmlFor="meeting-date">Date</Label>
                <Input
                  id="meeting-date"
                  type="date"
                  value={meetingForm.date_start}
                  onChange={(e) => setMeetingForm({ ...meetingForm, date_start: e.target.value })}
                />
              </div>
              <div>
                <Label htmlFor="meeting-time">Time</Label>
                <Input
                  id="meeting-time"
                  type="time"
                  value={meetingForm.time_start}
                  onChange={(e) => setMeetingForm({ ...meetingForm, time_start: e.target.value })}
                />
              </div>
            </div>
            <div>
              <Label htmlFor="meeting-duration">Duration (hours)</Label>
              <Select value={meetingForm.duration_hours.toString()} onValueChange={(value) => setMeetingForm({ ...meetingForm, duration_hours: parseFloat(value) })}>
                <SelectTrigger id="meeting-duration">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="0.5">30 minutes</SelectItem>
                  <SelectItem value="1">1 hour</SelectItem>
                  <SelectItem value="1.5">1.5 hours</SelectItem>
                  <SelectItem value="2">2 hours</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label htmlFor="meeting-description">Description</Label>
              <Textarea
                id="meeting-description"
                value={meetingForm.description}
                onChange={(e) => setMeetingForm({ ...meetingForm, description: e.target.value })}
                placeholder="Meeting agenda..."
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowMeetingDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleScheduleMeeting} disabled={isSubmitting}>
              {isSubmitting ? 'Scheduling...' : 'Schedule Meeting'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}