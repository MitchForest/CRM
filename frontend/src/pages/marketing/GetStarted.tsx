import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { 
  Terminal, 
  CheckCircle, 
  Copy, 
  ExternalLink,
  Server,
  Key,
  Rocket,
  AlertCircle,
  Clock,
  Users
} from 'lucide-react'

export function GetStarted() {
  const [copiedStep, setCopiedStep] = useState<string | null>(null)

  const copyToClipboard = (text: string, step: string) => {
    navigator.clipboard.writeText(text)
    setCopiedStep(step)
    setTimeout(() => setCopiedStep(null), 2000)
  }

  const requirements = [
    { icon: Server, text: 'Linux server (Ubuntu 20.04+ recommended)' },
    { icon: Terminal, text: 'Docker & Docker Compose installed' },
    { icon: Key, text: 'OpenAI API key for AI features' },
    { icon: Clock, text: '30 minutes for initial setup' },
  ]

  const steps = [
    {
      title: 'Clone the Repository',
      description: 'Get the AI CRM codebase',
      command: 'git clone https://github.com/yourusername/ai-crm.git\ncd ai-crm',
    },
    {
      title: 'Configure Environment',
      description: 'Set up your environment variables',
      command: 'cp .env.example .env\nnano .env',
      note: 'Add your OpenAI API key and customize settings',
    },
    {
      title: 'Start with Docker',
      description: 'Launch all services with one command',
      command: 'docker-compose up -d',
      note: 'This will download images and start MySQL, Redis, and the CRM',
    },
    {
      title: 'Run Initial Setup',
      description: 'Create database and admin user',
      command: 'docker exec -it ai-crm-app php install.php',
      note: 'Follow the prompts to create your admin account',
    },
    {
      title: 'Access Your CRM',
      description: 'Open your browser and start using AI CRM',
      command: 'http://your-server-ip',
      note: 'Default login: admin / password (change immediately!)',
    },
  ]

  return (
    <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white py-24">
      <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="text-center mb-16">
          <Badge className="mb-4" variant="outline">
            Quick Start Guide
          </Badge>
          <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
            Get AI CRM Running in 30 Minutes
          </h1>
          <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
            Follow these simple steps to self-host your own AI-powered CRM
          </p>
        </div>

        {/* Requirements */}
        <Card className="mb-12">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <AlertCircle className="h-5 w-5 text-amber-500" />
              Before You Start
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid sm:grid-cols-2 gap-4">
              {requirements.map((req) => {
                const Icon = req.icon
                return (
                  <div key={req.text} className="flex items-center gap-3">
                    <Icon className="h-5 w-5 text-gray-500 flex-shrink-0" />
                    <span className="text-gray-700">{req.text}</span>
                  </div>
                )
              })}
            </div>
          </CardContent>
        </Card>

        {/* Installation Method Tabs */}
        <Tabs defaultValue="docker" className="mb-12">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="docker">Docker (Recommended)</TabsTrigger>
            <TabsTrigger value="manual">Manual Installation</TabsTrigger>
          </TabsList>

          {/* Docker Installation */}
          <TabsContent value="docker" className="space-y-6">
            {steps.map((step, index) => (
              <Card key={step.title}>
                <CardHeader>
                  <CardTitle className="flex items-center justify-between">
                    <span className="flex items-center gap-3">
                      <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-white text-sm font-medium">
                        {index + 1}
                      </span>
                      {step.title}
                    </span>
                  </CardTitle>
                  <p className="text-sm text-gray-600 mt-1">{step.description}</p>
                </CardHeader>
                <CardContent>
                  <div className="relative">
                    <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto">
                      <code>{step.command}</code>
                    </pre>
                    <Button
                      size="sm"
                      variant="secondary"
                      className="absolute top-2 right-2"
                      onClick={() => copyToClipboard(step.command, step.title)}
                    >
                      {copiedStep === step.title ? (
                        <>
                          <CheckCircle className="h-4 w-4 mr-1" />
                          Copied
                        </>
                      ) : (
                        <>
                          <Copy className="h-4 w-4 mr-1" />
                          Copy
                        </>
                      )}
                    </Button>
                  </div>
                  {step.note && (
                    <p className="text-sm text-gray-600 mt-3 flex items-start gap-2">
                      <AlertCircle className="h-4 w-4 text-amber-500 flex-shrink-0 mt-0.5" />
                      {step.note}
                    </p>
                  )}
                </CardContent>
              </Card>
            ))}

            {/* Quick Docker Compose */}
            <Card className="border-primary">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Rocket className="h-5 w-5 text-primary" />
                  One-Liner Installation
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-gray-600 mb-3">
                  For the brave: Install everything with a single command
                </p>
                <div className="relative">
                  <pre className="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm">
                    <code>curl -sSL https://get.ai-crm.com | bash</code>
                  </pre>
                  <Button
                    size="sm"
                    variant="secondary"
                    className="absolute top-2 right-2"
                    onClick={() => copyToClipboard('curl -sSL https://get.ai-crm.com | bash', 'oneliner')}
                  >
                    {copiedStep === 'oneliner' ? (
                      <>
                        <CheckCircle className="h-4 w-4 mr-1" />
                        Copied
                      </>
                    ) : (
                      <>
                        <Copy className="h-4 w-4 mr-1" />
                        Copy
                      </>
                    )}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Manual Installation */}
          <TabsContent value="manual">
            <Card>
              <CardHeader>
                <CardTitle>Manual Installation</CardTitle>
              </CardHeader>
              <CardContent className="prose prose-sm max-w-none">
                <p>For advanced users who want more control over the installation:</p>
                <ol className="space-y-4 mt-4">
                  <li>Install PHP 8.1+, MySQL 5.7+, Redis, and Node.js 18+</li>
                  <li>Configure your web server (Apache/Nginx) to point to the public directory</li>
                  <li>Set up the database and import the schema</li>
                  <li>Configure environment variables</li>
                  <li>Run composer install and npm install</li>
                  <li>Build the frontend assets</li>
                  <li>Set up cron jobs for background tasks</li>
                </ol>
                <p className="mt-4">
                  Detailed manual installation instructions are available in our{' '}
                  <a href="/kb/public/manual-installation" className="text-primary hover:underline">
                    documentation
                  </a>.
                </p>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        {/* Post Installation */}
        <Card>
          <CardHeader>
            <CardTitle>After Installation</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid sm:grid-cols-2 gap-6">
              <div>
                <h3 className="font-semibold mb-2">🔒 First Steps</h3>
                <ul className="space-y-2 text-sm text-gray-600">
                  <li>• Change the default admin password</li>
                  <li>• Configure your OpenAI API key</li>
                  <li>• Set up email settings for notifications</li>
                  <li>• Create user accounts for your team</li>
                </ul>
              </div>
              <div>
                <h3 className="font-semibold mb-2">🚀 Start Using AI CRM</h3>
                <ul className="space-y-2 text-sm text-gray-600">
                  <li>• Import your existing leads</li>
                  <li>• Create your first form</li>
                  <li>• Train the chatbot with your FAQ</li>
                  <li>• Set up activity tracking on your site</li>
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Help Section */}
        <div className="mt-12 text-center">
          <h2 className="text-2xl font-bold mb-6">Need Help?</h2>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Button variant="outline" asChild>
              <a href="/kb/public" className="inline-flex items-center">
                <ExternalLink className="h-4 w-4 mr-2" />
                Documentation
              </a>
            </Button>
            <Button variant="outline" asChild>
              <a href="https://github.com/yourusername/ai-crm/issues" className="inline-flex items-center">
                <Users className="h-4 w-4 mr-2" />
                Community Support
              </a>
            </Button>
            <Button asChild>
              <a href="/demo" className="inline-flex items-center">
                See Live Demo
              </a>
            </Button>
          </div>
        </div>

        {/* Success Message */}
        <Card className="mt-12 bg-green-50 border-green-200">
          <CardContent className="pt-6">
            <div className="flex items-start gap-4">
              <CheckCircle className="h-6 w-6 text-green-600 flex-shrink-0" />
              <div>
                <h3 className="font-semibold text-green-900">You're almost there!</h3>
                <p className="text-sm text-green-800 mt-1">
                  Most teams have their AI CRM up and running in under 30 minutes. 
                  The hardest part is choosing your admin password! 😊
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}