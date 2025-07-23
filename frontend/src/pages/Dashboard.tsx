import { useQuery } from '@tanstack/react-query'
import { 
  Users, 
  TrendingUp, 
  DollarSign, 
  Activity,
  ArrowUp,
  ArrowDown
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { apiClient } from '@/lib/api-client'
import { Skeleton } from '@/components/ui/skeleton'

interface MetricCardProps {
  title: string
  value: string | number
  icon: React.ComponentType<{ className?: string }>
  trend?: {
    value: number
    isPositive: boolean
  }
}

function MetricCard({ title, value, icon: Icon, trend }: MetricCardProps) {
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
          <div className="rounded-full bg-muted p-3">
            <Icon className="h-6 w-6 text-muted-foreground" />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

export function DashboardPage() {
  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: () => apiClient.getDashboardStats(),
  })

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold">Dashboard</h1>
          <p className="text-muted-foreground">Welcome back! Here's what's happening today.</p>
        </div>
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          {[...Array(4)].map((_, i) => (
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

  const dashboardData = stats?.data || {
    totalContacts: 0,
    activeTrials: 0,
    monthlyRevenue: 0,
    conversionRate: 0,
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Dashboard</h1>
        <p className="text-muted-foreground">Welcome back! Here's what's happening today.</p>
      </div>

      {/* Metrics Grid */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Total Contacts"
          value={dashboardData.totalContacts}
          icon={Users}
          trend={{ value: 12, isPositive: true }}
        />
        <MetricCard
          title="Active Trials"
          value={dashboardData.activeTrials}
          icon={Activity}
          trend={{ value: 5, isPositive: true }}
        />
        <MetricCard
          title="Monthly Revenue"
          value={`$${dashboardData.monthlyRevenue.toLocaleString()}`}
          icon={DollarSign}
          trend={{ value: 8, isPositive: true }}
        />
        <MetricCard
          title="Conversion Rate"
          value={`${dashboardData.conversionRate}%`}
          icon={TrendingUp}
          trend={{ value: 2, isPositive: false }}
        />
      </div>

      {/* More dashboard content can be added here */}
    </div>
  )
}