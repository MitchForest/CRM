import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
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
  Shield,
  Clock,
  DollarSign,
  Sparkles,
  HelpCircle
} from 'lucide-react'
import { ChatWidget } from '@/components/features/chatbot/ChatWidget'
import { useEffect, useState } from 'react'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toast } from 'sonner'
import { apiClient } from '@/lib/api-client'

export function Homepage() {
  const [isVisible, setIsVisible] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [isSupportSubmitting, setIsSupportSubmitting] = useState(false)
  const [showSupportForm, setShowSupportForm] = useState(false)
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    company: '',
    phone: '',
    message: ''
  })
  const [supportFormData, setSupportFormData] = useState({
    name: '',
    email: '',
    subject: '',
    priority: 'medium',
    description: ''
  })

  useEffect(() => {
    setIsVisible(true)
  }, [])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSubmitting(true)
    
    try {
      const response = await apiClient.createLead({
        firstName: formData.firstName,
        lastName: formData.lastName,
        email: formData.email,
        company: formData.company,
        phone: formData.phone,
        description: formData.message,
        source: 'Contact Form',
        status: 'New'
      })
      
      if (response.success) {
        toast.success('Thank you! We\'ll be in touch soon.')
        // Reset form
        setFormData({
          firstName: '',
          lastName: '',
          email: '',
          company: '',
          phone: '',
          message: ''
        })
        
        // Trigger AI scoring in background
        if (response.data?.id) {
          apiClient.customPost(`/leads/${response.data.id}/ai-score`).catch(() => {
            // Silent fail - scoring happens in background
          })
        }
      }
    } catch {
      toast.error('Failed to submit form. Please try again.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleSupportSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsSupportSubmitting(true)
    
    try {
      const response = await apiClient.createCase({
        name: supportFormData.subject,
        description: supportFormData.description,
        priority: supportFormData.priority,
        type: 'technical',
        status: 'New',
        accountName: supportFormData.name,
        contactEmail: supportFormData.email
      })
      
      if (response.success) {
        toast.success('Support ticket created! We\'ll get back to you within 24 hours.')
        // Reset form and hide it
        setSupportFormData({
          name: '',
          email: '',
          subject: '',
          priority: 'medium',
          description: ''
        })
        setShowSupportForm(false)
      }
    } catch {
      toast.error('Failed to create support ticket. Please try again.')
    } finally {
      setIsSupportSubmitting(false)
    }
  }

  const features = [
    {
      icon: Brain,
      title: 'AI Lead Scoring',
      description: 'Automatically qualify leads with GPT-4 powered scoring that actually works',
      color: 'text-purple-600',
      bgColor: 'bg-purple-100',
    },
    {
      icon: MessageCircle,
      title: 'Intelligent Chatbot',
      description: 'Capture and qualify leads 24/7 with AI that understands your business',
      color: 'text-blue-600',
      bgColor: 'bg-blue-100',
    },
    {
      icon: Activity,
      title: 'Real-Time Tracking',
      description: 'See what prospects are doing on your site in real-time',
      color: 'text-orange-600',
      bgColor: 'bg-orange-100',
    },
    {
      icon: BarChart3,
      title: 'Visual Pipeline',
      description: 'Drag-and-drop deals through stages with AI insights at every step',
      color: 'text-indigo-600',
      bgColor: 'bg-indigo-100',
    },
    {
      icon: FileText,
      title: 'Smart Forms',
      description: 'Build forms that capture leads and trigger AI scoring automatically',
      color: 'text-green-600',
      bgColor: 'bg-green-100',
    },
    {
      icon: Shield,
      title: 'Self-Hosted',
      description: 'Your data, your servers, complete control and security',
      color: 'text-red-600',
      bgColor: 'bg-red-100',
    },
  ]

  const benefits = [
    '90% faster lead qualification',
    '2.5x improvement in sales velocity',
    '50% reduction in manual data entry',
    'Complete data ownership',
  ]

  const stats = [
    { value: '85%', label: 'Lead Score Accuracy' },
    { value: '2.5x', label: 'Faster Sales Cycle' },
    { value: '47%', label: 'More Qualified Leads' },
    { value: '100%', label: 'Data Ownership' },
  ]

  return (
    <div>
      {/* Hero Section */}
      <section className="relative overflow-hidden bg-gradient-to-b from-gray-50 to-white">
        <div className="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8 lg:py-32">
          <div 
            className={`text-center transition-all duration-1000 ${
              isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'
            }`}
          >
            <Badge className="mb-4" variant="outline">
              <Zap className="mr-1 h-3 w-3" />
              Powered by GPT-4
            </Badge>
            <h1 className="text-5xl font-bold tracking-tight text-gray-900 sm:text-6xl">
              The CRM That Actually
              <span className="block text-primary mt-2">Sells For You</span>
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-gray-600">
              AI that qualifies leads, schedules demos, and tells you exactly who to call next. 
              Self-hosted, secure, and ready in 30 minutes.
            </p>
            <div className="mt-10 flex items-center justify-center gap-x-6">
              <Button size="lg" asChild>
                <Link to="/demo">
                  See AI in Action
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Link>
              </Button>
              <Button size="lg" variant="outline" asChild>
                <Link to="/get-started">Start Free (Self-Hosted)</Link>
              </Button>
            </div>
            <p className="mt-4 text-sm text-gray-500">
              No credit card required • Set up in 30 minutes • Your data stays yours
            </p>
          </div>

          {/* Demo Preview */}
          <div 
            className={`mt-16 transition-all duration-1000 delay-300 ${
              isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'
            }`}
          >
            <div className="relative rounded-xl shadow-2xl overflow-hidden border">
              <div className="bg-gradient-to-br from-primary/5 to-primary/10 p-8">
                <div className="bg-white rounded-lg shadow-lg p-6">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold">Live AI Demo</h3>
                    <Badge variant="secondary" className="animate-pulse">
                      <Sparkles className="mr-1 h-3 w-3" />
                      AI Active
                    </Badge>
                  </div>
                  <p className="text-gray-600 mb-4">
                    👈 Try the chatbot in the bottom right corner. Ask about our features, pricing, or schedule a demo!
                  </p>
                  <div className="grid grid-cols-3 gap-4 mt-6">
                    <div className="text-center p-4 bg-gray-50 rounded-lg">
                      <div className="text-2xl font-bold text-primary">92</div>
                      <div className="text-sm text-gray-600">AI Score</div>
                    </div>
                    <div className="text-center p-4 bg-gray-50 rounded-lg">
                      <div className="text-2xl font-bold text-green-600">High</div>
                      <div className="text-sm text-gray-600">Lead Quality</div>
                    </div>
                    <div className="text-center p-4 bg-gray-50 rounded-lg">
                      <div className="text-2xl font-bold text-blue-600">Ready</div>
                      <div className="text-sm text-gray-600">Sales Status</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Stats Section */}
      <section className="bg-primary text-white py-16">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
            {stats.map((stat, index) => (
              <div 
                key={stat.label}
                className={`text-center transition-all duration-500 delay-${index * 100} ${
                  isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'
                }`}
              >
                <div className="text-4xl font-bold">{stat.value}</div>
                <div className="mt-2 text-sm opacity-90">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section id="features" className="py-24">
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
              const Icon = feature.icon
              return (
                <Card 
                  key={feature.title}
                  className={`h-full hover:shadow-lg transition-all duration-300 delay-${index * 50} ${
                    isVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'
                  }`}
                >
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
              )
            })}
          </div>
        </div>
      </section>

      {/* How It Works Section */}
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
                  <Link to="/demo">Schedule Live Demo</Link>
                </Button>
              </div>
            </div>
            <div className="relative">
              <div className="aspect-video rounded-lg bg-gradient-to-br from-primary/10 to-primary/20 p-8">
                <div className="bg-white rounded-lg shadow-xl p-6 h-full flex items-center justify-center">
                  <div className="text-center">
                    <Brain className="h-16 w-16 text-primary mx-auto mb-4" />
                    <h3 className="text-xl font-semibold mb-2">AI Lead Scoring</h3>
                    <p className="text-gray-600">Real-time qualification based on 20+ signals</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Social Proof */}
      <section className="py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
              Trusted by 500+ Sales Teams
            </h2>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
            {[
              { icon: Users, label: '10K+ Users' },
              { icon: TrendingUp, label: '2.5M+ Leads Scored' },
              { icon: Clock, label: '99.9% Uptime' },
              { icon: DollarSign, label: '$50M+ Pipeline' },
            ].map((item) => {
              const Icon = item.icon
              return (
                <div key={item.label} className="text-center">
                  <Icon className="h-8 w-8 text-primary mx-auto mb-2" />
                  <div className="font-semibold">{item.label}</div>
                </div>
              )
            })}
          </div>
        </div>
      </section>

      {/* Lead Capture Form */}
      <section className="py-16 bg-gray-50">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="mx-auto max-w-2xl">
            <div className="text-center mb-12">
              <h2 className="text-3xl font-bold mb-4">Get Started Today</h2>
              <p className="text-lg text-gray-600">
                See how AI can transform your sales process. Get a personalized demo.
              </p>
            </div>
            
            <Card>
              <CardContent className="p-6">
                <form onSubmit={handleSubmit} className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="firstName">First Name *</Label>
                      <Input
                        id="firstName"
                        required
                        value={formData.firstName}
                        onChange={(e) => setFormData({ ...formData, firstName: e.target.value })}
                      />
                    </div>
                    <div>
                      <Label htmlFor="lastName">Last Name *</Label>
                      <Input
                        id="lastName"
                        required
                        value={formData.lastName}
                        onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
                      />
                    </div>
                  </div>
                  
                  <div>
                    <Label htmlFor="email">Email *</Label>
                    <Input
                      id="email"
                      type="email"
                      required
                      value={formData.email}
                      onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                    />
                  </div>
                  
                  <div>
                    <Label htmlFor="company">Company *</Label>
                    <Input
                      id="company"
                      required
                      value={formData.company}
                      onChange={(e) => setFormData({ ...formData, company: e.target.value })}
                    />
                  </div>
                  
                  <div>
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                      id="phone"
                      type="tel"
                      value={formData.phone}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    />
                  </div>
                  
                  <div>
                    <Label htmlFor="message">Message</Label>
                    <Textarea
                      id="message"
                      rows={4}
                      placeholder="Tell us about your sales challenges..."
                      value={formData.message}
                      onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                    />
                  </div>
                  
                  <Button 
                    type="submit" 
                    className="w-full" 
                    size="lg"
                    disabled={isSubmitting}
                  >
                    {isSubmitting ? 'Submitting...' : 'Get Your Free Demo'}
                  </Button>
                  
                  <p className="text-xs text-center text-gray-500">
                    By submitting this form, you agree to our privacy policy.
                    Your data stays on your own servers.
                  </p>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="bg-primary py-16">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-3xl font-bold text-white">
            Ready to 2.5x your sales velocity?
          </h2>
          <p className="mt-4 text-lg text-white/90">
            Join hundreds of teams using AI to close more deals, faster
          </p>
          <div className="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            <Button size="lg" variant="secondary" asChild>
              <Link to="/demo">Get a Demo</Link>
            </Button>
            <Button size="lg" variant="outline" className="bg-white/10 text-white border-white/20 hover:bg-white/20" asChild>
              <Link to="/get-started">Start Free (Self-Hosted)</Link>
            </Button>
          </div>
        </div>
      </section>

      {/* Quick Support Section */}
      <section className="bg-gray-50 py-12">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <h2 className="text-2xl font-bold text-gray-900">Need Help?</h2>
            <p className="mt-2 text-gray-600">We're here to support you every step of the way</p>
            <div className="mt-6 flex flex-col sm:flex-row gap-4 justify-center">
              <Button variant="outline" asChild>
                <Link to="/support">
                  <HelpCircle className="mr-2 h-4 w-4" />
                  Visit Support Center
                </Link>
              </Button>
              <Button variant="outline" onClick={() => {
                // Trigger chat with support intent
                const event = new CustomEvent('chat-open', { detail: { message: 'I need help with an issue' }});
                window.dispatchEvent(event);
              }}>
                <MessageCircle className="mr-2 h-4 w-4" />
                Chat with Support
              </Button>
              <Button variant="outline" onClick={() => setShowSupportForm(!showSupportForm)}>
                <FileText className="mr-2 h-4 w-4" />
                Quick Support Ticket
              </Button>
            </div>
          </div>

          {/* Embeddable Support Form */}
          {showSupportForm && (
            <div className="mt-8 mx-auto max-w-2xl">
              <Card>
                <CardHeader>
                  <CardTitle>Quick Support Request</CardTitle>
                </CardHeader>
                <CardContent>
                  <form onSubmit={handleSupportSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="support-name">Your Name *</Label>
                        <Input
                          id="support-name"
                          required
                          value={supportFormData.name}
                          onChange={(e) => setSupportFormData({ ...supportFormData, name: e.target.value })}
                        />
                      </div>
                      <div>
                        <Label htmlFor="support-email">Email *</Label>
                        <Input
                          id="support-email"
                          type="email"
                          required
                          value={supportFormData.email}
                          onChange={(e) => setSupportFormData({ ...supportFormData, email: e.target.value })}
                        />
                      </div>
                    </div>
                    
                    <div>
                      <Label htmlFor="support-subject">Subject *</Label>
                      <Input
                        id="support-subject"
                        required
                        value={supportFormData.subject}
                        onChange={(e) => setSupportFormData({ ...supportFormData, subject: e.target.value })}
                        placeholder="Brief description of your issue"
                      />
                    </div>
                    
                    <div>
                      <Label htmlFor="support-priority">Priority</Label>
                      <Select 
                        value={supportFormData.priority} 
                        onValueChange={(value) => setSupportFormData({ ...supportFormData, priority: value })}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="low">Low</SelectItem>
                          <SelectItem value="medium">Medium</SelectItem>
                          <SelectItem value="high">High</SelectItem>
                          <SelectItem value="critical">Critical</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    
                    <div>
                      <Label htmlFor="support-description">Description *</Label>
                      <Textarea
                        id="support-description"
                        required
                        rows={4}
                        value={supportFormData.description}
                        onChange={(e) => setSupportFormData({ ...supportFormData, description: e.target.value })}
                        placeholder="Please describe your issue..."
                      />
                    </div>
                    
                    <div className="flex gap-4">
                      <Button 
                        type="submit" 
                        disabled={isSupportSubmitting}
                        className="flex-1"
                      >
                        {isSupportSubmitting ? 'Submitting...' : 'Submit Ticket'}
                      </Button>
                      <Button 
                        type="button" 
                        variant="outline"
                        onClick={() => setShowSupportForm(false)}
                      >
                        Cancel
                      </Button>
                    </div>
                  </form>
                </CardContent>
              </Card>
            </div>
          )}
        </div>
      </section>

      {/* Chat Widget - Already handles tracking and lead capture */}
      <ChatWidget position="bottom-right" />
    </div>
  )
}