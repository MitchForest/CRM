<?php
namespace Api\DTO;

use Api\DTO\Base\BaseDTO;

/**
 * Opportunity Data Transfer Object
 */
class OpportunityDTO extends BaseDTO
{
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?float $amount = null;
    protected ?string $currencyId = null;
    protected ?string $dateEntered = null;
    protected ?string $dateModified = null;
    protected ?string $dateClosed = null;
    protected ?string $nextStep = null;
    protected ?string $salesStage = null;
    protected ?float $probability = null;
    protected ?string $opportunityType = null;
    protected ?string $leadSource = null;
    protected ?string $description = null;
    protected ?string $assignedUserId = null;
    protected ?string $campaignId = null;
    protected ?string $contactId = null;
    protected ?array $contacts = null;
    protected ?string $accountId = null;
    protected ?string $accountName = null;
    protected ?float $amountUsdollar = null;
    protected ?bool $deleted = null;
    protected ?array $aiInsights = null;
    protected ?float $predictedRevenue = null;
    protected ?string $winProbability = null;
    protected ?array $riskFactors = null;
    
    protected function performValidation(): void
    {
        // Required fields
        if (empty($this->name)) {
            $this->addError('name', 'Opportunity name is required');
        }
        
        // Amount validation
        if ($this->amount !== null && $this->amount < 0) {
            $this->addError('amount', 'Amount must be non-negative');
        }
        
        // Probability validation
        if ($this->probability !== null && ($this->probability < 0 || $this->probability > 100)) {
            $this->addError('probability', 'Probability must be between 0 and 100');
        }
        
        // Sales stage validation
        $validStages = [
            'Prospecting', 'Qualification', 'Needs Analysis', 
            'Value Proposition', 'Id. Decision Makers', 'Perception Analysis',
            'Proposal/Price Quote', 'Negotiation/Review', 'Closed Won', 'Closed Lost'
        ];
        if (!empty($this->salesStage) && !in_array($this->salesStage, $validStages)) {
            $this->addError('sales_stage', 'Invalid sales stage');
        }
        
        // Date validation
        if (!empty($this->dateClosed) && !strtotime($this->dateClosed)) {
            $this->addError('date_closed', 'Invalid date format');
        }
    }
    
