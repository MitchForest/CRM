# Phase 1 - Frontend Implementation Guide

## Overview
Phase 1 establishes the React frontend foundation with authentication, basic CRM module views (Leads/Accounts), and a simple dashboard. By the end of this phase, users can log in, view/create/edit leads and accounts, and see basic metrics.

## Prerequisites
- Node.js 18+ and npm/yarn
- Git
- Docker (for backend integration testing)

## Step-by-Step Implementation

### 1. Project Setup and Configuration

#### 1.1 Initialize React Project with Vite
```bash
# Create frontend directory and initialize
mkdir -p frontend
cd frontend
npm create vite@latest . -- --template react-ts
npm install
```

#### 1.2 Install Core Dependencies
```bash
# UI Framework and Styling
npm install tailwindcss postcss autoprefixer
npm install @radix-ui/react-dialog @radix-ui/react-dropdown-menu @radix-ui/react-label @radix-ui/react-select @radix-ui/react-slot @radix-ui/react-tabs @radix-ui/react-toast
npm install class-variance-authority clsx tailwind-merge lucide-react

# State Management and Data Fetching
npm install @tanstack/react-query zustand
npm install axios

# Forms and Validation
npm install react-hook-form zod @hookform/resolvers

# Routing
npm install react-router-dom

# Development Dependencies
npm install -D @types/react @types/react-dom @types/node
npm install -D @typescript-eslint/eslint-plugin @typescript-eslint/parser eslint eslint-plugin-react-hooks eslint-plugin-react-refresh
```

#### 1.3 Configure Tailwind CSS
```bash
npx tailwindcss init -p
```

Update `tailwind.config.js`:
```javascript
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
    },
  },
  plugins: [],
}
```

Update `src/index.css`:
```css
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
}
```

#### 1.4 Project Structure Setup
```bash
# Create directory structure
mkdir -p src/{components,pages,lib,hooks,services,store,types}
mkdir -p src/components/{ui,layout,features}

# Create base files
touch src/lib/utils.ts
touch src/lib/api.ts
touch src/store/auth.ts
touch src/types/index.ts
```

### 2. Core Infrastructure Setup

#### 2.1 Create Utility Functions
`src/lib/utils.ts`:
```typescript
import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function formatDate(date: string | Date): string {
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(date))
}

export function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount)
}
```

#### 2.2 Configure API Client
`src/lib/api.ts`:
```typescript
import axios from 'axios';
import { useAuthStore } from '@/store/auth';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8080/api/v8';

export const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor for auth
api.interceptors.request.use(
  (config) => {
    const token = useAuthStore.getState().token;
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor for token refresh
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      try {
        const refreshToken = useAuthStore.getState().refreshToken;
        const response = await axios.post(`${API_URL}/refresh`, {
          refresh_token: refreshToken,
        });
        
        const { access_token } = response.data;
        useAuthStore.getState().setToken(access_token);
        
        originalRequest.headers.Authorization = `Bearer ${access_token}`;
        return api(originalRequest);
      } catch (refreshError) {
        useAuthStore.getState().logout();
        window.location.href = '/login';
        return Promise.reject(refreshError);
      }
    }
    
    return Promise.reject(error);
  }
);
```

#### 2.3 Define TypeScript Types
`src/types/index.ts`:
```typescript
// Authentication
export interface User {
  id: string;
  user_name: string;
  first_name: string;
  last_name: string;
  email: string;
  role: string;
  is_admin: boolean;
}

export interface AuthResponse {
  access_token: string;
  refresh_token: string;
  user: User;
}

// CRM Types
export interface Lead {
  id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone_mobile?: string;
  account_name?: string;
  title?: string;
  lead_source?: string;
  status: string;
  ai_score?: number;
  assigned_user_id?: string;
  assigned_user_name?: string;
  date_entered: string;
  date_modified: string;
}

export interface Account {
  id: string;
  name: string;
  phone_office?: string;
  website?: string;
  industry?: string;
  annual_revenue?: number;
  employees?: string;
  account_type: string;
  assigned_user_id?: string;
  assigned_user_name?: string;
  date_entered: string;
  date_modified: string;
}

export interface DashboardMetrics {
  total_leads: number;
  total_accounts: number;
  new_leads_today: number;
  pipeline_value: number;
}

// API Response Types
export interface ApiResponse<T> {
  data: T;
  meta?: {
    total: number;
    page: number;
    per_page: number;
  };
}

export interface ApiError {
  error: {
    code: string;
    message: string;
  };
}
```

