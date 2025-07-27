import * as React from 'react';
import { Input } from './input';
import { Label } from './label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './select';
import { Textarea } from './textarea';
import { useFieldLabel } from '@/hooks/use-field-labels';
import { useEnums } from '@/hooks/use-enums';
import { cn } from '@/lib/utils';

interface CRMFieldProps {
  table: 'leads' | 'contacts' | 'accounts' | 'opportunities' | 'cases';
  field: string;
  type?: 'text' | 'email' | 'tel' | 'number' | 'select' | 'textarea';
  label?: string;
  placeholder?: string;
  required?: boolean;
  error?: string;
  value?: string | number;
  onChange?: (value: string) => void;
  onBlur?: () => void;
  disabled?: boolean;
  className?: string;
}

export function CRMField({
  table,
  field,
  type = 'text',
  label: customLabel,
  placeholder,
  required,
  error,
  value,
  onChange,
  onBlur,
  disabled,
  className
}: CRMFieldProps) {
  const fieldLabel = useFieldLabel(table, field);
  const { data: enums } = useEnums(table);
  
  // Use custom label if provided, otherwise use the field label from API
  const displayLabel = customLabel || fieldLabel;
  
  // Check if this field has enum values
  const enumValues = enums?.[field];
  const isEnumField = enumValues && enumValues.length > 0;
  
  // Auto-detect field type based on field name
  const fieldType = React.useMemo(() => {
    if (isEnumField) return 'select';
    if (field.includes('email')) return 'email';
    if (field.includes('phone')) return 'tel';
    if (field.includes('description') || field.includes('notes')) return 'textarea';
    return type;
  }, [field, type, isEnumField]);
  
  const id = `${table}-${field}`;
  
  return (
    <div className={cn('space-y-2', className)}>
      {displayLabel && (
        <Label htmlFor={id}>
          {displayLabel}
          {required && <span className="text-destructive ml-1">*</span>}
        </Label>
      )}
      
      {fieldType === 'select' && enumValues ? (
        <Select
          value={value?.toString()}
          onValueChange={onChange}
          disabled={disabled}
        >
          <SelectTrigger id={id} className={error ? 'border-destructive' : ''}>
            <SelectValue placeholder={placeholder || `Select ${displayLabel}`} />
          </SelectTrigger>
          <SelectContent>
            {enumValues.map((option: string) => (
              <SelectItem key={option} value={option}>
                {option}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      ) : fieldType === 'textarea' ? (
        <Textarea
          id={id}
          name={field}
          value={value}
          onChange={(e) => onChange?.(e.target.value)}
          onBlur={onBlur}
          placeholder={placeholder}
          disabled={disabled}
          className={cn(error ? 'border-destructive' : '', 'min-h-[100px]')}
        />
      ) : (
        <Input
          id={id}
          name={field}
          type={fieldType}
          value={value}
          onChange={(e) => onChange?.(e.target.value)}
          onBlur={onBlur}
          placeholder={placeholder}
          disabled={disabled}
          className={error ? 'border-destructive' : ''}
        />
      )}
      
      {error && (
        <p className="text-sm text-destructive">{error}</p>
      )}
    </div>
  );
}

// Example usage:
// <CRMField
//   table="leads"
//   field="email1"
//   required
//   error={errors.email1}
//   value={formData.email1}
//   onChange={(value) => setFormData({ ...formData, email1: value })}
// />
// This will display "Email" as the label but use "email1" as the field name