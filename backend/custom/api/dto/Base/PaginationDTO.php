<?php
namespace Api\DTO\Base;

/**
 * Pagination Data Transfer Object
 */
class PaginationDTO extends BaseDTO
{
    protected int $page = 1;
    protected int $limit = 20;
    protected int $total = 0;
    protected int $pages = 0;
    
    protected function performValidation(): void
    {
        if ($this->page < 1) {
            $this->addError('page', 'Page must be greater than 0');
        }
        
        if ($this->limit < 1 || $this->limit > 100) {
            $this->addError('limit', 'Limit must be between 1 and 100');
        }
        
        if ($this->total < 0) {
            $this->addError('total', 'Total must be non-negative');
        }
    }
    
    public function getPage(): int
    {
        return $this->page;
    }
    
    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }
    
    public function getLimit(): int
    {
        return $this->limit;
    }
    
    public function setLimit(int $limit): self
    {
        $this->limit = min($limit, 100); // Max 100
        return $this;
    }
    
    public function getTotal(): int
    {
        return $this->total;
    }
    
    public function setTotal(int $total): self
    {
        $this->total = $total;
        $this->pages = ceil($total / $this->limit);
        return $this;
    }
    
    public function getPages(): int
    {
        return $this->pages;
    }
    
    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
    
    public function getTypeScriptInterface(): string
    {
        return <<<TS
export interface Pagination {
  page: number;
  limit: number;
  total: number;
  pages: number;
}
TS;
    }
    
    public function getZodSchema(): string
    {
        return <<<TS
export const PaginationSchema = z.object({
  page: z.number().min(1),
  limit: z.number().min(1).max(100),
  total: z.number().min(0),
  pages: z.number().min(0)
});
TS;
    }
}