### 3. Authentication Implementation

#### 3.1 Create Auth Store
`src/store/auth.ts`:
```typescript
import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User } from '@/types';

interface AuthState {
  user: User | null;
  token: string | null;
  refreshToken: string | null;
  isAuthenticated: boolean;
  setAuth: (token: string, refreshToken: string, user: User) => void;
  setToken: (token: string) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      refreshToken: null,
      isAuthenticated: false,
      setAuth: (token, refreshToken, user) =>
        set({
          token,
          refreshToken,
          user,
          isAuthenticated: true,
        }),
      setToken: (token) => set({ token }),
      logout: () =>
        set({
          user: null,
          token: null,
          refreshToken: null,
          isAuthenticated: false,
        }),
    }),
    {
      name: 'auth-storage',
    }
  )
);
```

#### 3.2 Create Auth Service
`src/services/auth.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { AuthResponse } from '@/types';

export const authService = {
  async login(username: string, password: string): Promise<AuthResponse> {
    const response = await api.post('/login', {
      grant_type: 'password',
      client_id: 'sugar',
      username,
      password,
    });
    
    return response.data;
  },

  async logout(): Promise<void> {
    await api.post('/logout');
  },

  async getCurrentUser() {
    const response = await api.get('/me');
    return response.data;
  },
};
```

#### 3.3 Create Login Page
`src/pages/Login.tsx`:
```typescript
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/components/ui/use-toast';
import { authService } from '@/services/auth.service';
import { useAuthStore } from '@/store/auth';

const loginSchema = z.object({
  username: z.string().min(1, 'Username is required'),
  password: z.string().min(1, 'Password is required'),
});

type LoginForm = z.infer<typeof loginSchema>;

export function LoginPage() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const setAuth = useAuthStore((state) => state.setAuth);
  const [isLoading, setIsLoading] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = async (data: LoginForm) => {
    setIsLoading(true);
    try {
      const response = await authService.login(data.username, data.password);
      setAuth(response.access_token, response.refresh_token, response.user);
      navigate('/');
    } catch (error) {
      toast({
        title: 'Login failed',
        description: 'Please check your credentials and try again.',
        variant: 'destructive',
      });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="w-full max-w-sm space-y-8">
        <div className="text-center">
          <h2 className="text-3xl font-bold">Welcome back</h2>
          <p className="mt-2 text-sm text-muted-foreground">
            Sign in to your CRM account
          </p>
        </div>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="username">Username</Label>
              <Input
                id="username"
                type="text"
                autoComplete="username"
                {...register('username')}
                disabled={isLoading}
              />
              {errors.username && (
                <p className="text-sm text-destructive">
                  {errors.username.message}
                </p>
              )}
            </div>
            
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                {...register('password')}
                disabled={isLoading}
              />
              {errors.password && (
                <p className="text-sm text-destructive">
                  {errors.password.message}
                </p>
              )}
            </div>
          </div>
          
          <Button type="submit" className="w-full" disabled={isLoading}>
            {isLoading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Sign in
          </Button>
        </form>
      </div>
    </div>
  );
}
```

### 4. UI Components Setup

#### 4.1 Install shadcn/ui Components
Create component files in `src/components/ui/`:

`src/components/ui/button.tsx`:
```typescript
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

`src/components/ui/input.tsx`:
```typescript
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

