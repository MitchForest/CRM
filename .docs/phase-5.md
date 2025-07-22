# Phase 5: Polish & Deploy - Implementation Plan

## Overview

Phase 5 focuses on production readiness, including performance optimization, comprehensive testing, security hardening, documentation, and deployment infrastructure. By the end of this phase, the CRM will be ready for production use with proper monitoring, backup strategies, and user training materials.

**Duration**: 2 weeks (Weeks 11-12)  
**Team Size**: 2-3 developers + 1 DevOps engineer  
**Prerequisites**: Phases 1-4 completed

## Week 11: Performance & Security

### Day 1-2: Frontend Performance Optimization

#### 1. Code Splitting & Lazy Loading
```typescript
// frontend/src/App.tsx - Optimized with lazy loading
import { lazy, Suspense } from 'react'
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClient } from '@/lib/query-client'
import { ErrorBoundary } from '@/components/ErrorBoundary'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { Layout } from '@/components/layout/Layout'
import { PageLoader } from '@/components/ui/page-loader'

// Eager load critical pages
import { LoginPage } from '@/pages/Login'
import { DashboardPage } from '@/pages/Dashboard'

// Lazy load other pages
const ContactsPage = lazy(() => import('@/pages/Contacts'))
const ContactDetailPage = lazy(() => import('@/pages/ContactDetail'))
const LeadsPage = lazy(() => import('@/pages/Leads'))
const OpportunitiesPage = lazy(() => import('@/pages/Opportunities'))
const QuotesPage = lazy(() => import('@/pages/Quotes'))
const CasesPage = lazy(() => import('@/pages/Cases'))
const ActivitiesPage = lazy(() => import('@/pages/Activities'))
const SettingsPage = lazy(() => import('@/pages/Settings'))

export function App() {
  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <Router>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            
            <Route element={
              <ProtectedRoute>
                <Layout />
              </ProtectedRoute>
            }>
              <Route path="/" element={<DashboardPage />} />
              
              <Route path="/contacts" element={
                <Suspense fallback={<PageLoader />}>
                  <ContactsPage />
                </Suspense>
              } />
              
              <Route path="/contacts/:id" element={
                <Suspense fallback={<PageLoader />}>
                  <ContactDetailPage />
                </Suspense>
              } />
              
              <Route path="/leads" element={
                <Suspense fallback={<PageLoader />}>
                  <LeadsPage />
                </Suspense>
              } />
              
              <Route path="/opportunities" element={
                <Suspense fallback={<PageLoader />}>
                  <OpportunitiesPage />
                </Suspense>
              } />
              
              <Route path="/activities" element={
                <Suspense fallback={<PageLoader />}>
                  <ActivitiesPage />
                </Suspense>
              } />
              
              <Route path="/quotes" element={
                <Suspense fallback={<PageLoader />}>
                  <QuotesPage />
                </Suspense>
              } />
              
              <Route path="/cases" element={
                <Suspense fallback={<PageLoader />}>
                  <CasesPage />
                </Suspense>
              } />
              
              <Route path="/settings/*" element={
                <Suspense fallback={<PageLoader />}>
                  <SettingsPage />
                </Suspense>
              } />
            </Route>
            
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Router>
        
        {import.meta.env.DEV && (
          <Suspense fallback={null}>
            <ReactQueryDevtools />
          </Suspense>
        )}
      </QueryClientProvider>
    </ErrorBoundary>
  )
}

// Preload critical chunks
const preloadCriticalChunks = () => {
  // Preload contacts page as it's commonly accessed after dashboard
  import('@/pages/Contacts')
  import('@/pages/Opportunities')
}

// Call after app initialization
if (typeof window !== 'undefined') {
  window.addEventListener('load', () => {
    // Give the browser time to idle
    requestIdleCallback(() => {
      preloadCriticalChunks()
    })
  })
}
```

#### 2. Bundle Optimization
```javascript
// frontend/vite.config.ts - Production optimizations
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import { visualizer } from 'rollup-plugin-visualizer'
import viteCompression from 'vite-plugin-compression'

export default defineConfig(({ mode }) => ({
  plugins: [
    react(),
    // Gzip compression
    viteCompression({
      algorithm: 'gzip',
      ext: '.gz',
    }),
    // Brotli compression
    viteCompression({
      algorithm: 'brotliCompress',
      ext: '.br',
    }),
    // Bundle visualization
    mode === 'analyze' && visualizer({
      open: true,
      filename: 'dist/bundle-analysis.html',
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  build: {
    target: 'es2020',
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
      },
    },
    rollupOptions: {
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom', 'react-router-dom'],
          'ui-vendor': ['@radix-ui/react-dialog', '@radix-ui/react-dropdown-menu', '@radix-ui/react-tabs'],
          'utils-vendor': ['date-fns', 'clsx', 'tailwind-merge'],
          'chart-vendor': ['recharts'],
        },
      },
    },
    // Enable CSS code splitting
    cssCodeSplit: true,
    // Increase chunk size warning limit
    chunkSizeWarningLimit: 1000,
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: process.env.VITE_API_URL || 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/custom/api'),
      },
    },
  },
}))
```

