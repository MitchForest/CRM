import { useEffect } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { DatePicker } from '@/components/ui/date-picker'
import { 
  useOpportunity, 
  useCreateOpportunity, 
  useUpdateOpportunity 
} from '@/hooks/use-opportunities'
import { useAccounts } from '@/hooks/use-accounts'

const opportunitySchema = z.object({
  name: z.string().min(1, 'Opportunity name is required'),
  account_id: z.string().min(1, 'Account is required'),
  sales_stage: z.string(),
  amount: z.number().min(0, 'Amount must be positive'),
  probability: z.number().min(0).max(100),
  date_closed: z.date(),
  lead_source: z.string().optional(),
  next_step: z.string().optional(),
  description: z.string().optional(),
})

type OpportunityFormData = z.infer<typeof opportunitySchema>

const stages = [
  'prospecting',
  'qualification',
  'needs_analysis',
  'value_proposition',
  'decision_makers',
  'perception_analysis',
  'proposal',
  'negotiation',
  'closed_won',
  'closed_lost',
] as const

const STAGE_PROBABILITIES = {
  'prospecting': 10,
  'qualification': 20,
  'needs_analysis': 25,
  'value_proposition': 30,
  'decision_makers': 40,
  'perception_analysis': 50,
  'proposal': 65,
  'negotiation': 80,
  'closed_won': 100,
  'closed_lost': 0,
} as const

export function OpportunityForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)

  const { data: opportunityData, isLoading: isLoadingOpportunity } = useOpportunity(id || '')
  const { data: accountsData } = useAccounts({ page: 1, limit: 100 })
  const createMutation = useCreateOpportunity()
  const updateMutation = useUpdateOpportunity(id || '')

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<OpportunityFormData>({
    resolver: zodResolver(opportunitySchema),
    defaultValues: {
      sales_stage: 'qualification',
      probability: 20,
      date_closed: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000), // 30 days from now
    },
  })

  const selectedStage = watch('sales_stage')

  // Update form when opportunity data loads
  useEffect(() => {
    if (opportunityData?.data && isEdit) {
      const opp = opportunityData.data
      setValue('name', opp.name || '')
      setValue('sales_stage', opp.sales_stage || 'qualification')
      setValue('amount', opp.amount || 0)
      setValue('probability', opp.probability || 0)
      setValue('date_closed', new Date(opp.date_closed || Date.now()))
      // lead_source not available in Opportunity type
      setValue('next_step', opp.next_step || '')
      setValue('description', opp.description || '')
      setValue('account_id', opp.account_id || '')
    }
  }, [opportunityData, isEdit, setValue])

  // Update probability when stage changes
  useEffect(() => {
    if (selectedStage && STAGE_PROBABILITIES[selectedStage as keyof typeof STAGE_PROBABILITIES] !== undefined) {
      setValue('probability', STAGE_PROBABILITIES[selectedStage as keyof typeof STAGE_PROBABILITIES])
    }
  }, [selectedStage, setValue])

  const onSubmit = async (data: OpportunityFormData) => {
    const formattedData = {
      name: data.name,
      sales_stage: data.sales_stage,
      amount: data.amount,
      probability: data.probability,
      date_closed: data.date_closed?.toISOString().split('T')[0] || '',
      next_step: data.next_step || null,
      description: data.description || null,
      account_id: data.account_id,
    }

    try {
      if (isEdit) {
        await updateMutation.mutateAsync(formattedData)
      } else {
        // Add required fields for creation
        const createData = {
          ...formattedData,
          created_by: null,
          modified_user_id: null,
          assigned_user_id: null,
          deleted: 0,
          opportunity_type: null,
          lead_source: null,
          amount_usdollar: formattedData.amount,
          currency_id: null,
          ai_close_probability: null,
          ai_risk_factors: null,
          ai_recommendations: null
        }
        await createMutation.mutateAsync(createData)
      }
      navigate('/opportunities')
    } catch {
      // Error is handled by the mutation
    }
  }

  if (isLoadingOpportunity && isEdit) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <Loader2 className="h-8 w-8 animate-spin" />
      </div>
    )
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => navigate('/opportunities')}
          className="mr-4"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <h1 className="text-2xl font-semibold">
          {isEdit ? 'Edit Opportunity' : 'New Opportunity'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="space-y-2">
          <Label htmlFor="name">Opportunity Name</Label>
          <Input
            id="name"
            {...register('name')}
            disabled={isSubmitting}
          />
          {errors.name && (
            <p className="text-sm text-destructive">{errors.name.message}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="accountId">Account</Label>
          <Select
            onValueChange={(value) => setValue('account_id', value)}
            defaultValue={watch('account_id')}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select an account" />
            </SelectTrigger>
            <SelectContent>
              {accountsData?.data.map((account) => (
                <SelectItem key={account.id} value={account.id || ''}>
                  {account.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.account_id && (
            <p className="text-sm text-destructive">{errors.account_id.message}</p>
          )}
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="salesStage">Sales Stage</Label>
            <Select
              onValueChange={(value) => setValue('sales_stage', value)}
              defaultValue={watch('sales_stage')}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {stages.map((stage) => (
                  <SelectItem key={stage} value={stage}>
                    {stage}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="probability">Probability (%)</Label>
            <Input
              id="probability"
              type="number"
              min="0"
              max="100"
              {...register('probability', { valueAsNumber: true })}
              disabled={isSubmitting}
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="amount">Amount ($)</Label>
            <Input
              id="amount"
              type="number"
              min="0"
              step="0.01"
              {...register('amount', { valueAsNumber: true })}
              disabled={isSubmitting}
            />
            {errors.amount && (
              <p className="text-sm text-destructive">{errors.amount.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="closeDate">Expected Close Date</Label>
            <DatePicker
              date={watch('date_closed')}
              onDateChange={(date) => setValue('date_closed', date || new Date())}
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="leadSource">Lead Source</Label>
          <Select
            onValueChange={(value) => setValue('lead_source', value)}
            defaultValue={watch('lead_source')}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select lead source" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Website">Website</SelectItem>
              <SelectItem value="Referral">Referral</SelectItem>
              <SelectItem value="Partner">Partner</SelectItem>
              <SelectItem value="Direct">Direct Sales</SelectItem>
              <SelectItem value="Marketing">Marketing Campaign</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-2">
          <Label htmlFor="nextStep">Next Step</Label>
          <Input
            id="nextStep"
            {...register('next_step')}
            disabled={isSubmitting}
            placeholder="e.g., Schedule demo, Send proposal"
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            rows={4}
            {...register('description')}
            disabled={isSubmitting}
          />
        </div>

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Opportunity' : 'Create Opportunity'}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/opportunities')}
            disabled={isSubmitting}
          >
            Cancel
          </Button>
        </div>
      </form>
    </div>
  )
}