Continue creating other necessary UI components: `label.tsx`, `toast.tsx`, `card.tsx`, `table.tsx`, `dialog.tsx`

### 5. Layout Components

#### 5.1 Create App Layout
`src/components/layout/AppLayout.tsx`:
```typescript
import { Link, Outlet, useNavigate } from 'react-router-dom';
import { Home, Users, Building2, Phone, FileText, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';

const navigation = [
  { name: 'Dashboard', href: '/', icon: Home },
  { name: 'Leads', href: '/leads', icon: Users },
  { name: 'Accounts', href: '/accounts', icon: Building2 },
];

export function AppLayout() {
  const navigate = useNavigate();
  const { user, logout } = useAuthStore();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <div className="flex h-screen bg-gray-50">
      {/* Sidebar */}
      <div className="hidden md:flex md:w-64 md:flex-col">
        <div className="flex flex-1 flex-col bg-white border-r">
          <div className="flex h-16 items-center px-4 border-b">
            <h1 className="text-xl font-semibold">CRM Platform</h1>
          </div>
          
          <nav className="flex-1 space-y-1 px-2 py-4">
            {navigation.map((item) => {
              const Icon = item.icon;
              return (
                <Link
                  key={item.name}
                  to={item.href}
                  className={cn(
                    "flex items-center px-2 py-2 text-sm font-medium rounded-md",
                    "hover:bg-gray-100"
                  )}
                >
                  <Icon className="mr-3 h-5 w-5" />
                  {item.name}
                </Link>
              );
            })}
          </nav>
          
          <div className="border-t p-4">
            <div className="flex items-center">
              <div className="flex-1">
                <p className="text-sm font-medium">
                  {user?.first_name} {user?.last_name}
                </p>
                <p className="text-xs text-gray-500">{user?.email}</p>
              </div>
              <Button
                variant="ghost"
                size="icon"
                onClick={handleLogout}
                title="Logout"
              >
                <LogOut className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>
      </div>

      {/* Main content */}
      <div className="flex flex-1 flex-col">
        <main className="flex-1 overflow-y-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
```

#### 5.2 Create Protected Route Component
`src/components/layout/ProtectedRoute.tsx`:
```typescript
import { Navigate, useLocation } from 'react-router-dom';
import { useAuthStore } from '@/store/auth';

interface ProtectedRouteProps {
  children: React.ReactNode;
}

export function ProtectedRoute({ children }: ProtectedRouteProps) {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const location = useLocation();

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return <>{children}</>;
}
```

### 6. CRM Module Services

#### 6.1 Create Lead Service
`src/services/lead.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Lead, ApiResponse } from '@/types';

export const leadService = {
  async getAll(params?: { page?: number; limit?: number; filter?: string }) {
    const response = await api.get<ApiResponse<Lead[]>>('/module/Leads', {
      params: {
        'page[number]': params?.page || 1,
        'page[size]': params?.limit || 20,
        filter: params?.filter,
      },
    });
    return response.data;
  },

  async getById(id: string) {
    const response = await api.get<Lead>(`/module/Leads/${id}`);
    return response.data;
  },

  async create(data: Partial<Lead>) {
    const response = await api.post<Lead>('/module/Leads', {
      data: {
        type: 'Leads',
        attributes: data,
      },
    });
    return response.data;
  },

  async update(id: string, data: Partial<Lead>) {
    const response = await api.patch<Lead>(`/module/Leads/${id}`, {
      data: {
        type: 'Leads',
        id,
        attributes: data,
      },
    });
    return response.data;
  },

  async delete(id: string) {
    await api.delete(`/module/Leads/${id}`);
  },
};
```

