import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { 
  Activity, 
  Users, 
  Eye, 
  Clock, 
  TrendingUp,
  MousePointer,
  Globe
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ScrollArea } from '@/components/ui/scroll-area'
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { apiClient } from '@/lib/api-client'
import { formatDistanceToNow } from 'date-fns'
import {
  BarChart,
  Bar,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell
} from 'recharts'
import type { WebsiteSession } from '@/types/api.types'

interface VisitorMetrics {
  total_visitors: number
  active_visitors: number
  avg_session_duration: number
  avg_pages_per_session: number
  top_pages: Array<{ url: string; views: number; avg_time: number }>
  device_types: Array<{ type: string; count: number; percentage: number }>
  referrers: Array<{ source: string; count: number }>
  hourly_traffic: Array<{ hour: string; visitors: number }>
}

export function ActivityTrackingDashboard() {
  const [timeRange, setTimeRange] = useState('24h')
  const [selectedSession, setSelectedSession] = useState<WebsiteSession | null>(null)

  const { data: metrics = {
    total_visitors: 0,
    active_visitors: 0,
    avg_session_duration: 0,
    avg_pages_per_session: 0,
    top_pages: [],
    device_types: [],
    referrers: [],
    hourly_traffic: []
  } } = useQuery({
    queryKey: ['visitor-metrics', timeRange],
    queryFn: async () => {
      try {
        const response = await apiClient.publicGet('/public/analytics/visitor-metrics', {
          params: { range: timeRange }
        })
        return response.data as VisitorMetrics
      } catch (error) {
        console.error('Failed to fetch visitor metrics:', error)
        // Return default data structure to prevent undefined errors
        return {
          total_visitors: 0,
          active_visitors: 0,
          avg_session_duration: 0,
          avg_pages_per_session: 0,
          top_pages: [],
          device_types: [],
          referrers: [],
          hourly_traffic: []
        } as VisitorMetrics
      }
    }
  })

  const { data: liveVisitors = [], isLoading: isLoadingLive } = useQuery({
    queryKey: ['live-visitors'],
    queryFn: async () => {
      try {
        const response = await apiClient.publicGet('/public/analytics/visitors', {
          params: { active_only: true }
        })
        return response.data.data || [] // Ensure we always return an array
      } catch (error) {
        console.error('Failed to fetch live visitors:', error)
        // Return empty array to prevent undefined errors
        return []
      }
    },
    refetchInterval: 5000 // Refresh every 5 seconds
  })

  const getEngagementLevel = (session: WebsiteSession) => {
    const pageViews = session.pages_viewed?.length || 0
    const totalTime = session.total_time || 0
    
    if (pageViews > 5 || totalTime > 300) return { level: 'high', color: 'default' }
    if (pageViews > 2 || totalTime > 120) return { level: 'medium', color: 'secondary' }
    return { level: 'low', color: 'outline' }
  }

  const deviceColors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold flex items-center gap-2">
          <Activity className="h-6 w-6" />
          Activity Tracking
        </h1>
        <Select value={timeRange} onValueChange={setTimeRange}>
          <SelectTrigger className="w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="1h">Last Hour</SelectItem>
            <SelectItem value="24h">Last 24 Hours</SelectItem>
            <SelectItem value="7d">Last 7 Days</SelectItem>
            <SelectItem value="30d">Last 30 Days</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Metrics Overview */}
      <div className="grid gap-4 md:grid-cols-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Users className="h-4 w-4" />
              Total Visitors
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{metrics?.total_visitors || 0}</div>
            <p className="text-xs text-muted-foreground mt-1">
              In selected period
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Eye className="h-4 w-4" />
              Active Now
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-600">
              {metrics?.active_visitors || 0}
            </div>
            <p className="text-xs text-muted-foreground mt-1">
              Currently browsing
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Avg Duration
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {Math.round((metrics?.avg_session_duration || 0) / 60)}m
            </div>
            <p className="text-xs text-muted-foreground mt-1">
              Per session
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <TrendingUp className="h-4 w-4" />
              Pages/Session
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {metrics?.avg_pages_per_session?.toFixed(1) || '0.0'}
            </div>
            <p className="text-xs text-muted-foreground mt-1">
              Average engagement
            </p>
          </CardContent>
        </Card>
      </div>

      <Tabs defaultValue="live" className="space-y-4">
        <TabsList>
          <TabsTrigger value="live">Live Visitors</TabsTrigger>
          <TabsTrigger value="analytics">Analytics</TabsTrigger>
          <TabsTrigger value="pages">Top Pages</TabsTrigger>
          <TabsTrigger value="sources">Traffic Sources</TabsTrigger>
        </TabsList>

        <TabsContent value="live" className="space-y-4">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Live Visitor Activity</CardTitle>
                <Badge variant="secondary">
                  {liveVisitors?.length || 0} Active
                </Badge>
              </div>
            </CardHeader>
            <CardContent>
              <ScrollArea className="h-[400px]">
                <div className="space-y-4">
                  {isLoadingLive ? (
                    <p className="text-center text-muted-foreground py-8">
                      Loading visitors...
                    </p>
                  ) : liveVisitors?.length === 0 ? (
                    <p className="text-center text-muted-foreground py-8">
                      No active visitors at the moment
                    </p>
                  ) : (
                    liveVisitors?.map((session: any) => {
                      const engagement = getEngagementLevel(session)
                      const currentPage = session.pages_viewed?.[session.pages_viewed.length - 1]
                      
                      return (
                        <div
                          key={session.id}
                          className="flex items-start justify-between p-4 rounded-lg border hover:bg-muted/50 cursor-pointer transition-colors"
                          onClick={() => setSelectedSession(session)}
                        >
                          <div className="space-y-2">
                            <div className="flex items-center gap-2">
                              {session.lead_id ? (
                                <Badge variant="outline">Known Lead</Badge>
                              ) : (
                                <span className="font-medium">Anonymous Visitor</span>
                              )}
                              <Badge variant={engagement.color as 'default' | 'secondary' | 'outline'}>
                                {engagement.level} engagement
                              </Badge>
                            </div>
                            
                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                              <span className="flex items-center gap-1">
                                <MousePointer className="h-3 w-3" />
                                {currentPage?.title || 'Unknown Page'}
                              </span>
                              <span className="flex items-center gap-1">
                                <Clock className="h-3 w-3" />
                                {Math.round((session.total_time || 0) / 60)}m
                              </span>
                              <span className="flex items-center gap-1">
                                <Eye className="h-3 w-3" />
                                {session.pages_viewed?.length || 0} pages
                              </span>
                            </div>
                            
                            {session.location && (
                              <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Globe className="h-3 w-3" />
                                {session.location.city}, {session.location.country}
                              </div>
                            )}
                          </div>
                          
                          <div className="text-right">
                            <p className="text-xs text-muted-foreground">
                              {formatDistanceToNow(new Date(session.date_created), {
                                addSuffix: true
                              })}
                            </p>
                            {session.referrer && (
                              <p className="text-xs text-muted-foreground mt-1">
                                from {new URL(session.referrer).hostname}
                              </p>
                            )}
                          </div>
                        </div>
                      )
                    })
                  )}
                </div>
              </ScrollArea>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="analytics" className="space-y-4">
          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Hourly Traffic</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={metrics?.hourly_traffic || []}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="hour" />
                    <YAxis />
                    <Tooltip />
                    <Line 
                      type="monotone" 
                      dataKey="visitors" 
                      stroke="#3b82f6" 
                      strokeWidth={2}
                    />
                  </LineChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Device Types</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={metrics?.device_types || []}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ percentage }) => `${percentage}%`}
                      outerRadius={80}
                      fill="#8884d8"
                      dataKey="count"
                    >
                      {metrics?.device_types?.map((_, index) => (
                        <Cell key={`cell-${index}`} fill={deviceColors[index % deviceColors.length]} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
                <div className="flex flex-wrap gap-2 mt-4">
                  {metrics?.device_types?.map((device, index) => (
                    <div key={device.type} className="flex items-center gap-2">
                      <div 
                        className="w-3 h-3 rounded" 
                        style={{ backgroundColor: deviceColors[index % deviceColors.length] }}
                      />
                      <span className="text-sm">{device.type}</span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="pages" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Top Pages</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {metrics?.top_pages?.map((page, index) => (
                  <div key={page.url} className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <span className="text-sm font-medium text-muted-foreground w-6">
                        {index + 1}.
                      </span>
                      <div>
                        <p className="font-medium">{page.url}</p>
                        <p className="text-sm text-muted-foreground">
                          Avg time: {Math.round(page.avg_time / 60)}m {page.avg_time % 60}s
                        </p>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="font-medium">{page.views}</p>
                      <p className="text-sm text-muted-foreground">views</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="sources" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Traffic Sources</CardTitle>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={metrics?.referrers || []}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="source" />
                  <YAxis />
                  <Tooltip />
                  <Bar dataKey="count" fill="#3b82f6" />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Session Detail Modal */}
      {selectedSession && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <Card className="w-full max-w-2xl max-h-[80vh] overflow-hidden">
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>Session Details</CardTitle>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => setSelectedSession(null)}
                >
                  ✕
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <ScrollArea className="h-[500px]">
                <div className="space-y-6">
                  <div>
                    <h3 className="font-medium mb-2">Visitor Information</h3>
                    <div className="space-y-2 text-sm">
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Session ID:</span>
                        <span className="font-mono">{selectedSession.id}</span>
                      </div>
                      {selectedSession.lead_id && (
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Lead ID:</span>
                          <span>{selectedSession.lead_id}</span>
                        </div>
                      )}
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Started:</span>
                        <span>{new Date(selectedSession.date_created).toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-muted-foreground">Duration:</span>
                        <span>{Math.round((selectedSession.total_time || 0) / 60)}m</span>
                      </div>
                      {selectedSession.referrer && (
                        <div className="flex justify-between">
                          <span className="text-muted-foreground">Referrer:</span>
                          <span>{selectedSession.referrer}</span>
                        </div>
                      )}
                    </div>
                  </div>

                  <div>
                    <h3 className="font-medium mb-2">Page Journey</h3>
                    <div className="space-y-3">
                      {selectedSession.pages_viewed?.map((page, index) => (
                        <div key={index} className="flex items-start gap-3">
                          <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-medium">
                            {index + 1}
                          </div>
                          <div className="flex-1">
                            <p className="font-medium">{page.title}</p>
                            <p className="text-sm text-muted-foreground">{page.url}</p>
                            <div className="flex items-center gap-4 text-xs text-muted-foreground mt-1">
                              <span>Duration: {page.duration}s</span>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </ScrollArea>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}