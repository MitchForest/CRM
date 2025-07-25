import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Calendar } from '@/components/ui/calendar'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { toast } from 'sonner'
import { 
  Calendar as CalendarIcon, 
  Clock, 
  CheckCircle, 
  User,
  Building,
  Mail,
  Phone,
  Sparkles,
  Users
} from 'lucide-react'
import { format, setHours, setMinutes, isBefore, isWeekend } from 'date-fns'
import { aiService } from '@/services/ai.service'
import { apiClient } from '@/lib/api-client'
import { cn } from '@/lib/utils'
import { ArrowRight } from 'lucide-react'

export function DemoBooking() {
  const [step, setStep] = useState<'info' | 'schedule' | 'confirm'>('info')
  const [isSubmitting, setIsSubmitting] = useState(false)
  
  // Form data
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    company: '',
    phone: '',
    companySize: '',
  })
  
  // Scheduling data
  const [selectedDate, setSelectedDate] = useState<Date | undefined>()
  const [selectedTime, setSelectedTime] = useState<string>('')
  
  // Available time slots (in production, fetch from API)
  const timeSlots = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
  ]

  const handleInfoSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    
    // Basic validation
    if (!formData.firstName || !formData.lastName || !formData.email || !formData.company) {
      toast.error('Please fill in all required fields')
      return
    }
    
    setStep('schedule')
  }

  const handleScheduleSubmit = async () => {
    if (!selectedDate || !selectedTime) {
      toast.error('Please select a date and time')
      return
    }

    setIsSubmitting(true)
    
    try {
      // Create lead first
      const leadResponse = await apiClient.createLead({
        firstName: formData.firstName,
        lastName: formData.lastName,
        email: formData.email,
        phone: formData.phone,
        company: formData.company,
        description: `Demo request - Company size: ${formData.companySize}`,
        source: 'Demo Request',
        status: 'New',
      })

      if (!leadResponse.success || !leadResponse.data) {
        throw new Error('Failed to create lead')
      }

      // Schedule meeting
      const [hours, minutes] = selectedTime.split(':').map(Number)
      const meetingDate = setHours(setMinutes(selectedDate!, minutes ?? 0), hours ?? 0)
      
      const meetingResponse = await apiClient.createMeeting({
        name: `Demo with ${formData.firstName} ${formData.lastName} - ${formData.company}`,
        startDate: format(meetingDate, "yyyy-MM-dd HH:mm:ss"),
        endDate: format(new Date(meetingDate.getTime() + 30 * 60000), "yyyy-MM-dd HH:mm:ss"),
        duration: 30,
        status: 'Planned',
        type: 'Virtual',
        description: `Demo meeting\nCompany size: ${formData.companySize}\nContact: ${formData.email}`,
        parentType: 'Leads',
        parentId: leadResponse.data.id,
        assignedUserId: '1' // Default to admin user
      })

      if (!meetingResponse.success) {
        throw new Error('Failed to schedule meeting')
      }

      // Trigger AI scoring for the lead
      await aiService.scoreLead(leadResponse.data.id!)
      
      setStep('confirm')
      toast.success('Demo scheduled successfully!')
      
    } catch (error) {
      console.error('Error scheduling demo:', error)
      toast.error('Failed to schedule demo. Please try again.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const isDateDisabled = (date: Date) => {
    return isBefore(date, new Date()) || isWeekend(date)
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white py-24">
      <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="text-center mb-12">
          <Badge className="mb-4" variant="outline">
            <Sparkles className="mr-1 h-3 w-3" />
            Book Your Demo
          </Badge>
          <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
            See AI CRM in Action
          </h1>
          <p className="mx-auto mt-4 max-w-xl text-lg text-gray-600">
            Get a personalized demo and see how AI can transform your sales process
          </p>
        </div>

        {/* Progress Steps */}
        <div className="mb-8">
          <div className="flex items-center justify-center space-x-4">
            <div className={cn("flex items-center", step === 'info' ? 'text-primary' : 'text-gray-400')}>
              <div className={cn(
                "flex h-8 w-8 items-center justify-center rounded-full border-2",
                step === 'info' ? 'border-primary bg-primary text-white' : 'border-gray-300'
              )}>
                1
              </div>
              <span className="ml-2 text-sm font-medium">Your Info</span>
            </div>
            <div className="w-16 h-0.5 bg-gray-300" />
            <div className={cn("flex items-center", step === 'schedule' ? 'text-primary' : 'text-gray-400')}>
              <div className={cn(
                "flex h-8 w-8 items-center justify-center rounded-full border-2",
                step === 'schedule' ? 'border-primary bg-primary text-white' : 'border-gray-300'
              )}>
                2
              </div>
              <span className="ml-2 text-sm font-medium">Schedule</span>
            </div>
            <div className="w-16 h-0.5 bg-gray-300" />
            <div className={cn("flex items-center", step === 'confirm' ? 'text-primary' : 'text-gray-400')}>
              <div className={cn(
                "flex h-8 w-8 items-center justify-center rounded-full border-2",
                step === 'confirm' ? 'border-primary bg-primary text-white' : 'border-gray-300'
              )}>
                3
              </div>
              <span className="ml-2 text-sm font-medium">Confirm</span>
            </div>
          </div>
        </div>

        {/* Step 1: Contact Info */}
        {step === 'info' && (
          <Card>
            <CardHeader>
              <CardTitle>Tell us about yourself</CardTitle>
              <CardDescription>We'll use this to personalize your demo</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleInfoSubmit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="firstName">First Name *</Label>
                    <div className="relative mt-1">
                      <User className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                      <Input
                        id="firstName"
                        placeholder="John"
                        className="pl-10"
                        value={formData.firstName}
                        onChange={(e) => setFormData({ ...formData, firstName: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                  <div>
                    <Label htmlFor="lastName">Last Name *</Label>
                    <div className="relative mt-1">
                      <User className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                      <Input
                        id="lastName"
                        placeholder="Doe"
                        className="pl-10"
                        value={formData.lastName}
                        onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                </div>
                
                <div>
                  <Label htmlFor="email">Work Email *</Label>
                  <div className="relative mt-1">
                    <Mail className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                    <Input
                      id="email"
                      type="email"
                      placeholder="john@company.com"
                      className="pl-10"
                      value={formData.email}
                      onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                      required
                    />
                  </div>
                </div>
                
                <div>
                  <Label htmlFor="company">Company *</Label>
                  <div className="relative mt-1">
                    <Building className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                    <Input
                      id="company"
                      placeholder="Acme Inc"
                      className="pl-10"
                      value={formData.company}
                      onChange={(e) => setFormData({ ...formData, company: e.target.value })}
                      required
                    />
                  </div>
                </div>
                
                <div>
                  <Label htmlFor="phone">Phone Number</Label>
                  <div className="relative mt-1">
                    <Phone className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                    <Input
                      id="phone"
                      type="tel"
                      placeholder="+1 (555) 123-4567"
                      className="pl-10"
                      value={formData.phone}
                      onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                    />
                  </div>
                </div>
                
                <div>
                  <Label htmlFor="companySize">Company Size *</Label>
                  <div className="relative mt-1">
                    <Users className="absolute left-3 top-3 h-4 w-4 text-gray-400 z-10" />
                    <Select 
                      value={formData.companySize} 
                      onValueChange={(value) => setFormData({ ...formData, companySize: value })}
                    >
                      <SelectTrigger className="pl-10">
                        <SelectValue placeholder="Select company size" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="1-10">1-10 employees</SelectItem>
                        <SelectItem value="11-50">11-50 employees</SelectItem>
                        <SelectItem value="51-200">51-200 employees</SelectItem>
                        <SelectItem value="201-500">201-500 employees</SelectItem>
                        <SelectItem value="500+">500+ employees</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
                
                <Button type="submit" className="w-full">
                  Continue to Scheduling
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
              </form>
            </CardContent>
          </Card>
        )}

        {/* Step 2: Schedule */}
        {step === 'schedule' && (
          <Card>
            <CardHeader>
              <CardTitle>Choose a time that works for you</CardTitle>
              <CardDescription>All demos are 30 minutes via video call</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="grid md:grid-cols-2 gap-6">
                <div>
                  <Label className="mb-3 block">Select Date</Label>
                  <Calendar
                    mode="single"
                    selected={selectedDate}
                    onSelect={setSelectedDate}
                    disabled={isDateDisabled}
                    className="rounded-md border"
                  />
                </div>
                
                <div>
                  <Label className="mb-3 block">Available Times</Label>
                  {selectedDate ? (
                    <div className="grid grid-cols-2 gap-2">
                      {timeSlots.map((time) => (
                        <Button
                          key={time}
                          variant={selectedTime === time ? 'default' : 'outline'}
                          size="sm"
                          onClick={() => setSelectedTime(time)}
                          className="justify-start"
                        >
                          <Clock className="mr-2 h-4 w-4" />
                          {time}
                        </Button>
                      ))}
                    </div>
                  ) : (
                    <p className="text-sm text-gray-500 text-center py-8">
                      Please select a date first
                    </p>
                  )}
                </div>
              </div>
              
              {selectedDate && selectedTime && (
                <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                  <p className="text-sm font-medium">
                    Selected: {format(selectedDate, 'EEEE, MMMM d, yyyy')} at {selectedTime}
                  </p>
                </div>
              )}
              
              <div className="mt-6 flex gap-4">
                <Button 
                  variant="outline" 
                  onClick={() => setStep('info')}
                  className="flex-1"
                >
                  Back
                </Button>
                <Button 
                  onClick={handleScheduleSubmit}
                  disabled={!selectedDate || !selectedTime || isSubmitting}
                  className="flex-1"
                >
                  {isSubmitting ? 'Scheduling...' : 'Schedule Demo'}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Step 3: Confirmation */}
        {step === 'confirm' && (
          <Card>
            <CardContent className="pt-12 pb-8 text-center">
              <div className="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <CheckCircle className="h-8 w-8 text-green-600" />
              </div>
              <h2 className="text-2xl font-bold mb-2">Demo Scheduled!</h2>
              <p className="text-gray-600 mb-6">
                We've sent a calendar invite to {formData.email}
              </p>
              
              <div className="bg-gray-50 rounded-lg p-6 text-left max-w-md mx-auto mb-8">
                <h3 className="font-semibold mb-3">Your Demo Details:</h3>
                <div className="space-y-2 text-sm">
                  <div className="flex items-center gap-2">
                    <CalendarIcon className="h-4 w-4 text-gray-500" />
                    <span>{selectedDate && format(selectedDate, 'EEEE, MMMM d, yyyy')}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Clock className="h-4 w-4 text-gray-500" />
                    <span>{selectedTime} (30 minutes)</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <User className="h-4 w-4 text-gray-500" />
                    <span>{formData.firstName} {formData.lastName}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Building className="h-4 w-4 text-gray-500" />
                    <span>{formData.company}</span>
                  </div>
                </div>
              </div>
              
              <div className="space-y-3">
                <Button asChild className="w-full max-w-md">
                  <a href="/">Return to Homepage</a>
                </Button>
                <p className="text-sm text-gray-500">
                  Questions? Chat with us using the widget below!
                </p>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Value Props */}
        <div className="mt-12 grid md:grid-cols-3 gap-6">
          <div className="text-center">
            <div className="mx-auto w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mb-3">
              <Sparkles className="h-6 w-6 text-primary" />
            </div>
            <h3 className="font-semibold mb-1">AI-Powered Demo</h3>
            <p className="text-sm text-gray-600">See real AI scoring and automation in action</p>
          </div>
          <div className="text-center">
            <div className="mx-auto w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mb-3">
              <Users className="h-6 w-6 text-primary" />
            </div>
            <h3 className="font-semibold mb-1">Personalized for You</h3>
            <p className="text-sm text-gray-600">Tailored to your industry and use case</p>
          </div>
          <div className="text-center">
            <div className="mx-auto w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mb-3">
              <Clock className="h-6 w-6 text-primary" />
            </div>
            <h3 className="font-semibold mb-1">30 Minutes</h3>
            <p className="text-sm text-gray-600">Quick demo with immediate value</p>
          </div>
        </div>
      </div>
    </div>
  )
}