#### 6.2 Create Account Service
`src/services/account.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { Account, ApiResponse } from '@/types';

export const accountService = {
  async getAll(params?: { page?: number; limit?: number; filter?: string }) {
    const response = await api.get<ApiResponse<Account[]>>('/module/Accounts', {
      params: {
        'page[number]': params?.page || 1,
        'page[size]': params?.limit || 20,
        filter: params?.filter,
      },
    });
    return response.data;
  },

  async getById(id: string) {
    const response = await api.get<Account>(`/module/Accounts/${id}`);
    return response.data;
  },

  async create(data: Partial<Account>) {
    const response = await api.post<Account>('/module/Accounts', {
      data: {
        type: 'Accounts',
        attributes: data,
      },
    });
    return response.data;
  },

  async update(id: string, data: Partial<Account>) {
    const response = await api.patch<Account>(`/module/Accounts/${id}`, {
      data: {
        type: 'Accounts',
        id,
        attributes: data,
      },
    });
    return response.data;
  },

  async delete(id: string) {
    await api.delete(`/module/Accounts/${id}`);
  },
};
```

### 7. CRM Module Pages

#### 7.1 Create Leads List Page
`src/pages/Leads/LeadsList.tsx`:
```typescript
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { leadService } from '@/services/lead.service';
import { formatDate } from '@/lib/utils';

export function LeadsListPage() {
  const [searchTerm, setSearchTerm] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading, error } = useQuery({
    queryKey: ['leads', page, searchTerm],
    queryFn: () =>
      leadService.getAll({
        page,
        filter: searchTerm,
      }),
  });

  const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
      New: 'bg-blue-100 text-blue-800',
      Contacted: 'bg-yellow-100 text-yellow-800',
      Qualified: 'bg-green-100 text-green-800',
      Lost: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
  };

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Leads</h1>
        <Button asChild>
          <Link to="/leads/new">
            <Plus className="mr-2 h-4 w-4" />
            New Lead
          </Link>
        </Button>
      </div>

      <div className="mb-4 flex items-center space-x-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <Input
            placeholder="Search leads..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-10"
          />
        </div>
      </div>

      {isLoading && <div>Loading...</div>}
      {error && <div>Error loading leads</div>}

      {data && (
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Company</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>AI Score</TableHead>
                <TableHead>Created</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.data.map((lead) => (
                <TableRow key={lead.id}>
                  <TableCell className="font-medium">
                    {lead.first_name} {lead.last_name}
                  </TableCell>
                  <TableCell>{lead.email}</TableCell>
                  <TableCell>{lead.account_name || '-'}</TableCell>
                  <TableCell>
                    <Badge className={getStatusColor(lead.status)}>
                      {lead.status}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {lead.ai_score ? (
                      <span className="font-semibold">{lead.ai_score}</span>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>{formatDate(lead.date_entered)}</TableCell>
                  <TableCell>
                    <Button variant="ghost" size="sm" asChild>
                      <Link to={`/leads/${lead.id}`}>View</Link>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      )}
    </div>
  );
}
```

