import { 
  Users, 
  Target,
  Building2, 
  TrendingUp,
  Phone,
  Calendar,
  CheckSquare,
  AlertCircle,
  ArrowUp,
  ArrowDown
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { EntityActivities } from '@/components/dashboard/EntityActivities'
import {
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts'
import { useDashboardData } from '@/hooks/use-dashboard'
import { formatCurrency, formatDate } from '@/lib/utils'
import { Link } from 'react-router-dom'
import type { DashboardMetrics, ActivityMetrics, CaseMetrics, PipelineData } from '@/types/api.types'

const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8', '#82CA9D', '#FFC658', '#8DD1E1']

interface MetricCardProps {
  title: string
  value: string | number
  icon: React.ComponentType<{ className?: string }>
  trend?: {
    value: number
    isPositive: boolean
  }
  color?: string
}

function MetricCard({ title, value, icon: Icon, trend, color = 'text-muted-foreground' }: MetricCardProps) {
  return (
    <Card>
      <CardContent className="p-6">
        <div className="flex items-center justify-between">
          <div className="space-y-1">
            <p className="text-sm text-muted-foreground">{title}</p>
            <p className="text-2xl font-bold">{value}</p>
            {trend && (
              <div className="flex items-center text-sm">
                {trend.isPositive ? (
                  <ArrowUp className="mr-1 h-3 w-3 text-green-500" />
                ) : (
                  <ArrowDown className="mr-1 h-3 w-3 text-red-500" />
                )}
                <span
                  className={
                    trend.isPositive ? 'text-green-500' : 'text-red-500'
                  }
                >
                  {trend.value}%
                </span>
                <span className="ml-1 text-muted-foreground">vs last month</span>
              </div>
            )}
          </div>
          <div className={`rounded-full bg-muted p-3`}>
            <Icon className={`h-6 w-6 ${color}`} />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

export function DashboardPage() {
  const { stats, pipeline, activityMetrics, caseMetrics, recentActivities, isLoading } = useDashboardData()

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold">Dashboard</h1>
          <p className="text-muted-foreground">Welcome back! Here's what's happening today.</p>
        </div>
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          {[...Array(8)].map((_, i) => (
            <Card key={i}>
              <CardContent className="p-6">
                <Skeleton className="h-20 w-full" />
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    )
  }

  const metricsData = stats?.data || {}
  const dashboardData = {
    total_leads: (metricsData as DashboardMetrics).total_leads || 0,
    total_accounts: (metricsData as DashboardMetrics).total_accounts || 0,
    new_leads_today: (metricsData as DashboardMetrics).new_leads_today || 0,
    pipeline_value: (metricsData as DashboardMetrics).pipeline_value || 0,
  }

  // Calculate pipeline metrics
  const pipelineArray = pipeline?.data || []
  const totalPipelineValue = Array.isArray(pipelineArray) ? pipelineArray.reduce((sum: number, stage: PipelineData) => {
    if (stage.stage !== 'Lost') {
      return sum + stage.value
    }
    return sum
  }, 0) : 0

  const wonValue = Array.isArray(pipelineArray) ? (pipelineArray as PipelineData[]).find((s) => s.stage === 'Won')?.value || 0 : 0

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Dashboard</h1>
        <p className="text-muted-foreground">Welcome back! Here's what's happening today.</p>
      </div>

      {/* Key Metrics */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Total Leads"
          value={dashboardData.total_leads}
          icon={Target}
          color="text-blue-600"
          trend={{ value: 12, isPositive: true }}
        />
        <MetricCard
          title="Total Accounts"
          value={dashboardData.total_accounts}
          icon={Building2}
          color="text-green-600"
          trend={{ value: 5, isPositive: true }}
        />
        <MetricCard
          title="New Leads Today"
          value={dashboardData.new_leads_today}
          icon={Users}
          color="text-purple-600"
          trend={{ value: 8, isPositive: true }}
        />
        <MetricCard
          title="Pipeline Value"
          value={formatCurrency(totalPipelineValue)}
          icon={TrendingUp}
          color="text-orange-600"
          trend={{ value: 15, isPositive: true }}
        />
      </div>

      {/* Activity Metrics */}
      <div className="grid gap-4 md:grid-cols-4">
        <MetricCard
          title="Today's Calls"
          value={(activityMetrics?.data as ActivityMetrics)?.calls_today || 0}
          icon={Phone}
          color="text-blue-600"
        />
        <MetricCard
          title="Today's Meetings"
          value={(activityMetrics?.data as ActivityMetrics)?.meetings_today || 0}
          icon={Calendar}
          color="text-green-600"
        />
        <MetricCard
          title="Overdue Tasks"
          value={(activityMetrics?.data as ActivityMetrics)?.tasks_overdue || 0}
          icon={CheckSquare}
          color="text-red-600"
        />
        <MetricCard
          title="Open Cases"
          value={(caseMetrics?.data as CaseMetrics)?.open_cases || 0}
          icon={AlertCircle}
          color="text-orange-600"
        />
      </div>

      {/* Charts */}
      <div className="grid gap-6 md:grid-cols-2">
        {/* Pipeline Chart */}
        <Card>
          <CardHeader>
            <CardTitle>Sales Pipeline</CardTitle>
          </CardHeader>
          <CardContent>
            {pipelineArray && Array.isArray(pipelineArray) && pipelineArray.length > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={pipelineArray.filter((s: any) => s.stage !== 'Lost' && s.stage !== 'Won')}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="stage" angle={-45} textAnchor="end" height={80} />
                  <YAxis tickFormatter={(value) => `$${(value / 1000).toFixed(0)}k`} />
                  <Tooltip formatter={(value) => formatCurrency(Number(value))} />
                  <Bar dataKey="value" fill="#8884d8" />
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-[300px] flex items-center justify-center">
                <p className="text-muted-foreground">No pipeline data available</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Cases by Priority */}
        <Card>
          <CardHeader>
            <CardTitle>Cases by Priority</CardTitle>
          </CardHeader>
          <CardContent>
            {caseMetrics?.data && (caseMetrics.data as CaseMetrics).open_cases > 0 ? (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={[
                      { name: 'Open', value: (caseMetrics.data as CaseMetrics).open_cases },
                      { name: 'High Priority', value: (caseMetrics.data as CaseMetrics).high_priority },
                      { name: 'Closed This Month', value: (caseMetrics.data as CaseMetrics).closed_this_month }
                    ]}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, value }) => `${name}: ${value}`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {[0, 1, 2].map((index: number) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            ) : (
              <div className="h-[300px] flex items-center justify-center">
                <p className="text-muted-foreground">No case data available</p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Recent Activity */}
      <Card>
        <CardHeader>
          <CardTitle>Recent Activity</CardTitle>
        </CardHeader>
        <CardContent>
          <Tabs defaultValue="all">
            <TabsList>
              <TabsTrigger value="all">All</TabsTrigger>
              <TabsTrigger value="leads">Leads</TabsTrigger>
              <TabsTrigger value="opportunities">Opportunities</TabsTrigger>
              <TabsTrigger value="cases">Cases</TabsTrigger>
            </TabsList>
            <TabsContent value="all" className="mt-4">
              {recentActivities?.data && recentActivities.data.length > 0 ? (
                <div className="space-y-4">
                  {recentActivities.data.map((activity) => {
                    const Icon = activity.icon === 'Target' ? Target :
                      activity.icon === 'Building2' ? Building2 :
                      activity.icon === 'TrendingUp' ? TrendingUp :
                      AlertCircle

                    return (
                      <div key={`${activity.type}-${activity.id}`} className="flex items-center justify-between py-2 border-b last:border-0">
                        <div className="flex items-center gap-3">
                          <Icon className="h-4 w-4 text-muted-foreground" />
                          <div>
                            <Link 
                              to={`/${activity.type.toLowerCase()}s/${activity.id}`}
                              className="font-medium hover:underline"
                            >
                              {String(activity.name)}
                            </Link>
                            <p className="text-sm text-muted-foreground">
                              {String(activity.description)}
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <Badge variant="outline">{activity.type}</Badge>
                          <span className="text-xs text-muted-foreground">
                            {formatDate(String(activity.date))}
                          </span>
                        </div>
                      </div>
                    )
                  })}
                </div>
              ) : (
                <p className="text-muted-foreground">No recent activity</p>
              )}
            </TabsContent>
            <TabsContent value="leads" className="mt-4">
              <EntityActivities entityType="Lead" />
            </TabsContent>
            <TabsContent value="opportunities" className="mt-4">
              <EntityActivities entityType="Opportunity" />
            </TabsContent>
            <TabsContent value="cases" className="mt-4">
              <EntityActivities entityType="Case" />
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>

      {/* Performance Summary */}
      <div className="grid gap-6 md:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Win Rate</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">
              {pipelineArray && Array.isArray(pipelineArray) && pipelineArray.length > 0 ? 
                `${Math.round((wonValue / (totalPipelineValue + wonValue)) * 100)}%` : 
                'N/A'
              }
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              Based on closed opportunities
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Avg Resolution Time</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">
              {caseMetrics?.data && (caseMetrics.data as CaseMetrics).avg_resolution_days ? 
                `${(caseMetrics.data as CaseMetrics).avg_resolution_days.toFixed(1)} days` : 
                'N/A'
              }
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              For closed cases
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Critical Cases</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-red-600">
              {caseMetrics?.data && (caseMetrics.data as CaseMetrics).high_priority || 0}
            </div>
            <p className="text-sm text-muted-foreground mt-1">
              Require immediate attention
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}