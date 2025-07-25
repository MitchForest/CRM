import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { 
  MessageCircle, 
  Save,
  Copy,
  Check,
  Eye
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { 
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { Badge } from '@/components/ui/badge'
import { useToast } from '@/components/ui/use-toast'
import { ChatWidget } from '@/components/features/chatbot/ChatWidget'
import CopyToClipboard from 'react-copy-to-clipboard'

interface ChatbotConfig {
  enabled: boolean
  greeting: string
  offlineMessage: string
  position: 'bottom-right' | 'bottom-left'
  primaryColor: string
  fontFamily: string
  autoPopupDelay: number
  leadCaptureThreshold: number
  departments: string[]
  customFields: Array<{
    name: string
    type: 'text' | 'email' | 'tel' | 'select'
    required: boolean
    label: string
  }>
  knowledgeBaseEnabled: boolean
  maxSuggestions: number
  notificationEmail: string
  businessHours: {
    enabled: boolean
    timezone: string
    schedule: Record<string, { start: string; end: string }>
  }
}

export function ChatbotSettings() {
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const [showPreview, setShowPreview] = useState(false)
  const [copiedTab, setCopiedTab] = useState<string | null>(null)

  const [config, setConfig] = useState<ChatbotConfig>({
    enabled: true,
    greeting: "Hi! I'm here to help. What can I assist you with today?",
    offlineMessage: "We're currently offline. Please leave a message and we'll get back to you.",
    position: 'bottom-right',
    primaryColor: '#3b82f6',
    fontFamily: 'Inter',
    autoPopupDelay: 5000,
    leadCaptureThreshold: 60,
    departments: ['sales', 'support'],
    customFields: [],
    knowledgeBaseEnabled: true,
    maxSuggestions: 3,
    notificationEmail: '',
    businessHours: {
      enabled: false,
      timezone: 'America/New_York',
      schedule: {
        monday: { start: '09:00', end: '17:00' },
        tuesday: { start: '09:00', end: '17:00' },
        wednesday: { start: '09:00', end: '17:00' },
        thursday: { start: '09:00', end: '17:00' },
        friday: { start: '09:00', end: '17:00' },
        saturday: { start: '10:00', end: '14:00' },
        sunday: { start: '00:00', end: '00:00' }
      }
    }
  })

  const { data } = useQuery({
    queryKey: ['chatbot-config'],
    queryFn: async () => {
      // Mock data for now since backend endpoint doesn't exist
      return {
        enabled: true,
        greeting: "Hi! I'm here to help. What can I assist you with today?",
        offlineMessage: "We're currently offline. Please leave a message and we'll get back to you.",
        position: 'bottom-right',
        primaryColor: '#3b82f6',
        fontFamily: 'Inter',
        autoPopupDelay: 5000,
        leadCaptureThreshold: 60,
        departments: ['sales', 'support'],
        customFields: [],
        knowledgeBaseEnabled: true,
        maxSuggestions: 3,
        notificationEmail: '',
        businessHours: {
          enabled: false,
          timezone: 'America/New_York',
          schedule: {
            monday: { start: '09:00', end: '17:00' },
            tuesday: { start: '09:00', end: '17:00' },
            wednesday: { start: '09:00', end: '17:00' },
            thursday: { start: '09:00', end: '17:00' },
            friday: { start: '09:00', end: '17:00' },
            saturday: { start: '10:00', end: '14:00' },
            sunday: { start: '00:00', end: '00:00' }
          }
        }
      } as ChatbotConfig
    }
  })

  useEffect(() => {
    if (data) {
      setConfig(data)
    }
  }, [data])

  const saveMutation = useMutation({
    mutationFn: async (data: ChatbotConfig) => {
      // Mock save for now since backend endpoint doesn't exist
      console.log('Saving chatbot config:', data);
      return { success: true, data }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['chatbot-config'] })
      toast({
        title: 'Settings saved',
        description: 'Chatbot configuration has been updated.',
      })
    },
    onError: () => {
      toast({
        title: 'Save failed',
        description: 'Unable to save chatbot settings. Please try again.',
        variant: 'destructive',
      })
    }
  })

  const handleCopy = (tab: string) => {
    setCopiedTab(tab)
    toast({
      title: 'Copied to clipboard',
      description: 'The embed code has been copied to your clipboard.',
    })
    setTimeout(() => setCopiedTab(null), 2000)
  }

  const basicEmbedCode = `<!-- CRM Chat Widget -->
<script>
  (function(w,d,s,l,i){
    w[l]=w[l]||[];
    w[l].push({'chat.start': new Date().getTime(), site: i});
    var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s);j.async=true;
    j.src='${window.location.origin}/js/chat-widget.js';
    f.parentNode.insertBefore(j,f);
  })(window,document,'script','crmChat','YOUR_SITE_ID');
</script>`

  const advancedEmbedCode = `<!-- CRM Chat Widget with Custom Configuration -->
<script>
  (function(w,d,s,l,i){
    w[l]=w[l]||[];
    w[l].push({
      'chat.start': new Date().getTime(),
      site: i,
      config: {
        position: '${config.position}',
        primaryColor: '${config.primaryColor}',
        greeting: '${config.greeting}',
        offlineMessage: '${config.offlineMessage}',
        departments: ${JSON.stringify(config.departments)},
        knowledgeBaseEnabled: ${config.knowledgeBaseEnabled}
      }
    });
    var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s);j.async=true;
    j.src='${window.location.origin}/js/chat-widget.js';
    f.parentNode.insertBefore(j,f);
  })(window,document,'script','crmChat','YOUR_SITE_ID');
</script>`

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold flex items-center gap-2">
          <MessageCircle className="h-6 w-6" />
          Chatbot Settings
        </h1>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            onClick={() => setShowPreview(!showPreview)}
          >
            <Eye className="mr-2 h-4 w-4" />
            {showPreview ? 'Hide' : 'Show'} Preview
          </Button>
          <Button
            onClick={() => saveMutation.mutate(config)}
            disabled={saveMutation.isPending}
          >
            <Save className="mr-2 h-4 w-4" />
            Save Settings
          </Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-6">
          <Tabs defaultValue="general">
            <TabsList className="grid w-full grid-cols-4">
              <TabsTrigger value="general">General</TabsTrigger>
              <TabsTrigger value="appearance">Appearance</TabsTrigger>
              <TabsTrigger value="behavior">Behavior</TabsTrigger>
              <TabsTrigger value="embed">Embed Code</TabsTrigger>
            </TabsList>

            <TabsContent value="general" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Basic Settings</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label htmlFor="enabled">Enable Chatbot</Label>
                      <p className="text-sm text-muted-foreground">
                        Turn the chatbot on or off for all visitors
                      </p>
                    </div>
                    <Switch
                      id="enabled"
                      checked={config.enabled}
                      onCheckedChange={(checked) => 
                        setConfig({ ...config, enabled: checked })
                      }
                    />
                  </div>

                  <Separator />

                  <div className="space-y-2">
                    <Label htmlFor="greeting">Welcome Message</Label>
                    <Textarea
                      id="greeting"
                      value={config.greeting}
                      onChange={(e) => 
                        setConfig({ ...config, greeting: e.target.value })
                      }
                      placeholder="Enter your greeting message..."
                      rows={3}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="offline">Offline Message</Label>
                    <Textarea
                      id="offline"
                      value={config.offlineMessage}
                      onChange={(e) => 
                        setConfig({ ...config, offlineMessage: e.target.value })
                      }
                      placeholder="Message shown when agents are offline..."
                      rows={3}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="notification">Notification Email</Label>
                    <Input
                      id="notification"
                      type="email"
                      value={config.notificationEmail}
                      onChange={(e) => 
                        setConfig({ ...config, notificationEmail: e.target.value })
                      }
                      placeholder="notifications@example.com"
                    />
                    <p className="text-sm text-muted-foreground">
                      Receive alerts when new conversations start
                    </p>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Knowledge Base Integration</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label htmlFor="kb-enabled">Enable KB Suggestions</Label>
                      <p className="text-sm text-muted-foreground">
                        Show relevant articles during conversations
                      </p>
                    </div>
                    <Switch
                      id="kb-enabled"
                      checked={config.knowledgeBaseEnabled}
                      onCheckedChange={(checked) => 
                        setConfig({ ...config, knowledgeBaseEnabled: checked })
                      }
                    />
                  </div>

                  {config.knowledgeBaseEnabled && (
                    <div className="space-y-2">
                      <Label htmlFor="max-suggestions">Max Suggestions</Label>
                      <Select
                        value={config.maxSuggestions.toString()}
                        onValueChange={(value) => 
                          setConfig({ ...config, maxSuggestions: parseInt(value) })
                        }
                      >
                        <SelectTrigger id="max-suggestions">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="1">1 article</SelectItem>
                          <SelectItem value="3">3 articles</SelectItem>
                          <SelectItem value="5">5 articles</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="appearance" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Widget Appearance</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="position">Position</Label>
                    <Select
                      value={config.position}
                      onValueChange={(value: 'bottom-right' | 'bottom-left') => 
                        setConfig({ ...config, position: value })
                      }
                    >
                      <SelectTrigger id="position">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="bottom-right">Bottom Right</SelectItem>
                        <SelectItem value="bottom-left">Bottom Left</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="color">Primary Color</Label>
                    <div className="flex gap-2">
                      <Input
                        id="color"
                        type="color"
                        value={config.primaryColor}
                        onChange={(e) => 
                          setConfig({ ...config, primaryColor: e.target.value })
                        }
                        className="w-20 h-10"
                      />
                      <Input
                        value={config.primaryColor}
                        onChange={(e) => 
                          setConfig({ ...config, primaryColor: e.target.value })
                        }
                        placeholder="#3b82f6"
                      />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="font">Font Family</Label>
                    <Select
                      value={config.fontFamily}
                      onValueChange={(value) => 
                        setConfig({ ...config, fontFamily: value })
                      }
                    >
                      <SelectTrigger id="font">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="Inter">Inter</SelectItem>
                        <SelectItem value="Roboto">Roboto</SelectItem>
                        <SelectItem value="Open Sans">Open Sans</SelectItem>
                        <SelectItem value="Lato">Lato</SelectItem>
                        <SelectItem value="system-ui">System UI</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="behavior" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Behavior Settings</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="popup-delay">Auto Popup Delay (ms)</Label>
                    <Input
                      id="popup-delay"
                      type="number"
                      value={config.autoPopupDelay}
                      onChange={(e) => 
                        setConfig({ ...config, autoPopupDelay: parseInt(e.target.value) || 0 })
                      }
                      placeholder="5000"
                    />
                    <p className="text-sm text-muted-foreground">
                      0 to disable auto popup
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="lead-threshold">Lead Capture Score Threshold</Label>
                    <Input
                      id="lead-threshold"
                      type="number"
                      value={config.leadCaptureThreshold}
                      onChange={(e) => 
                        setConfig({ ...config, leadCaptureThreshold: parseInt(e.target.value) || 0 })
                      }
                      placeholder="60"
                      min="0"
                      max="100"
                    />
                    <p className="text-sm text-muted-foreground">
                      Capture lead info when AI confidence is above this score
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label>Departments</Label>
                    <div className="flex flex-wrap gap-2">
                      {config.departments.map((dept, index) => (
                        <div key={index} className="flex items-center gap-1">
                          <Badge variant="secondary">{dept}</Badge>
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                              const newDepts = [...config.departments]
                              newDepts.splice(index, 1)
                              setConfig({ ...config, departments: newDepts })
                            }}
                          >
                            ×
                          </Button>
                        </div>
                      ))}
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          const dept = prompt('Enter department name:')
                          if (dept) {
                            setConfig({ 
                              ...config, 
                              departments: [...config.departments, dept] 
                            })
                          }
                        }}
                      >
                        Add Department
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Business Hours</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <div className="space-y-0.5">
                      <Label htmlFor="hours-enabled">Enable Business Hours</Label>
                      <p className="text-sm text-muted-foreground">
                        Show offline message outside business hours
                      </p>
                    </div>
                    <Switch
                      id="hours-enabled"
                      checked={config.businessHours.enabled}
                      onCheckedChange={(checked) => 
                        setConfig({ 
                          ...config, 
                          businessHours: { ...config.businessHours, enabled: checked }
                        })
                      }
                    />
                  </div>

                  {config.businessHours.enabled && (
                    <div className="space-y-2">
                      <Label htmlFor="timezone">Timezone</Label>
                      <Select
                        value={config.businessHours.timezone}
                        onValueChange={(value) => 
                          setConfig({ 
                            ...config, 
                            businessHours: { ...config.businessHours, timezone: value }
                          })
                        }
                      >
                        <SelectTrigger id="timezone">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="America/New_York">Eastern Time</SelectItem>
                          <SelectItem value="America/Chicago">Central Time</SelectItem>
                          <SelectItem value="America/Denver">Mountain Time</SelectItem>
                          <SelectItem value="America/Los_Angeles">Pacific Time</SelectItem>
                          <SelectItem value="Europe/London">London</SelectItem>
                          <SelectItem value="Europe/Paris">Paris</SelectItem>
                          <SelectItem value="Asia/Tokyo">Tokyo</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  )}
                </CardContent>
              </Card>
            </TabsContent>

            <TabsContent value="embed" className="space-y-4">
              <Card>
                <CardHeader>
                  <CardTitle>Installation</CardTitle>
                  <CardDescription>
                    Add the chat widget to your website
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <Tabs defaultValue="basic">
                    <TabsList className="grid w-full grid-cols-2">
                      <TabsTrigger value="basic">Basic</TabsTrigger>
                      <TabsTrigger value="advanced">Advanced</TabsTrigger>
                    </TabsList>

                    <TabsContent value="basic" className="space-y-4">
                      <p className="text-sm text-muted-foreground">
                        Add this code before the closing &lt;/body&gt; tag on your website.
                      </p>
                      <div className="relative">
                        <pre className="bg-muted p-4 rounded-md overflow-x-auto text-sm">
                          <code>{basicEmbedCode}</code>
                        </pre>
                        <CopyToClipboard text={basicEmbedCode} onCopy={() => handleCopy('basic')}>
                          <Button
                            size="sm"
                            variant="outline"
                            className="absolute top-2 right-2"
                          >
                            {copiedTab === 'basic' ? (
                              <Check className="h-4 w-4" />
                            ) : (
                              <Copy className="h-4 w-4" />
                            )}
                          </Button>
                        </CopyToClipboard>
                      </div>
                    </TabsContent>

                    <TabsContent value="advanced" className="space-y-4">
                      <p className="text-sm text-muted-foreground">
                        Includes your current configuration settings.
                      </p>
                      <div className="relative">
                        <pre className="bg-muted p-4 rounded-md overflow-x-auto text-sm">
                          <code>{advancedEmbedCode}</code>
                        </pre>
                        <CopyToClipboard text={advancedEmbedCode} onCopy={() => handleCopy('advanced')}>
                          <Button
                            size="sm"
                            variant="outline"
                            className="absolute top-2 right-2"
                          >
                            {copiedTab === 'advanced' ? (
                              <Check className="h-4 w-4" />
                            ) : (
                              <Copy className="h-4 w-4" />
                            )}
                          </Button>
                        </CopyToClipboard>
                      </div>
                    </TabsContent>
                  </Tabs>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        {/* Preview */}
        {showPreview && (
          <div className="lg:col-span-1">
            <Card>
              <CardHeader>
                <CardTitle>Live Preview</CardTitle>
                <CardDescription>
                  This shows how the chat widget will appear on your website
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="relative h-[600px] bg-gray-50 rounded-md overflow-hidden">
                  <div className="absolute inset-0 flex items-center justify-center text-muted-foreground">
                    Your website content
                  </div>
                  <ChatWidget position={config.position} />
                </div>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </div>
  )
}