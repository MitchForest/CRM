import fs from 'fs';
import path from 'path';
import axios from 'axios';

const API_BASE = process.env.VITE_API_URL || 'http://localhost:8080';

async function generateTypes() {
  try {
    console.log('🔄 Fetching database types from schema API...');
    
    // Fetch TypeScript types directly
    const response = await axios.get(`${API_BASE}/api/schema/typescript`, {
      responseType: 'text'
    });
    
    // Ensure types directory exists
    const typesDir = path.join(process.cwd(), 'src', 'types');
    if (!fs.existsSync(typesDir)) {
      fs.mkdirSync(typesDir, { recursive: true });
    }
    
    // Save to types directory
    const outputPath = path.join(typesDir, 'database.generated.ts');
    fs.writeFileSync(outputPath, response.data);
    
    console.log('✅ Database types generated successfully');
    
    // Fetch validation rules
    console.log('🔄 Fetching validation rules...');
    const validationResponse = await axios.get(`${API_BASE}/api/schema/validation`);
    const validation = validationResponse.data;
    
    // Generate Zod schemas from validation rules
    const zodSchemas = generateZodSchemas(validation.rules);
    
    // Ensure validation directory exists
    const validationDir = path.join(process.cwd(), 'src', 'validation');
    if (!fs.existsSync(validationDir)) {
      fs.mkdirSync(validationDir, { recursive: true });
    }
    
    const validationPath = path.join(validationDir, 'schemas.generated.ts');
    fs.writeFileSync(validationPath, zodSchemas);
    
    console.log('✅ Validation schemas generated successfully');
    
    // Fetch enum values
    console.log('🔄 Fetching enum values...');
    const enumsResponse = await axios.get(`${API_BASE}/api/schema/enums`);
    const enums = enumsResponse.data;
    
    // Generate enum constants
    const enumConstants = generateEnumConstants(enums.enums);
    const enumsPath = path.join(typesDir, 'enums.generated.ts');
    fs.writeFileSync(enumsPath, enumConstants);
    
    console.log('✅ Enum constants generated successfully');
    
    // Fetch field mapping documentation
    console.log('🔄 Fetching field mapping documentation...');
    const mappingResponse = await axios.get(`${API_BASE}/api/schema/field-mapping`);
    const fieldMapping = mappingResponse.data;
    
    // Generate field mapping helper
    const fieldMappingHelper = generateFieldMappingHelper(fieldMapping);
    const mappingPath = path.join(typesDir, 'field-mapping.generated.ts');
    fs.writeFileSync(mappingPath, fieldMappingHelper);
    
    console.log('✅ Field mapping helper generated successfully');
    
    console.log('\n🎉 All types generated successfully!');
    
  } catch (error) {
    console.error('❌ Failed to generate types:', error);
    if (axios.isAxiosError(error)) {
      console.error('Response:', error.response?.data);
      console.error('Status:', error.response?.status);
    }
    process.exit(1);
  }
}

function generateZodSchemas(rules: any): string {
  let output = `// Generated from database schema on ${new Date().toISOString()}\n\n`;
  output += `import { z } from 'zod';\n\n`;
  
  for (const [table, tableRules] of Object.entries(rules)) {
    if (!tableRules || typeof tableRules !== 'object') continue;
    
    const modelName = table.charAt(0).toUpperCase() + table.slice(1, -1); // Remove 's' and capitalize
    
    // Generate create schema
    if (tableRules.create) {
      output += `export const ${modelName}CreateSchema = z.object({\n`;
      
      for (const [field, rule] of Object.entries(tableRules.create)) {
        const zodRule = convertValidationToZod(rule as string);
        output += `  ${field}: ${zodRule},\n`;
      }
      
      output += `});\n\n`;
      
      output += `export type ${modelName}CreateInput = z.infer<typeof ${modelName}CreateSchema>;\n\n`;
    }
    
    // Generate update schema (all fields optional)
    if (tableRules.update) {
      output += `export const ${modelName}UpdateSchema = z.object({\n`;
      
      for (const [field, rule] of Object.entries(tableRules.update)) {
        const zodRule = convertValidationToZod(rule as string);
        output += `  ${field}: ${zodRule},\n`;
      }
      
      output += `});\n\n`;
      
      output += `export type ${modelName}UpdateInput = z.infer<typeof ${modelName}UpdateSchema>;\n\n`;
    }
  }
  
  return output;
}

