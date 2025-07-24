import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Phone, Calendar, CheckSquare, FileText } from 'lucide-react'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { 
  useCalls, 
  useMeetings, 
  useTasks, 
  useNotes,
  useUpcomingActivities, 
  useOverdueTasks,
  useActivityMetrics
} from '@/hooks/use-activities'
import { formatDate, formatTime } from '@/lib/utils'
import type { BaseActivity } from '@/types/phase2.types'
import type { Call, Meeting, Task, Note } from '@/types/api.generated'

export function ActivitiesList() {
  const [activeTab, setActiveTab] = useState('all')

  const { data: upcomingActivities } = useUpcomingActivities(20)
  const { data: overdueTasks } = useOverdueTasks()
  const { data: activityMetrics } = useActivityMetrics()
  const { data: recentCalls } = useCalls(1, 10)
  const { data: recentMeetings } = useMeetings(1, 10)
  const { data: recentTasks } = useTasks(1, 10)
  const { data: recentNotes } = useNotes(1, 10)

  const activityTypes = [
    { id: 'call', label: 'Call', icon: Phone, color: 'text-blue-600', path: '/activities/calls/new' },
    { id: 'meeting', label: 'Meeting', icon: Calendar, color: 'text-green-600', path: '/activities/meetings/new' },
    { id: 'task', label: 'Task', icon: CheckSquare, color: 'text-purple-600', path: '/activities/tasks/new' },
    { id: 'note', label: 'Note', icon: FileText, color: 'text-gray-600', path: '/activities/notes/new' },
  ]

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'Call':
        return Phone
      case 'Meeting':
        return Calendar
      case 'Task':
        return CheckSquare
      case 'Note':
        return FileText
      default:
        return FileText
    }
  }

  const todaysActivities = upcomingActivities?.filter(activity => {
    const activityDate = new Date(activity.dateModified)
    const today = new Date()
    return activityDate.toDateString() === today.toDateString()
  }) || []

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Activities</h1>
        <div className="flex gap-2">
          {activityTypes.map((type) => {
            const Icon = type.icon
            return (
              <Button key={type.id} variant="outline" size="sm" asChild>
                <Link to={type.path}>
                  <Icon className={`mr-1 h-4 w-4 ${type.color}`} />
                  {type.label}
                </Link>
              </Button>
            )
          })}
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Overdue Tasks</CardTitle>
          </CardHeader>
          <CardContent>
            {overdueTasks?.data.length === 0 ? (
              <p className="text-sm text-muted-foreground">No overdue tasks</p>
            ) : (
              <div className="space-y-2">
                {overdueTasks?.data.slice(0, 5).map((task) => (
                  <div key={task.id} className="flex items-center justify-between">
                    <Link
                      to={`/activities/tasks/${task.id}`}
                      className="text-sm hover:underline line-clamp-1 flex-1"
                    >
                      {task.name}
                    </Link>
                    {task.dueDate && (
                      <span className="text-xs text-red-600">
                        {formatDate(task.dueDate)}
                      </span>
                    )}
                  </div>
                ))}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Today's Activities</CardTitle>
          </CardHeader>
          <CardContent>
            {todaysActivities.length === 0 ? (
              <p className="text-sm text-muted-foreground">No activities scheduled for today</p>
            ) : (
              <div className="space-y-2">
                {todaysActivities.slice(0, 5).map((activity) => {
                  const Icon = getActivityIcon(activity.type || '')
                  
                  return (
                    <div key={activity.id} className="flex items-center gap-2">
                      <Icon className="h-4 w-4 text-muted-foreground" />
                      <Link
                        to={`/activities/${activity.type?.toLowerCase()}s/${activity.id}`}
                        className="text-sm hover:underline line-clamp-1 flex-1"
                      >
                        {activity.name}
                      </Link>
                    </div>
                  )
                })}
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Activity Summary</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm">Calls Today</span>
                <Badge variant="secondary">{activityMetrics?.callsToday || 0}</Badge>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm">Meetings Today</span>
                <Badge variant="secondary">{activityMetrics?.meetingsToday || 0}</Badge>
              </div>
              <div className="flex items-center justify-between">
                <span className="text-sm">Overdue Tasks</span>
                <Badge variant="destructive">{activityMetrics?.tasksOverdue || 0}</Badge>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="mt-6">
        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList>
            <TabsTrigger value="all">All Activities</TabsTrigger>
            <TabsTrigger value="calls">Calls</TabsTrigger>
            <TabsTrigger value="meetings">Meetings</TabsTrigger>
            <TabsTrigger value="tasks">Tasks</TabsTrigger>
            <TabsTrigger value="notes">Notes</TabsTrigger>
          </TabsList>

          <TabsContent value="all" className="mt-4">
            <ActivityTimeline activities={upcomingActivities || []} />
          </TabsContent>

          <TabsContent value="calls" className="mt-4">
            <CallsList calls={recentCalls?.data || []} />
          </TabsContent>

          <TabsContent value="meetings" className="mt-4">
            <MeetingsList meetings={recentMeetings?.data || []} />
          </TabsContent>

          <TabsContent value="tasks" className="mt-4">
            <TasksList tasks={recentTasks?.data || []} />
          </TabsContent>

          <TabsContent value="notes" className="mt-4">
            <NotesList notes={recentNotes?.data || []} />
          </TabsContent>
        </Tabs>
      </div>
    </div>
  )
}

// Activity Timeline Component
function ActivityTimeline({ activities }: { activities: BaseActivity[] }) {
  if (activities.length === 0) {
    return <p className="text-muted-foreground">No activities found</p>
  }

  return (
    <div className="space-y-4">
      {activities.map((activity) => {
        const Icon = activity.type === 'Call' ? Phone :
          activity.type === 'Meeting' ? Calendar :
          activity.type === 'Task' ? CheckSquare : FileText

        return (
          <div key={activity.id} className="flex gap-4 pb-4 border-b last:border-0">
            <div className={`rounded-full p-2 h-fit ${
              activity.type === 'Call' ? 'bg-blue-100' :
              activity.type === 'Meeting' ? 'bg-green-100' :
              activity.type === 'Task' ? 'bg-purple-100' : 'bg-gray-100'
            }`}>
              <Icon className={`h-4 w-4 ${
                activity.type === 'Call' ? 'text-blue-600' :
                activity.type === 'Meeting' ? 'text-green-600' :
                activity.type === 'Task' ? 'text-purple-600' : 'text-gray-600'
              }`} />
            </div>
            <div className="flex-1">
              <Link
                to={`/activities/${activity.type?.toLowerCase()}s/${activity.id}`}
                className="font-medium hover:underline"
              >
                {activity.name}
              </Link>
              <p className="text-sm text-muted-foreground mt-1">
                {activity.description}
              </p>
              <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                <span>{activity.type}</span>
                {activity.status && <Badge variant="outline" className="text-xs">{activity.status}</Badge>}
                {activity.assignedUserName && <span>Assigned to: {activity.assignedUserName}</span>}
                {activity.parentType && activity.parentId && activity.parentName && (
                  <Link 
                    to={`/${activity.parentType.toLowerCase()}s/${activity.parentId}`}
                    className="text-primary hover:underline"
                  >
                    {activity.parentName}
                  </Link>
                )}
              </div>
            </div>
          </div>
        )
      })}
    </div>
  )
}

// Calls List Component
function CallsList({ calls }: { calls: Call[] }) {
  if (calls.length === 0) {
    return <p className="text-muted-foreground">No calls found</p>
  }

  return (
    <div className="space-y-4">
      {calls.map((call) => (
        <div key={call.id} className="flex items-center justify-between p-4 border rounded-lg">
          <div>
            <Link
              to={`/activities/calls/${call.id}`}
              className="font-medium hover:underline"
            >
              {call.name}
            </Link>
            <div className="flex items-center gap-4 mt-1 text-sm text-muted-foreground">
              <span>{call.direction || 'Outbound'}</span>
              {call.startDate && <span>{formatDate(call.startDate)} {formatTime(call.startDate)}</span>}
              <span>{call.duration ? `${Math.floor(call.duration / 60)}h ${call.duration % 60}m` : '0h 0m'}</span>
              {call.parentType && call.parentId && call.parentName && (
                <Link 
                  to={`/${call.parentType.toLowerCase()}s/${call.parentId}`}
                  className="text-primary hover:underline"
                >
                  {call.parentName}
                </Link>
              )}
            </div>
          </div>
          <Badge variant="outline">{call.status}</Badge>
        </div>
      ))}
    </div>
  )
}

// Meetings List Component
function MeetingsList({ meetings }: { meetings: Meeting[] }) {
  if (meetings.length === 0) {
    return <p className="text-muted-foreground">No meetings found</p>
  }

  return (
    <div className="space-y-4">
      {meetings.map((meeting) => (
        <div key={meeting.id} className="flex items-center justify-between p-4 border rounded-lg">
          <div>
            <Link
              to={`/activities/meetings/${meeting.id}`}
              className="font-medium hover:underline"
            >
              {meeting.name}
            </Link>
            <div className="flex items-center gap-4 mt-1 text-sm text-muted-foreground">
              {meeting.location && <span>{meeting.location}</span>}
              {meeting.startDate && <span>{formatDate(meeting.startDate)} {formatTime(meeting.startDate)}</span>}
              <span>{meeting.duration ? `${Math.floor(meeting.duration / 60)}h ${meeting.duration % 60}m` : '0h 0m'}</span>
              {meeting.parentType && meeting.parentId && meeting.parentName && (
                <Link 
                  to={`/${meeting.parentType.toLowerCase()}s/${meeting.parentId}`}
                  className="text-primary hover:underline"
                >
                  {meeting.parentName}
                </Link>
              )}
            </div>
          </div>
          <Badge variant="outline">{meeting.status}</Badge>
        </div>
      ))}
    </div>
  )
}

// Tasks List Component
function TasksList({ tasks }: { tasks: Task[] }) {
  if (tasks.length === 0) {
    return <p className="text-muted-foreground">No tasks found</p>
  }

  return (
    <div className="space-y-4">
      {tasks.map((task) => (
        <div key={task.id} className="flex items-center justify-between p-4 border rounded-lg">
          <div>
            <Link
              to={`/activities/tasks/${task.id}`}
              className="font-medium hover:underline"
            >
              {task.name}
            </Link>
            <div className="flex items-center gap-4 mt-1 text-sm text-muted-foreground">
              {task.priority && <Badge variant={task.priority === 'High' ? 'destructive' : 'secondary'}>{task.priority}</Badge>}
              {task.dueDate && <span>Due: {formatDate(task.dueDate)}</span>}
              {task.parentType && task.parentId && task.parentName && (
                <Link 
                  to={`/${task.parentType.toLowerCase()}s/${task.parentId}`}
                  className="text-primary hover:underline"
                >
                  {task.parentName}
                </Link>
              )}
            </div>
          </div>
          <Badge variant="outline">{task.status}</Badge>
        </div>
      ))}
    </div>
  )
}

// Notes List Component
function NotesList({ notes }: { notes: Note[] }) {
  if (notes.length === 0) {
    return <p className="text-muted-foreground">No notes found</p>
  }

  return (
    <div className="space-y-4">
      {notes.map((note) => (
        <div key={note.id} className="flex items-center justify-between p-4 border rounded-lg">
          <div>
            <Link
              to={`/activities/notes/${note.id}`}
              className="font-medium hover:underline"
            >
              {note.name}
            </Link>
            <p className="text-sm text-muted-foreground mt-1 line-clamp-2">
              {note.description}
            </p>
            <div className="flex items-center gap-4 mt-1 text-xs text-muted-foreground">
              <span>Created: {formatDate('dateEntered' in note && typeof note.dateEntered === 'string' ? note.dateEntered : '')}</span>
              {'fileName' in note && note.fileName ? <span>📎 {String(note.fileName)}</span> : null}
              {note.parentType && note.parentId && note.parentName && (
                <Link 
                  to={`/${note.parentType.toLowerCase()}s/${note.parentId}`}
                  className="text-primary hover:underline"
                >
                  {note.parentName}
                </Link>
              )}
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}