#### 3. Image Optimization Component
```typescript
// frontend/src/components/ui/optimized-image.tsx
import { useState, useEffect } from 'react'
import { cn } from '@/lib/utils'

interface OptimizedImageProps extends React.ImgHTMLAttributes<HTMLImageElement> {
  src: string
  fallback?: string
  lazy?: boolean
}

export function OptimizedImage({ 
  src, 
  fallback = '/placeholder.png',
  lazy = true,
  className,
  alt,
  ...props 
}: OptimizedImageProps) {
  const [imageSrc, setImageSrc] = useState(lazy ? fallback : src)
  const [isLoading, setIsLoading] = useState(lazy)
  const [hasError, setHasError] = useState(false)

  useEffect(() => {
    if (!lazy) return

    const imageObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setImageSrc(src)
            setIsLoading(false)
            imageObserver.disconnect()
          }
        })
      },
      { threshold: 0.1 }
    )

    const imgElement = document.querySelector(`[data-src="${src}"]`)
    if (imgElement) {
      imageObserver.observe(imgElement)
    }

    return () => {
      imageObserver.disconnect()
    }
  }, [src, lazy])

  return (
    <img
      {...props}
      src={hasError ? fallback : imageSrc}
      alt={alt}
      data-src={src}
      className={cn(
        'transition-opacity duration-300',
        isLoading ? 'opacity-0' : 'opacity-100',
        className
      )}
      onError={() => setHasError(true)}
      loading={lazy ? 'lazy' : undefined}
    />
  )
}
```

### Day 3: Backend Performance Optimization

#### 1. API Response Caching
```php
// backend/custom/api/middleware/CacheMiddleware.php
<?php
namespace Api\Middleware;

use Api\Request;
use Api\Response;

class CacheMiddleware {
    private $redis;
    private $cacheDuration = 300; // 5 minutes default
    
    // Cache configuration per endpoint
    private $cacheConfig = [
        'GET:/dashboard/stats' => 300,
        'GET:/contacts' => 60,
        'GET:/opportunities' => 60,
        'GET:/activities' => 30,
    ];
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', 6379);
    }
    
    public function handle(Request $request) {
        // Only cache GET requests
        if ($request->getMethod() !== 'GET') {
            return true;
        }
        
        $cacheKey = $this->getCacheKey($request);
        $cachedResponse = $this->redis->get($cacheKey);
        
        if ($cachedResponse !== false) {
            // Add cache headers
            header('X-Cache: HIT');
            header('Content-Type: application/json');
            echo $cachedResponse;
            return false; // Stop processing
        }
        
        // Continue processing and cache the response
        return true;
    }
    
    public function cacheResponse(Request $request, $response) {
        if ($request->getMethod() !== 'GET') {
            return;
        }
        
        $route = $request->getMethod() . ':' . $request->getPath();
        $duration = $this->cacheConfig[$route] ?? $this->cacheDuration;
        
        $cacheKey = $this->getCacheKey($request);
        $this->redis->setex($cacheKey, $duration, json_encode($response));
    }
    
    private function getCacheKey(Request $request) {
        $user = $request->user ?? null;
        $userId = $user ? $user->id : 'anonymous';
        
        return sprintf(
            'api:cache:%s:%s:%s',
            $userId,
            $request->getPath(),
            md5(json_encode($request->getQuery()))
        );
    }
    
    public function invalidateCache($pattern) {
        $keys = $this->redis->keys("api:cache:*$pattern*");
        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }
}
```

#### 2. Database Query Optimization
```php
// backend/custom/api/services/QueryOptimizer.php
<?php
namespace Api\Services;

class QueryOptimizer {
    private $db;
    private $queryCache = [];
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Optimize contact queries with eager loading
     */
    public function getContactsOptimized($filters = [], $limit = 20, $offset = 0) {
        // Build optimized query with necessary joins
        $query = "SELECT 
            c.id,
            c.first_name,
            c.last_name,
            c.email1,
            c.phone_mobile,
            c.date_entered,
            c.date_modified,
            -- Aggregate activity data
            (SELECT COUNT(*) FROM tasks t 
             WHERE t.contact_id = c.id AND t.deleted = 0) as task_count,
            (SELECT MAX(t.date_entered) FROM tasks t 
             WHERE t.contact_id = c.id AND t.deleted = 0) as last_task_date,
            (SELECT COUNT(*) FROM emails e 
             WHERE e.parent_type = 'Contacts' AND e.parent_id = c.id AND e.deleted = 0) as email_count,
            -- Calculate engagement score
            CASE 
                WHEN DATEDIFF(NOW(), c.date_modified) < 7 THEN 100
                WHEN DATEDIFF(NOW(), c.date_modified) < 30 THEN 75
                WHEN DATEDIFF(NOW(), c.date_modified) < 90 THEN 50
                ELSE 25
            END as engagement_score
        FROM contacts c
        WHERE c.deleted = 0";
        
        // Add filters
        if (!empty($filters)) {
            $whereConditions = $this->buildWhereConditions($filters);
            if ($whereConditions) {
                $query .= " AND $whereConditions";
            }
        }
        
        $query .= " ORDER BY c.date_modified DESC";
        $query .= " LIMIT $limit OFFSET $offset";
        
        // Use query result caching
        $cacheKey = md5($query);
        if (isset($this->queryCache[$cacheKey])) {
            return $this->queryCache[$cacheKey];
        }
        
        $result = $this->db->query($query);
        $contacts = [];
        
        while ($row = $this->db->fetchByAssoc($result)) {
            $contacts[] = $row;
        }
        
        $this->queryCache[$cacheKey] = $contacts;
        return $contacts;
    }
    
    /**
     * Create database indexes for performance
     */
    public function createPerformanceIndexes() {
        $indexes = [
            "CREATE INDEX idx_contacts_email ON contacts(email1)",
            "CREATE INDEX idx_contacts_date_modified ON contacts(date_modified)",
            "CREATE INDEX idx_tasks_contact_date ON tasks(contact_id, date_entered)",
            "CREATE INDEX idx_emails_parent ON emails(parent_type, parent_id, date_entered)",
            "CREATE INDEX idx_opportunities_stage_date ON opportunities(sales_stage, date_closed)",
            "CREATE INDEX idx_leads_status_score ON leads(status, score)",
            "CREATE INDEX idx_activities_date ON activities(date_entered)",
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->db->query($index);
            } catch (\Exception $e) {
                // Index might already exist
                error_log("Index creation failed: " . $e->getMessage());
            }
        }
    }
}
```

