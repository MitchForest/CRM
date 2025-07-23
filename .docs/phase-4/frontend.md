# Phase 4 - Frontend Implementation Guide

## Overview
Phase 4 completes the CRM platform by creating a marketing website that showcases all features, implementing customer health scoring UI, adding advanced chatbot capabilities (meeting scheduling), activity-based alerts, and preparing everything for demo. This phase focuses on integration and polish following the 90/30 approach.

## Prerequisites
- Phases 1-3 completed and working
- All custom modules functional
- AI features operational
- Backend APIs available

## Step-by-Step Implementation

### 1. Additional Dependencies

#### 1.1 Install Required Packages
```bash
cd frontend
# Calendar for meeting scheduling
npm install react-big-calendar date-fns

# Notifications
npm install react-hot-toast

# Animation for marketing site
npm install framer-motion

# Chart enhancements
npm install react-chartjs-2 chart.js

# Marketing site components
npm install react-intersection-observer

# Demo data generation
npm install @faker-js/faker
```

### 2. Marketing Website Implementation

#### 2.1 Create Marketing Layout Component
`src/components/marketing/MarketingLayout.tsx`:
```typescript
import { Link, Outlet } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { Menu, X, Brain, Building2, MessageCircle, BarChart3 } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

export function MarketingLayout() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navigation = [
    { name: 'Features', href: '#features' },
    { name: 'Pricing', href: '#pricing' },
    { name: 'Demo', href: '#demo' },
    { name: 'Knowledge Base', href: '/kb/public' },
  ];

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <header className="fixed top-0 w-full bg-white/80 backdrop-blur-md z-50 border-b">
        <nav className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="flex h-16 items-center justify-between">
            <div className="flex items-center">
              <Link to="/" className="flex items-center gap-2">
                <Brain className="h-8 w-8 text-primary" />
                <span className="text-xl font-bold">AI CRM</span>
              </Link>
            </div>

            {/* Desktop Navigation */}
            <div className="hidden md:flex md:items-center md:gap-x-8">
              {navigation.map((item) => (
                <a
                  key={item.name}
                  href={item.href}
                  className="text-sm font-medium text-gray-700 hover:text-primary"
                >
                  {item.name}
                </a>
              ))}
              <Button variant="outline" asChild>
                <Link to="/login">Sign In</Link>
              </Button>
              <Button asChild>
                <Link to="/demo">Get Demo</Link>
              </Button>
            </div>

            {/* Mobile menu button */}
            <div className="flex md:hidden">
              <button
                type="button"
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className="text-gray-700"
              >
                {mobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
              </button>
            </div>
          </div>
        </nav>

        {/* Mobile menu */}
        {mobileMenuOpen && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="md:hidden bg-white border-b"
          >
            <div className="space-y-1 px-4 pb-3 pt-2">
              {navigation.map((item) => (
                <a
                  key={item.name}
                  href={item.href}
                  className="block py-2 text-base font-medium text-gray-700"
                  onClick={() => setMobileMenuOpen(false)}
                >
                  {item.name}
                </a>
              ))}
              <div className="pt-4 space-y-2">
                <Button variant="outline" className="w-full" asChild>
                  <Link to="/login">Sign In</Link>
                </Button>
                <Button className="w-full" asChild>
                  <Link to="/demo">Get Demo</Link>
                </Button>
              </div>
            </div>
          </motion.div>
        )}
      </header>

      {/* Main Content */}
      <main className="pt-16">
        <Outlet />
      </main>

      {/* Footer */}
      <footer className="bg-gray-50 mt-24">
        <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
              <div className="flex items-center gap-2 mb-4">
                <Brain className="h-6 w-6 text-primary" />
                <span className="font-bold">AI CRM</span>
              </div>
              <p className="text-sm text-gray-600">
                Transform your sales with AI-powered CRM
              </p>
            </div>
            <div>
              <h3 className="font-semibold mb-3">Product</h3>
              <ul className="space-y-2 text-sm text-gray-600">
                <li><Link to="#features">Features</Link></li>
                <li><Link to="#pricing">Pricing</Link></li>
                <li><Link to="/kb/public">Documentation</Link></li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold mb-3">Company</h3>
              <ul className="space-y-2 text-sm text-gray-600">
                <li><a href="#">About</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Careers</a></li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold mb-3">Legal</h3>
              <ul className="space-y-2 text-sm text-gray-600">
                <li><a href="#">Privacy</a></li>
                <li><a href="#">Terms</a></li>
              </ul>
            </div>
          </div>
          <div className="mt-8 pt-8 border-t text-center text-sm text-gray-600">
            © 2024 AI CRM. All rights reserved.
          </div>
        </div>
      </footer>
    </div>
  );
}
```

