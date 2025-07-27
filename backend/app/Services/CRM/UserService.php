<?php

namespace App\Services\CRM;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        // Generate username if not provided
        if (empty($data['user_name'])) {
            $data['user_name'] = $this->generateUsername($data['first_name'], $data['last_name']);
        }
        
        // Hash password (using MD5 for SuiteCRM compatibility)
        // TODO: Migrate to bcrypt
        if (!empty($data['password'])) {
            $data['user_hash'] = md5($data['password']);
            unset($data['password']);
        }
        
        // Set defaults
        $data['status'] = $data['status'] ?? 'active';
        $data['is_admin'] = $data['is_admin'] ?? false;
        
        $user = User::create($data);
        
        // Assign default role if needed
        $this->assignDefaultRole($user);
        
        return $user;
    }
    
    /**
     * Update a user
     */
    public function update(string $id, array $data): User
    {
        $user = User::findOrFail($id);
        
        // Handle password update
        if (!empty($data['password'])) {
            $data['user_hash'] = md5($data['password']);
            unset($data['password']);
        }
        
        // Prevent deactivating last admin
        if (isset($data['status']) && $data['status'] !== 'active') {
            if ($user->is_admin && $this->getActiveAdminCount() <= 1) {
                throw new \Exception('Cannot deactivate the last admin user', 400);
            }
        }
        
        $user->update($data);
        
        return $user;
    }
    
    /**
     * Delete (soft) a user
     */
    public function delete(string $id): void
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting last admin
        if ($user->is_admin && $this->getActiveAdminCount() <= 1) {
            throw new \Exception('Cannot delete the last admin user', 400);
        }
        
        // Reassign records before deletion
        $this->reassignUserRecords($user);
        
        // Soft delete
        $user->delete();
    }
    
    /**
     * Get user by ID with stats
     */
    public function getWithStats(string $id): array
    {
        $user = User::findOrFail($id);
        
        return [
            'user' => $user,
            'stats' => $this->getUserStats($user),
            'recent_activity' => $this->getRecentActivity($user),
            'permissions' => $this->getUserPermissions($user)
        ];
    }
    
    /**
     * Get team members for a user
     */
    public function getTeamMembers(string $userId): Collection
    {
        $user = User::findOrFail($userId);
        
        // Get users in same department
        return User::where('department', $user->department)
            ->where('id', '!=', $userId)
            ->where('status', 'active')
            ->orderBy('first_name')
            ->get();
    }
    
    /**
     * Search users
     */
    public function search(string $query, array $filters = []): Collection
    {
        $users = User::query();
        
        // Search by name, email, or username
        if ($query) {
            $users->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('user_name', 'like', "%{$query}%");
            });
        }
        
        // Apply filters
        if (!empty($filters['status'])) {
            $users->where('status', $filters['status']);
        }
        
        if (!empty($filters['department'])) {
            $users->where('department', $filters['department']);
        }
        
        if (isset($filters['is_admin'])) {
            $users->where('is_admin', $filters['is_admin']);
        }
        
        return $users->orderBy('first_name')->get();
    }
    
    /**
     * Generate unique username
     */
    private function generateUsername(string $firstName, string $lastName): string
    {
        $base = strtolower(substr($firstName, 0, 1) . $lastName);
        $username = $base;
        $counter = 1;
        
        while (User::where('user_name', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Get count of active admin users
     */
    private function getActiveAdminCount(): int
    {
        return User::where('status', 'active')
            ->where('is_admin', true)
            ->count();
    }
    
    /**
     * Reassign user's records to another user
     */
    private function reassignUserRecords(User $user): void
    {
        // Find another active user (prefer admin)
        $newUser = User::where('status', 'active')
            ->where('id', '!=', $user->id)
            ->orderByDesc('is_admin')
            ->first();
        
        if (!$newUser) {
            throw new \Exception('No other active users to reassign records to', 400);
        }
        
        // Reassign all records
        $user->leads()->update(['assigned_user_id' => $newUser->id]);
        $user->contacts()->update(['assigned_user_id' => $newUser->id]);
        $user->opportunities()->update(['assigned_user_id' => $newUser->id]);
        $user->cases()->update(['assigned_user_id' => $newUser->id]);
        $user->tasks()->update(['assigned_user_id' => $newUser->id]);
    }
    
    /**
     * Get user statistics
     */
    private function getUserStats(User $user): array
    {
        return [
            'leads' => [
                'total' => $user->leads()->count(),
                'active' => $user->leads()->whereNotIn('status', ['converted', 'dead'])->count()
            ],
            'contacts' => $user->contacts()->count(),
            'opportunities' => [
                'total' => $user->opportunities()->count(),
                'open' => $user->opportunities()->whereNotIn('sales_stage', ['Closed Won', 'Closed Lost'])->count(),
                'won' => $user->opportunities()->where('sales_stage', 'Closed Won')->count(),
                'value' => $user->opportunities()->where('sales_stage', 'Closed Won')->sum('amount')
            ],
            'cases' => [
                'total' => $user->cases()->count(),
                'open' => $user->cases()->where('status', '!=', 'closed')->count()
            ],
            'tasks' => [
                'total' => $user->tasks()->count(),
                'pending' => $user->tasks()->where('status', '!=', 'completed')->count()
            ]
        ];
    }
    
    /**
     * Get user's recent activity
     */
    private function getRecentActivity(User $user): array
    {
        $activities = [];
        
        // Recent leads
        $recentLeads = $user->leads()
            ->orderBy('date_entered', 'desc')
            ->limit(3)
            ->get(['id', 'first_name', 'last_name', 'date_entered']);
        
        foreach ($recentLeads as $lead) {
            $activities[] = [
                'type' => 'lead',
                'action' => 'created',
                'timestamp' => $lead->date_entered,
                'description' => "Created lead: {$lead->full_name}",
                'entity_id' => $lead->id
            ];
        }
        
        // Recent opportunities
        $recentOpps = $user->opportunities()
            ->orderBy('date_entered', 'desc')
            ->limit(3)
            ->get(['id', 'name', 'amount', 'date_entered']);
        
        foreach ($recentOpps as $opp) {
            $activities[] = [
                'type' => 'opportunity',
                'action' => 'created',
                'timestamp' => $opp->date_entered,
                'description' => "Created opportunity: {$opp->name} (\${$opp->amount})",
                'entity_id' => $opp->id
            ];
        }
        
        // Sort by timestamp
        return collect($activities)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->toArray();
    }
    
    /**
     * Get user permissions
     */
    private function getUserPermissions(User $user): array
    {
        // Simple permission system
        // In production, implement proper ACL/roles
        
        if ($user->is_admin) {
            return [
                'leads' => ['create', 'read', 'update', 'delete', 'export', 'import'],
                'contacts' => ['create', 'read', 'update', 'delete', 'export', 'import'],
                'opportunities' => ['create', 'read', 'update', 'delete', 'export', 'import'],
                'cases' => ['create', 'read', 'update', 'delete', 'export', 'import'],
                'users' => ['create', 'read', 'update', 'delete'],
                'settings' => ['read', 'update'],
                'reports' => ['create', 'read', 'update', 'delete']
            ];
        }
        
        // Regular user permissions
        return [
            'leads' => ['create', 'read', 'update'],
            'contacts' => ['create', 'read', 'update'],
            'opportunities' => ['create', 'read', 'update'],
            'cases' => ['create', 'read', 'update'],
            'users' => ['read'],
            'settings' => [],
            'reports' => ['read']
        ];
    }
    
    /**
     * Assign default role to user
     */
    private function assignDefaultRole(User $user): void
    {
        // In SuiteCRM, roles are complex
        // For now, just set basic flags
        
        if ($user->department === 'sales') {
            // Sales users get lead/opportunity access
            $user->update(['role_flags' => json_encode(['sales' => true])]);
        } elseif ($user->department === 'support') {
            // Support users get case access
            $user->update(['role_flags' => json_encode(['support' => true])]);
        }
    }
    
    /**
     * Get users for assignment dropdown
     */
    public function getAssignableUsers(): Collection
    {
        return User::where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'department'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'department' => $user->department
                ];
            });
    }
    
    /**
     * Update user preferences
     */
    public function updatePreferences(string $userId, array $preferences): void
    {
        $user = User::findOrFail($userId);
        
        // Store preferences as JSON
        $currentPrefs = json_decode($user->preferences ?? '{}', true);
        $updatedPrefs = array_merge($currentPrefs, $preferences);
        
        $user->update(['preferences' => json_encode($updatedPrefs)]);
    }
    
    /**
     * Get user preferences
     */
    public function getPreferences(string $userId): array
    {
        $user = User::findOrFail($userId);
        
        $defaults = [
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'language' => 'en',
            'theme' => 'light',
            'notifications' => [
                'email' => true,
                'desktop' => true,
                'mobile' => false
            ],
            'dashboard' => [
                'widgets' => ['stats', 'pipeline', 'tasks', 'leads']
            ]
        ];
        
        $userPrefs = json_decode($user->preferences ?? '{}', true);
        
        return array_merge($defaults, $userPrefs);
    }
}