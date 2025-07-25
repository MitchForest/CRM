import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { CheckCircle, Server, Shield, Zap, GitBranch, Users, Headphones } from 'lucide-react'

export function Pricing() {
  const features = [
    { icon: Zap, text: 'AI Lead Scoring with GPT-4' },
    { icon: Users, text: 'Unlimited Users' },
    { icon: Server, text: 'Self-Hosted on Your Servers' },
    { icon: Shield, text: 'Complete Data Ownership' },
    { icon: GitBranch, text: 'Open Source Codebase' },
    { icon: Headphones, text: 'Community Support' },
  ]

  const allFeatures = [
    'AI-Powered Lead Scoring',
    'Intelligent Chatbot',
    'Real-Time Activity Tracking',
    'Visual Pipeline Management',
    'Smart Form Builder',
    'Knowledge Base System',
    'Customer Health Scoring',
    'Meeting Scheduler',
    'Email Integration',
    'Customizable Dashboards',
    'Role-Based Access Control',
    'REST API Access',
    'Webhook Support',
    'Mobile Responsive',
    'Multi-Language Support',
    'And much more...'
  ]

  return (
    <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="text-center mb-16">
          <Badge className="mb-4" variant="outline">
            Simple Pricing
          </Badge>
          <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
            One Price: Free Forever
          </h1>
          <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
            Self-host on your servers and get all features without any subscription fees. 
            Your data, your control, no vendor lock-in.
          </p>
        </div>

        {/* Single Pricing Card */}
        <div className="max-w-lg mx-auto">
          <Card className="relative overflow-hidden border-2 border-primary shadow-xl">
            <div className="absolute top-0 right-0 bg-primary text-white px-3 py-1 text-sm font-medium">
              Self-Hosted
            </div>
            <CardHeader className="text-center pb-8 pt-12">
              <CardTitle className="text-3xl">AI CRM Platform</CardTitle>
              <CardDescription className="mt-2">Everything you need to transform your sales</CardDescription>
              <div className="mt-8">
                <span className="text-5xl font-bold">$0</span>
                <span className="text-gray-600 ml-2">forever</span>
              </div>
              <p className="text-sm text-gray-500 mt-2">No hidden fees • No subscriptions • No limits</p>
            </CardHeader>
            <CardContent>
              <div className="space-y-4 mb-8">
                {features.map((feature) => {
                  const Icon = feature.icon
                  return (
                    <div key={feature.text} className="flex items-center gap-3">
                      <div className="flex-shrink-0 w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                        <Icon className="h-4 w-4 text-primary" />
                      </div>
                      <span className="text-gray-700">{feature.text}</span>
                    </div>
                  )
                })}
              </div>
              
              <Button size="lg" className="w-full" asChild>
                <Link to="/get-started">
                  Get Started Now
                </Link>
              </Button>
              
              <p className="text-center text-sm text-gray-500 mt-4">
                Setup takes ~30 minutes with Docker
              </p>
            </CardContent>
          </Card>
        </div>

        {/* All Features Section */}
        <div className="mt-24">
          <h2 className="text-2xl font-bold text-center mb-12">Everything Included</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-w-4xl mx-auto">
            {allFeatures.map((feature) => (
              <div key={feature} className="flex items-center gap-2">
                <CheckCircle className="h-5 w-5 text-green-500 flex-shrink-0" />
                <span className="text-gray-700">{feature}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Comparison Section */}
        <div className="mt-24 max-w-4xl mx-auto">
          <h2 className="text-2xl font-bold text-center mb-12">Why Self-Hosted?</h2>
          <div className="grid md:grid-cols-2 gap-8">
            <Card>
              <CardHeader>
                <CardTitle className="text-xl text-red-600">Traditional SaaS CRM</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-start gap-2">
                  <span className="text-red-500">✗</span>
                  <span className="text-gray-600">$99-299/user/month</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-red-500">✗</span>
                  <span className="text-gray-600">Your data on their servers</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-red-500">✗</span>
                  <span className="text-gray-600">Vendor lock-in</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-red-500">✗</span>
                  <span className="text-gray-600">Limited customization</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-red-500">✗</span>
                  <span className="text-gray-600">Usage limits and overage fees</span>
                </div>
              </CardContent>
            </Card>
            
            <Card className="border-primary">
              <CardHeader>
                <CardTitle className="text-xl text-primary">AI CRM (Self-Hosted)</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="flex items-start gap-2">
                  <span className="text-green-500">✓</span>
                  <span className="text-gray-700 font-medium">Free forever</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-green-500">✓</span>
                  <span className="text-gray-700 font-medium">Your data stays yours</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-green-500">✓</span>
                  <span className="text-gray-700 font-medium">No vendor lock-in</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-green-500">✓</span>
                  <span className="text-gray-700 font-medium">Fully customizable</span>
                </div>
                <div className="flex items-start gap-2">
                  <span className="text-green-500">✓</span>
                  <span className="text-gray-700 font-medium">Unlimited everything</span>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        {/* FAQ Section */}
        <div className="mt-24 max-w-3xl mx-auto">
          <h2 className="text-2xl font-bold text-center mb-12">Common Questions</h2>
          <div className="space-y-8">
            <div>
              <h3 className="font-semibold text-lg mb-2">What are the server requirements?</h3>
              <p className="text-gray-600">
                A server with 4GB RAM, 2 CPU cores, and 20GB storage is sufficient for most teams. 
                We provide Docker images that work on any Linux server.
              </p>
            </div>
            <div>
              <h3 className="font-semibold text-lg mb-2">Do I need to pay for OpenAI?</h3>
              <p className="text-gray-600">
                Yes, the AI features use OpenAI's GPT-4 API which requires your own API key. 
                Typical usage is $10-50/month depending on volume.
              </p>
            </div>
            <div>
              <h3 className="font-semibold text-lg mb-2">How hard is it to set up?</h3>
              <p className="text-gray-600">
                With our Docker setup, most teams are up and running in 30 minutes. 
                We provide step-by-step instructions and the community is very helpful.
              </p>
            </div>
            <div>
              <h3 className="font-semibold text-lg mb-2">Can I customize it?</h3>
              <p className="text-gray-600">
                Absolutely! The entire codebase is open source. You can modify anything, 
                add custom features, or integrate with your existing tools.
              </p>
            </div>
          </div>
        </div>

        {/* CTA */}
        <div className="mt-24 text-center">
          <h2 className="text-3xl font-bold mb-4">Ready to Get Started?</h2>
          <p className="text-lg text-gray-600 mb-8">
            Join hundreds of teams who own their CRM and their data
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Button size="lg" asChild>
              <Link to="/get-started">Start Installation</Link>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <Link to="/demo">See Live Demo First</Link>
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}