#### 2.2 Create Marketing Homepage
`src/pages/Marketing/Homepage.tsx`:
```typescript
import { motion } from 'framer-motion';
import { useInView } from 'react-intersection-observer';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { 
  Brain, 
  Zap, 
  BarChart3, 
  MessageCircle, 
  FileText, 
  Activity,
  CheckCircle,
  ArrowRight,
  Users,
  TrendingUp,
  Shield
} from 'lucide-react';
import { ChatWidget } from '@/components/features/Chatbot/ChatWidget';
import { Badge } from '@/components/ui/badge';

export function Homepage() {
  const [heroRef, heroInView] = useInView({ triggerOnce: true });
  const [featuresRef, featuresInView] = useInView({ triggerOnce: true });

  const features = [
    {
      icon: Brain,
      title: 'AI Lead Scoring',
      description: 'Automatically qualify leads with GPT-4 powered scoring',
      color: 'text-purple-600',
      bgColor: 'bg-purple-100',
    },
    {
      icon: MessageCircle,
      title: 'Intelligent Chatbot',
      description: 'Capture and qualify leads 24/7 with AI chat',
      color: 'text-blue-600',
      bgColor: 'bg-blue-100',
    },
    {
      icon: FileText,
      title: 'Form Builder',
      description: 'Create beautiful forms with drag-and-drop simplicity',
      color: 'text-green-600',
      bgColor: 'bg-green-100',
    },
    {
      icon: Activity,
      title: 'Activity Tracking',
      description: 'See what your leads are doing in real-time',
      color: 'text-orange-600',
      bgColor: 'bg-orange-100',
    },
    {
      icon: BarChart3,
      title: 'Pipeline Management',
      description: 'Visual pipeline with drag-and-drop deals',
      color: 'text-indigo-600',
      bgColor: 'bg-indigo-100',
    },
    {
      icon: Shield,
      title: 'Self-Hosted',
      description: 'Your data, your servers, complete control',
      color: 'text-red-600',
      bgColor: 'bg-red-100',
    },
  ];

  const benefits = [
    '90% faster lead qualification',
    '2x improvement in sales velocity',
    '50% reduction in support tickets',
    'Complete data ownership',
  ];

  return (
    <div>
      {/* Hero Section */}
      <section ref={heroRef} className="relative overflow-hidden bg-gradient-to-b from-gray-50 to-white">
        <div className="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8 lg:py-32">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={heroInView ? { opacity: 1, y: 0 } : {}}
            transition={{ duration: 0.5 }}
            className="text-center"
          >
            <Badge className="mb-4" variant="outline">
              <Zap className="mr-1 h-3 w-3" />
              Powered by GPT-4
            </Badge>
            <h1 className="text-5xl font-bold tracking-tight text-gray-900 sm:text-6xl">
              Your Sales Team's
              <span className="block text-primary">AI Copilot</span>
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-gray-600">
              Transform SuiteCRM into an intelligent sales platform. AI scoring, chatbots, 
              and automation that actually works. Self-hosted and secure.
            </p>
            <div className="mt-10 flex items-center justify-center gap-x-6">
              <Button size="lg" asChild>
                <Link to="/demo">
                  See Live Demo
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Link>
              </Button>
              <Button size="lg" variant="outline" asChild>
                <Link to="#features">Learn More</Link>
              </Button>
            </div>
          </motion.div>

          {/* Hero Image/Demo */}
          <motion.div
            initial={{ opacity: 0, y: 40 }}
            animate={heroInView ? { opacity: 1, y: 0 } : {}}
            transition={{ duration: 0.5, delay: 0.2 }}
            className="mt-16"
          >
            <div className="relative rounded-xl shadow-2xl overflow-hidden">
              <img
                src="/demo-screenshot.png"
                alt="CRM Dashboard"
                className="w-full"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent" />
            </div>
          </motion.div>
        </div>
      </section>

      {/* Stats Section */}
      <section className="bg-primary text-white py-16">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
            <div className="text-center">
              <div className="text-4xl font-bold">85%</div>
              <div className="mt-2 text-sm opacity-90">Avg Lead Score Accuracy</div>
            </div>
            <div className="text-center">
              <div className="text-4xl font-bold">2.5x</div>
              <div className="mt-2 text-sm opacity-90">Faster Response Time</div>
            </div>
            <div className="text-center">
              <div className="text-4xl font-bold">47%</div>
              <div className="mt-2 text-sm opacity-90">More Qualified Leads</div>
            </div>
            <div className="text-center">
              <div className="text-4xl font-bold">100%</div>
              <div className="mt-2 text-sm opacity-90">Data Ownership</div>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section ref={featuresRef} id="features" className="py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <h2 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
              Everything you need to close more deals
            </h2>
            <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
              Built on SuiteCRM's proven foundation with modern AI capabilities
            </p>
          </div>

          <div className="mt-16 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            {features.map((feature, index) => {
              const Icon = feature.icon;
              return (
                <motion.div
                  key={feature.title}
                  initial={{ opacity: 0, y: 20 }}
                  animate={featuresInView ? { opacity: 1, y: 0 } : {}}
                  transition={{ duration: 0.5, delay: index * 0.1 }}
                >
                  <Card className="h-full hover:shadow-lg transition-shadow">
                    <CardHeader>
                      <div className={`rounded-lg ${feature.bgColor} p-3 w-fit`}>
                        <Icon className={`h-6 w-6 ${feature.color}`} />
                      </div>
                      <CardTitle className="mt-4">{feature.title}</CardTitle>
                    </CardHeader>
                    <CardContent>
                      <p className="text-gray-600">{feature.description}</p>
                    </CardContent>
                  </Card>
                </motion.div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Demo Section */}
      <section className="bg-gray-50 py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="grid gap-12 lg:grid-cols-2 lg:gap-8 items-center">
            <div>
              <h2 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                See AI Lead Scoring in Action
              </h2>
              <p className="mt-4 text-lg text-gray-600">
                Watch how our AI analyzes visitor behavior, company data, and engagement 
                signals to automatically score and prioritize your leads.
              </p>
              <ul className="mt-8 space-y-4">
                {benefits.map((benefit) => (
                  <li key={benefit} className="flex items-start">
                    <CheckCircle className="h-6 w-6 text-green-500 flex-shrink-0" />
                    <span className="ml-3 text-gray-700">{benefit}</span>
                  </li>
                ))}
              </ul>
              <div className="mt-8">
                <Button size="lg" asChild>
                  <Link to="/demo">Try Interactive Demo</Link>
                </Button>
              </div>
            </div>
            <div className="relative">
              <div className="aspect-video rounded-lg bg-gray-200 overflow-hidden shadow-xl">
                <img
                  src="/ai-scoring-demo.png"
                  alt="AI Scoring Demo"
                  className="w-full h-full object-cover"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="bg-primary py-16">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl font-bold text-white">
            Ready to transform your sales?
          </h2>
          <p className="mt-4 text-lg text-white/90">
            Join hundreds of teams using AI to close more deals
          </p>
          <div className="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            <Button size="lg" variant="secondary" asChild>
              <Link to="/demo">Get Started Free</Link>
            </Button>
            <Button size="lg" variant="outline" className="bg-white/10 text-white border-white/20 hover:bg-white/20" asChild>
              <Link to="/pricing">View Pricing</Link>
            </Button>
          </div>
        </div>
      </section>

      {/* Chat Widget */}
      <ChatWidget position="bottom-right" />
    </div>
  );
}
```

### 3. Customer Health Scoring Implementation