function convertValidationToZod(rule: string): string {
  const parts = rule.split('|');
  let zodParts: string[] = [];
  let isOptional = false;
  
  for (const part of parts) {
    if (part === 'required') {
      // Required is default in zod
      continue;
    } else if (part === 'sometimes') {
      isOptional = true;
    } else if (part === 'string') {
      zodParts.push('z.string()');
    } else if (part === 'integer') {
      zodParts.push('z.number().int()');
    } else if (part === 'numeric') {
      zodParts.push('z.number()');
    } else if (part === 'boolean') {
      zodParts.push('z.boolean()');
    } else if (part === 'email') {
      zodParts.push('.email()');
    } else if (part === 'url') {
      zodParts.push('.url()');
    } else if (part === 'date') {
      zodParts.push('z.string().datetime()');
    } else if (part === 'array') {
      zodParts.push('z.array(z.any())');
    } else if (part.startsWith('max:')) {
      const max = part.split(':')[1];
      zodParts.push(`.max(${max})`);
    } else if (part.startsWith('min:')) {
      const min = part.split(':')[1];
      zodParts.push(`.min(${min})`);
    } else if (part.startsWith('in:')) {
      const values = part.split(':')[1].split(',');
      const enumValues = values.map(v => `"${v}"`).join(', ');
      zodParts = [`z.enum([${enumValues}])`];
    } else if (part.startsWith('exists:')) {
      // For exists validation, we'll just use string
      if (!zodParts.some(p => p.includes('z.string'))) {
        zodParts.push('z.string()');
      }
    }
  }
  
  // Build the zod chain
  let zodChain = zodParts.join('');
  if (zodChain === '') {
    zodChain = 'z.string()';
  }
  
  if (isOptional) {
    zodChain += '.optional()';
  }
  
  return zodChain;
}

function generateEnumConstants(enums: any): string {
  let output = `// Generated enum constants from database\n\n`;
  
  for (const [table, tableEnums] of Object.entries(enums)) {
    if (!tableEnums || typeof tableEnums !== 'object') continue;
    
    const tableName = table.toUpperCase();
    
    for (const [field, values] of Object.entries(tableEnums)) {
      if (!Array.isArray(values)) continue;
      
      const fieldName = field.toUpperCase();
      output += `export const ${tableName}_${fieldName} = {\n`;
      
      for (const value of values) {
        const key = value.toString().toUpperCase().replace(/\s+/g, '_').replace(/[^A-Z0-9_]/g, '');
        output += `  ${key}: '${value}',\n`;
      }
      
      output += `} as const;\n\n`;
      
      output += `export type ${tableName}_${fieldName}_TYPE = typeof ${tableName}_${fieldName}[keyof typeof ${tableName}_${fieldName}];\n\n`;
    }
  }
  
  return output;
}

function generateFieldMappingHelper(fieldMapping: any): string {
  let output = `// Field mapping helper - shows common field naming patterns\n\n`;
  
  output += `export const FIELD_MAPPING = ${JSON.stringify(fieldMapping, null, 2)} as const;\n\n`;
  
  output += `// Helper to get user-friendly label for a field\n`;
  output += `export function getFieldLabel(table: string, field: string): string {\n`;
  output += `  const labels: Record<string, Record<string, string>> = {\n`;
  output += `    leads: {\n`;
  output += `      email1: 'Email',\n`;
  output += `      phone_work: 'Work Phone',\n`;
  output += `      phone_mobile: 'Mobile Phone',\n`;
  output += `      account_name: 'Company',\n`;
  output += `      primary_address_street: 'Street Address',\n`;
  output += `      primary_address_city: 'City',\n`;
  output += `      primary_address_state: 'State/Province',\n`;
  output += `      primary_address_postalcode: 'Postal Code',\n`;
  output += `      primary_address_country: 'Country',\n`;
  output += `    },\n`;
  output += `    contacts: {\n`;
  output += `      email1: 'Email',\n`;
  output += `      phone_work: 'Work Phone',\n`;
  output += `      phone_mobile: 'Mobile Phone',\n`;
  output += `    },\n`;
  output += `  };\n`;
  output += `  \n`;
  output += `  return labels[table]?.[field] || field;\n`;
  output += `};\n`;
  
  return output;
}

// Run the generation
generateTypes();