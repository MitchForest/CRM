#!/usr/bin/env tsx

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

console.log('\n=== FRONTEND FIELD USAGE SCANNER ===\n');

// Frontend root path
const frontendPath = path.join(__dirname, '..');

// Load generated types
const generatedTypesPath = path.join(frontendPath, 'src/types/database.generated.ts');
const generatedTypesContent = fs.readFileSync(generatedTypesPath, 'utf8');

// Extract all field names from generated types
const fieldPattern = /(\w+):\s*(?:string|number|boolean|Date|any|null)/g;
const generatedFields = new Set<string>();
let match;
while ((match = fieldPattern.exec(generatedTypesContent)) !== null) {
    generatedFields.add(match[1]);
}

console.log(`Found ${generatedFields.size} fields in generated types\n`);

// Patterns to find field usage
const usagePatterns = [
    /\.(\w+)/g,                     // obj.field
    /\['(\w+)'\]/g,                 // obj['field']
    /\["(\w+)"\]/g,                 // obj["field"]
    /name=["'](\w+)["']/g,          // form field names
    /field=["'](\w+)["']/g,         // field props
    /dataIndex=["'](\w+)["']/g,     // table columns
];

// Fields to specifically look for
const problematicFields = [
    'converted',
    'converted_opp_id',
    'deleted',
    'scored_at',
    'date_scored'
];

interface Issue {
    field: string;
    line: number;
    context: string;
}

interface FileIssue {
    file: string;
    problems: Issue[];
}

interface Warning {
    file: string;
    field: string;
    line: number;
    message: string;
}

const issues: FileIssue[] = [];
const warnings: Warning[] = [];
const stats = {
    filesScanned: 0,
    fieldReferences: 0,
    unknownFields: 0,
    problematicFieldUsage: 0
};

// Scan all TypeScript/TSX files
function getAllTsFiles(dir: string, files: string[] = []): string[] {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    
    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);
        
        if (entry.isDirectory()) {
            if (!['node_modules', 'dist', 'build'].includes(entry.name)) {
                getAllTsFiles(fullPath, files);
            }
        } else if (entry.isFile() && (entry.name.endsWith('.ts') || entry.name.endsWith('.tsx'))) {
            if (!entry.name.endsWith('.generated.ts') && !entry.name.endsWith('.d.ts')) {
                files.push(fullPath);
            }
        }
    }
    
    return files;
}

const tsFiles = getAllTsFiles(path.join(frontendPath, 'src'));

console.log(`Scanning ${tsFiles.length} TypeScript files...\n`);

tsFiles.forEach(filePath => {
    const content = fs.readFileSync(filePath, 'utf8');
    const relativePath = path.relative(frontendPath, filePath);
    const lines = content.split('\n');
    
    stats.filesScanned++;
    
    // Track fields used in this file
    const fileFields = new Set<string>();
    const fileIssues: Issue[] = [];
    
    // Check for problematic fields
    problematicFields.forEach(field => {
        const regex = new RegExp(`\\b${field}\\b`, 'g');
        let match;
        while ((match = regex.exec(content)) !== null) {
            const lineNum = content.substring(0, match.index).split('\n').length;
            fileIssues.push({
                field,
                line: lineNum,
                context: lines[lineNum - 1].trim()
            });
            stats.problematicFieldUsage++;
        }
    });
    
    // Extract all field references
    usagePatterns.forEach(pattern => {
        let match;
        const regex = new RegExp(pattern);
        const contentCopy = content;
        regex.lastIndex = 0;
        while ((match = regex.exec(contentCopy)) !== null) {
            const field = match[1];
            if (field && /^[a-z_]+$/.test(field)) { // Only lowercase with underscores (snake_case)
                fileFields.add(field);
                stats.fieldReferences++;
            }
        }
    });
    
    // Check for unknown fields
    fileFields.forEach(field => {
        if (!generatedFields.has(field) && 
            !['id', 'created_at', 'updated_at', 'className', 'children', 'onClick', 'onChange', 'value', 'name', 'type', 'style'].includes(field)) {
            const fieldIndex = content.indexOf(field);
            if (fieldIndex !== -1) {
                const lineNum = content.substring(0, fieldIndex).split('\n').length;
                warnings.push({
                    file: relativePath,
                    field,
                    line: lineNum,
                    message: `Field '${field}' not found in generated types`
                });
                stats.unknownFields++;
            }
        }
    });
    
    // Report issues for this file
    if (fileIssues.length > 0) {
        issues.push({
            file: relativePath,
            problems: fileIssues
        });
    }
});

// Generate report
console.log('\n=== SCAN SUMMARY ===');
console.log('='.repeat(50));
console.log(`Files scanned: ${stats.filesScanned}`);
console.log(`Total field references: ${stats.fieldReferences}`);
console.log(`Unknown fields: ${stats.unknownFields}`);
console.log(`Problematic field usage: ${stats.problematicFieldUsage}`);

if (issues.length > 0) {
    console.log('\n\n=== CRITICAL ISSUES ===');
    issues.forEach(issue => {
        console.log(`\n❌ ${issue.file}`);
        issue.problems.forEach(problem => {
            console.log(`   Line ${problem.line}: References '${problem.field}'`);
            console.log(`   Context: ${problem.context}`);
        });
    });
}

if (warnings.length > 0) {
    console.log('\n\n=== WARNINGS ===');
    const warningsByFile: Record<string, Warning[]> = {};
    warnings.forEach(warning => {
        if (!warningsByFile[warning.file]) {
            warningsByFile[warning.file] = [];
        }
        warningsByFile[warning.file].push(warning);
    });
    
    Object.entries(warningsByFile).forEach(([file, fileWarnings]) => {
        console.log(`\n⚠️  ${file}`);
        fileWarnings.forEach(warning => {
            console.log(`   Line ${warning.line}: ${warning.message}`);
        });
    });
}

// Write detailed report
const reportPath = path.join(__dirname, '../backend/tests/audit/frontend-scan-report.txt');
let report = 'Frontend Field Usage Scan Report\n';
report += `Generated: ${new Date().toISOString()}\n\n`;
report += 'STATISTICS:\n';
Object.entries(stats).forEach(([key, value]) => {
    report += `  ${key}: ${value}\n`;
});

if (issues.length > 0) {
    report += '\n\nCRITICAL ISSUES:\n';
    issues.forEach(issue => {
        report += `\nFile: ${issue.file}\n`;
        issue.problems.forEach(problem => {
            report += `  - Line ${problem.line}: References '${problem.field}'\n`;
        });
    });
}

fs.writeFileSync(reportPath, report);
console.log(`\n\nDetailed report written to: backend/tests/audit/frontend-scan-report.txt`);

// Exit with error code if issues found
if (issues.length > 0 || stats.unknownFields > 0) {
    process.exit(1);
}