#### 3.1 Create Health Score Service
`src/services/healthScore.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Account } from '@/types';

export interface HealthScoreData {
  account_id: string;
  score: number;
  factors: {
    login_frequency: number;
    feature_usage: number;
    support_tickets: number;
    user_growth: number;
    contract_value: number;
    engagement_trend: number;
  };
  risk_level: 'low' | 'medium' | 'high';
  churn_probability: number;
  recommendations: string[];
  calculated_at: string;
}

export interface HealthScoreTrend {
  date: string;
  score: number;
  risk_level: 'low' | 'medium' | 'high';
}

export const healthScoreService = {
  async calculateHealthScore(accountId: string): Promise<HealthScoreData> {
    const response = await api.post(`/health/calculate/${accountId}`);
    return response.data;
  },

  async getHealthScore(accountId: string): Promise<HealthScoreData> {
    const response = await api.get(`/health/score/${accountId}`);
    return response.data;
  },

  async getHealthScoreTrends(accountId: string, days = 90): Promise<HealthScoreTrend[]> {
    const response = await api.get(`/health/trends/${accountId}`, {
      params: { days },
    });
    return response.data;
  },

  async getAtRiskAccounts(limit = 10) {
    const response = await api.get('/health/at-risk', {
      params: { limit },
    });
    return response.data;
  },

  async updateHealthFactors(accountId: string, factors: Partial<HealthScoreData['factors']>) {
    const response = await api.patch(`/health/factors/${accountId}`, factors);
    return response.data;
  },
};
```

#### 3.2 Create Health Score Dashboard Component
`src/components/features/HealthScore/HealthScoreDashboard.tsx`:
```typescript
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Button } from '@/components/ui/button';
import { 
  TrendingUp, 
  TrendingDown, 
  AlertTriangle, 
  Shield,
  Users,
  Activity,
  HeadphonesIcon,
  DollarSign,
  RefreshCw
} from 'lucide-react';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  RadarChart,
  PolarGrid,
  PolarAngleAxis,
  PolarRadiusAxis,
  Radar,
} from 'recharts';
import { healthScoreService } from '@/services/healthScore.service';
import { cn } from '@/lib/utils';
import type { HealthScoreData } from '@/services/healthScore.service';

interface HealthScoreDashboardProps {
  accountId: string;
  accountName: string;
}

export function HealthScoreDashboard({ accountId, accountName }: HealthScoreDashboardProps) {
  const [isCalculating, setIsCalculating] = useState(false);

  const { data: healthScore, refetch: refetchScore } = useQuery({
    queryKey: ['health-score', accountId],
    queryFn: () => healthScoreService.getHealthScore(accountId),
  });

  const { data: trends } = useQuery({
    queryKey: ['health-trends', accountId],
    queryFn: () => healthScoreService.getHealthScoreTrends(accountId),
  });

  const handleRecalculate = async () => {
    setIsCalculating(true);
    try {
      await healthScoreService.calculateHealthScore(accountId);
      await refetchScore();
    } finally {
      setIsCalculating(false);
    }
  };

  const getScoreColor = (score: number) => {
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-yellow-600';
    if (score >= 40) return 'text-orange-600';
    return 'text-red-600';
  };

  const getRiskBadge = (risk: string) => {
    const variants: Record<string, any> = {
      low: { color: 'bg-green-100 text-green-800', icon: Shield },
      medium: { color: 'bg-yellow-100 text-yellow-800', icon: AlertTriangle },
      high: { color: 'bg-red-100 text-red-800', icon: AlertTriangle },
    };
    
    const variant = variants[risk] || variants.medium;
    const Icon = variant.icon;
    
    return (
      <Badge className={cn('flex items-center gap-1', variant.color)}>
        <Icon className="h-3 w-3" />
        {risk.charAt(0).toUpperCase() + risk.slice(1)} Risk
      </Badge>
    );
  };

  const factorIcons: Record<string, any> = {
    login_frequency: Users,
    feature_usage: Activity,
    support_tickets: HeadphonesIcon,
    user_growth: TrendingUp,
    contract_value: DollarSign,
    engagement_trend: TrendingUp,
  };

  const radarData = healthScore
    ? Object.entries(healthScore.factors).map(([key, value]) => ({
        factor: key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
        value: value,
        fullMark: 20,
      }))
    : [];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold">{accountName} Health Score</h2>
          <p className="text-muted-foreground">
            Last calculated: {healthScore ? new Date(healthScore.calculated_at).toLocaleString() : 'Never'}
          </p>
        </div>
        <Button onClick={handleRecalculate} disabled={isCalculating}>
          {isCalculating && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
          Recalculate
        </Button>
      </div>

      {healthScore && (
        <>
          {/* Main Score Card */}
          <Card>
            <CardContent className="pt-6">
              <div className="grid gap-6 md:grid-cols-3">
                {/* Score */}
                <div className="text-center">
                  <div className={cn("text-6xl font-bold", getScoreColor(healthScore.score))}>
                    {healthScore.score}
                  </div>
                  <p className="text-sm text-muted-foreground mt-1">Health Score</p>
                  <div className="mt-4">
                    {getRiskBadge(healthScore.risk_level)}
                  </div>
                </div>

                {/* Churn Probability */}
                <div className="text-center">
                  <div className="text-4xl font-bold text-gray-700">
                    {Math.round(healthScore.churn_probability * 100)}%
                  </div>
                  <p className="text-sm text-muted-foreground mt-1">Churn Probability</p>
                  <Progress 
                    value={healthScore.churn_probability * 100} 
                    className="mt-4"
                  />
                </div>

                {/* Trend */}
                <div>
                  {trends && trends.length > 1 && (
                    <ResponsiveContainer width="100%" height={100}>
                      <LineChart data={trends.slice(-30)}>
                        <Line
                          type="monotone"
                          dataKey="score"
                          stroke="#3b82f6"
                          strokeWidth={2}
                          dot={false}
                        />
                        <Tooltip />
                      </LineChart>
                    </ResponsiveContainer>
                  )}
                  <p className="text-sm text-muted-foreground text-center mt-2">
                    30-Day Trend
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Factor Analysis */}
          <div className="grid gap-6 md:grid-cols-2">
            {/* Factor Breakdown */}
            <Card>
              <CardHeader>
                <CardTitle>Health Factors</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {Object.entries(healthScore.factors).map(([key, value]) => {
                    const Icon = factorIcons[key] || Activity;
                    return (
                      <div key={key} className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <Icon className="h-5 w-5 text-muted-foreground" />
                          <span className="capitalize">
                            {key.replace(/_/g, ' ')}
                          </span>
                        </div>
                        <div className="flex items-center gap-2">
                          <Progress 
                            value={(value / 20) * 100} 
                            className="w-24"
                          />
                          <span className="text-sm font-medium w-8">
                            {value}/20
                          </span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </CardContent>
            </Card>

            {/* Radar Chart */}
            <Card>
              <CardHeader>
                <CardTitle>Factor Analysis</CardTitle>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <RadarChart data={radarData}>
                    <PolarGrid />
                    <PolarAngleAxis dataKey="factor" />
                    <PolarRadiusAxis angle={90} domain={[0, 20]} />
                    <Radar
                      name="Score"
                      dataKey="value"
                      stroke="#3b82f6"
                      fill="#3b82f6"
                      fillOpacity={0.6}
                    />
                    <Tooltip />
                  </RadarChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </div>

          {/* Recommendations */}
          {healthScore.recommendations.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>AI Recommendations</CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="space-y-3">
                  {healthScore.recommendations.map((recommendation, index) => (
                    <li key={index} className="flex items-start gap-3">
                      <div className="rounded-full bg-primary/10 p-1 mt-0.5">
                        <TrendingUp className="h-4 w-4 text-primary" />
                      </div>
                      <span className="text-sm">{recommendation}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
```

