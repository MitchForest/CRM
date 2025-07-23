<?php
namespace Api\DTO\Base;

/**
 * Error Response DTO
 */
class ErrorDTO extends BaseDTO
{
    protected string $error = '';
    protected string $code = '';
    protected ?array $details = null;
    protected ?array $validation = null;
    protected int $statusCode = 400;
    
    protected function performValidation(): void
    {
        if (empty($this->error)) {
            $this->addError('error', 'Error message is required');
        }
        
        if (empty($this->code)) {
            $this->addError('code', 'Error code is required');
        }
    }
    
    public function getError(): string
    {
        return $this->error;
    }
    
    public function setError(string $error): self
    {
        $this->error = $error;
        return $this;
    }
    
    public function getCode(): string
    {
        return $this->code;
    }
    
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }
    
    public function getDetails(): ?array
    {
        return $this->details;
    }
    
    public function setDetails(?array $details): self
    {
        $this->details = $details;
        return $this;
    }
    
    public function getValidation(): ?array
    {
        return $this->validation;
    }
    
    public function setValidation(?array $validation): self
    {
        $this->validation = $validation;
        return $this;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface ErrorResponse {
  error: string;
  code: string;
  details?: any;
  validation?: Record<string, string[]>;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const ErrorResponseSchema = z.object({
  error: z.string(),
  code: z.string(),
  details: z.any().optional(),
  validation: z.record(z.array(z.string())).optional()
});
TS;
    }
    
    /**
     * Common error codes
     */
    const CODE_VALIDATION_FAILED = 'VALIDATION_FAILED';
    const CODE_NOT_FOUND = 'NOT_FOUND';
    const CODE_UNAUTHORIZED = 'UNAUTHORIZED';
    const CODE_FORBIDDEN = 'FORBIDDEN';
    const CODE_INTERNAL_ERROR = 'INTERNAL_ERROR';
    const CODE_INVALID_REQUEST = 'INVALID_REQUEST';
    const CODE_DUPLICATE_ENTRY = 'DUPLICATE_ENTRY';
    const CODE_RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    const CODE_SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    const CODE_SQL_ERROR = 'SQL_ERROR';
}