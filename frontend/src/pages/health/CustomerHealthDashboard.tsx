import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  Heart, 
  TrendingDown, 
  TrendingUp, 
  AlertTriangle,
  Activity,
  RefreshCw
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { 
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { apiClient } from '@/lib/api-client'
import { useToast } from '@/components/ui/use-toast'
import { formatCurrency, formatDate } from '@/lib/utils'
import {
  LineChart,
  Line,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell
} from 'recharts'

interface HealthDashboardData {
  summary: {
    totalAccounts: number
    healthyAccounts: number
    atRiskAccounts: number
    criticalAccounts: number
    avgHealthScore: number
  }
  distribution: Array<{
    category: string
    count: number
    percentage: number
  }>
  trends: Array<{
    date: string
    avgScore: number
    healthy: number
    atRisk: number
    critical: number
  }>
  atRiskAccounts: Array<{
    id: string
    name: string
    healthScore: number
    mrr: number
    lastActivity: string
    riskFactors: string[]
    trend: 'improving' | 'declining' | 'stable'
  }>
  topFactors: Array<{
    factor: string
    impact: number
    affectedAccounts: number
  }>
}

export function CustomerHealthDashboard() {
  const [timeRange, setTimeRange] = useState('30d')
  const [filter, setFilter] = useState('all')
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const { data: dashboard } = useQuery({
    queryKey: ['health-dashboard', timeRange],
    queryFn: async () => {
      const response = await apiClient.customGet('/analytics/health-dashboard', {
        params: { range: timeRange }
      })
      return response.data as HealthDashboardData
    }
  })

  const recalculateMutation = useMutation({
    mutationFn: async () => {
      return await apiClient.customPost('/admin/recalculate-health-scores')
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['health-dashboard'] })
      toast({
        title: 'Health scores recalculated',
        description: 'All account health scores have been updated.',
      })
    },
    onError: () => {
      toast({
        title: 'Recalculation failed',
        description: 'Unable to recalculate health scores. Please try again.',
        variant: 'destructive',
      })
    }
  })

  const calculateScoreMutation = useMutation({
    mutationFn: async (accountId: string) => {
      return await apiClient.customPost(`/accounts/${accountId}/health-score`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['health-dashboard'] })
      toast({
        title: 'Score updated',
        description: 'Account health score has been recalculated.',
      })
    }
  })

  const getHealthColor = (score: number) => {
    if (score >= 80) return 'text-green-600'
    if (score >= 60) return 'text-yellow-600'
    if (score >= 40) return 'text-orange-600'
    return 'text-red-600'
  }

  const getHealthBadge = (score: number) => {
    if (score >= 80) return { label: 'Healthy', variant: 'default' as const }
    if (score >= 60) return { label: 'At Risk', variant: 'secondary' as const }
    if (score >= 40) return { label: 'Critical', variant: 'destructive' as const }
    return { label: 'Urgent', variant: 'destructive' as const }
  }

  const getTrendIcon = (trend: string) => {
    switch (trend) {
      case 'improving':
        return <TrendingUp className="h-4 w-4 text-green-600" />
      case 'declining':
        return <TrendingDown className="h-4 w-4 text-red-600" />
      default:
        return <Activity className="h-4 w-4 text-gray-400" />
    }
  }

  const pieColors = ['#10b981', '#f59e0b', '#ef4444', '#6b7280']

  const filteredAccounts = dashboard?.atRiskAccounts.filter(account => {
    if (filter === 'all') return true
    if (filter === 'critical') return account.healthScore < 40
    if (filter === 'at-risk') return account.healthScore >= 40 && account.healthScore < 60
    if (filter === 'declining') return account.trend === 'declining'
    return true
  }) || []

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold flex items-center gap-2">
          <Heart className="h-6 w-6" />
          Customer Health
        </h1>
        <div className="flex items-center gap-2">
          <Select value={timeRange} onValueChange={setTimeRange}>
            <SelectTrigger className="w-32">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="7d">Last 7 Days</SelectItem>
              <SelectItem value="30d">Last 30 Days</SelectItem>
              <SelectItem value="90d">Last 90 Days</SelectItem>
            </SelectContent>
          </Select>
          <Button
            onClick={() => recalculateMutation.mutate()}
            disabled={recalculateMutation.isPending}
            variant="outline"
          >
            {recalculateMutation.isPending && (
              <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
            )}
            Recalculate All
          </Button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid gap-4 md:grid-cols-5">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Total Accounts</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{dashboard?.summary.totalAccounts || 0}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Average Health</CardTitle>
          </CardHeader>
          <CardContent>
            <div className={`text-2xl font-bold ${getHealthColor(dashboard?.summary.avgHealthScore || 0)}`}>
              {dashboard?.summary.avgHealthScore || 0}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-green-600">Healthy</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{dashboard?.summary.healthyAccounts || 0}</div>
            <p className="text-xs text-muted-foreground">Score ≥ 80</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-yellow-600">At Risk</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{dashboard?.summary.atRiskAccounts || 0}</div>
            <p className="text-xs text-muted-foreground">Score 40-79</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-red-600">Critical</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{dashboard?.summary.criticalAccounts || 0}</div>
            <p className="text-xs text-muted-foreground">Score &lt; 40</p>
          </CardContent>
        </Card>
      </div>

      <Tabs defaultValue="overview" className="space-y-4">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="at-risk">At-Risk Accounts</TabsTrigger>
          <TabsTrigger value="factors">Risk Factors</TabsTrigger>
          <TabsTrigger value="trends">Trends</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="space-y-4">
          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Health Distribution</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={dashboard?.distribution || []}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ percentage }) => `${percentage}%`}
                      outerRadius={80}
                      fill="#8884d8"
                      dataKey="count"
                    >
                      {dashboard?.distribution?.map((_, index) => (
                        <Cell key={`cell-${index}`} fill={pieColors[index % pieColors.length]} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
                <div className="flex flex-wrap gap-4 mt-4 justify-center">
                  {dashboard?.distribution?.map((item, index) => (
                    <div key={item.category} className="flex items-center gap-2">
                      <div 
                        className="w-3 h-3 rounded" 
                        style={{ backgroundColor: pieColors[index % pieColors.length] }}
                      />
                      <span className="text-sm">
                        {item.category} ({item.count})
                      </span>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Top Risk Factors</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {dashboard?.topFactors?.map((factor) => (
                    <div key={factor.factor}>
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-medium">{factor.factor}</span>
                        <span className="text-sm text-muted-foreground">
                          {factor.affectedAccounts} accounts
                        </span>
                      </div>
                      <Progress value={factor.impact} className="h-2" />
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="at-risk" className="space-y-4">
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle>At-Risk Accounts</CardTitle>
                <Select value={filter} onValueChange={setFilter}>
                  <SelectTrigger className="w-32">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All</SelectItem>
                    <SelectItem value="critical">Critical</SelectItem>
                    <SelectItem value="at-risk">At Risk</SelectItem>
                    <SelectItem value="declining">Declining</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Account</TableHead>
                    <TableHead>Health Score</TableHead>
                    <TableHead>MRR</TableHead>
                    <TableHead>Risk Factors</TableHead>
                    <TableHead>Last Activity</TableHead>
                    <TableHead>Trend</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredAccounts.map((account) => {
                    const health = getHealthBadge(account.healthScore)
                    return (
                      <TableRow key={account.id}>
                        <TableCell>
                          <div>
                            <p className="font-medium">{account.name}</p>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <div className={`font-bold ${getHealthColor(account.healthScore)}`}>
                              {account.healthScore}
                            </div>
                            <Badge variant={health.variant}>{health.label}</Badge>
                          </div>
                        </TableCell>
                        <TableCell>{formatCurrency(account.mrr)}</TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            {account.riskFactors.slice(0, 2).map((factor, index) => (
                              <Badge key={index} variant="outline" className="text-xs">
                                {factor}
                              </Badge>
                            ))}
                            {account.riskFactors.length > 2 && (
                              <span className="text-xs text-muted-foreground">
                                +{account.riskFactors.length - 2} more
                              </span>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <p className="text-sm text-muted-foreground">
                            {formatDate(account.lastActivity)}
                          </p>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1">
                            {getTrendIcon(account.trend)}
                            <span className="text-sm capitalize">{account.trend}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => calculateScoreMutation.mutate(account.id)}
                            disabled={calculateScoreMutation.isPending}
                          >
                            Recalculate
                          </Button>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="factors" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Risk Factor Analysis</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-6">
                {dashboard?.topFactors?.map((factor) => (
                  <div key={factor.factor} className="space-y-2">
                    <div className="flex items-center justify-between">
                      <div>
                        <h4 className="font-medium">{factor.factor}</h4>
                        <p className="text-sm text-muted-foreground">
                          Affecting {factor.affectedAccounts} accounts
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-2xl font-bold text-red-600">{factor.impact}%</p>
                        <p className="text-xs text-muted-foreground">Impact Score</p>
                      </div>
                    </div>
                    <Progress value={factor.impact} className="h-2" />
                    <div className="flex items-center gap-2 text-sm">
                      <AlertTriangle className="h-4 w-4 text-yellow-600" />
                      <span className="text-muted-foreground">
                        This factor is contributing to lower health scores across multiple accounts
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="trends" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Health Score Trends</CardTitle>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={400}>
                <AreaChart data={dashboard?.trends || []}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="date" />
                  <YAxis />
                  <Tooltip />
                  <Area
                    type="monotone"
                    dataKey="healthy"
                    stackId="1"
                    stroke="#10b981"
                    fill="#10b981"
                    fillOpacity={0.6}
                  />
                  <Area
                    type="monotone"
                    dataKey="atRisk"
                    stackId="1"
                    stroke="#f59e0b"
                    fill="#f59e0b"
                    fillOpacity={0.6}
                  />
                  <Area
                    type="monotone"
                    dataKey="critical"
                    stackId="1"
                    stroke="#ef4444"
                    fill="#ef4444"
                    fillOpacity={0.6}
                  />
                </AreaChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Average Score Trend</CardTitle>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={dashboard?.trends || []}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="date" />
                  <YAxis domain={[0, 100]} />
                  <Tooltip />
                  <Line
                    type="monotone"
                    dataKey="avgScore"
                    stroke="#3b82f6"
                    strokeWidth={2}
                    dot={{ fill: '#3b82f6' }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}