### 4. Advanced Chatbot Features

#### 4.1 Create Meeting Scheduler Component
`src/components/features/Chatbot/MeetingScheduler.tsx`:
```typescript
import { useState } from 'react';
import { Calendar as BigCalendar, momentLocalizer } from 'react-big-calendar';
import moment from 'moment';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Calendar, Clock, Video, MapPin } from 'lucide-react';
import 'react-big-calendar/lib/css/react-big-calendar.css';

const localizer = momentLocalizer(moment);

interface MeetingSlot {
  start: Date;
  end: Date;
  available: boolean;
}

interface MeetingSchedulerProps {
  onSchedule: (meeting: {
    date: Date;
    duration: number;
    type: string;
    notes?: string;
  }) => void;
  availableSlots?: MeetingSlot[];
}

export function MeetingScheduler({ onSchedule, availableSlots = [] }: MeetingSchedulerProps) {
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const [selectedTime, setSelectedTime] = useState<string>('');
  const [duration, setDuration] = useState('30');
  const [meetingType, setMeetingType] = useState('demo');
  const [notes, setNotes] = useState('');

  // Generate time slots
  const generateTimeSlots = (date: Date) => {
    const slots = [];
    const startHour = 9;
    const endHour = 17;
    
    for (let hour = startHour; hour < endHour; hour++) {
      for (let minute = 0; minute < 60; minute += 30) {
        const time = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
        slots.push(time);
      }
    }
    
    return slots;
  };

  const handleSchedule = () => {
    if (!selectedDate || !selectedTime) return;

    const [hours, minutes] = selectedTime.split(':').map(Number);
    const meetingDate = new Date(selectedDate);
    meetingDate.setHours(hours, minutes, 0, 0);

    onSchedule({
      date: meetingDate,
      duration: parseInt(duration),
      type: meetingType,
      notes,
    });
  };

  const events = availableSlots.map((slot, index) => ({
    id: index,
    title: slot.available ? 'Available' : 'Busy',
    start: slot.start,
    end: slot.end,
    resource: { available: slot.available },
  }));

  const eventStyleGetter = (event: any) => {
    const backgroundColor = event.resource.available ? '#10b981' : '#ef4444';
    return {
      style: {
        backgroundColor,
        opacity: 0.8,
      },
    };
  };

  return (
    <div className="space-y-6">
      {/* Calendar View */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Calendar className="h-5 w-5" />
            Select a Date
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div style={{ height: 400 }}>
            <BigCalendar
              localizer={localizer}
              events={events}
              startAccessor="start"
              endAccessor="end"
              onSelectSlot={(slotInfo) => setSelectedDate(slotInfo.start)}
              selectable
              eventPropGetter={eventStyleGetter}
              views={['month', 'week']}
              defaultView="week"
            />
          </div>
        </CardContent>
      </Card>

      {/* Meeting Details */}
      {selectedDate && (
        <Card>
          <CardHeader>
            <CardTitle>Meeting Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div>
              <Label>Selected Date</Label>
              <p className="text-sm text-muted-foreground">
                {moment(selectedDate).format('MMMM D, YYYY')}
              </p>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="time">Time</Label>
                <Select value={selectedTime} onValueChange={setSelectedTime}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select time" />
                  </SelectTrigger>
                  <SelectContent>
                    {generateTimeSlots(selectedDate).map((time) => (
                      <SelectItem key={time} value={time}>
                        <div className="flex items-center gap-2">
                          <Clock className="h-4 w-4" />
                          {time}
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="duration">Duration</Label>
                <Select value={duration} onValueChange={setDuration}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="15">15 minutes</SelectItem>
                    <SelectItem value="30">30 minutes</SelectItem>
                    <SelectItem value="45">45 minutes</SelectItem>
                    <SelectItem value="60">1 hour</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="type">Meeting Type</Label>
              <Select value={meetingType} onValueChange={setMeetingType}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="demo">
                    <div className="flex items-center gap-2">
                      <Video className="h-4 w-4" />
                      Product Demo
                    </div>
                  </SelectItem>
                  <SelectItem value="discovery">
                    <div className="flex items-center gap-2">
                      <MapPin className="h-4 w-4" />
                      Discovery Call
                    </div>
                  </SelectItem>
                  <SelectItem value="support">
                    <div className="flex items-center gap-2">
                      <Clock className="h-4 w-4" />
                      Support Session
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="notes">Notes (Optional)</Label>
              <textarea
                id="notes"
                className="w-full rounded-md border border-input bg-background px-3 py-2"
                rows={3}
                placeholder="Any specific topics you'd like to discuss?"
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
              />
            </div>

            <Button
              onClick={handleSchedule}
              disabled={!selectedTime}
              className="w-full"
            >
              Schedule Meeting
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
```

### 5. Activity-Based Alerts System