### Day 4-5: Security Hardening

#### 1. Security Headers & CORS
```php
// backend/custom/api/middleware/SecurityMiddleware.php
<?php
namespace Api\Middleware;

use Api\Request;

class SecurityMiddleware {
    private $allowedOrigins;
    private $rateLimiter;
    
    public function __construct() {
        $this->allowedOrigins = explode(',', $_ENV['ALLOWED_ORIGINS'] ?? 'http://localhost:3000');
        $this->rateLimiter = new RateLimiter();
    }
    
    public function handle(Request $request) {
        // Set security headers
        $this->setSecurityHeaders($request);
        
        // Check rate limiting
        if (!$this->rateLimiter->checkLimit($request)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Too many requests']);
            return false;
        }
        
        // Validate request
        if (!$this->validateRequest($request)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid request']);
            return false;
        }
        
        return true;
    }
    
    private function setSecurityHeaders(Request $request) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // CORS headers
        if (in_array($origin, $this->allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Max-Age: 86400");
        
        // Security headers
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;");
        
        // Remove server information
        header_remove("X-Powered-By");
        header_remove("Server");
    }
    
    private function validateRequest(Request $request) {
        // Validate JSON payload size
        if ($request->getMethod() === 'POST' || $request->getMethod() === 'PUT') {
            $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            if ($contentLength > $maxSize) {
                return false;
            }
        }
        
        // Validate input data
        $data = $request->getData();
        if (!$this->validateInput($data)) {
            return false;
        }
        
        return true;
    }
    
    private function validateInput($data) {
        if (!is_array($data)) {
            return true;
        }
        
        foreach ($data as $key => $value) {
            // Check for SQL injection patterns
            if (is_string($value)) {
                $suspicious = [
                    '/\bUNION\b.*\bSELECT\b/i',
                    '/\bDROP\b.*\bTABLE\b/i',
                    '/\bINSERT\b.*\bINTO\b/i',
                    '/\bDELETE\b.*\bFROM\b/i',
                    '/<script[^>]*>.*?<\/script>/is',
                ];
                
                foreach ($suspicious as $pattern) {
                    if (preg_match($pattern, $value)) {
                        error_log("Suspicious input detected: $value");
                        return false;
                    }
                }
            } elseif (is_array($value)) {
                if (!$this->validateInput($value)) {
                    return false;
                }
            }
        }
        
        return true;
    }
}

class RateLimiter {
    private $redis;
    private $limits = [
        'default' => ['requests' => 100, 'window' => 60],
        'auth' => ['requests' => 5, 'window' => 300],
        'ai' => ['requests' => 20, 'window' => 3600],
    ];
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'localhost', 6379);
    }
    
    public function checkLimit(Request $request) {
        $user = $request->user ?? null;
        $key = $this->getRateLimitKey($request, $user);
        $limit = $this->getLimit($request->getPath());
        
        $current = $this->redis->incr($key);
        
        if ($current === 1) {
            $this->redis->expire($key, $limit['window']);
        }
        
        if ($current > $limit['requests']) {
            return false;
        }
        
        // Add rate limit headers
        header("X-RateLimit-Limit: {$limit['requests']}");
        header("X-RateLimit-Remaining: " . max(0, $limit['requests'] - $current));
        header("X-RateLimit-Reset: " . (time() + $this->redis->ttl($key)));
        
        return true;
    }
    
    private function getRateLimitKey(Request $request, $user) {
        $userId = $user ? $user->id : $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $endpoint = $request->getPath();
        
        return "rate_limit:$userId:$endpoint";
    }
    
    private function getLimit($path) {
        if (strpos($path, '/auth') === 0) {
            return $this->limits['auth'];
        }
        
        if (strpos($path, '/ai') === 0) {
            return $this->limits['ai'];
        }
        
        return $this->limits['default'];
    }
}
```

