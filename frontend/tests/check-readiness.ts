#!/usr/bin/env node

/**
 * Quick readiness check for production
 */

import { execSync } from 'child_process';

console.log('🚀 Checking Frontend Production Readiness...\n');

const checks = {
  typescript: { name: 'TypeScript Compilation', passed: false },
  lint: { name: 'Linting', passed: false },
  build: { name: 'Production Build', passed: false },
  api: { name: 'API Client', passed: false },
  types: { name: 'Database Types', passed: false }
};

// Check TypeScript
try {
  execSync('npm run typecheck', { stdio: 'ignore' });
  checks.typescript.passed = true;
} catch (e) {
  // Failed
}

// Check Linting
try {
  execSync('npm run lint', { stdio: 'ignore' });
  checks.lint.passed = true;
} catch (e) {
  // Failed
}

// Check API Client exists
try {
  require.resolve('../src/api/client.ts');
  checks.api.passed = true;
} catch (e) {
  // Failed
}

// Check Database Types exist
try {
  require.resolve('../src/types/database.generated.ts');
  checks.types.passed = true;
} catch (e) {
  // Failed
}

// Check Build
try {
  console.log('Running production build (this may take a moment)...');
  execSync('npm run build', { stdio: 'ignore' });
  checks.build.passed = true;
} catch (e) {
  // Failed
}

// Results
console.log('\n📊 Frontend Readiness Report:\n');

let allPassed = true;
for (const check of Object.values(checks)) {
  console.log(`${check.passed ? '✅' : '❌'} ${check.name}`);
  if (!check.passed) allPassed = false;
}

console.log('\n' + '='.repeat(50));

if (allPassed) {
  console.log('✅ Frontend is READY for production!');
  console.log('\nNext steps:');
  console.log('1. Ensure backend is properly seeded with user data');
  console.log('2. Update login credentials in your .env file');
  console.log('3. Run integration tests with real backend');
} else {
  console.log('❌ Frontend needs fixes before production');
  console.log('\nFix the failing checks above and run this again.');
}

process.exit(allPassed ? 0 : 1);