#### 5.1 Create Alerts Service
`src/services/alerts.service.ts`:
```typescript
import { api } from '@/lib/api';

export interface Alert {
  id: string;
  type: 'lead_score' | 'high_activity' | 'health_risk' | 'form_submission' | 'chat_qualified';
  severity: 'info' | 'warning' | 'critical';
  title: string;
  message: string;
  data: Record<string, any>;
  read: boolean;
  created_at: string;
  expires_at?: string;
}

export interface AlertRule {
  id: string;
  name: string;
  type: string;
  conditions: Record<string, any>;
  actions: Array<{
    type: 'email' | 'notification' | 'webhook';
    config: Record<string, any>;
  }>;
  enabled: boolean;
}

export const alertsService = {
  async getAlerts(params?: {
    unread?: boolean;
    type?: string;
    severity?: string;
    limit?: number;
  }) {
    const response = await api.get<Alert[]>('/alerts', { params });
    return response.data;
  },

  async markAsRead(alertId: string) {
    const response = await api.patch(`/alerts/${alertId}/read`);
    return response.data;
  },

  async markAllAsRead() {
    const response = await api.patch('/alerts/read-all');
    return response.data;
  },

  async getAlertRules() {
    const response = await api.get<AlertRule[]>('/alerts/rules');
    return response.data;
  },

  async createAlertRule(rule: Partial<AlertRule>) {
    const response = await api.post<AlertRule>('/alerts/rules', rule);
    return response.data;
  },

  async updateAlertRule(id: string, rule: Partial<AlertRule>) {
    const response = await api.patch<AlertRule>(`/alerts/rules/${id}`, rule);
    return response.data;
  },

  async deleteAlertRule(id: string) {
    await api.delete(`/alerts/rules/${id}`);
  },

  async testAlertRule(rule: Partial<AlertRule>) {
    const response = await api.post('/alerts/rules/test', rule);
    return response.data;
  },
};
```

#### 5.2 Create Alerts Center Component
`src/components/features/Alerts/AlertsCenter.tsx`:
```typescript
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Bell, AlertTriangle, Info, CheckCircle, X, Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { alertsService } from '@/services/alerts.service';
import { formatDistanceToNow } from 'date-fns';
import { cn } from '@/lib/utils';
import { useToast } from '@/components/ui/use-toast';
import type { Alert } from '@/services/alerts.service';

export function AlertsCenter() {
  const [isOpen, setIsOpen] = useState(false);
  const queryClient = useQueryClient();
  const { toast: showToast } = useToast();

  const { data: alerts, isLoading } = useQuery({
    queryKey: ['alerts', { unread: true }],
    queryFn: () => alertsService.getAlerts({ unread: true, limit: 20 }),
    refetchInterval: 30000, // Check every 30 seconds
  });

  const markAsReadMutation = useMutation({
    mutationFn: alertsService.markAsRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
    },
  });

  const markAllAsReadMutation = useMutation({
    mutationFn: alertsService.markAllAsRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['alerts'] });
      showToast({
        title: 'All alerts marked as read',
      });
    },
  });

  // Show toast for new critical alerts
  useEffect(() => {
    const criticalAlerts = alerts?.filter(
      a => a.severity === 'critical' && !a.read
    ) || [];
    
    criticalAlerts.forEach(alert => {
      showToast({
        title: alert.title,
        description: alert.message,
        variant: 'destructive',
      });
    });
  }, [alerts, showToast]);

  const getAlertIcon = (alert: Alert) => {
    switch (alert.severity) {
      case 'critical':
        return <AlertTriangle className="h-4 w-4 text-red-500" />;
      case 'warning':
        return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
      default:
        return <Info className="h-4 w-4 text-blue-500" />;
    }
  };

  const getSeverityBadge = (severity: string) => {
    const variants: Record<string, string> = {
      critical: 'bg-red-100 text-red-800',
      warning: 'bg-yellow-100 text-yellow-800',
      info: 'bg-blue-100 text-blue-800',
    };
    
    return (
      <Badge className={cn('text-xs', variants[severity] || variants.info)}>
        {severity}
      </Badge>
    );
  };

  const unreadCount = alerts?.filter(a => !a.read).length || 0;

  return (
    <Popover open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative">
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-500 text-xs text-white flex items-center justify-center">
              {unreadCount > 9 ? '9+' : unreadCount}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-96 p-0" align="end">
        <div className="flex items-center justify-between p-4 border-b">
          <h3 className="font-semibold">Alerts</h3>
          <div className="flex items-center gap-2">
            {unreadCount > 0 && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => markAllAsReadMutation.mutate()}
              >
                Mark all read
              </Button>
            )}
            <Button variant="ghost" size="icon" asChild>
              <Link to="/settings/alerts">
                <Settings className="h-4 w-4" />
              </Link>
            </Button>
          </div>
        </div>

        <Tabs defaultValue="all" className="w-full">
          <TabsList className="grid w-full grid-cols-3 border-b rounded-none">
            <TabsTrigger value="all">All</TabsTrigger>
            <TabsTrigger value="critical">Critical</TabsTrigger>
            <TabsTrigger value="unread">Unread</TabsTrigger>
          </TabsList>

          <TabsContent value="all" className="m-0">
            <AlertsList
              alerts={alerts || []}
              onMarkAsRead={(id) => markAsReadMutation.mutate(id)}
            />
          </TabsContent>

          <TabsContent value="critical" className="m-0">
            <AlertsList
              alerts={alerts?.filter(a => a.severity === 'critical') || []}
              onMarkAsRead={(id) => markAsReadMutation.mutate(id)}
            />
          </TabsContent>

          <TabsContent value="unread" className="m-0">
            <AlertsList
              alerts={alerts?.filter(a => !a.read) || []}
              onMarkAsRead={(id) => markAsReadMutation.mutate(id)}
            />
          </TabsContent>
        </Tabs>
      </PopoverContent>
    </Popover>
  );
}

interface AlertsListProps {
  alerts: Alert[];
  onMarkAsRead: (id: string) => void;
}

function AlertsList({ alerts, onMarkAsRead }: AlertsListProps) {
  if (alerts.length === 0) {
    return (
      <div className="p-8 text-center text-muted-foreground">
        <Bell className="h-8 w-8 mx-auto mb-2 opacity-50" />
        <p>No alerts</p>
      </div>
    );
  }

  return (
    <ScrollArea className="h-[400px]">
      <div className="divide-y">
        {alerts.map((alert) => (
          <div
            key={alert.id}
            className={cn(
              "p-4 hover:bg-muted/50 cursor-pointer transition-colors",
              !alert.read && "bg-blue-50/50"
            )}
            onClick={() => {
              if (!alert.read) {
                onMarkAsRead(alert.id);
              }
              // Navigate to relevant page based on alert type
              handleAlertClick(alert);
            }}
          >
            <div className="flex items-start gap-3">
              <div className="mt-1">{getAlertIcon(alert)}</div>
              <div className="flex-1 space-y-1">
                <div className="flex items-center justify-between">
                  <p className="font-medium text-sm">{alert.title}</p>
                  <div className="flex items-center gap-2">
                    {getSeverityBadge(alert.severity)}
                    {!alert.read && (
                      <div className="h-2 w-2 rounded-full bg-blue-500" />
                    )}
                  </div>
                </div>
                <p className="text-sm text-muted-foreground line-clamp-2">
                  {alert.message}
                </p>
                <p className="text-xs text-muted-foreground">
                  {formatDistanceToNow(new Date(alert.created_at), {
                    addSuffix: true,
                  })}
                </p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </ScrollArea>
  );
}

function handleAlertClick(alert: Alert) {
  // Navigate based on alert type
  switch (alert.type) {
    case 'lead_score':
      window.location.href = `/leads/${alert.data.lead_id}`;
      break;
    case 'high_activity':
      window.location.href = `/tracking/sessions/${alert.data.session_id}`;
      break;
    case 'health_risk':
      window.location.href = `/accounts/${alert.data.account_id}`;
      break;
    case 'form_submission':
      window.location.href = `/forms/${alert.data.form_id}/submissions`;
      break;
    case 'chat_qualified':
      window.location.href = `/leads/${alert.data.lead_id}`;
      break;
  }
}
```