#### 2. Frontend Security Utils
```typescript
// frontend/src/lib/security.ts
import DOMPurify from 'dompurify'

/**
 * Sanitize HTML content to prevent XSS
 */
export function sanitizeHTML(dirty: string): string {
  return DOMPurify.sanitize(dirty, {
    ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a', 'p', 'br'],
    ALLOWED_ATTR: ['href', 'target'],
  })
}

/**
 * Validate email format
 */
export function isValidEmail(email: string): boolean {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

/**
 * Escape special characters for display
 */
export function escapeHtml(unsafe: string): string {
  return unsafe
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

/**
 * Generate secure random string
 */
export function generateSecureRandom(length: number = 32): string {
  const array = new Uint8Array(length)
  crypto.getRandomValues(array)
  return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('')
}

/**
 * Content Security Policy configuration
 */
export const CSP_HEADER = {
  'default-src': ["'self'"],
  'script-src': ["'self'", "'unsafe-inline'", 'https://cdnjs.cloudflare.com'],
  'style-src': ["'self'", "'unsafe-inline'"],
  'img-src': ["'self'", 'data:', 'https:'],
  'font-src': ["'self'", 'data:'],
  'connect-src': ["'self'", process.env.VITE_API_URL],
  'frame-ancestors': ["'none'"],
  'base-uri': ["'self'"],
  'form-action': ["'self'"],
}

/**
 * Input validation schemas
 */
export const ValidationSchemas = {
  password: {
    minLength: 8,
    requireUppercase: true,
    requireLowercase: true,
    requireNumbers: true,
    requireSpecialChars: true,
  },
  
  username: {
    minLength: 3,
    maxLength: 20,
    pattern: /^[a-zA-Z0-9_]+$/,
  },
}

/**
 * Secure storage wrapper
 */
export class SecureStorage {
  private static encrypt(data: string): string {
    // In production, use a proper encryption library
    return btoa(data)
  }
  
  private static decrypt(data: string): string {
    return atob(data)
  }
  
  static setItem(key: string, value: any): void {
    const encrypted = this.encrypt(JSON.stringify(value))
    localStorage.setItem(key, encrypted)
  }
  
  static getItem(key: string): any {
    const encrypted = localStorage.getItem(key)
    if (!encrypted) return null
    
    try {
      return JSON.parse(this.decrypt(encrypted))
    } catch {
      return null
    }
  }
  
  static removeItem(key: string): void {
    localStorage.removeItem(key)
  }
  
  static clear(): void {
    localStorage.clear()
  }
}
```

## Week 12: Testing, Documentation & Deployment

### Day 1: Comprehensive Testing

#### 1. E2E Test Suite
```typescript
// frontend/cypress/e2e/critical-flows.cy.ts
describe('Critical User Flows', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  describe('Authentication Flow', () => {
    it('should login successfully with valid credentials', () => {
      cy.visit('/login')
      cy.get('[data-testid="username-input"]').type('admin')
      cy.get('[data-testid="password-input"]').type('admin123')
      cy.get('[data-testid="login-button"]').click()
      
      cy.url().should('eq', Cypress.config().baseUrl + '/')
      cy.contains('Dashboard').should('be.visible')
    })

    it('should handle invalid credentials', () => {
      cy.visit('/login')
      cy.get('[data-testid="username-input"]').type('invalid')
      cy.get('[data-testid="password-input"]').type('wrong')
      cy.get('[data-testid="login-button"]').click()
      
      cy.contains('Invalid credentials').should('be.visible')
    })

    it('should logout successfully', () => {
      cy.login('admin', 'admin123')
      cy.get('[data-testid="logout-button"]').click()
      cy.url().should('include', '/login')
    })
  })

  describe('Contact Management', () => {
    beforeEach(() => {
      cy.login('admin', 'admin123')
    })

    it('should create a new contact', () => {
      cy.visit('/contacts')
      cy.get('[data-testid="add-contact-button"]').click()
      
      cy.get('[data-testid="first-name-input"]').type('John')
      cy.get('[data-testid="last-name-input"]').type('Doe')
      cy.get('[data-testid="email-input"]').type('john.doe@example.com')
      cy.get('[data-testid="save-button"]').click()
      
      cy.contains('Contact created successfully').should('be.visible')
      cy.contains('John Doe').should('be.visible')
    })

    it('should search for contacts', () => {
      cy.visit('/contacts')
      cy.get('[data-testid="search-input"]').type('john')
      cy.get('[data-testid="search-button"]').click()
      
      cy.get('[data-testid="contact-list"]').should('contain', 'John')
    })

    it('should view contact details', () => {
      cy.visit('/contacts')
      cy.get('[data-testid="contact-list"] tr').first().click()
      
      cy.url().should('match', /\/contacts\/[\w-]+$/)
      cy.contains('Contact Information').should('be.visible')
      cy.contains('Activity Timeline').should('be.visible')
    })
  })

  describe('Lead Conversion', () => {
    beforeEach(() => {
      cy.login('admin', 'admin123')
    })

    it('should convert lead to contact', () => {
      cy.visit('/leads')
      cy.get('[data-testid="lead-list"] tr').first().within(() => {
        cy.get('[data-testid="actions-menu"]').click()
      })
      cy.contains('Convert to Contact').click()
      
      cy.get('[data-testid="create-opportunity-checkbox"]').check()
      cy.get('[data-testid="opportunity-name-input"]').should('be.visible')
      cy.get('[data-testid="convert-button"]').click()
      
      cy.contains('Lead converted successfully').should('be.visible')
      cy.url().should('include', '/contacts/')
    })
  })

  describe('Pipeline Management', () => {
    beforeEach(() => {
      cy.login('admin', 'admin123')
    })

    it('should drag opportunity between stages', () => {
      cy.visit('/opportunities')
      
      // Find an opportunity card
      cy.get('[data-testid="opportunity-card"]').first().as('opportunityCard')
      
      // Drag to next stage
      cy.get('@opportunityCard').drag('[data-testid="stage-negotiation"]')
      
      // Verify stage update
      cy.get('[data-testid="stage-negotiation"]').should('contain', '@opportunityCard')
    })
  })

  describe('AI Features', () => {
    beforeEach(() => {
      cy.login('admin', 'admin123')
    })

    it('should enrich contact data', () => {
      cy.visit('/contacts/123')
      cy.get('[data-testid="enrich-button"]').click()
      
      cy.contains('Enriching contact...', { timeout: 10000 }).should('be.visible')
      cy.contains('Enrichment complete').should('be.visible')
      
      // Check for enriched data
      cy.contains('Company Information').should('be.visible')
    })

    it('should generate email with AI', () => {
      cy.visit('/contacts/123')
      cy.get('[data-testid="compose-email-button"]').click()
      
      cy.get('[data-testid="ai-assist-tab"]').click()
      cy.get('[data-testid="email-purpose-input"]').type('Schedule a demo')
      cy.get('[data-testid="generate-email-button"]').click()
      
      cy.get('[data-testid="email-body"]', { timeout: 10000 })
        .should('not.be.empty')
    })
  })
})

// Custom commands
Cypress.Commands.add('login', (username: string, password: string) => {
  cy.session([username, password], () => {
    cy.visit('/login')
    cy.get('[data-testid="username-input"]').type(username)
    cy.get('[data-testid="password-input"]').type(password)
    cy.get('[data-testid="login-button"]').click()
    cy.url().should('eq', Cypress.config().baseUrl + '/')
  })
})

// Drag and drop command
Cypress.Commands.add('drag', { prevSubject: true }, (subject, target) => {
  cy.wrap(subject).trigger('dragstart')
  cy.get(target).trigger('drop')
  cy.get(target).trigger('dragend')
})
```