#### 7.2 Create Lead Form Page
`src/pages/Leads/LeadForm.tsx`:
```typescript
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/components/ui/use-toast';
import { leadService } from '@/services/lead.service';

const leadSchema = z.object({
  first_name: z.string().min(1, 'First name is required'),
  last_name: z.string().min(1, 'Last name is required'),
  email: z.string().email('Invalid email address'),
  phone_mobile: z.string().optional(),
  account_name: z.string().optional(),
  title: z.string().optional(),
  lead_source: z.string().optional(),
  status: z.string(),
});

type LeadFormData = z.infer<typeof leadSchema>;

export function LeadFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const isEdit = Boolean(id);

  const { data: lead, isLoading: isLoadingLead } = useQuery({
    queryKey: ['lead', id],
    queryFn: () => leadService.getById(id!),
    enabled: isEdit,
  });

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<LeadFormData>({
    resolver: zodResolver(leadSchema),
    defaultValues: lead || {
      status: 'New',
    },
  });

  const createMutation = useMutation({
    mutationFn: leadService.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      toast({
        title: 'Lead created',
        description: 'The lead has been created successfully.',
      });
      navigate('/leads');
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: Partial<LeadFormData> }) =>
      leadService.update(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      queryClient.invalidateQueries({ queryKey: ['lead', id] });
      toast({
        title: 'Lead updated',
        description: 'The lead has been updated successfully.',
      });
      navigate('/leads');
    },
  });

  const onSubmit = async (data: LeadFormData) => {
    if (isEdit && id) {
      updateMutation.mutate({ id, data });
    } else {
      createMutation.mutate(data);
    }
  };

  if (isLoadingLead) {
    return <div>Loading...</div>;
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex items-center">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => navigate('/leads')}
          className="mr-4"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <h1 className="text-2xl font-semibold">
          {isEdit ? 'Edit Lead' : 'New Lead'}
        </h1>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="max-w-2xl space-y-6">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="first_name">First Name</Label>
            <Input
              id="first_name"
              {...register('first_name')}
              disabled={isSubmitting}
            />
            {errors.first_name && (
              <p className="text-sm text-destructive">
                {errors.first_name.message}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="last_name">Last Name</Label>
            <Input
              id="last_name"
              {...register('last_name')}
              disabled={isSubmitting}
            />
            {errors.last_name && (
              <p className="text-sm text-destructive">
                {errors.last_name.message}
              </p>
            )}
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="email">Email</Label>
          <Input
            id="email"
            type="email"
            {...register('email')}
            disabled={isSubmitting}
          />
          {errors.email && (
            <p className="text-sm text-destructive">{errors.email.message}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="phone_mobile">Phone</Label>
          <Input
            id="phone_mobile"
            type="tel"
            {...register('phone_mobile')}
            disabled={isSubmitting}
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="account_name">Company</Label>
          <Input
            id="account_name"
            {...register('account_name')}
            disabled={isSubmitting}
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="title">Title</Label>
          <Input id="title" {...register('title')} disabled={isSubmitting} />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label htmlFor="lead_source">Lead Source</Label>
            <Select
              onValueChange={(value) => setValue('lead_source', value)}
              defaultValue={lead?.lead_source}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select source" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Website">Website</SelectItem>
                <SelectItem value="Referral">Referral</SelectItem>
                <SelectItem value="Social Media">Social Media</SelectItem>
                <SelectItem value="Email">Email</SelectItem>
                <SelectItem value="Cold Call">Cold Call</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="status">Status</Label>
            <Select
              onValueChange={(value) => setValue('status', value)}
              defaultValue={lead?.status || 'New'}
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="New">New</SelectItem>
                <SelectItem value="Contacted">Contacted</SelectItem>
                <SelectItem value="Qualified">Qualified</SelectItem>
                <SelectItem value="Lost">Lost</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="flex space-x-4">
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {isEdit ? 'Update Lead' : 'Create Lead'}
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => navigate('/leads')}
            disabled={isSubmitting}
          >
            Cancel
          </Button>
        </div>
      </form>
    </div>
  );
}
```

### 8. Dashboard Implementation

#### 8.1 Create Dashboard Service
`src/services/dashboard.service.ts`:
```typescript
import { api } from '@/lib/api';
import type { DashboardMetrics } from '@/types';

export const dashboardService = {
  async getMetrics(): Promise<DashboardMetrics> {
    // In Phase 1, we'll use direct API calls to get counts
    // In later phases, this would be a custom endpoint
    const [leadsResponse, accountsResponse] = await Promise.all([
      api.get('/module/Leads', { params: { 'page[size]': 1 } }),
      api.get('/module/Accounts', { params: { 'page[size]': 1 } }),
    ]);

    // Get today's leads - this would be done server-side in production
    const todayLeadsResponse = await api.get('/module/Leads', {
      params: {
        'page[size]': 100,
        'filter[date_entered][gte]': new Date().toISOString().split('T')[0],
      },
    });

    return {
      total_leads: leadsResponse.data.meta?.total || 0,
      total_accounts: accountsResponse.data.meta?.total || 0,
      new_leads_today: todayLeadsResponse.data.data?.length || 0,
      pipeline_value: 0, // Will be implemented in Phase 2 with Opportunities
    };
  },
};
```

