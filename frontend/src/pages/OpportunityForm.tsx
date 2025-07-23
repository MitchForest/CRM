import { useEffect } from 'react'
import { useNavigate, useParams, Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useOpportunity, useCreateOpportunity, useUpdateOpportunity } from '@/hooks/use-opportunities'
import { useContacts } from '@/hooks/use-contacts'
import { toast } from 'sonner'

const opportunitySchema = z.object({
  name: z.string().min(1, 'Opportunity name is required'),
  amount: z.coerce.number().min(0, 'Amount must be positive'),
  currency: z.string().default('USD'),
  probability: z.coerce.number().min(0).max(100).optional(),
  salesStage: z.string().min(1, 'Sales stage is required'),
  closeDate: z.string().min(1, 'Close date is required'),
  description: z.string().optional(),
  nextStep: z.string().optional(),
  contactId: z.string().optional(),
})

type OpportunityFormData = z.infer<typeof opportunitySchema>

const SALES_STAGES = [
  { value: 'prospecting', label: 'Prospecting' },
  { value: 'qualification', label: 'Qualification' },
  { value: 'proposal', label: 'Proposal' },
  { value: 'negotiation', label: 'Negotiation' },
  { value: 'closed-won', label: 'Closed Won' },
  { value: 'closed-lost', label: 'Closed Lost' },
]

const CURRENCIES = [
  { value: 'USD', label: 'USD - US Dollar' },
  { value: 'EUR', label: 'EUR - Euro' },
  { value: 'GBP', label: 'GBP - British Pound' },
  { value: 'CAD', label: 'CAD - Canadian Dollar' },
  { value: 'AUD', label: 'AUD - Australian Dollar' },
]

export function OpportunityFormPage() {
  const { id } = useParams<{ id?: string }>()
  const navigate = useNavigate()
  const isEdit = !!id

  const { data: opportunityData, isLoading: opportunityLoading } = useOpportunity(id || '')
  const { data: contactsData } = useContacts({ limit: 100 })
  const createOpportunity = useCreateOpportunity()
  const updateOpportunity = useUpdateOpportunity()

  const form = useForm<OpportunityFormData>({
    resolver: zodResolver(opportunitySchema),
    defaultValues: {
      name: '',
      amount: 0,
      currency: 'USD',
      probability: 20,
      salesStage: 'prospecting',
      closeDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days from now
      description: '',
      nextStep: '',
      contactId: undefined,
    },
  })

  // Load opportunity data for editing
  useEffect(() => {
    if (opportunityData?.success && opportunityData.data) {
      const opportunity = opportunityData.data
      form.reset({
        name: opportunity.name,
        amount: opportunity.amount,
        currency: opportunity.currency || 'USD',
        probability: opportunity.probability || 20,
        salesStage: opportunity.salesStage,
        closeDate: opportunity.closeDate.split('T')[0], // Convert to date input format
        description: opportunity.description || '',
        nextStep: opportunity.nextStep || '',
        contactId: opportunity.contactId || undefined,
      })
    }
  }, [opportunityData, form])

  const onSubmit = async (data: OpportunityFormData) => {
    try {
      // Convert date to ISO string
      const opportunityData = {
        ...data,
        closeDate: new Date(data.closeDate).toISOString(),
      }

      if (isEdit) {
        await updateOpportunity.mutateAsync({ id: id!, data: opportunityData })
        toast.success('Opportunity updated successfully')
      } else {
        await createOpportunity.mutateAsync(opportunityData)
        toast.success('Opportunity created successfully')
      }
      navigate('/opportunities')
    } catch (error) {
      toast.error(`Failed to ${isEdit ? 'update' : 'create'} opportunity. Please try again.`)
    }
  }

  if (isEdit && opportunityLoading) {
    return (
      <div className="flex items-center justify-center h-96">
        <Loader2 className="h-8 w-8 animate-spin" />
      </div>
    )
  }

  const isSubmitting = createOpportunity.isPending || updateOpportunity.isPending

  return (
    <div className="p-8 max-w-4xl mx-auto space-y-6">
      <div className="flex items-center gap-4">
        <Link to="/opportunities">
          <Button variant="ghost" size="sm">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Opportunities
          </Button>
        </Link>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{isEdit ? 'Edit Opportunity' : 'Create New Opportunity'}</CardTitle>
          <CardDescription>
            {isEdit ? 'Update the opportunity details below' : 'Fill in the details to create a new opportunity'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <FormField
                  control={form.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem className="col-span-2">
                      <FormLabel>Opportunity Name</FormLabel>
                      <FormControl>
                        <Input placeholder="e.g., Enterprise Deal - Acme Corp" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="amount"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Amount</FormLabel>
                      <FormControl>
                        <Input type="number" placeholder="50000" {...field} />
                      </FormControl>
                      <FormDescription>Deal value in selected currency</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="currency"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Currency</FormLabel>
                      <Select onValueChange={field.onChange} defaultValue={field.value}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Select currency" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {CURRENCIES.map((currency) => (
                            <SelectItem key={currency.value} value={currency.value}>
                              {currency.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="salesStage"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Sales Stage</FormLabel>
                      <Select onValueChange={field.onChange} defaultValue={field.value}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Select stage" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {SALES_STAGES.map((stage) => (
                            <SelectItem key={stage.value} value={stage.value}>
                              {stage.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="probability"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Probability (%)</FormLabel>
                      <FormControl>
                        <Input type="number" min="0" max="100" {...field} />
                      </FormControl>
                      <FormDescription>Win probability (0-100)</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="closeDate"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Expected Close Date</FormLabel>
                      <FormControl>
                        <Input type="date" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="contactId"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Associated Contact</FormLabel>
                      <Select onValueChange={field.onChange} defaultValue={field.value}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder="Select a contact" />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="">No contact</SelectItem>
                          {contactsData?.data?.map((contact) => (
                            <SelectItem key={contact.id} value={contact.id!}>
                              {contact.firstName} {contact.lastName}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormDescription>Link this opportunity to a contact</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="nextStep"
                  render={({ field }) => (
                    <FormItem className="col-span-2">
                      <FormLabel>Next Step</FormLabel>
                      <FormControl>
                        <Input placeholder="e.g., Send proposal by Friday" {...field} />
                      </FormControl>
                      <FormDescription>What needs to happen next?</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="description"
                  render={({ field }) => (
                    <FormItem className="col-span-2">
                      <FormLabel>Description</FormLabel>
                      <FormControl>
                        <Textarea 
                          placeholder="Provide details about this opportunity..."
                          className="min-h-[100px]"
                          {...field} 
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => navigate('/opportunities')}>
                  Cancel
                </Button>
                <Button type="submit" disabled={isSubmitting}>
                  {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                  {isEdit ? 'Update Opportunity' : 'Create Opportunity'}
                </Button>
              </div>
            </form>
          </Form>
        </CardContent>
      </Card>
    </div>
  )
}