### 6. Demo Data Generation

#### 6.1 Create Demo Data Generator
`src/utils/demoDataGenerator.ts`:
```typescript
import { faker } from '@faker-js/faker';
import type { Lead, Account, Opportunity, Case } from '@/types';

export class DemoDataGenerator {
  generateLeads(count: number): Partial<Lead>[] {
    return Array.from({ length: count }, () => ({
      first_name: faker.person.firstName(),
      last_name: faker.person.lastName(),
      email: faker.internet.email(),
      phone_mobile: faker.phone.number(),
      account_name: faker.company.name(),
      title: faker.person.jobTitle(),
      lead_source: faker.helpers.arrayElement(['Website', 'Referral', 'Social Media', 'Email', 'Chat']),
      status: faker.helpers.arrayElement(['New', 'Contacted', 'Qualified', 'Lost']),
      ai_score: faker.number.int({ min: 0, max: 100 }),
      description: faker.lorem.paragraph(),
    }));
  }

  generateAccounts(count: number): Partial<Account>[] {
    return Array.from({ length: count }, () => ({
      name: faker.company.name(),
      website: faker.internet.url(),
      phone_office: faker.phone.number(),
      industry: faker.helpers.arrayElement(['Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing']),
      annual_revenue: faker.number.int({ min: 100000, max: 10000000 }),
      employees: faker.helpers.arrayElement(['1-10', '11-50', '51-200', '201-500', '500+']),
      account_type: faker.helpers.arrayElement(['Customer', 'Prospect', 'Partner']),
      description: faker.company.catchPhrase(),
    }));
  }

  generateOpportunities(count: number): Partial<Opportunity>[] {
    const stages: OpportunityStage[] = [
      'Qualification',
      'Needs Analysis',
      'Value Proposition',
      'Decision Makers',
      'Proposal',
      'Negotiation',
      'Closed Won',
      'Closed Lost',
    ];

    return Array.from({ length: count }, () => {
      const stage = faker.helpers.arrayElement(stages);
      const probability = {
        'Qualification': 10,
        'Needs Analysis': 20,
        'Value Proposition': 40,
        'Decision Makers': 60,
        'Proposal': 75,
        'Negotiation': 90,
        'Closed Won': 100,
        'Closed Lost': 0,
      }[stage];

      return {
        name: `${faker.company.name()} - ${faker.commerce.productName()}`,
        sales_stage: stage,
        amount: faker.number.int({ min: 10000, max: 500000 }),
        probability,
        date_closed: faker.date.future({ years: 0.5 }).toISOString(),
        lead_source: faker.helpers.arrayElement(['Website', 'Referral', 'Partner', 'Direct']),
        next_step: faker.lorem.sentence(),
        description: faker.lorem.paragraph(),
      };
    });
  }

  generateCases(count: number): Partial<Case>[] {
    return Array.from({ length: count }, () => ({
      name: faker.lorem.sentence(),
      status: faker.helpers.arrayElement(['New', 'Assigned', 'In Progress', 'Pending Input', 'Closed']),
      priority: faker.helpers.arrayElement(['P1', 'P2', 'P3']),
      type: faker.helpers.arrayElement(['Bug', 'Feature Request', 'Support', 'Other']),
      description: faker.lorem.paragraphs(2),
    }));
  }

  generateActivityData(days: number = 30) {
    const data = [];
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - days);

    for (let i = 0; i < days; i++) {
      const date = new Date(startDate);
      date.setDate(date.getDate() + i);

      data.push({
        date: date.toISOString().split('T')[0],
        visitors: faker.number.int({ min: 50, max: 500 }),
        leads: faker.number.int({ min: 5, max: 50 }),
        conversions: faker.number.int({ min: 1, max: 10 }),
      });
    }

    return data;
  }

  generateHealthScoreHistory(days: number = 90) {
    const data = [];
    let score = 85;

    for (let i = 0; i < days; i++) {
      // Simulate score changes
      score += faker.number.int({ min: -5, max: 5 });
      score = Math.max(0, Math.min(100, score)); // Keep between 0-100

      data.push({
        date: new Date(Date.now() - (days - i) * 24 * 60 * 60 * 1000).toISOString(),
        score,
        risk_level: score >= 80 ? 'low' : score >= 60 ? 'medium' : 'high',
      });
    }

    return data;
  }
}

export const demoDataGenerator = new DemoDataGenerator();
```

### 7. Demo Environment Setup