#### 8.2 Create Dashboard Page
`src/pages/Dashboard.tsx`:
```typescript
import { useQuery } from '@tanstack/react-query';
import { Users, Building2, TrendingUp, DollarSign } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboardService } from '@/services/dashboard.service';
import { formatCurrency } from '@/lib/utils';

export function DashboardPage() {
  const { data: metrics, isLoading } = useQuery({
    queryKey: ['dashboard-metrics'],
    queryFn: dashboardService.getMetrics,
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  const cards = [
    {
      title: 'Total Leads',
      value: metrics?.total_leads || 0,
      icon: Users,
      color: 'text-blue-600',
      bgColor: 'bg-blue-100',
    },
    {
      title: 'Total Accounts',
      value: metrics?.total_accounts || 0,
      icon: Building2,
      color: 'text-green-600',
      bgColor: 'bg-green-100',
    },
    {
      title: 'New Leads Today',
      value: metrics?.new_leads_today || 0,
      icon: TrendingUp,
      color: 'text-purple-600',
      bgColor: 'bg-purple-100',
    },
    {
      title: 'Pipeline Value',
      value: formatCurrency(metrics?.pipeline_value || 0),
      icon: DollarSign,
      color: 'text-orange-600',
      bgColor: 'bg-orange-100',
    },
  ];

  return (
    <div className="p-6">
      <h1 className="mb-6 text-2xl font-semibold">Dashboard</h1>

      {isLoading && <div>Loading metrics...</div>}

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {cards.map((card) => {
          const Icon = card.icon;
          return (
            <Card key={card.title}>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                  {card.title}
                </CardTitle>
                <div className={`rounded-full p-2 ${card.bgColor}`}>
                  <Icon className={`h-4 w-4 ${card.color}`} />
                </div>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{card.value}</div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      <div className="mt-8">
        <h2 className="mb-4 text-lg font-semibold">Recent Activity</h2>
        <Card>
          <CardContent className="p-6">
            <p className="text-center text-muted-foreground">
              Activity timeline will be implemented in Phase 2
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
```

### 9. App Configuration and Routing

#### 9.1 Configure React Query
`src/lib/query-client.ts`:
```typescript
import { QueryClient } from '@tanstack/react-query';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5, // 5 minutes
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});
```

#### 9.2 Create App Router
`src/App.tsx`:
```typescript
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { Toaster } from '@/components/ui/toaster';
import { queryClient } from '@/lib/query-client';
import { AppLayout } from '@/components/layout/AppLayout';
import { ProtectedRoute } from '@/components/layout/ProtectedRoute';
import { LoginPage } from '@/pages/Login';
import { DashboardPage } from '@/pages/Dashboard';
import { LeadsListPage } from '@/pages/Leads/LeadsList';
import { LeadFormPage } from '@/pages/Leads/LeadForm';
import { AccountsListPage } from '@/pages/Accounts/AccountsList';
import { AccountFormPage } from '@/pages/Accounts/AccountForm';

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<DashboardPage />} />
            
            {/* Leads Routes */}
            <Route path="leads" element={<LeadsListPage />} />
            <Route path="leads/new" element={<LeadFormPage />} />
            <Route path="leads/:id" element={<LeadFormPage />} />
            
            {/* Accounts Routes */}
            <Route path="accounts" element={<AccountsListPage />} />
            <Route path="accounts/new" element={<AccountFormPage />} />
            <Route path="accounts/:id" element={<AccountFormPage />} />
          </Route>
          
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
      <Toaster />
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  );
}
```

#### 9.3 Update Main Entry Point
`src/main.tsx`:
```typescript
import React from 'react';
import ReactDOM from 'react-dom/client';
import { App } from './App';
import './index.css';

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
```

### 10. Environment Configuration

#### 10.1 Create Environment Files
`.env.example`:
```bash
VITE_API_URL=http://localhost:8080/api/v8
```

