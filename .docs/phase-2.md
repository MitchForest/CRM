# Phase 2: Frontend Core - Implementation Plan

## Overview

Phase 2 establishes the React frontend foundation, implementing core UI components, authentication, and the essential pages for our B2C CRM. By the end of this phase, users will be able to log in, view their dashboard, manage contacts, and see the activity timeline.

**Duration**: 3 weeks (Weeks 4-6)  
**Team Size**: 1-2 frontend developers  
**Prerequisites**: Phase 1 completed with working API

## Week 4: React Foundation & Authentication

### Day 1: Project Setup

#### 1. Initialize Vite React Project
```bash
# From project root
cd frontend
npm create vite@latest . -- --template react-ts
npm install

# Install core dependencies
npm install axios zustand react-router-dom @tanstack/react-query
npm install @tanstack/react-table recharts date-fns
npm install react-hook-form @hookform/resolvers zod
npm install lucide-react clsx tailwind-merge

# Install dev dependencies
npm install -D @types/node @tailwindcss/forms @tailwindcss/typography
npm install -D prettier eslint-config-prettier autoprefixer
```

#### 2. Configure TypeScript
```json
// frontend/tsconfig.json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    "baseUrl": ".",
    "paths": {
      "@/*": ["./src/*"]
    }
  },
  "include": ["src"],
  "references": [{ "path": "./tsconfig.node.json" }]
}
```

#### 3. Setup Tailwind CSS
```bash
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p
```

```javascript
// frontend/tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ["class"],
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    container: {
      center: true,
      padding: "2rem",
      screens: {
        "2xl": "1400px",
      },
    },
    extend: {
      colors: {
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        popover: {
          DEFAULT: "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        card: {
          DEFAULT: "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      keyframes: {
        "accordion-down": {
          from: { height: 0 },
          to: { height: "var(--radix-accordion-content-height)" },
        },
        "accordion-up": {
          from: { height: "var(--radix-accordion-content-height)" },
          to: { height: 0 },
        },
      },
      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
      },
    },
  },
  plugins: [require("@tailwindcss/forms"), require("@tailwindcss/typography")],
}
```

#### 4. Global Styles
```css
/* frontend/src/index.css */
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  :root {
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    --card: 0 0% 100%;
    --card-foreground: 222.2 84% 4.9%;
    --popover: 0 0% 100%;
    --popover-foreground: 222.2 84% 4.9%;
    --primary: 222.2 47.4% 11.2%;
    --primary-foreground: 210 40% 98%;
    --secondary: 210 40% 96.1%;
    --secondary-foreground: 222.2 47.4% 11.2%;
    --muted: 210 40% 96.1%;
    --muted-foreground: 215.4 16.3% 46.9%;
    --accent: 210 40% 96.1%;
    --accent-foreground: 222.2 47.4% 11.2%;
    --destructive: 0 84.2% 60.2%;
    --destructive-foreground: 210 40% 98%;
    --border: 214.3 31.8% 91.4%;
    --input: 214.3 31.8% 91.4%;
    --ring: 222.2 84% 4.9%;
    --radius: 0.5rem;
  }

  .dark {
    --background: 222.2 84% 4.9%;
    --foreground: 210 40% 98%;
    --card: 222.2 84% 4.9%;
    --card-foreground: 210 40% 98%;
    --popover: 222.2 84% 4.9%;
    --popover-foreground: 210 40% 98%;
    --primary: 210 40% 98%;
    --primary-foreground: 222.2 47.4% 11.2%;
    --secondary: 217.2 32.6% 17.5%;
    --secondary-foreground: 210 40% 98%;
    --muted: 217.2 32.6% 17.5%;
    --muted-foreground: 215 20.2% 65.1%;
    --accent: 217.2 32.6% 17.5%;
    --accent-foreground: 210 40% 98%;
    --destructive: 0 62.8% 30.6%;
    --destructive-foreground: 210 40% 98%;
    --border: 217.2 32.6% 17.5%;
    --input: 217.2 32.6% 17.5%;
    --ring: 212.7 26.8% 83.9%;
  }
}

@layer base {
  * {
    @apply border-border;
  }
  body {
    @apply bg-background text-foreground;
  }
}
```

### Day 2: Core UI Components (Shadcn Setup)

#### 1. Utility Functions
```typescript
// frontend/src/lib/utils.ts
import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(date: string | Date) {
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(date))
}

export function formatDateTime(date: string | Date) {
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "numeric",
  }).format(new Date(date))
}
```

#### 2. Button Component
```typescript
// frontend/src/components/ui/button.tsx
import * as React from "react"
import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground hover:bg-primary/90",
        destructive:
          "bg-destructive text-destructive-foreground hover:bg-destructive/90",
        outline:
          "border border-input bg-background hover:bg-accent hover:text-accent-foreground",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-10 px-4 py-2",
        sm: "h-9 rounded-md px-3",
        lg: "h-11 rounded-md px-8",
        icon: "h-10 w-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : "button"
    return (
      <Comp
        className={cn(buttonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      />
    )
  }
)
Button.displayName = "Button"

export { Button, buttonVariants }
```

#### 3. Input Component
```typescript
// frontend/src/components/ui/input.tsx
import * as React from "react"
import { cn } from "@/lib/utils"

export interface InputProps
  extends React.InputHTMLAttributes<HTMLInputElement> {}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type, ...props }, ref) => {
    return (
      <input
        type={type}
        className={cn(
          "flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50",
          className
        )}
        ref={ref}
        {...props}
      />
    )
  }
)
Input.displayName = "Input"

export { Input }
```