#### 2. Performance Testing
```javascript
// tests/performance/lighthouse.config.js
module.exports = {
  ci: {
    collect: {
      url: [
        'http://localhost:3000/',
        'http://localhost:3000/contacts',
        'http://localhost:3000/opportunities',
      ],
      numberOfRuns: 3,
      settings: {
        preset: 'desktop',
        throttling: {
          cpuSlowdownMultiplier: 1,
        },
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['error', { minScore: 0.9 }],
        'categories:accessibility': ['warn', { minScore: 0.9 }],
        'categories:best-practices': ['warn', { minScore: 0.9 }],
        'categories:seo': ['warn', { minScore: 0.9 }],
        'first-contentful-paint': ['error', { maxNumericValue: 2000 }],
        'largest-contentful-paint': ['error', { maxNumericValue: 3000 }],
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.1 }],
        'total-blocking-time': ['error', { maxNumericValue: 300 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
}
```

### Day 2: Documentation

#### 1. API Documentation
```yaml
# docs/api-documentation.yaml
openapi: 3.0.0
info:
  title: SuiteCRM B2C API
  version: 1.0.0
  description: REST API for SuiteCRM B2C CRM

servers:
  - url: https://api.yourdomain.com/v1
    description: Production server
  - url: http://localhost:8080/custom/api
    description: Development server

components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT

  schemas:
    Contact:
      type: object
      properties:
        id:
          type: string
          format: uuid
        firstName:
          type: string
        lastName:
          type: string
        email:
          type: string
          format: email
        engagementScore:
          type: integer
          minimum: 0
          maximum: 100
        churnRisk:
          type: string
          enum: [low, medium, high]

    Lead:
      type: object
      properties:
        id:
          type: string
          format: uuid
        firstName:
          type: string
        lastName:
          type: string
        email:
          type: string
          format: email
        score:
          type: integer
          minimum: 0
          maximum: 100
        source:
          type: string
          enum: [website, trial, webinar, referral, ad]

paths:
  /auth/login:
    post:
      summary: User login
      tags:
        - Authentication
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                username:
                  type: string
                password:
                  type: string
      responses:
        200:
          description: Successful login
          content:
            application/json:
              schema:
                type: object
                properties:
                  accessToken:
                    type: string
                  refreshToken:
                    type: string
                  user:
                    $ref: '#/components/schemas/User'

  /contacts:
    get:
      summary: List contacts
      tags:
        - Contacts
      security:
        - bearerAuth: []
      parameters:
        - name: page
          in: query
          schema:
            type: integer
            default: 1
        - name: limit
          in: query
          schema:
            type: integer
            default: 20
            maximum: 100
      responses:
        200:
          description: List of contacts
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Contact'
                  pagination:
                    type: object
                    properties:
                      page:
                        type: integer
                      limit:
                        type: integer
                      total:
                        type: integer
                      pages:
                        type: integer
```

#### 2. User Guide
```markdown
# SuiteCRM B2C User Guide

## Table of Contents
1. Getting Started
2. Dashboard Overview
3. Managing Contacts
4. Lead Management
5. Opportunity Pipeline
6. AI Features
7. Email Management
8. Reports & Analytics

## 1. Getting Started

### Logging In
1. Navigate to your CRM URL
2. Enter your username and password
3. Click "Sign In"

### First Time Setup
After logging in for the first time:
1. Complete your profile in Settings
2. Configure your email preferences
3. Set up AI features (optional)

## 2. Dashboard Overview

The dashboard provides a quick overview of your sales activities:

- **Key Metrics**: Total contacts, active trials, monthly revenue, conversion rate
- **Sales Pipeline**: Visual representation of opportunities by stage
- **Recent Activities**: Timeline of recent customer interactions
- **AI Insights**: Smart recommendations and alerts

### Customizing Your Dashboard
1. Click the settings icon in the top right
2. Select "Customize Dashboard"
3. Drag and drop widgets to rearrange
4. Click "Save Layout"

## 3. Managing Contacts

### Creating a Contact
1. Navigate to Contacts
2. Click "Add Contact"
3. Fill in the required fields:
   - First Name
   - Last Name
   - Email
4. Click "Save"

### Contact Enrichment
Our AI can automatically enrich contact data:
1. Open a contact record
2. Click "Enrich with AI"
3. Review the enriched information
4. Click "Accept" to update the contact

### Activity Timeline
Each contact has a unified activity timeline showing:
- Emails sent and received
- Calls logged
- Meetings scheduled
- Tasks created
- Notes added

## 4. Lead Management

### Lead Scoring
Leads are automatically scored based on:
- Email engagement
- Website activity
- Company fit
- Behavioral signals

### Converting Leads
To convert a lead to a contact:
1. Open the lead record
2. Click "Convert Lead"
3. Choose whether to create an opportunity
4. Click "Convert"

## 5. AI Features

### Email Assistant
1. Click "Compose Email"
2. Switch to "AI Assisted" tab
3. Enter the purpose and key points
4. Click "Generate Email"
5. Review and customize the draft
6. Send when ready

### Churn Prediction
For active customers, the AI monitors:
- Engagement patterns
- Support ticket sentiment
- Usage trends

High-risk customers are flagged with recommendations.

### Next Best Action
On each opportunity, AI suggests:
- What action to take next
- Why it's recommended
- Expected outcome

## Keyboard Shortcuts

- `Ctrl/Cmd + K`: Global search
- `Ctrl/Cmd + N`: Create new record
- `Ctrl/Cmd + S`: Save current form
- `Esc`: Close modal/dialog
```