#### 7.1 Create Demo Mode Component
`src/components/features/Demo/DemoMode.tsx`:
```typescript
import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { 
  Sparkles, 
  Play, 
  Pause, 
  SkipForward,
  RefreshCw,
  X 
} from 'lucide-react';
import { useDemoStore } from '@/store/demo';
import { cn } from '@/lib/utils';

interface DemoStep {
  id: string;
  title: string;
  description: string;
  action: () => void;
  highlight?: string; // CSS selector to highlight
}

const demoSteps: DemoStep[] = [
  {
    id: 'welcome',
    title: 'Welcome to AI CRM Demo',
    description: 'Let\'s explore how AI can transform your sales process',
    action: () => {},
  },
  {
    id: 'ai-scoring',
    title: 'AI Lead Scoring in Action',
    description: 'Watch as our AI analyzes and scores leads automatically',
    action: () => {
      window.location.href = '/leads/scoring';
    },
    highlight: '[data-demo="ai-score-button"]',
  },
  {
    id: 'chatbot',
    title: 'Intelligent Chatbot',
    description: 'See how our chatbot qualifies leads 24/7',
    action: () => {
      // Trigger chatbot
      const chatButton = document.querySelector('[data-demo="chat-widget"]');
      if (chatButton) chatButton.click();
    },
  },
  {
    id: 'pipeline',
    title: 'Visual Pipeline Management',
    description: 'Drag and drop deals through your sales stages',
    action: () => {
      window.location.href = '/opportunities';
    },
  },
  {
    id: 'activity',
    title: 'Real-Time Activity Tracking',
    description: 'See what your leads are doing right now',
    action: () => {
      window.location.href = '/tracking';
    },
  },
  {
    id: 'health',
    title: 'Customer Health Monitoring',
    description: 'AI predicts churn risk and suggests actions',
    action: () => {
      window.location.href = '/accounts';
    },
  },
];

export function DemoMode() {
  const { isDemoMode, currentStep, setCurrentStep, toggleDemoMode } = useDemoStore();
  const [isPlaying, setIsPlaying] = useState(true);

  if (!isDemoMode) return null;

  const step = demoSteps[currentStep];
  const progress = ((currentStep + 1) / demoSteps.length) * 100;

  const handleNext = () => {
    if (currentStep < demoSteps.length - 1) {
      setCurrentStep(currentStep + 1);
      demoSteps[currentStep + 1].action();
    }
  };

  const handlePrevious = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
    }
  };

  const handleRestart = () => {
    setCurrentStep(0);
    demoSteps[0].action();
  };

  // Auto-advance
  useEffect(() => {
    if (!isPlaying) return;

    const timer = setTimeout(() => {
      handleNext();
    }, 15000); // 15 seconds per step

    return () => clearTimeout(timer);
  }, [currentStep, isPlaying]);

  // Highlight elements
  useEffect(() => {
    if (step.highlight) {
      const element = document.querySelector(step.highlight);
      if (element) {
        element.classList.add('demo-highlight');
        return () => {
          element.classList.remove('demo-highlight');
        };
      }
    }
  }, [step]);

  return (
    <>
      <style>{`
        .demo-highlight {
          position: relative;
          z-index: 1000;
          box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.5);
          animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
          0% {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.5);
          }
          50% {
            box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.3);
          }
          100% {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.5);
          }
        }
      `}</style>

      <AnimatePresence>
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: 20 }}
          className="fixed bottom-6 right-6 z-50 w-96"
        >
          <Card className="shadow-2xl border-primary/20">
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Sparkles className="h-5 w-5 text-primary" />
                  <CardTitle className="text-lg">Demo Mode</CardTitle>
                  <Badge variant="secondary">
                    {currentStep + 1} / {demoSteps.length}
                  </Badge>
                </div>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={toggleDemoMode}
                  className="h-8 w-8"
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
              <Progress value={progress} className="mt-2" />
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                <div>
                  <h4 className="font-semibold">{step.title}</h4>
                  <p className="text-sm text-muted-foreground mt-1">
                    {step.description}
                  </p>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => setIsPlaying(!isPlaying)}
                    >
                      {isPlaying ? (
                        <Pause className="h-4 w-4" />
                      ) : (
                        <Play className="h-4 w-4" />
                      )}
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={handlePrevious}
                      disabled={currentStep === 0}
                    >
                      Previous
                    </Button>
                    <Button
                      size="sm"
                      onClick={handleNext}
                      disabled={currentStep === demoSteps.length - 1}
                    >
                      Next
                      <SkipForward className="ml-1 h-4 w-4" />
                    </Button>
                  </div>
                  {currentStep === demoSteps.length - 1 && (
                    <Button size="sm" variant="outline" onClick={handleRestart}>
                      <RefreshCw className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        </motion.div>
      </AnimatePresence>
    </>
  );
}
```

### 8. Update App Router with Marketing Routes

`src/App.tsx` (update with Phase 4 routes):
```typescript
// Add to imports
import { MarketingLayout } from '@/components/marketing/MarketingLayout';
import { Homepage } from '@/pages/Marketing/Homepage';
import { PricingPage } from '@/pages/Marketing/PricingPage';
import { DemoPage } from '@/pages/Marketing/DemoPage';
import { HealthScoreDashboard } from '@/components/features/HealthScore/HealthScoreDashboard';
import { AlertsSettings } from '@/pages/Settings/AlertsSettings';
import { DemoMode } from '@/components/features/Demo/DemoMode';

// Update routes
export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          {/* Marketing Routes */}
          <Route path="/" element={<MarketingLayout />}>
            <Route index element={<Homepage />} />
            <Route path="pricing" element={<PricingPage />} />
            <Route path="demo" element={<DemoPage />} />
          </Route>

          {/* Public KB Route */}
          <Route path="/kb/public/*" element={<KnowledgeBasePublic />} />

          {/* Auth */}
          <Route path="/login" element={<LoginPage />} />
          
          {/* Protected App Routes */}
          <Route
            path="/app"
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<DashboardPage />} />
            
            {/* All existing routes... */}
            
            {/* Health Score */}
            <Route path="accounts/:id/health" element={<AccountHealthPage />} />
            
            {/* Settings */}
            <Route path="settings/alerts" element={<AlertsSettings />} />
          </Route>
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>

        {/* Demo Mode Overlay */}
        <DemoMode />
        
        {/* Alerts Center */}
        <AlertsCenter />
        
        {/* Toast Notifications */}
        <Toaster />
      </BrowserRouter>
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  );
}
```

### 9. Create Demo Store

`src/store/demo.ts`:
```typescript
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface DemoState {
  isDemoMode: boolean;
  currentStep: number;
  completedSteps: string[];
  toggleDemoMode: () => void;
  setCurrentStep: (step: number) => void;
  markStepCompleted: (stepId: string) => void;
  resetDemo: () => void;
}

export const useDemoStore = create<DemoState>()(
  persist(
    (set) => ({
      isDemoMode: false,
      currentStep: 0,
      completedSteps: [],
      toggleDemoMode: () => set((state) => ({ 
        isDemoMode: !state.isDemoMode,
        currentStep: state.isDemoMode ? 0 : state.currentStep,
      })),
      setCurrentStep: (step) => set({ currentStep: step }),
      markStepCompleted: (stepId) => set((state) => ({
        completedSteps: [...new Set([...state.completedSteps, stepId])],
      })),
      resetDemo: () => set({
        currentStep: 0,
        completedSteps: [],
      }),
    }),
    {
      name: 'demo-storage',
    }
  )
);
```