#### 4. Card Component
```typescript
// frontend/src/components/ui/card.tsx
import * as React from "react"
import { cn } from "@/lib/utils"

const Card = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      "rounded-lg border bg-card text-card-foreground shadow-sm",
      className
    )}
    {...props}
  />
))
Card.displayName = "Card"

const CardHeader = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn("flex flex-col space-y-1.5 p-6", className)}
    {...props}
  />
))
CardHeader.displayName = "CardHeader"

const CardTitle = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLHeadingElement>
>(({ className, ...props }, ref) => (
  <h3
    ref={ref}
    className={cn(
      "text-2xl font-semibold leading-none tracking-tight",
      className
    )}
    {...props}
  />
))
CardTitle.displayName = "CardTitle"

const CardDescription = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLParagraphElement>
>(({ className, ...props }, ref) => (
  <p
    ref={ref}
    className={cn("text-sm text-muted-foreground", className)}
    {...props}
  />
))
CardDescription.displayName = "CardDescription"

const CardContent = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div ref={ref} className={cn("p-6 pt-0", className)} {...props} />
))
CardContent.displayName = "CardContent"

const CardFooter = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn("flex items-center p-6 pt-0", className)}
    {...props}
  />
))
CardFooter.displayName = "CardFooter"

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent }
```

### Day 3: API Client & Type Definitions

#### 1. API Types
```typescript
// frontend/src/types/api.ts
export interface User {
  id: string
  username: string
  email: string
  firstName: string
  lastName: string
}

export interface AuthResponse {
  accessToken: string
  refreshToken: string
  user: User
}

export interface Contact {
  id: string
  firstName: string
  lastName: string
  email: string
  phone?: string
  customerSince?: string
  lifetimeValue: number
  subscriptionStatus?: 'trial' | 'active' | 'cancelled' | 'expired'
  productInterests: string[]
  lastActivityDate: string
  engagementScore: number
  churnRisk?: 'low' | 'medium' | 'high'
  preferredContactMethod?: 'email' | 'phone' | 'chat'
}

export interface Lead {
  id: string
  firstName: string
  lastName: string
  email: string
  source: 'website' | 'trial' | 'webinar' | 'referral' | 'ad'
  productInterest?: string
  score: number
  status: 'new' | 'contacted' | 'qualified' | 'converted'
  conversionProbability?: number
  dateEntered: string
}

export interface Activity {
  id: string
  type: 'task' | 'email' | 'call' | 'meeting' | 'note'
  subject?: string
  name?: string
  description?: string
  contactId?: string
  status: 'pending' | 'completed' | 'sent' | 'planned'
  date: string
  dueDate?: string
  completedDate?: string
  // Email specific
  emailDirection?: 'inbound' | 'outbound'
  emailSentiment?: 'positive' | 'neutral' | 'negative'
  // Call specific
  callDuration?: number
  callOutcome?: string
}

export interface Opportunity {
  id: string
  name: string
  contactId: string
  amount: number
  product: 'starter' | 'pro' | 'enterprise'
  stage: 'trial' | 'negotiation' | 'closing' | 'won' | 'lost'
  probability: number
  closeDate: string
  winReasons?: string[]
  nextBestAction?: string
}

export interface PaginatedResponse<T> {
  data: T[]
  pagination: {
    page: number
    limit: number
    total: number
    pages: number
  }
}

export interface ApiError {
  success: false
  error: string
}
```

#### 2. API Client
```typescript
// frontend/src/lib/api-client.ts
import axios, { AxiosError, AxiosInstance } from 'axios'
import { useAuthStore } from '@/stores/auth-store'

class ApiClient {
  private client: AxiosInstance
  private refreshingToken: Promise<string> | null = null

  constructor() {
    this.client = axios.create({
      baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8080/custom/api',
      headers: {
        'Content-Type': 'application/json',
      },
    })

    // Request interceptor
    this.client.interceptors.request.use(
      (config) => {
        const token = useAuthStore.getState().accessToken
        if (token) {
          config.headers.Authorization = `Bearer ${token}`
        }
        return config
      },
      (error) => Promise.reject(error)
    )

    // Response interceptor
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        const originalRequest = error.config

        if (error.response?.status === 401 && originalRequest) {
          // Token expired, try to refresh
          if (!this.refreshingToken) {
            this.refreshingToken = this.refreshToken()
          }

          try {
            const newToken = await this.refreshingToken
            originalRequest.headers.Authorization = `Bearer ${newToken}`
            return this.client(originalRequest)
          } catch (refreshError) {
            // Refresh failed, logout
            useAuthStore.getState().logout()
            window.location.href = '/login'
            return Promise.reject(refreshError)
          } finally {
            this.refreshingToken = null
          }
        }

        return Promise.reject(error)
      }
    )
  }

  private async refreshToken(): Promise<string> {
    const refreshToken = useAuthStore.getState().refreshToken
    if (!refreshToken) {
      throw new Error('No refresh token')
    }

    const response = await this.client.post('/auth/refresh', { refreshToken })
    const { accessToken } = response.data.data
    
    useAuthStore.getState().setAccessToken(accessToken)
    return accessToken
  }

  // Auth methods
  async login(username: string, password: string) {
    const response = await this.client.post('/auth/login', { username, password })
    return response.data
  }

  async logout() {
    await this.client.post('/auth/logout')
  }

  // Contact methods
  async getContacts(params?: { page?: number; limit?: number; filters?: any }) {
    const response = await this.client.get('/contacts', { params })
    return response.data
  }

  async getContact(id: string) {
    const response = await this.client.get(`/contacts/${id}`)
    return response.data
  }

  async createContact(data: Partial<Contact>) {
    const response = await this.client.post('/contacts', data)
    return response.data
  }

  async updateContact(id: string, data: Partial<Contact>) {
    const response = await this.client.put(`/contacts/${id}`, data)
    return response.data
  }

  async deleteContact(id: string) {
    const response = await this.client.delete(`/contacts/${id}`)
    return response.data
  }

  async getContactActivities(id: string, params?: { page?: number; limit?: number }) {
    const response = await this.client.get(`/contacts/${id}/activities`, { params })
    return response.data
  }

  // Activity methods
  async getActivities(params?: { 
    page?: number; 
    limit?: number; 
    filters?: { type?: string; contact_id?: string; status?: string } 
  }) {
    const response = await this.client.get('/activities', { params })
    return response.data
  }

  async createActivity(data: {
    type: 'task' | 'email' | 'call' | 'meeting' | 'note'
    subject?: string
    contact_id?: string
    [key: string]: any
  }) {
    const response = await this.client.post('/activities', data)
    return response.data
  }

  // Lead methods
  async getLeads(params?: { page?: number; limit?: number; filters?: any }) {
    const response = await this.client.get('/leads', { params })
    return response.data
  }

  async convertLead(id: string, data?: { create_opportunity?: boolean; opportunity_name?: string }) {
    const response = await this.client.post(`/leads/${id}/convert`, data)
    return response.data
  }

  // Opportunity methods
  async getOpportunities(params?: { page?: number; limit?: number; filters?: any }) {
    const response = await this.client.get('/opportunities', { params })
    return response.data
  }

  // Dashboard methods
  async getDashboardStats() {
    const response = await this.client.get('/dashboard/stats')
    return response.data
  }
}

export const apiClient = new ApiClient()
```