### Day 3: Production Docker Setup

#### 1. Production Docker Compose
```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./nginx/ssl:/etc/nginx/ssl:ro
      - frontend_build:/usr/share/nginx/html:ro
    depends_on:
      - frontend
      - backend
    networks:
      - crm_network
    restart: always

  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile.prod
    volumes:
      - frontend_build:/app/dist
    environment:
      - NODE_ENV=production
      - VITE_API_URL=${API_URL}
    networks:
      - crm_network

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile.prod
    environment:
      - APP_ENV=production
      - DATABASE_URL=mysql://${DB_USER}:${DB_PASSWORD}@db:3306/${DB_NAME}
      - REDIS_URL=redis://cache:6379
      - JWT_SECRET=${JWT_SECRET}
      - OPENAI_API_KEY=${OPENAI_API_KEY}
    depends_on:
      - db
      - cache
    volumes:
      - ./backend/upload:/var/www/html/upload
      - ./backend/logs:/var/www/html/logs
    networks:
      - crm_network
    restart: always

  db:
    image: mariadb:10.11
    environment:
      - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
      - MYSQL_DATABASE=${DB_NAME}
      - MYSQL_USER=${DB_USER}
      - MYSQL_PASSWORD=${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
      - ./backup:/backup
    networks:
      - crm_network
    restart: always

  cache:
    image: redis:7-alpine
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks:
      - crm_network
    restart: always

  backup:
    build: ./docker/backup
    environment:
      - DB_HOST=db
      - DB_NAME=${DB_NAME}
      - DB_USER=${DB_USER}
      - DB_PASSWORD=${DB_PASSWORD}
      - S3_BUCKET=${BACKUP_S3_BUCKET}
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
    volumes:
      - ./backup:/backup
      - ./backend/upload:/var/www/html/upload:ro
    networks:
      - crm_network
    restart: always

volumes:
  frontend_build:
  db_data:
  redis_data:

networks:
  crm_network:
    driver: bridge
```

#### 2. Nginx Configuration
```nginx
# nginx/nginx.conf
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    # Logging
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';
    
    access_log /var/log/nginx/access.log main;

    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 50M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript 
               application/json application/javascript application/xml+rss 
               application/rss+xml application/atom+xml image/svg+xml;

    # SSL Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=auth:10m rate=5r/m;

    # Upstream servers
    upstream backend {
        server backend:80;
        keepalive 32;
    }

    # Redirect HTTP to HTTPS
    server {
        listen 80;
        server_name _;
        return 301 https://$host$request_uri;
    }

    # Main HTTPS server
    server {
        listen 443 ssl http2;
        server_name yourdomain.com;

        ssl_certificate /etc/nginx/ssl/cert.pem;
        ssl_certificate_key /etc/nginx/ssl/key.pem;

        # Security headers
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header X-XSS-Protection "1; mode=block" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;" always;

        # Frontend
        location / {
            root /usr/share/nginx/html;
            try_files $uri $uri/ /index.html;
            
            # Cache static assets
            location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
                expires 1y;
                add_header Cache-Control "public, immutable";
            }
        }

        # API proxy
        location /api/ {
            # Rate limiting
            limit_req zone=api burst=20 nodelay;
            
            # CORS headers
            add_header Access-Control-Allow-Origin $http_origin always;
            add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
            add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With" always;
            add_header Access-Control-Allow-Credentials "true" always;

            if ($request_method = OPTIONS) {
                return 204;
            }

            proxy_pass http://backend/custom/api/;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_set_header Connection "";
            
            # Timeouts
            proxy_connect_timeout 60s;
            proxy_send_timeout 60s;
            proxy_read_timeout 60s;
        }

        # Auth endpoints with stricter rate limiting
        location /api/auth/ {
            limit_req zone=auth burst=5 nodelay;
            
            proxy_pass http://backend/custom/api/auth/;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # Health check
        location /health {
            access_log off;
            return 200 "healthy\n";
            add_header Content-Type text/plain;
        }
    }
}
```

### Day 4: CI/CD Pipeline

