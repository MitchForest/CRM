<?php
namespace Api\DTO\Base;

/**
 * Base Data Transfer Object
 * Provides common functionality for all DTOs
 */
abstract class BaseDTO implements \JsonSerializable
{
    protected array $data = [];
    protected array $errors = [];
    
    /**
     * Constructor accepts array data or object
     */
    public function __construct($data = null)
    {
        if ($data !== null) {
            $this->hydrate($data);
        }
    }
    
    /**
     * Hydrate DTO from array or object
     */
    public function hydrate($data): self
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Data must be array or object');
        }
        
        foreach ($data as $key => $value) {
            $method = 'set' . $this->toCamelCase($key);
            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                // Store in data array for dynamic properties
                $this->data[$key] = $value;
            }
        }
        
        return $this;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = new \ReflectionClass($this);
        
        foreach ($reflection->getProperties() as $property) {
            if ($property->getName() === 'data' || $property->getName() === 'errors') {
                continue;
            }
            
            $property->setAccessible(true);
            $value = $property->getValue($this);
            
            if ($value !== null) {
                $key = $this->toSnakeCase($property->getName());
                $result[$key] = $this->convertValue($value);
            }
        }
        
        // Include dynamic data
        foreach ($this->data as $key => $value) {
            if (!isset($result[$key])) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert value for array representation
     */
    protected function convertValue($value)
    {
        if ($value instanceof BaseDTO) {
            return $value->toArray();
        } elseif (is_array($value)) {
            return array_map([$this, 'convertValue'], $value);
        } elseif ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        
        return $value;
    }
    
    /**
     * Validate the DTO
     */
    public function validate(): bool
    {
        $this->errors = [];
        $this->performValidation();
        return empty($this->errors);
    }
    
    /**
     * Perform validation - override in child classes
     */
    abstract protected function performValidation(): void;
    
    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Add validation error
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * JsonSerializable implementation
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
    
    /**
     * Convert snake_case to camelCase
     */
    protected function toCamelCase(string $str): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
    }
    
    /**
     * Convert camelCase to snake_case
     */
    protected function toSnakeCase(string $str): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
    }
    
    /**
     * Magic getter for dynamic properties
     */
    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }
    
    /**
     * Magic setter for dynamic properties
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }
    
    /**
     * Check if property is set
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
    
    /**
     * Generate TypeScript interface definition
     */
    abstract public function getTypeScriptInterface(): string;
    
    /**
     * Generate Zod schema definition
     */
    abstract public function getZodSchema(): string;
}