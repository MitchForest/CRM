<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\ApiRefreshToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
// Removed Laravel helper

class AuthService
{
    private string $jwtSecret;
    private int $accessTokenLifetime;
    private int $refreshTokenLifetime;
    
    public function __construct()
    {
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default-secret-change-me';
        $this->accessTokenLifetime = (int)($_ENV['JWT_ACCESS_TOKEN_LIFETIME'] ?? 900); // 15 minutes
        $this->refreshTokenLifetime = (int)($_ENV['JWT_REFRESH_TOKEN_LIFETIME'] ?? 2592000); // 30 days
    }
    
    /**
     * Authenticate user with email/username and password
     */
    public function authenticate(string $username, string $password): array
    {
        // Find user by email or username
        $user = User::where('email', $username)
            ->orWhere('user_name', $username)
            ->first();
        
        if (!$user) {
            throw new \Exception('Invalid credentials', 401);
        }
        
        // Verify password (SuiteCRM uses MD5, we should migrate to bcrypt)
        if (!$this->verifyPassword($password, $user->user_hash)) {
            throw new \Exception('Invalid credentials', 401);
        }
        
        // Check if user is active
        if ($user->status !== 'active') {
            throw new \Exception('Account is not active', 403);
        }
        
        // Generate tokens
        $tokens = $this->generateTokens($user);
        
        // Update last login
        $user->update(['last_login' => (new \DateTime())->format('Y-m-d H:i:s')]);
        
        return [
            'user' => $this->formatUserData($user),
            'tokens' => $tokens
        ];
    }
    
    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(string $refreshToken): array
    {
        // Find refresh token
        $tokenRecord = ApiRefreshToken::where('token', $refreshToken)
            ->valid()
            ->first();
        
        if (!$tokenRecord) {
            throw new \Exception('Invalid refresh token', 401);
        }
        
        // Get user
        $user = $tokenRecord->user;
        if (!$user || $user->status !== 'active') {
            throw new \Exception('User not found or inactive', 401);
        }
        
        // Generate new access token
        $accessToken = $this->generateAccessToken($user);
        
        // Update refresh token usage
        $tokenRecord->recordUsage();
        
        return [
            'access_token' => $accessToken,
            'expires_in' => $this->accessTokenLifetime
        ];
    }
    
    /**
     * Logout user (revoke refresh token)
     */
    public function logout(string $refreshToken): void
    {
        ApiRefreshToken::where('token', $refreshToken)->delete();
    }
    
    /**
     * Validate JWT token and return user data
     */
    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Check if token is expired (shouldn't happen as JWT handles this)
            if ($decoded->exp < time()) {
                throw new \Exception('Token expired');
            }
            
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new \Exception('Invalid token: ' . $e->getMessage(), 401);
        }
    }
    
    /**
     * Get current user from token
     */
    public function getCurrentUser(string $userId): ?User
    {
        return User::find($userId);
    }
    
    /**
     * Generate both access and refresh tokens
     */
    private function generateTokens(User $user): array
    {
        $accessToken = $this->generateAccessToken($user);
        $refreshToken = $this->generateRefreshToken($user);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenLifetime
        ];
    }
    
    /**
     * Generate JWT access token
     */
    private function generateAccessToken(User $user): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'http://localhost:8080', // Issuer
            'sub' => $user->id, // Subject (user ID)
            'iat' => time(), // Issued at
            'exp' => time() + $this->accessTokenLifetime, // Expiration
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->full_name,
                'is_admin' => $user->is_admin
            ]
        ];
        
        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }
    
    /**
     * Generate refresh token
     */
    private function generateRefreshToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        
        // Store refresh token
        ApiRefreshToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => (new \DateTime())->modify("+{$this->refreshTokenLifetime} seconds")->format('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        // Clean up old tokens (keep last 5)
        $oldTokens = ApiRefreshToken::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->skip(5)
            ->pluck('id');
        
        if ($oldTokens->isNotEmpty()) {
            ApiRefreshToken::whereIn('id', $oldTokens)->delete();
        }
        
        return $token;
    }
    
    /**
     * Verify password
     */
    private function verifyPassword(string $password, string $hash): bool
    {
        // SuiteCRM uses MD5 (legacy)
        // TODO: Migrate to bcrypt
        return md5($password) === $hash;
    }
    
    /**
     * Format user data for response
     */
    private function formatUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->user_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'title' => $user->title,
            'department' => $user->department,
            'is_admin' => $user->is_admin,
            'created_at' => $user->date_entered,
            'last_login' => $user->last_login ?? null
        ];
    }
    
    /**
     * Change user password
     */
    public function changePassword(string $userId, string $currentPassword, string $newPassword): void
    {
        $user = User::findOrFail($userId);
        
        // Verify current password
        if (!$this->verifyPassword($currentPassword, $user->user_hash)) {
            throw new \Exception('Current password is incorrect', 400);
        }
        
        // Update password (still using MD5 for compatibility)
        // TODO: Migrate to bcrypt
        $user->update(['user_hash' => md5($newPassword)]);
        
        // Revoke all refresh tokens
        ApiRefreshToken::where('user_id', $userId)->delete();
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset(string $email): void
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            // Don't reveal if email exists
            return;
        }
        
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        
        // Store token (you'd need a password_resets table)
        // For now, just log it
        \Log::info('Password reset requested', [
            'user_id' => $user->id,
            'email' => $email,
            'token' => $resetToken
        ]);
        
        // In production, send email with reset link
    }
    
    /**
     * Get active sessions for user
     */
    public function getActiveSessions(string $userId): array
    {
        return ApiRefreshToken::where('user_id', $userId)
            ->valid()
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'ip_address' => $token->ip_address,
                    'user_agent' => $token->user_agent,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at
                ];
            })
            ->toArray();
    }
    
    /**
     * Revoke specific session
     */
    public function revokeSession(string $userId, string $sessionId): void
    {
        ApiRefreshToken::where('user_id', $userId)
            ->where('id', $sessionId)
            ->delete();
    }
    
    /**
     * Revoke all sessions except current
     */
    public function revokeAllSessions(string $userId, string $currentToken): void
    {
        ApiRefreshToken::where('user_id', $userId)
            ->where('token', '!=', $currentToken)
            ->delete();
    }
}