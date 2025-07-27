<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ApiRefreshToken;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends Controller
{
    private string $jwtKey;
    private string $jwtAlgorithm = 'HS256';
    
    public function __construct()
    {
        parent::__construct();
        $this->jwtKey = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    }
    
    public function login(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'email' => 'required_without:username|email',
            'username' => 'required_without:email|string',
            'password' => 'required|string'
        ]);
        
        $username = $data['email'] ?? $data['username'];
        $password = $data['password'];
        
        // Find user by email or username
        $user = User::where(function ($query) use ($username) {
            if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $query->where('email1', $username);
            } else {
                $query->where('user_name', $username);
            }
        })
        ->where('deleted', 0)
        ->where('status', 'Active')
        ->first();
        
        if (!$user || !password_verify($password, $user->user_hash)) {
            return $this->error($response, 'Invalid username or password', 401);
        }
        
        // Generate JWT token
        $payload = [
            'user_id' => $user->id,
            'username' => $user->user_name,
            'email' => $user->email1 ?? '',
            'exp' => time() + (15 * 60), // 15 minutes
            'iat' => time()
        ];
        
        $token = JWT::encode($payload, $this->jwtKey, $this->jwtAlgorithm);
        
        // Generate refresh token
        $refreshPayload = [
            'user_id' => $user->id,
            'type' => 'refresh',
            'exp' => time() + (30 * 24 * 60 * 60), // 30 days
            'iat' => time()
        ];
        
        $refreshToken = JWT::encode($refreshPayload, $this->jwtKey, $this->jwtAlgorithm);
        
        // Store refresh token
        $this->storeRefreshToken($user->id, $refreshToken);
        
        return $this->json($response, [
            'accessToken' => $token,
            'refreshToken' => $refreshToken,
            'user' => [
                'id' => $user->id,
                'username' => $user->user_name,
                'email' => $user->email1 ?? '',
                'firstName' => $user->first_name ?? '',
                'lastName' => $user->last_name ?? ''
            ]
        ]);
    }
    
    public function refresh(Request $request, Response $response, array $args): Response
    {
        $data = $this->validate($request, [
            'refreshToken' => 'required|string'
        ]);
        
        $refreshToken = $data['refreshToken'];
        
        try {
            $payload = JWT::decode($refreshToken, new Key($this->jwtKey, $this->jwtAlgorithm));
            
            if (($payload->type ?? '') !== 'refresh') {
                return $this->error($response, 'Invalid token type', 400);
            }
            
            // Verify refresh token in database
            if (!$this->verifyRefreshToken($payload->user_id, $refreshToken)) {
                return $this->error($response, 'Invalid refresh token', 401);
            }
            
            // Get user data
            $user = User::where('id', $payload->user_id)
                ->where('deleted', 0)
                ->first();
            
            if (!$user) {
                return $this->error($response, 'User not found', 404);
            }
            
            // Generate new access token
            $newPayload = [
                'user_id' => $user->id,
                'username' => $user->user_name,
                'email' => $user->email1 ?? '',
                'exp' => time() + (15 * 60), // 15 minutes
                'iat' => time()
            ];
            
            $newToken = JWT::encode($newPayload, $this->jwtKey, $this->jwtAlgorithm);
            
            return $this->json($response, [
                'accessToken' => $newToken
            ]);
            
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 401);
        }
    }
    
    public function logout(Request $request, Response $response, array $args): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            try {
                $payload = JWT::decode($token, new Key($this->jwtKey, $this->jwtAlgorithm));
                $this->removeRefreshToken($payload->user_id);
            } catch (\Exception $e) {
                // Token might be invalid, but we still return success
            }
        }
        
        return $this->json($response, ['message' => 'Logged out successfully']);
    }
    
    public function getCurrentUser(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $this->error($response, 'Not authenticated', 401);
        }
        
        $user = User::where('id', $userId)
            ->where('deleted', 0)
            ->first();
        
        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }
        
        return $this->json($response, [
            'id' => $user->id,
            'username' => $user->user_name,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'email' => $user->email1 ?? '',
            'title' => $user->title,
            'department' => $user->department,
            'phoneWork' => $user->phone_work,
            'phoneMobile' => $user->phone_mobile
        ]);
    }
    
    public function updateProfile(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $this->error($response, 'Not authenticated', 401);
        }
        
        $data = $this->validate($request, [
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'phoneWork' => 'sometimes|string|max:50',
            'phoneMobile' => 'sometimes|string|max:50'
        ]);
        
        $user = User::find($userId);
        
        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }
        
        // Map camelCase to snake_case fields
        $fieldMapping = [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'phoneWork' => 'phone_work',
            'phoneMobile' => 'phone_mobile'
        ];
        
        $updates = [];
        foreach ($fieldMapping as $inputField => $dbField) {
            if (isset($data[$inputField])) {
                $updates[$dbField] = $data[$inputField];
            }
        }
        
        // Direct fields
        foreach (['title', 'department'] as $field) {
            if (isset($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }
        
        if (!empty($updates)) {
            $user->update($updates);
        }
        
        return $this->json($response, ['message' => 'Profile updated successfully']);
    }
    
    private function storeRefreshToken(string $userId, string $token): void
    {
        // Remove old tokens
        ApiRefreshToken::where('user_id', $userId)->delete();
        
        // Store new token
        ApiRefreshToken::create([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => now()->addDays(30)
        ]);
    }
    
    private function verifyRefreshToken(string $userId, string $token): bool
    {
        return ApiRefreshToken::where('user_id', $userId)
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->exists();
    }
    
    private function removeRefreshToken(string $userId): void
    {
        ApiRefreshToken::where('user_id', $userId)->delete();
    }
    
    private function getCurrentUserId(Request $request): ?string
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        
        $token = substr($authHeader, 7);
        
        try {
            $payload = JWT::decode($token, new Key($this->jwtKey, $this->jwtAlgorithm));
            return $payload->user_id ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}