`.env.local`:
```bash
VITE_API_URL=http://localhost:8080/api/v8
```

#### 10.2 Update Vite Config
`vite.config.ts`:
```typescript
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

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
      },
    },
  },
});
```

## Testing Setup

### Unit Tests Configuration
`tests/frontend/setup.ts`:
```typescript
import '@testing-library/jest-dom';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

afterEach(() => {
  cleanup();
});
```

### Example Component Test
`tests/frontend/components/LoginPage.test.tsx`:
```typescript
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { LoginPage } from '@/pages/Login';
import { authService } from '@/services/auth.service';

vi.mock('@/services/auth.service');

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false },
  },
});

const renderWithProviders = (component: React.ReactNode) => {
  return render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{component}</BrowserRouter>
    </QueryClientProvider>
  );
};

describe('LoginPage', () => {
  it('renders login form', () => {
    renderWithProviders(<LoginPage />);
    
    expect(screen.getByLabelText('Username')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });

  it('handles successful login', async () => {
    const mockAuth = {
      access_token: 'token123',
      refresh_token: 'refresh123',
      user: {
        id: '1',
        user_name: 'admin',
        first_name: 'Admin',
        last_name: 'User',
        email: 'admin@example.com',
        role: 'admin',
        is_admin: true,
      },
    };

    (authService.login as jest.Mock).mockResolvedValueOnce(mockAuth);

    renderWithProviders(<LoginPage />);
    
    fireEvent.change(screen.getByLabelText('Username'), {
      target: { value: 'admin' },
    });
    fireEvent.change(screen.getByLabelText('Password'), {
      target: { value: 'password' },
    });
    
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(authService.login).toHaveBeenCalledWith('admin', 'password');
    });
  });
});
```

## Definition of Success

### ✅ Phase 1 Frontend Success Criteria:

1. **Setup Complete**
   - [ ] React app created with Vite and TypeScript
   - [ ] All dependencies installed
   - [ ] Tailwind CSS and shadcn/ui configured
   - [ ] Project structure established

2. **Authentication Working**
   - [ ] Login page renders and accepts credentials
   - [ ] JWT tokens stored in Zustand persist store
   - [ ] Protected routes redirect to login when unauthenticated
   - [ ] Logout clears tokens and redirects to login
   - [ ] Token refresh handled automatically

3. **Core UI Components**
   - [ ] App layout with sidebar navigation
   - [ ] All necessary shadcn/ui components installed
   - [ ] Responsive design working on mobile and desktop

4. **CRM Modules**
   - [ ] Leads list page displays data from API
   - [ ] Lead create/edit form saves to backend
   - [ ] Accounts list page displays data from API
   - [ ] Account create/edit form saves to backend
   - [ ] Form validation working with Zod

5. **Dashboard**
   - [ ] Metrics cards show real data counts
   - [ ] Page refreshes data every 30 seconds
   - [ ] Loading states implemented

6. **Testing**
   - [ ] Unit tests for auth flow
   - [ ] Component tests for forms
   - [ ] Service tests for API calls

### Manual Verification Steps:
1. Start the dev server: `npm run dev`
2. Navigate to http://localhost:3000
3. Verify redirect to login page
4. Log in with SuiteCRM credentials
5. Verify dashboard loads with metrics
6. Create a new lead and verify it saves
7. Edit the lead and verify changes persist
8. Create a new account
9. Log out and verify redirect to login
10. Check responsive design on mobile viewport

### Integration Points:
- Frontend expects SuiteCRM v8 API at http://localhost:8080/api/v8
- Authentication uses JWT with Bearer tokens
- API responses follow JSON:API specification
- CORS must be enabled on backend

### Next Phase Preview:
Phase 2 will add:
- Opportunities pipeline with kanban view
- Activities management (calls, meetings, tasks)
- Cases/support tickets
- Email viewing
- Enhanced dashboard with charts