### Day 4: Authentication Implementation

#### 1. Auth Store (Zustand)
```typescript
// frontend/src/stores/auth-store.ts
import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import { User } from '@/types/api'

interface AuthState {
  user: User | null
  accessToken: string | null
  refreshToken: string | null
  isAuthenticated: boolean
  
  setAuth: (user: User, accessToken: string, refreshToken: string) => void
  setAccessToken: (token: string) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      accessToken: null,
      refreshToken: null,
      isAuthenticated: false,

      setAuth: (user, accessToken, refreshToken) => 
        set({ user, accessToken, refreshToken, isAuthenticated: true }),

      setAccessToken: (accessToken) => 
        set({ accessToken }),

      logout: () => 
        set({ user: null, accessToken: null, refreshToken: null, isAuthenticated: false }),
    }),
    {
      name: 'auth-storage',
      partialize: (state) => ({ 
        accessToken: state.accessToken,
        refreshToken: state.refreshToken,
        user: state.user,
        isAuthenticated: state.isAuthenticated
      }),
    }
  )
)
```

#### 2. Login Page
```typescript
// frontend/src/pages/Login.tsx
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuthStore } from '@/stores/auth-store'
import { apiClient } from '@/lib/api-client'

const loginSchema = z.object({
  username: z.string().min(1, 'Username is required'),
  password: z.string().min(1, 'Password is required'),
})

type LoginForm = z.infer<typeof loginSchema>

export function LoginPage() {
  const navigate = useNavigate()
  const setAuth = useAuthStore((state) => state.setAuth)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
  })

  const onSubmit = async (data: LoginForm) => {
    setIsLoading(true)
    setError(null)

    try {
      const response = await apiClient.login(data.username, data.password)
      
      if (response.success) {
        const { accessToken, refreshToken, user } = response.data
        setAuth(user, accessToken, refreshToken)
        navigate('/')
      }
    } catch (err: any) {
      setError(err.response?.data?.error || 'Invalid credentials')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50">
      <Card className="w-full max-w-md">
        <CardHeader className="space-y-1">
          <CardTitle className="text-2xl font-bold text-center">
            Suite B2C CRM
          </CardTitle>
          <CardDescription className="text-center">
            Enter your credentials to access your account
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div className="space-y-2">
              <label htmlFor="username" className="text-sm font-medium">
                Username
              </label>
              <Input
                id="username"
                type="text"
                placeholder="Enter your username"
                {...register('username')}
                disabled={isLoading}
              />
              {errors.username && (
                <p className="text-sm text-red-500">{errors.username.message}</p>
              )}
            </div>

            <div className="space-y-2">
              <label htmlFor="password" className="text-sm font-medium">
                Password
              </label>
              <Input
                id="password"
                type="password"
                placeholder="Enter your password"
                {...register('password')}
                disabled={isLoading}
              />
              {errors.password && (
                <p className="text-sm text-red-500">{errors.password.message}</p>
              )}
            </div>

            {error && (
              <div className="rounded-md bg-red-50 p-3">
                <p className="text-sm text-red-800">{error}</p>
              </div>
            )}

            <Button type="submit" className="w-full" disabled={isLoading}>
              {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Sign In
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
```

#### 3. Protected Route Component
```typescript
// frontend/src/components/ProtectedRoute.tsx
import { Navigate, useLocation } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth-store'

interface ProtectedRouteProps {
  children: React.ReactNode
}

export function ProtectedRoute({ children }: ProtectedRouteProps) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated)
  const location = useLocation()

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  return <>{children}</>
}
```

### Day 5: Layout Components

