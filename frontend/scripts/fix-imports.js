#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const glob = require('glob');

// Find all TypeScript/TSX files
const files = glob.sync('src/**/*.{ts,tsx}', { cwd: process.cwd() });

console.log(`Found ${files.length} TypeScript files to check...`);

let totalFixed = 0;

// Patterns to fix type imports
const importPatterns = [
  // Fix named imports that should be type imports
  {
    // Match: import { Something } from 'module' where Something ends with Props, Type, or is a known type
    pattern: /import\s*{\s*([^}]+)\s*}\s*from\s*['"]([^'"]+)['"]/g,
    fix: (match, imports, module) => {
      const importList = imports.split(',').map(imp => imp.trim());
      const typeImports = [];
      const regularImports = [];
      
      importList.forEach(imp => {
        // Check if this should be a type import
        if (shouldBeTypeImport(imp, module)) {
          typeImports.push(imp);
        } else {
          regularImports.push(imp);
        }
      });
      
      const results = [];
      if (typeImports.length > 0) {
        results.push(`import { ${typeImports.map(imp => `type ${imp}`).join(', ')} } from '${module}'`);
      }
      if (regularImports.length > 0) {
        results.push(`import { ${regularImports.join(', ')} } from '${module}'`);
      }
      
      return results.join('\n');
    }
  }
];

function shouldBeTypeImport(importName, moduleName) {
  // Clean up the import name (remove 'as' aliases)
  const cleanName = importName.split(' as ')[0].trim();
  
  // Known type imports
  const knownTypes = [
    'ColumnDef', 'VariantProps', 'AxiosInstance', 'AxiosError', 'LucideIcon',
    'UseQueryOptions', 'UseMutationOptions', 'ColumnFiltersState', 'SortingState',
    'VisibilityState'
  ];
  
  // Patterns that indicate types
  const typePatterns = [
    /Props$/,
    /Type$/,
    /Interface$/,
    /^I[A-Z]/, // Interface naming convention
    /State$/,
    /Options$/,
    /Config$/,
    /Schema$/
  ];
  
  // Check if it's a known type
  if (knownTypes.includes(cleanName)) {
    return true;
  }
  
  // Check if it matches type patterns
  return typePatterns.some(pattern => pattern.test(cleanName));
}

files.forEach(file => {
  const filePath = path.resolve(file);
  let content = fs.readFileSync(filePath, 'utf8');
  let modified = false;
  
  // Skip if already has type imports correctly
  if (content.includes('import { type ') || content.includes('import type {')) {
    return;
  }
  
  importPatterns.forEach(({ pattern, fix }) => {
    const newContent = content.replace(pattern, fix);
    if (newContent !== content) {
      content = newContent;
      modified = true;
    }
  });
  
  if (modified) {
    fs.writeFileSync(filePath, content);
    console.log(`✅ Fixed imports in: ${file}`);
    totalFixed++;
  }
});

console.log(`\n✨ Fixed ${totalFixed} files with import issues`);