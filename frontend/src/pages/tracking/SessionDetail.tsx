import { useParams, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { 
  ArrowLeft,
  Clock,
  Eye,
  Globe,
  User,
  Calendar,
  Monitor,
  Smartphone,
  Tablet
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Separator } from '@/components/ui/separator'
import { apiClient } from '@/lib/api-client'
import { formatDistanceToNow, format } from 'date-fns'
import type { WebsiteSession } from '@/types'

interface SessionDetailData extends WebsiteSession {
  device_info?: {
    type: string
    browser: string
    os: string
    screenResolution?: string
  }
  leadInfo?: {
    id: string
    name: string
    email: string
    company?: string
    score?: number
  }
  events?: Array<{
    type: string
    timestamp: string
    properties?: Record<string, unknown>
  }>
}

export function SessionDetail() {
  const { id } = useParams()
  const navigate = useNavigate()

  const { data: session, isLoading } = useQuery({
    queryKey: ['session-detail', id],
    queryFn: async () => {
      const response = await apiClient.customGet(`/analytics/sessions/${id}`)
      return response.data as SessionDetailData
    }
  })

  const getDeviceIcon = (type?: string) => {
    switch (type?.toLowerCase()) {
      case 'mobile':
        return <Smartphone className="h-4 w-4" />
      case 'tablet':
        return <Tablet className="h-4 w-4" />
      default:
        return <Monitor className="h-4 w-4" />
    }
  }

  const getEngagementScore = () => {
    if (!session) return 0
    const pageViews = session.pages_viewed?.length || 0
    const duration = session.total_time || 0
    const hasConversion = session.events?.some(e => e.type === 'conversion')
    
    let score = 0
    score += Math.min(pageViews * 10, 30) // Max 30 points for page views
    score += Math.min(duration / 60 * 5, 30) // Max 30 points for duration
    score += hasConversion ? 40 : 0 // 40 points for conversion
    
    return Math.round(score)
  }

  if (isLoading) {
    return (
      <div className="p-6">
        <div className="flex items-center justify-center h-64">
          <p className="text-muted-foreground">Loading session details...</p>
        </div>
      </div>
    )
  }

  if (!session) {
    return (
      <div className="p-6">
        <div className="flex flex-col items-center justify-center h-64 gap-4">
          <p className="text-muted-foreground">Session not found</p>
          <Button variant="outline" onClick={() => navigate('/tracking')}>
            Back to Activity Tracking
          </Button>
        </div>
      </div>
    )
  }

  const engagementScore = getEngagementScore()

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate('/tracking')}
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <h1 className="text-2xl font-semibold">Session Details</h1>
        </div>
        <div className="flex items-center gap-2">
          <Badge variant={session.is_active ? 'default' : 'secondary'}>
            {session.is_active ? 'Active' : 'Ended'}
          </Badge>
          {session.lead_id && (
            <Badge variant="outline">Known Lead</Badge>
          )}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Main Info */}
        <div className="lg:col-span-2 space-y-6">
          {/* Session Overview */}
          <Card>
            <CardHeader>
              <CardTitle>Session Overview</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground flex items-center gap-2">
                      <Calendar className="h-4 w-4" />
                      Started
                    </span>
                    <span className="text-sm font-medium">
                      {format(new Date(session.date_created), 'PPp')}
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground flex items-center gap-2">
                      <Clock className="h-4 w-4" />
                      Duration
                    </span>
                    <span className="text-sm font-medium">
                      {Math.floor((session.total_time || 0) / 60)}m {(session.total_time || 0) % 60}s
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground flex items-center gap-2">
                      <Eye className="h-4 w-4" />
                      Pages Viewed
                    </span>
                    <span className="text-sm font-medium">
                      {session.pages_viewed?.length || 0}
                    </span>
                  </div>
                </div>
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground flex items-center gap-2">
                      {getDeviceIcon(session.device_info?.type)}
                      Device
                    </span>
                    <span className="text-sm font-medium">
                      {session.device_info?.type || 'Unknown'}
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Browser</span>
                    <span className="text-sm font-medium">
                      {session.device_info?.browser || 'Unknown'}
                    </span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground flex items-center gap-2">
                      <Globe className="h-4 w-4" />
                      Location
                    </span>
                    <span className="text-sm font-medium">
                      {session.location ? `${session.location.city}, ${session.location.country}` : 'Unknown'}
                    </span>
                  </div>
                </div>
              </div>

              <Separator className="my-4" />

              <div>
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-medium">Engagement Score</span>
                  <span className="text-sm font-medium">{engagementScore}%</span>
                </div>
                <Progress value={engagementScore} className="h-2" />
                <p className="text-xs text-muted-foreground mt-1">
                  Based on pages viewed, time spent, and actions taken
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Page Journey */}
          <Card>
            <CardHeader>
              <CardTitle>Page Journey</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {session.pages_viewed?.map((page, index) => (
                  <div key={index} className="relative">
                    {index < (session.pages_viewed?.length || 0) - 1 && (
                      <div className="absolute left-5 top-10 bottom-0 w-0.5 bg-border" />
                    )}
                    <div className="flex gap-4">
                      <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium flex-shrink-0">
                        {index + 1}
                      </div>
                      <div className="flex-1 pb-4">
                        <div className="bg-muted/50 rounded-lg p-4">
                          <h4 className="font-medium">{page.title}</h4>
                          <p className="text-sm text-muted-foreground mt-1">{page.url}</p>
                          <div className="grid grid-cols-3 gap-4 mt-3 text-sm">
                            <div>
                              <span className="text-muted-foreground">Time on page:</span>
                              <p className="font-medium">{page.duration}s</p>
                            </div>
                            <div>
                              <span className="text-muted-foreground">Scroll depth:</span>
                              <p className="font-medium">{page.scrollDepth}%</p>
                            </div>
                            <div>
                              <span className="text-muted-foreground">Interactions:</span>
                              <p className="font-medium">{page.clicks} clicks</p>
                            </div>
                          </div>
                          <p className="text-xs text-muted-foreground mt-2">
                            {formatDistanceToNow(new Date(page.timestamp), { addSuffix: true })}
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          {/* Events Timeline */}
          {session.events && session.events.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Events Timeline</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {session.events.map((event, index) => (
                    <div key={index} className="flex items-start gap-3 text-sm">
                      <div className="w-2 h-2 rounded-full bg-primary mt-1.5 flex-shrink-0" />
                      <div className="flex-1">
                        <div className="flex items-center justify-between">
                          <span className="font-medium">{event.type}</span>
                          <span className="text-xs text-muted-foreground">
                            {format(new Date(event.timestamp), 'p')}
                          </span>
                        </div>
                        {event.properties && (
                          <div className="text-muted-foreground mt-1">
                            {Object.entries(event.properties).map(([key, value]) => (
                              <span key={key} className="mr-3">
                                {key}: {String(value)}
                              </span>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Lead Info */}
          {session.leadInfo && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <User className="h-4 w-4" />
                  Lead Information
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <div>
                    <p className="font-medium">{session.leadInfo.name}</p>
                    <p className="text-sm text-muted-foreground">{session.leadInfo.email}</p>
                  </div>
                  {session.leadInfo.company && (
                    <div>
                      <p className="text-sm text-muted-foreground">Company</p>
                      <p className="font-medium">{session.leadInfo.company}</p>
                    </div>
                  )}
                  {session.leadInfo.score && (
                    <div>
                      <p className="text-sm text-muted-foreground">Lead Score</p>
                      <div className="flex items-center gap-2 mt-1">
                        <Progress value={session.leadInfo.score} className="h-2 flex-1" />
                        <span className="text-sm font-medium">{session.leadInfo.score}</span>
                      </div>
                    </div>
                  )}
                  <Button className="w-full" size="sm" onClick={() => navigate(`/leads/${session.leadInfo?.id}`)}>
                    View Lead Profile
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Technical Details */}
          <Card>
            <CardHeader>
              <CardTitle>Technical Details</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3 text-sm">
                <div>
                  <p className="text-muted-foreground">Session ID</p>
                  <p className="font-mono text-xs break-all">{session.id}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">Visitor ID</p>
                  <p className="font-mono text-xs break-all">{session.visitor_id}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">IP Address</p>
                  <p className="font-medium">{session.ip_address}</p>
                </div>
                <div>
                  <p className="text-muted-foreground">User Agent</p>
                  <p className="text-xs break-all">{session.user_agent}</p>
                </div>
                {session.referrer && (
                  <div>
                    <p className="text-muted-foreground">Referrer</p>
                    <p className="text-xs break-all">{session.referrer}</p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Actions */}
          <Card>
            <CardHeader>
              <CardTitle>Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <Button 
                className="w-full" 
                size="sm" 
                variant="outline"
                onClick={() => {
                  // Export session data
                  const dataStr = JSON.stringify(session, null, 2)
                  const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr)
                  const exportFileDefaultName = `session_${session.id}.json`
                  
                  const linkElement = document.createElement('a')
                  linkElement.setAttribute('href', dataUri)
                  linkElement.setAttribute('download', exportFileDefaultName)
                  linkElement.click()
                }}
              >
                Export Session Data
              </Button>
              {!session.lead_id && (
                <Button className="w-full" size="sm" variant="outline">
                  Create Lead from Session
                </Button>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}