#### 1. Main Layout
```typescript
// frontend/src/components/layout/Layout.tsx
import { Outlet } from 'react-router-dom'
import { Sidebar } from './Sidebar'
import { Header } from './Header'

export function Layout() {
  return (
    <div className="flex h-screen bg-gray-50">
      <Sidebar />
      <div className="flex flex-1 flex-col">
        <Header />
        <main className="flex-1 overflow-y-auto">
          <div className="container mx-auto py-6">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
```

#### 2. Sidebar Navigation
```typescript
// frontend/src/components/layout/Sidebar.tsx
import { NavLink } from 'react-router-dom'
import { 
  LayoutDashboard, 
  Users, 
  Target, 
  TrendingUp, 
  Activity,
  Settings,
  LogOut
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth-store'
import { Button } from '@/components/ui/button'

const navigation = [
  { name: 'Dashboard', href: '/', icon: LayoutDashboard },
  { name: 'Contacts', href: '/contacts', icon: Users },
  { name: 'Leads', href: '/leads', icon: Target },
  { name: 'Opportunities', href: '/opportunities', icon: TrendingUp },
  { name: 'Activities', href: '/activities', icon: Activity },
  { name: 'Settings', href: '/settings', icon: Settings },
]

export function Sidebar() {
  const logout = useAuthStore((state) => state.logout)

  const handleLogout = () => {
    logout()
    window.location.href = '/login'
  }

  return (
    <div className="flex h-full w-64 flex-col bg-gray-900">
      <div className="flex h-16 items-center justify-center border-b border-gray-800">
        <h1 className="text-xl font-bold text-white">Suite B2C CRM</h1>
      </div>
      
      <nav className="flex-1 space-y-1 px-2 py-4">
        {navigation.map((item) => (
          <NavLink
            key={item.name}
            to={item.href}
            className={({ isActive }) =>
              cn(
                'flex items-center rounded-md px-2 py-2 text-sm font-medium transition-colors',
                isActive
                  ? 'bg-gray-800 text-white'
                  : 'text-gray-300 hover:bg-gray-800 hover:text-white'
              )
            }
          >
            <item.icon className="mr-3 h-5 w-5" />
            {item.name}
          </NavLink>
        ))}
      </nav>

      <div className="border-t border-gray-800 p-4">
        <Button
          variant="ghost"
          className="w-full justify-start text-gray-300 hover:bg-gray-800 hover:text-white"
          onClick={handleLogout}
        >
          <LogOut className="mr-3 h-5 w-5" />
          Logout
        </Button>
      </div>
    </div>
  )
}
```

#### 3. Header Component
```typescript
// frontend/src/components/layout/Header.tsx
import { Bell, Search } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { useAuthStore } from '@/stores/auth-store'

export function Header() {
  const user = useAuthStore((state) => state.user)

  return (
    <header className="flex h-16 items-center justify-between border-b bg-white px-6">
      <div className="flex items-center flex-1">
        <div className="relative w-96">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            type="search"
            placeholder="Search contacts, leads, opportunities..."
            className="pl-10"
          />
        </div>
      </div>

      <div className="flex items-center gap-4">
        <Button variant="ghost" size="icon">
          <Bell className="h-5 w-5" />
        </Button>
        
        <div className="flex items-center gap-3">
          <div className="text-right">
            <p className="text-sm font-medium">
              {user?.firstName} {user?.lastName}
            </p>
            <p className="text-xs text-gray-500">{user?.email}</p>
          </div>
          <div className="h-8 w-8 rounded-full bg-gray-300" />
        </div>
      </div>
    </header>
  )
}
```

## Week 5: Dashboard & Core Features

### Day 1-2: Dashboard Implementation

#### 1. Dashboard Page
```typescript
// frontend/src/pages/Dashboard.tsx
import { useQuery } from '@tanstack/react-query'
import { 
  Users, 
  TrendingUp, 
  DollarSign, 
  Activity,
  ArrowUp,
  ArrowDown
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { apiClient } from '@/lib/api-client'
import { RecentActivities } from '@/components/dashboard/RecentActivities'
import { PipelineChart } from '@/components/dashboard/PipelineChart'
import { MetricCard } from '@/components/dashboard/MetricCard'

export function DashboardPage() {
  const { data: stats, isLoading } = useQuery({
    queryKey: ['dashboard-stats'],
    queryFn: () => apiClient.getDashboardStats(),
  })

  if (isLoading) {
    return <div>Loading...</div>
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold">Dashboard</h1>
        <p className="text-gray-500">Welcome back! Here's what's happening today.</p>
      </div>

      {/* Metrics Grid */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        <MetricCard
          title="Total Contacts"
          value={stats?.data?.totalContacts || 0}
          icon={Users}
          trend={{ value: 12, isPositive: true }}
        />
        <MetricCard
          title="Active Trials"
          value={stats?.data?.activeTrials || 0}
          icon={Activity}
          trend={{ value: 5, isPositive: true }}
        />
        <MetricCard
          title="Monthly Revenue"
          value={`$${(stats?.data?.monthlyRevenue || 0).toLocaleString()}`}
          icon={DollarSign}
          trend={{ value: 8, isPositive: true }}
        />
        <MetricCard
          title="Conversion Rate"
          value={`${stats?.data?.conversionRate || 0}%`}
          icon={TrendingUp}
          trend={{ value: 2, isPositive: false }}
        />
      </div>

      {/* Charts Row */}
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Sales Pipeline</CardTitle>
          </CardHeader>
          <CardContent>
            <PipelineChart data={stats?.data?.pipeline || []} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Recent Activities</CardTitle>
          </CardHeader>
          <CardContent>
            <RecentActivities />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
```