### 10. Deployment Configuration

#### 10.1 Create Production Build Script
`package.json` (add scripts):
```json
{
  "scripts": {
    "build:prod": "vite build",
    "preview:prod": "vite preview",
    "generate:demo": "tsx scripts/generateDemoData.ts",
    "deploy:vercel": "vercel --prod",
    "deploy:netlify": "netlify deploy --prod"
  }
}
```

#### 10.2 Create Demo Data Script
`scripts/generateDemoData.ts`:
```typescript
import { demoDataGenerator } from '../src/utils/demoDataGenerator';
import { leadService } from '../src/services/lead.service';
import { accountService } from '../src/services/account.service';
import { opportunityService } from '../src/services/opportunity.service';
import { caseService } from '../src/services/case.service';

async function generateDemoData() {
  console.log('🚀 Generating demo data...');

  try {
    // Generate Leads
    console.log('Creating leads...');
    const leads = demoDataGenerator.generateLeads(50);
    for (const lead of leads) {
      await leadService.create(lead);
    }

    // Generate Accounts
    console.log('Creating accounts...');
    const accounts = demoDataGenerator.generateAccounts(20);
    for (const account of accounts) {
      await accountService.create(account);
    }

    // Generate Opportunities
    console.log('Creating opportunities...');
    const opportunities = demoDataGenerator.generateOpportunities(30);
    for (const opportunity of opportunities) {
      await opportunityService.create(opportunity);
    }

    // Generate Cases
    console.log('Creating cases...');
    const cases = demoDataGenerator.generateCases(15);
    for (const caseData of cases) {
      await caseService.create(caseData);
    }

    console.log('✅ Demo data generated successfully!');
  } catch (error) {
    console.error('❌ Error generating demo data:', error);
  }
}

generateDemoData();
```

## Testing Setup

### End-to-End Demo Test
`tests/e2e/demo-flow.test.ts`:
```typescript
import { test, expect } from '@playwright/test';

test.describe('Complete Demo Flow', () => {
  test('should complete full demo journey', async ({ page }) => {
    // Visit marketing site
    await page.goto('/');
    await expect(page.locator('h1')).toContainText('AI Copilot');

    // Start demo
    await page.click('text=Get Demo');
    await expect(page).toHaveURL('/demo');

    // Enable demo mode
    await page.click('[data-test="start-demo"]');
    await expect(page.locator('.demo-mode')).toBeVisible();

    // Step through demo
    await page.click('text=Next');
    await expect(page).toHaveURL('/leads/scoring');

    // Test AI scoring
    await page.click('[data-test="score-lead"]');
    await expect(page.locator('.ai-score')).toBeVisible();

    // Test chatbot
    await page.click('[data-test="chat-widget"]');
    await page.fill('[data-test="chat-input"]', 'I need a demo');
    await page.keyboard.press('Enter');
    await expect(page.locator('.chat-message')).toContainText('demo');

    // Test pipeline
    await page.goto('/opportunities');
    const opportunity = page.locator('[data-test="opportunity-card"]').first();
    await opportunity.dragTo(page.locator('[data-stage="Proposal"]'));

    // Verify health score
    await page.goto('/accounts/1/health');
    await expect(page.locator('.health-score')).toBeVisible();
  });
});
```

## Definition of Success

### ✅ Phase 4 Frontend Success Criteria:

1. **Marketing Website**
   - [ ] Homepage with hero, features, and CTA sections
   - [ ] Smooth animations and interactions
   - [ ] Chat widget embedded and functional
   - [ ] Mobile responsive design
   - [ ] SEO optimized with meta tags

2. **Customer Health Scoring**
   - [ ] Health score dashboard displays for accounts
   - [ ] Factor breakdown with visual charts
   - [ ] Trend visualization over time
   - [ ] AI recommendations displayed
   - [ ] Recalculation triggers work

3. **Advanced Chatbot**
   - [ ] Meeting scheduler integrated
   - [ ] Calendar view shows availability
   - [ ] Meeting booking creates activity
   - [ ] Lead qualification flow works
   - [ ] Context-aware responses

4. **Activity Alerts**
   - [ ] Real-time alert notifications
   - [ ] Alert center with categorization
   - [ ] Mark as read functionality
   - [ ] Alert rules configuration
   - [ ] Critical alerts show toasts

5. **Demo Experience**
   - [ ] Demo mode with guided tour
   - [ ] Step-by-step progression
   - [ ] Element highlighting works
   - [ ] Demo data populated
   - [ ] Auto-play functionality

6. **Integration & Polish**
   - [ ] All features work together seamlessly
   - [ ] Performance optimized (< 3s load)
   - [ ] Error handling throughout
   - [ ] Loading states consistent
   - [ ] Accessibility standards met

### Manual Verification Steps:
1. Visit marketing homepage and verify all sections
2. Click through to demo and start guided tour
3. Complete demo flow end-to-end
4. Test health score calculation and visualization
5. Book a meeting through chatbot
6. Trigger various alerts and verify notifications
7. Generate demo data and verify display
8. Test on mobile devices
9. Verify all integrations work
10. Run performance audit

### Deployment Checklist:
- [ ] Environment variables configured
- [ ] API endpoints updated for production
- [ ] Demo data seeded
- [ ] SSL certificates configured
- [ ] Analytics tracking added
- [ ] Error monitoring setup
- [ ] Backup procedures in place
- [ ] Documentation complete

### Demo Script Highlights:
1. **Opening**: AI-powered CRM that transforms sales
2. **Lead Scoring**: Real-time AI qualification
3. **Chatbot**: 24/7 lead capture and qualification
4. **Pipeline**: Visual deal management
5. **Health Scoring**: Predict and prevent churn
6. **Activity Tracking**: Know what prospects are doing
7. **ROI**: 2x sales velocity, 50% less manual work

This completes the frontend implementation following the 90/30 approach, focusing on essential features that create a compelling demo experience.