#### 1. GitHub Actions Workflow
```yaml
# .github/workflows/deploy.yml
name: Build and Deploy

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: frontend/package-lock.json
      
      - name: Install frontend dependencies
        working-directory: frontend
        run: npm ci
      
      - name: Run frontend tests
        working-directory: frontend
        run: npm test
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql
      
      - name: Install backend dependencies
        working-directory: backend
        run: composer install --prefer-dist --no-progress
      
      - name: Run backend tests
        working-directory: backend
        run: ./vendor/bin/phpunit

  build:
    needs: test
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Log in to Container Registry
        uses: docker/login-action@v2
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      
      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v4
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
      
      - name: Build and push frontend image
        uses: docker/build-push-action@v4
        with:
          context: ./frontend
          file: ./frontend/Dockerfile.prod
          push: true
          tags: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}/frontend:latest
      
      - name: Build and push backend image
        uses: docker/build-push-action@v4
        with:
          context: ./backend
          file: ./backend/Dockerfile.prod
          push: true
          tags: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}/backend:latest

  deploy:
    needs: build
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
      - name: Deploy to production
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /opt/suitecrm
            docker-compose -f docker-compose.prod.yml pull
            docker-compose -f docker-compose.prod.yml up -d
            docker system prune -f
```

#### 2. Production Dockerfiles
```dockerfile
# frontend/Dockerfile.prod
FROM node:20-alpine AS builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy source code
COPY . .

# Build application
RUN npm run build

# Production stage
FROM nginx:alpine

# Copy built files
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy nginx config
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

```dockerfile
# backend/Dockerfile.prod
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache
RUN pecl install redis && docker-php-ext-enable redis

# Configure PHP
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Enable Apache modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s \
    CMD curl -f http://localhost/api/health || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
```

### Day 5: Migration & Launch

#### 1. Data Migration Script
```php
#!/usr/bin/env php
<?php
// scripts/migrate-from-suitecrm.php

require_once __DIR__ . '/../backend/vendor/autoload.php';

class SuiteCRMMigration {
    private $sourceDb;
    private $targetDb;
    private $batchSize = 1000;
    private $progress = [];
    
    public function __construct($sourceConfig, $targetConfig) {
        $this->sourceDb = new PDO(
            "mysql:host={$sourceConfig['host']};dbname={$sourceConfig['database']}",
            $sourceConfig['user'],
            $sourceConfig['password']
        );
        
        $this->targetDb = new PDO(
            "mysql:host={$targetConfig['host']};dbname={$targetConfig['database']}",
            $targetConfig['user'],
            $targetConfig['password']
        );
    }
    
    public function migrate() {
        $this->log("Starting SuiteCRM migration...");
        
        try {
            $this->migrateUsers();
            $this->migrateContacts();
            $this->migrateLeads();
            $this->migrateOpportunities();
            $this->migrateActivities();
            $this->migrateEmails();
            $this->cleanupData();
            
            $this->log("Migration completed successfully!");
            $this->printSummary();
        } catch (Exception $e) {
            $this->log("Migration failed: " . $e->getMessage(), 'error');
            exit(1);
        }
    }
    
    private function migrateContacts() {
        $this->log("Migrating contacts...");
        
        $count = $this->getCount('contacts');
        $offset = 0;
        
        while ($offset < $count) {
            $contacts = $this->sourceDb->query(
                "SELECT * FROM contacts 
                 WHERE deleted = 0 
                 LIMIT {$this->batchSize} OFFSET {$offset}"
            )->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($contacts as $contact) {
                // Transform data for B2C model
                $transformed = [
                    'id' => $contact['id'],
                    'first_name' => $contact['first_name'],
                    'last_name' => $contact['last_name'],
                    'email1' => $contact['email1'],
                    'phone_mobile' => $contact['phone_mobile'],
                    'date_entered' => $contact['date_entered'],
                    'date_modified' => $contact['date_modified'],
                    'description' => $contact['description'],
                    // Add B2C specific fields
                    'customer_since' => $contact['date_entered'],
                    'lifetime_value' => 0, // Calculate from opportunities
                    'engagement_score' => 50, // Default, will be recalculated
                    'subscription_status' => 'active',
                ];
                
                $this->insertRecord('contacts', $transformed);
            }
            
            $offset += $this->batchSize;
            $this->showProgress('contacts', $offset, $count);
        }
        
        $this->progress['contacts'] = $count;
    }
    
    private function insertRecord($table, $data) {
        $fields = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) 
             ON DUPLICATE KEY UPDATE %s",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders),
            implode(', ', array_map(fn($f) => "$f = VALUES($f)", $fields))
        );
        
        $stmt = $this->targetDb->prepare($sql);
        $stmt->execute($values);
    }
    
    private function cleanupData() {
        $this->log("Cleaning up data...");
        
        // Remove orphaned records
        $this->targetDb->exec("
            DELETE FROM tasks 
            WHERE contact_id NOT IN (SELECT id FROM contacts)
        ");
        
        // Update calculated fields
        $this->targetDb->exec("
            UPDATE contacts c
            SET lifetime_value = (
                SELECT COALESCE(SUM(amount), 0)
                FROM opportunities o
                WHERE o.contact_id = c.id
                AND o.sales_stage = 'Closed Won'
            )
        ");
    }
    
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] [$level] $message\n";
        
        // Also log to file
        file_put_contents(
            __DIR__ . '/migration.log',
            "[$timestamp] [$level] $message\n",
            FILE_APPEND
        );
    }
    
    private function showProgress($module, $current, $total) {
        $percentage = round(($current / $total) * 100);
        $bar = str_repeat('=', $percentage / 2) . str_repeat(' ', 50 - $percentage / 2);
        echo "\r$module: [$bar] $percentage% ($current/$total)";
        
        if ($current >= $total) {
            echo "\n";
        }
    }
    
    private function printSummary() {
        echo "\n\nMigration Summary:\n";
        echo "==================\n";
        foreach ($this->progress as $module => $count) {
            echo "$module: $count records\n";
        }
    }
}