#### 2. Metric Card Component
```typescript
// frontend/src/components/dashboard/MetricCard.tsx
import { LucideIcon } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { ArrowUp, ArrowDown } from 'lucide-react'
import { cn } from '@/lib/utils'

interface MetricCardProps {
  title: string
  value: string | number
  icon: LucideIcon
  trend?: {
    value: number
    isPositive: boolean
  }
}

export function MetricCard({ title, value, icon: Icon, trend }: MetricCardProps) {
  return (
    <Card>
      <CardContent className="p-6">
        <div className="flex items-center justify-between">
          <div className="space-y-1">
            <p className="text-sm text-gray-500">{title}</p>
            <p className="text-2xl font-bold">{value}</p>
            {trend && (
              <div className="flex items-center text-sm">
                {trend.isPositive ? (
                  <ArrowUp className="mr-1 h-3 w-3 text-green-500" />
                ) : (
                  <ArrowDown className="mr-1 h-3 w-3 text-red-500" />
                )}
                <span
                  className={cn(
                    'font-medium',
                    trend.isPositive ? 'text-green-500' : 'text-red-500'
                  )}
                >
                  {trend.value}%
                </span>
                <span className="ml-1 text-gray-500">vs last month</span>
              </div>
            )}
          </div>
          <div className="rounded-full bg-gray-100 p-3">
            <Icon className="h-6 w-6 text-gray-600" />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
```

#### 3. Pipeline Chart Component
```typescript
// frontend/src/components/dashboard/PipelineChart.tsx
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

interface PipelineData {
  stage: string
  count: number
  value: number
}

interface PipelineChartProps {
  data: PipelineData[]
}

export function PipelineChart({ data }: PipelineChartProps) {
  return (
    <ResponsiveContainer width="100%" height={300}>
      <BarChart data={data}>
        <CartesianGrid strokeDasharray="3 3" />
        <XAxis dataKey="stage" />
        <YAxis />
        <Tooltip 
          formatter={(value: number, name: string) => {
            if (name === 'value') {
              return [`$${value.toLocaleString()}`, 'Value']
            }
            return [value, 'Count']
          }}
        />
        <Bar dataKey="count" fill="#3b82f6" name="Deals" />
        <Bar dataKey="value" fill="#10b981" name="Value" />
      </BarChart>
    </ResponsiveContainer>
  )
}
```

#### 4. Recent Activities Component
```typescript
// frontend/src/components/dashboard/RecentActivities.tsx
import { useQuery } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { Mail, Phone, Calendar, FileText, CheckCircle } from 'lucide-react'
import { apiClient } from '@/lib/api-client'
import { Activity } from '@/types/api'

const activityIcons = {
  email: Mail,
  call: Phone,
  meeting: Calendar,
  task: CheckCircle,
  note: FileText,
}

export function RecentActivities() {
  const { data, isLoading } = useQuery({
    queryKey: ['recent-activities'],
    queryFn: () => apiClient.getActivities({ limit: 10 }),
  })

  if (isLoading) {
    return <div className="animate-pulse">Loading activities...</div>
  }

  const activities = data?.data || []

  return (
    <div className="space-y-4">
      {activities.map((activity: Activity) => {
        const Icon = activityIcons[activity.type]
        
        return (
          <div key={activity.id} className="flex items-start gap-3">
            <div className="rounded-full bg-gray-100 p-2">
              <Icon className="h-4 w-4 text-gray-600" />
            </div>
            <div className="flex-1 space-y-1">
              <p className="text-sm font-medium">
                {activity.subject || activity.name}
              </p>
              <p className="text-xs text-gray-500">
                {formatDistanceToNow(new Date(activity.date), { addSuffix: true })}
              </p>
            </div>
          </div>
        )
      })}
    </div>
  )
}
```

### Day 3-4: Contacts Page

#### 1. Contacts List Page
```typescript
// frontend/src/pages/Contacts.tsx
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Plus, Search, Filter } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ContactsTable } from '@/components/contacts/ContactsTable'
import { CreateContactModal } from '@/components/contacts/CreateContactModal'
import { apiClient } from '@/lib/api-client'

export function ContactsPage() {
  const [page, setPage] = useState(1)
  const [searchTerm, setSearchTerm] = useState('')
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['contacts', page, searchTerm],
    queryFn: () => apiClient.getContacts({ 
      page, 
      limit: 20,
      filters: searchTerm ? { email: { like: searchTerm } } : undefined
    }),
  })

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">Contacts</h1>
          <p className="text-gray-500">Manage your customer relationships</p>
        </div>
        <Button onClick={() => setIsCreateModalOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          Add Contact
        </Button>
      </div>

      <div className="flex items-center gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            placeholder="Search contacts..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
        <Button variant="outline">
          <Filter className="mr-2 h-4 w-4" />
          Filters
        </Button>
      </div>

      <ContactsTable
        contacts={data?.data || []}
        pagination={data?.pagination}
        isLoading={isLoading}
        onPageChange={setPage}
      />

      <CreateContactModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onSuccess={() => {
          refetch()
          setIsCreateModalOpen(false)
        }}
      />
    </div>
  )
}
```