    // Getters and Setters
    public function getId(): ?string
    {
        return $this->id;
    }
    
    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getName(): ?string
    {
        return $this->name;
    }
    
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    public function getAmount(): ?float
    {
        return $this->amount;
    }
    
    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }
    
    public function getSalesStage(): ?string
    {
        return $this->salesStage;
    }
    
    public function setSalesStage(?string $salesStage): self
    {
        $this->salesStage = $salesStage;
        // Auto-update probability based on stage
        $stageProbabilities = [
            'Prospecting' => 10,
            'Qualification' => 20,
            'Needs Analysis' => 25,
            'Value Proposition' => 30,
            'Id. Decision Makers' => 40,
            'Perception Analysis' => 50,
            'Proposal/Price Quote' => 65,
            'Negotiation/Review' => 80,
            'Closed Won' => 100,
            'Closed Lost' => 0
        ];
        
        if (isset($stageProbabilities[$salesStage])) {
            $this->probability = $stageProbabilities[$salesStage];
        }
        
        return $this;
    }
    
    public function getProbability(): ?float
    {
        return $this->probability;
    }
    
    public function setProbability(?float $probability): self
    {
        $this->probability = $probability;
        return $this;
    }
    
    public function getDateClosed(): ?string
    {
        return $this->dateClosed;
    }
    
    public function setDateClosed(?string $dateClosed): self
    {
        $this->dateClosed = $dateClosed;
        return $this;
    }
    
    // Additional setters/getters for other properties...
    
    /**
     * Create from SugarBean
     */
    public static function fromBean($bean): self
    {
        $dto = new self();
        
        $dto->setId($bean->id)
            ->setName($bean->name)
            ->setAmount((float)$bean->amount)
            ->setCurrencyId($bean->currency_id)
            ->setDateEntered($bean->date_entered)
            ->setDateModified($bean->date_modified)
            ->setDateClosed($bean->date_closed)
            ->setNextStep($bean->next_step)
            ->setSalesStage($bean->sales_stage)
            ->setProbability((float)$bean->probability)
            ->setOpportunityType($bean->opportunity_type)
            ->setLeadSource($bean->lead_source)
            ->setDescription($bean->description)
            ->setAssignedUserId($bean->assigned_user_id)
            ->setCampaignId($bean->campaign_id)
            ->setAccountId($bean->account_id)
            ->setAccountName($bean->account_name)
            ->setAmountUsdollar((float)$bean->amount_usdollar)
            ->setDeleted((bool)$bean->deleted);
            
        return $dto;
    }
    
    /**
     * Update SugarBean from DTO
     */
    public function toBean($bean): void
    {
        if ($this->name !== null) $bean->name = $this->name;
        if ($this->amount !== null) $bean->amount = $this->amount;
        if ($this->currencyId !== null) $bean->currency_id = $this->currencyId;
        if ($this->dateClosed !== null) $bean->date_closed = $this->dateClosed;
        if ($this->nextStep !== null) $bean->next_step = $this->nextStep;
        if ($this->salesStage !== null) $bean->sales_stage = $this->salesStage;
        if ($this->probability !== null) $bean->probability = $this->probability;
        if ($this->opportunityType !== null) $bean->opportunity_type = $this->opportunityType;
        if ($this->leadSource !== null) $bean->lead_source = $this->leadSource;
        if ($this->description !== null) $bean->description = $this->description;
        if ($this->assignedUserId !== null) $bean->assigned_user_id = $this->assignedUserId;
        if ($this->campaignId !== null) $bean->campaign_id = $this->campaignId;
        if ($this->accountId !== null) $bean->account_id = $this->accountId;
        if ($this->accountName !== null) $bean->account_name = $this->accountName;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Opportunity {
  id?: string;
  name?: string;
  amount?: number;
  currencyId?: string;
  dateEntered?: string;
  dateModified?: string;
  dateClosed?: string;
  nextStep?: string;
  salesStage?: 'Prospecting' | 'Qualification' | 'Needs Analysis' | 
               'Value Proposition' | 'Id. Decision Makers' | 'Perception Analysis' |
               'Proposal/Price Quote' | 'Negotiation/Review' | 'Closed Won' | 'Closed Lost';
  probability?: number;
  opportunityType?: string;
  leadSource?: string;
  description?: string;
  assignedUserId?: string;
  campaignId?: string;
  contactId?: string;
  contacts?: Contact[];
  accountId?: string;
  accountName?: string;
  amountUsdollar?: number;
  deleted?: boolean;
  aiInsights?: {
    winProbability?: number;
    riskFactors?: string[];
    recommendations?: string[];
    predictedCloseDate?: string;
  };
  predictedRevenue?: number;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const OpportunitySchema = z.object({
  id: z.string().optional(),
  name: z.string().min(1, "Opportunity name is required"),
  amount: z.number().min(0, "Amount must be non-negative").optional(),
  currencyId: z.string().optional(),
  dateEntered: z.string().optional(),
  dateModified: z.string().optional(),
  dateClosed: z.string().optional(),
  nextStep: z.string().optional(),
  salesStage: z.enum([
    'Prospecting', 'Qualification', 'Needs Analysis',
    'Value Proposition', 'Id. Decision Makers', 'Perception Analysis',
    'Proposal/Price Quote', 'Negotiation/Review', 'Closed Won', 'Closed Lost'
  ]).optional(),
  probability: z.number().min(0).max(100).optional(),
  opportunityType: z.string().optional(),
  leadSource: z.string().optional(),
  description: z.string().optional(),
  assignedUserId: z.string().optional(),
  campaignId: z.string().optional(),
  contactId: z.string().optional(),
  contacts: z.array(ContactSchema).optional(),
  accountId: z.string().optional(),
  accountName: z.string().optional(),
  amountUsdollar: z.number().optional(),
  deleted: z.boolean().optional(),
  aiInsights: z.object({
    winProbability: z.number().min(0).max(100).optional(),
    riskFactors: z.array(z.string()).optional(),
    recommendations: z.array(z.string()).optional(),
    predictedCloseDate: z.string().optional()
  }).optional(),
  predictedRevenue: z.number().optional()
});
TS;
    }
}