// Run migration
$sourceConfig = [
    'host' => getenv('SOURCE_DB_HOST') ?: 'localhost',
    'database' => getenv('SOURCE_DB_NAME') ?: 'suitecrm_old',
    'user' => getenv('SOURCE_DB_USER') ?: 'root',
    'password' => getenv('SOURCE_DB_PASS') ?: '',
];

$targetConfig = [
    'host' => getenv('TARGET_DB_HOST') ?: 'localhost',
    'database' => getenv('TARGET_DB_NAME') ?: 'suitecrm_b2c',
    'user' => getenv('TARGET_DB_USER') ?: 'root',
    'password' => getenv('TARGET_DB_PASS') ?: '',
];

$migration = new SuiteCRMMigration($sourceConfig, $targetConfig);
$migration->migrate();
```

#### 2. Launch Checklist
```markdown
# SuiteCRM B2C Launch Checklist

## Pre-Launch (1 week before)

### Infrastructure
- [ ] Production servers provisioned
- [ ] SSL certificates installed
- [ ] Domain DNS configured
- [ ] CDN setup (CloudFlare/AWS CloudFront)
- [ ] Backup system tested
- [ ] Monitoring tools configured (Datadog/New Relic)

### Security
- [ ] Security audit completed
- [ ] Penetration testing performed
- [ ] OWASP Top 10 vulnerabilities checked
- [ ] API rate limiting tested
- [ ] CORS configuration verified
- [ ] Environment variables secured

### Performance
- [ ] Load testing completed (target: 1000 concurrent users)
- [ ] Database indexes optimized
- [ ] Redis caching verified
- [ ] CDN caching rules set
- [ ] Image optimization completed

### Data
- [ ] Production database created
- [ ] Data migration tested
- [ ] Backup/restore procedures verified
- [ ] Data retention policies implemented

## Launch Day

### Morning (6 AM - 12 PM)
- [ ] Final backup of old system
- [ ] Deploy production code
- [ ] Run database migrations
- [ ] Verify all services are running
- [ ] Test critical user flows
- [ ] Enable monitoring alerts

### Afternoon (12 PM - 6 PM)
- [ ] Gradual user migration (10% → 50% → 100%)
- [ ] Monitor system performance
- [ ] Check error logs
- [ ] Verify AI features working
- [ ] Test email delivery

### Evening (6 PM - 10 PM)
- [ ] Full system health check
- [ ] Review performance metrics
- [ ] Address any critical issues
- [ ] Update status page
- [ ] Send launch confirmation

## Post-Launch (Day 1-7)

### Day 1
- [ ] Monitor user feedback
- [ ] Check system stability
- [ ] Review error reports
- [ ] Analyze performance metrics
- [ ] Daily standup with team

### Day 2-3
- [ ] Address minor bugs
- [ ] Optimize slow queries
- [ ] User training sessions
- [ ] Documentation updates

### Day 4-7
- [ ] Gather user feedback
- [ ] Plan iteration 1 improvements
- [ ] Performance tuning
- [ ] Security monitoring
- [ ] Success metrics review

## Success Criteria
- ✓ 99.9% uptime in first week
- ✓ Page load time < 2 seconds
- ✓ Zero critical security incidents
- ✓ 90% user satisfaction score
- ✓ All data migrated successfully
```

## Deliverables Checklist

### Week 11 Deliverables
- [ ] Frontend Performance
  - [ ] Code splitting implemented
  - [ ] Lazy loading for routes
  - [ ] Bundle size < 500KB initial
  - [ ] Lighthouse score > 90
- [ ] Backend Performance
  - [ ] API response caching
  - [ ] Database query optimization
  - [ ] Redis caching layer
  - [ ] Rate limiting
- [ ] Security
  - [ ] Security headers configured
  - [ ] Input validation
  - [ ] XSS protection
  - [ ] CSRF protection
  - [ ] Rate limiting by endpoint

### Week 12 Deliverables
- [ ] Testing
  - [ ] E2E test suite complete
  - [ ] Performance benchmarks met
  - [ ] Security audit passed
- [ ] Documentation
  - [ ] API documentation
  - [ ] User guide
  - [ ] Admin guide
  - [ ] Developer docs
- [ ] Infrastructure
  - [ ] Production Docker setup
  - [ ] CI/CD pipeline
  - [ ] Monitoring configured
  - [ ] Backup system
- [ ] Migration
  - [ ] Migration scripts tested
  - [ ] Data validation complete
  - [ ] Rollback plan ready
- [ ] Launch
  - [ ] Launch checklist complete
  - [ ] Team training done
  - [ ] Support ready

## Monitoring & Maintenance

### Application Monitoring
- **Uptime**: Pingdom/UptimeRobot
- **Performance**: New Relic/Datadog
- **Errors**: Sentry
- **Analytics**: Google Analytics/Plausible

### Infrastructure Monitoring
- **Servers**: CPU, Memory, Disk
- **Database**: Query performance, connections
- **Redis**: Memory usage, hit rate
- **API**: Response times, error rates

### Maintenance Schedule
- **Daily**: Check monitoring dashboards
- **Weekly**: Review error logs, update dependencies
- **Monthly**: Security patches, performance review
- **Quarterly**: Major updates, feature releases

## Conclusion

Phase 5 completes the transformation of SuiteCRM into a production-ready, modern B2C CRM. The application is now:

1. **Performant**: Optimized for speed with caching and lazy loading
2. **Secure**: Hardened against common vulnerabilities
3. **Scalable**: Containerized with horizontal scaling capability
4. **Maintainable**: Comprehensive documentation and monitoring
5. **User-Friendly**: Tested and polished for excellent UX

The CRM is ready for production use with enterprise-grade reliability and modern development practices.