#### 2. Contacts Table Component
```typescript
// frontend/src/components/contacts/ContactsTable.tsx
import { Link } from 'react-router-dom'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Contact } from '@/types/api'
import { formatDate } from '@/lib/utils'
import { ChevronLeft, ChevronRight } from 'lucide-react'

interface ContactsTableProps {
  contacts: Contact[]
  pagination?: {
    page: number
    pages: number
    total: number
  }
  isLoading: boolean
  onPageChange: (page: number) => void
}

export function ContactsTable({ 
  contacts, 
  pagination, 
  isLoading, 
  onPageChange 
}: ContactsTableProps) {
  if (isLoading) {
    return <div>Loading...</div>
  }

  return (
    <div className="space-y-4">
      <div className="rounded-lg border bg-white">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Email</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Customer Since</TableHead>
              <TableHead>Engagement</TableHead>
              <TableHead>Churn Risk</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {contacts.map((contact) => (
              <TableRow key={contact.id}>
                <TableCell>
                  <Link 
                    to={`/contacts/${contact.id}`}
                    className="font-medium hover:underline"
                  >
                    {contact.firstName} {contact.lastName}
                  </Link>
                </TableCell>
                <TableCell>{contact.email}</TableCell>
                <TableCell>
                  <Badge variant={
                    contact.subscriptionStatus === 'active' ? 'default' : 
                    contact.subscriptionStatus === 'trial' ? 'secondary' : 
                    'destructive'
                  }>
                    {contact.subscriptionStatus}
                  </Badge>
                </TableCell>
                <TableCell>
                  {contact.customerSince && formatDate(contact.customerSince)}
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <div className="h-2 w-20 rounded-full bg-gray-200">
                      <div 
                        className="h-full rounded-full bg-blue-500"
                        style={{ width: `${contact.engagementScore}%` }}
                      />
                    </div>
                    <span className="text-sm">{contact.engagementScore}%</span>
                  </div>
                </TableCell>
                <TableCell>
                  {contact.churnRisk && (
                    <Badge variant={
                      contact.churnRisk === 'low' ? 'secondary' :
                      contact.churnRisk === 'medium' ? 'default' :
                      'destructive'
                    }>
                      {contact.churnRisk}
                    </Badge>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {pagination && pagination.pages > 1 && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-gray-500">
            Showing {contacts.length} of {pagination.total} contacts
          </p>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(pagination.page - 1)}
              disabled={pagination.page === 1}
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <span className="text-sm">
              Page {pagination.page} of {pagination.pages}
            </span>
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(pagination.page + 1)}
              disabled={pagination.page === pagination.pages}
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
```

### Day 5: Contact Detail Page

#### 1. Contact Detail Page
```typescript
// frontend/src/pages/ContactDetail.tsx
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ContactInfo } from '@/components/contacts/ContactInfo'
import { ActivityTimeline } from '@/components/activities/ActivityTimeline'
import { ContactOpportunities } from '@/components/contacts/ContactOpportunities'
import { ContactInsights } from '@/components/contacts/ContactInsights'
import { apiClient } from '@/lib/api-client'
import { Button } from '@/components/ui/button'
import { ArrowLeft } from 'lucide-react'
import { Link } from 'react-router-dom'

export function ContactDetailPage() {
  const { id } = useParams<{ id: string }>()
  
  const { data: contact, isLoading } = useQuery({
    queryKey: ['contact', id],
    queryFn: () => apiClient.getContact(id!),
    enabled: !!id,
  })

  if (isLoading) {
    return <div>Loading...</div>
  }

  if (!contact?.data) {
    return <div>Contact not found</div>
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Link to="/contacts">
          <Button variant="ghost" size="sm">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Contacts
          </Button>
        </Link>
      </div>

      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-3xl font-bold">
            {contact.data.firstName} {contact.data.lastName}
          </h1>
          <p className="text-gray-500">{contact.data.email}</p>
        </div>
        <Button>Edit Contact</Button>
      </div>

      <Tabs defaultValue="overview" className="space-y-6">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="activities">Activities</TabsTrigger>
          <TabsTrigger value="opportunities">Opportunities</TabsTrigger>
          <TabsTrigger value="insights">AI Insights</TabsTrigger>
        </TabsList>

        <TabsContent value="overview">
          <ContactInfo contact={contact.data} />
        </TabsContent>

        <TabsContent value="activities">
          <ActivityTimeline contactId={id!} />
        </TabsContent>

        <TabsContent value="opportunities">
          <ContactOpportunities contactId={id!} />
        </TabsContent>

        <TabsContent value="insights">
          <ContactInsights contact={contact.data} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
```

## Week 6: Activity Timeline & Polish

### Day 1-2: Activity Timeline Component

#### 1. Activity Timeline Implementation
```typescript
// frontend/src/components/activities/ActivityTimeline.tsx
import { useQuery } from '@tanstack/react-query'
import { formatDistanceToNow } from 'date-fns'
import { 
  Mail, 
  Phone, 
  Calendar, 
  FileText, 
  CheckCircle,
  Clock,
  ArrowRight
} from 'lucide-react'
import { apiClient } from '@/lib/api-client'
import { Activity } from '@/types/api'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { CreateActivityModal } from './CreateActivityModal'
import { useState } from 'react'

interface ActivityTimelineProps {
  contactId: string
}

const activityConfig = {
  email: {
    icon: Mail,
    color: 'bg-blue-100 text-blue-600',
    label: 'Email',
  },
  call: {
    icon: Phone,
    color: 'bg-green-100 text-green-600',
    label: 'Call',
  },
  meeting: {
    icon: Calendar,
    color: 'bg-purple-100 text-purple-600',
    label: 'Meeting',
  },
  task: {
    icon: CheckCircle,
    color: 'bg-yellow-100 text-yellow-600',
    label: 'Task',
  },
  note: {
    icon: FileText,
    color: 'bg-gray-100 text-gray-600',
    label: 'Note',
  },
}

export function ActivityTimeline({ contactId }: ActivityTimelineProps) {
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [page, setPage] = useState(1)

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['contact-activities', contactId, page],
    queryFn: () => apiClient.getContactActivities(contactId, { page, limit: 20 }),
  })

  if (isLoading) {
    return <div>Loading activities...</div>
  }

  const activities = data?.data || []
  const pagination = data?.pagination

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="text-xl font-semibold">Activity Timeline</h2>
        <Button onClick={() => setIsCreateModalOpen(true)}>
          Add Activity
        </Button>
      </div>

      <div className="relative">
        {/* Timeline line */}
        <div className="absolute left-5 top-0 bottom-0 w-0.5 bg-gray-200" />

        {/* Activities */}
        <div className="space-y-6">
          {activities.map((activity, index) => {
            const config = activityConfig[activity.type]
            const Icon = config.icon

            return (
              <div key={activity.id} className="relative flex gap-4">
                {/* Icon */}
                <div className={`relative z-10 flex h-10 w-10 items-center justify-center rounded-full ${config.color}`}>
                  <Icon className="h-5 w-5" />
                </div>

                {/* Content */}
                <Card className="flex-1 p-4">
                  <div className="flex items-start justify-between">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <h3 className="font-medium">
                          {activity.subject || activity.name || 'Untitled'}
                        </h3>
                        <Badge variant="secondary" className="text-xs">
                          {config.label}
                        </Badge>
                        {activity.status && (
                          <Badge 
                            variant={activity.status === 'completed' ? 'default' : 'outline'}
                            className="text-xs"
                          >
                            {activity.status}
                          </Badge>
                        )}
                      </div>
                      
                      {activity.description && (
                        <p className="text-sm text-gray-600">
                          {activity.description}
                        </p>
                      )}

                      {/* Type-specific content */}
                      {activity.type === 'email' && activity.emailDirection && (
                        <div className="flex items-center gap-2 text-sm text-gray-500">
                          <ArrowRight className="h-3 w-3" />
                          <span>{activity.emailDirection === 'inbound' ? 'Received' : 'Sent'}</span>
                        </div>
                      )}

                      {activity.type === 'call' && activity.callDuration && (
                        <div className="flex items-center gap-2 text-sm text-gray-500">
                          <Clock className="h-3 w-3" />
                          <span>{activity.callDuration} minutes</span>
                        </div>
                      )}

                      {activity.type === 'task' && activity.dueDate && (
                        <div className="flex items-center gap-2 text-sm text-gray-500">
                          <Clock className="h-3 w-3" />
                          <span>Due {formatDistanceToNow(new Date(activity.dueDate), { addSuffix: true })}</span>
                        </div>
                      )}
                    </div>

                    <span className="text-sm text-gray-500">
                      {formatDistanceToNow(new Date(activity.date), { addSuffix: true })}
                    </span>
                  </div>
                </Card>
              </div>
            )
          })}
        </div>

        {/* Load more */}
        {pagination && pagination.pages > page && (
          <div className="mt-6 text-center">
            <Button variant="outline" onClick={() => setPage(page + 1)}>
              Load More
            </Button>
          </div>
        )}
      </div>

      <CreateActivityModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        contactId={contactId}
        onSuccess={() => {
          refetch()
          setIsCreateModalOpen(false)
        }}
      />
    </div>
  )
}
```

#### 2. Create Activity Modal
```typescript
// frontend/src/components/activities/CreateActivityModal.tsx
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { apiClient } from '@/lib/api-client'
import { Loader2 } from 'lucide-react'

const activitySchema = z.object({
  type: z.enum(['task', 'email', 'call', 'meeting', 'note']),
  subject: z.string().min(1, 'Subject is required'),
  description: z.string().optional(),
  date_due: z.string().optional(),
  status: z.string().optional(),
})

type ActivityForm = z.infer<typeof activitySchema>

interface CreateActivityModalProps {
  isOpen: boolean
  onClose: () => void
  contactId: string
  onSuccess: () => void
}

export function CreateActivityModal({ 
  isOpen, 
  onClose, 
  contactId, 
  onSuccess 
}: CreateActivityModalProps) {
  const [isLoading, setIsLoading] = useState(false)

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<ActivityForm>({
    resolver: zodResolver(activitySchema),
    defaultValues: {
      type: 'task',
      status: 'Not Started',
    },
  })

  const activityType = watch('type')

  const onSubmit = async (data: ActivityForm) => {
    setIsLoading(true)
    try {
      await apiClient.createActivity({
        ...data,
        contact_id: contactId,
      })
      onSuccess()
      reset()
    } catch (error) {
      console.error('Failed to create activity:', error)
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Add Activity</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium">Type</label>
            <Select
              value={activityType}
              onValueChange={(value) => setValue('type', value as any)}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="task">Task</SelectItem>
                <SelectItem value="email">Email</SelectItem>
                <SelectItem value="call">Call</SelectItem>
                <SelectItem value="meeting">Meeting</SelectItem>
                <SelectItem value="note">Note</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Subject</label>
            <Input {...register('subject')} placeholder="Enter subject" />
            {errors.subject && (
              <p className="text-sm text-red-500">{errors.subject.message}</p>
            )}
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Description</label>
            <Textarea 
              {...register('description')} 
              placeholder="Enter description"
              rows={3}
            />
          </div>

          {activityType === 'task' && (
            <>
              <div className="space-y-2">
                <label className="text-sm font-medium">Due Date</label>
                <Input {...register('date_due')} type="date" />
              </div>
              
              <div className="space-y-2">
                <label className="text-sm font-medium">Status</label>
                <Select
                  defaultValue="Not Started"
                  onValueChange={(value) => setValue('status', value)}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="Not Started">Not Started</SelectItem>
                    <SelectItem value="In Progress">In Progress</SelectItem>
                    <SelectItem value="Completed">Completed</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </>
          )}

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" disabled={isLoading}>
              {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Create Activity
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  )
}
```

### Day 3: React Query Setup & Data Management

#### 1. Query Client Configuration
```typescript
// frontend/src/lib/query-client.ts
import { QueryClient } from '@tanstack/react-query'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      gcTime: 10 * 60 * 1000, // 10 minutes (formerly cacheTime)
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
})
```

#### 2. Custom Hooks for Data Fetching
```typescript
// frontend/src/hooks/use-contacts.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '@/lib/api-client'
import { Contact } from '@/types/api'

export function useContacts(page: number = 1, filters?: any) {
  return useQuery({
    queryKey: ['contacts', page, filters],
    queryFn: () => apiClient.getContacts({ page, filters }),
  })
}

export function useContact(id: string) {
  return useQuery({
    queryKey: ['contact', id],
    queryFn: () => apiClient.getContact(id),
    enabled: !!id,
  })
}

export function useCreateContact() {
  const queryClient = useQueryClient()
  
  return useMutation({
    mutationFn: (data: Partial<Contact>) => apiClient.createContact(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
    },
  })
}

export function useUpdateContact() {
  const queryClient = useQueryClient()
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<Contact> }) => 
      apiClient.updateContact(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['contact', id] })
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
    },
  })
}
```

### Day 4: Router Setup & Main App

#### 1. Router Configuration
```typescript
// frontend/src/App.tsx
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { queryClient } from '@/lib/query-client'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { Layout } from '@/components/layout/Layout'
import { LoginPage } from '@/pages/Login'
import { DashboardPage } from '@/pages/Dashboard'
import { ContactsPage } from '@/pages/Contacts'
import { ContactDetailPage } from '@/pages/ContactDetail'
import { LeadsPage } from '@/pages/Leads'
import { OpportunitiesPage } from '@/pages/Opportunities'
import { ActivitiesPage } from '@/pages/Activities'
import { SettingsPage } from '@/pages/Settings'

export function App() {
  return (
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
            <Route path="/contacts" element={<ContactsPage />} />
            <Route path="/contacts/:id" element={<ContactDetailPage />} />
            <Route path="/leads" element={<LeadsPage />} />
            <Route path="/opportunities" element={<OpportunitiesPage />} />
            <Route path="/activities" element={<ActivitiesPage />} />
            <Route path="/settings" element={<SettingsPage />} />
          </Route>
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </Router>
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  )
}
```

#### 2. Main Entry Point
```typescript
// frontend/src/main.tsx
import React from 'react'
import ReactDOM from 'react-dom/client'
import { App } from './App'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
```

### Day 5: Testing & Final Polish

#### 1. Basic Component Tests
```typescript
// frontend/src/components/__tests__/Button.test.tsx
import { render, screen } from '@testing-library/react'
import { Button } from '@/components/ui/button'

describe('Button', () => {
  it('renders correctly', () => {
    render(<Button>Click me</Button>)
    expect(screen.getByText('Click me')).toBeInTheDocument()
  })

  it('applies variant classes', () => {
    render(<Button variant="destructive">Delete</Button>)
    const button = screen.getByText('Delete')
    expect(button).toHaveClass('bg-destructive')
  })

  it('handles click events', () => {
    const handleClick = vi.fn()
    render(<Button onClick={handleClick}>Click me</Button>)
    screen.getByText('Click me').click()
    expect(handleClick).toHaveBeenCalledTimes(1)
  })
})
```

#### 2. Vite Configuration
```typescript
// frontend/vite.config.ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/custom/api'),
      },
    },
  },
})
```

#### 3. Environment Variables
```bash
# frontend/.env.example
VITE_API_URL=http://localhost:8080/custom/api
```

#### 4. Package.json Scripts
```json
{
  "name": "suite-b2c-crm-frontend",
  "version": "0.1.0",
  "scripts": {
    "dev": "vite",
    "build": "tsc && vite build",
    "preview": "vite preview",
    "test": "vitest",
    "test:ui": "vitest --ui",
    "lint": "eslint . --ext ts,tsx --report-unused-disable-directives --max-warnings 0",
    "format": "prettier --write \"src/**/*.{ts,tsx,css,md}\""
  }
}
```

## Deliverables Checklist

### Week 4 Deliverables
- [ ] React/Vite project initialized with TypeScript
- [ ] Tailwind CSS configured with shadcn/ui theme
- [ ] Core UI components implemented
- [ ] API client with JWT authentication
- [ ] Login flow working
- [ ] Protected routes configured

### Week 5 Deliverables
- [ ] Layout components (Sidebar, Header)
- [ ] Dashboard page with metrics and charts
- [ ] Contacts list page with search and pagination
- [ ] Contact detail page with tabs
- [ ] React Query integration
- [ ] Data fetching hooks

### Week 6 Deliverables
- [ ] Activity Timeline component
- [ ] Create activity modal
- [ ] All routing configured
- [ ] Basic tests written
- [ ] Development environment optimized
- [ ] Ready for Phase 3 features

## Next Steps

After completing Phase 2, you'll have:
1. A fully functional React frontend
2. Authentication and protected routes
3. Dashboard with real data
4. Complete contacts management
5. Activity timeline showing all interactions
6. Foundation ready for remaining features

The application is now usable for basic CRM operations, and Phase 3 can add the remaining modules (Leads, Opportunities) and more advanced features.