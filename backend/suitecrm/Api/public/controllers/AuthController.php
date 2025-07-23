<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Api\Auth\JWT;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController extends BaseController {
    
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$username || !$password) {
            return $this->validationErrorResponse($response, 'Username and password required', [
                'username' => $username ? null : 'Required field',
                'password' => $password ? null : 'Required field'
            ]);
        }
        
        // Authenticate user
        global $current_user, $db;
        
        // Find user
        $username_escaped = $db->quote($username);
        // Remove surrounding quotes if present
        $username_escaped = trim($username_escaped, "'\"");
        $query = "SELECT * FROM users WHERE user_name = '$username_escaped' AND deleted = 0 AND status = 'Active'";
        $result = $db->query($query);
        $user_row = $db->fetchByAssoc($result);
        
        if (!$user_row) {
            return $this->unauthorizedResponse($response, 'Invalid username or password');
        }
        
        if (!password_verify($password, $user_row['user_hash'])) {
            return $this->unauthorizedResponse($response, 'Invalid username or password');
        }
        
        // Load user bean
        $current_user = \BeanFactory::getBean('Users', $user_row['id']);
        
        // Generate JWT token
        $payload = [
            'user_id' => $current_user->id,
            'username' => $current_user->user_name,
            'email' => $current_user->email1,
            'exp' => time() + (24 * 60 * 60), // 24 hours
            'iat' => time()
        ];
        
        $token = JWT::encode($payload);
        
        // Generate refresh token
        $refreshPayload = [
            'user_id' => $current_user->id,
            'type' => 'refresh',
            'exp' => time() + (30 * 24 * 60 * 60), // 30 days
            'iat' => time()
        ];
        
        $refreshToken = JWT::encode($refreshPayload);
        
        // Store refresh token in database
        $this->storeRefreshToken($current_user->id, $refreshToken);
        
        return $response->json([
            'accessToken' => $token,
            'refreshToken' => $refreshToken,
            'user' => [
                'id' => $current_user->id,
                'username' => $current_user->user_name,
                'email' => $current_user->email1,
                'firstName' => $current_user->first_name,
                'lastName' => $current_user->last_name
            ]
        ]);
    }
    
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $refreshToken = $data['refreshToken'] ?? '';
        
        if (!$refreshToken) {
            return $this->validationErrorResponse($response, 'Refresh token required', ['refreshToken' => 'Required field']);
        }
        
        try {
            $payload = JWT::decode($refreshToken);
            
            if (($payload['type'] ?? '') !== 'refresh') {
                return $this->validationErrorResponse($response, 'Invalid token type', ['refreshToken' => 'Must be a refresh token']);
            }
            
            // Verify refresh token in database
            if (!$this->verifyRefreshToken($payload['user_id'], $refreshToken)) {
                return $this->unauthorizedResponse($response, 'Invalid refresh token');
            }
            
            // Generate new access token
            $user = \BeanFactory::getBean('Users', $payload['user_id']);
            
            $newPayload = [
                'user_id' => $user->id,
                'username' => $user->user_name,
                'email' => $user->email1,
                'exp' => time() + (24 * 60 * 60),
                'iat' => time()
            ];
            
            $newToken = JWT::encode($newPayload);
            
            return $response->json([
                'accessToken' => $newToken
            ]);
            
        } catch (\Exception $e) {
            return $this->unauthorizedResponse($response, $e->getMessage());
        }
    }
    
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            try {
                $payload = JWT::decode($token);
                $this->removeRefreshToken($payload['user_id']);
            } catch (\Exception $e) {
                // Token might be invalid, but we still return success
            }
        }
        
        return $response->json(['message' => 'Logged out successfully']);
    }
    
    private function storeRefreshToken($userId, $token) {
        global $db;
        
        // First create the table if it doesn't exist
        $this->createRefreshTokenTable();
        
        // Remove old tokens
        $db->query("DELETE FROM api_refresh_tokens WHERE user_id = '$userId'");
        
        // Store new token
        $id = create_guid();
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
        
        $db->query("INSERT INTO api_refresh_tokens (id, user_id, token, expires_at, created_at) 
                    VALUES ('$id', '$userId', '$token', '$expires', NOW())");
    }
    
    private function verifyRefreshToken($userId, $token) {
        global $db;
        
        $result = $db->query("SELECT * FROM api_refresh_tokens 
                              WHERE user_id = '$userId' 
                              AND token = '$token' 
                              AND expires_at > NOW()");
        
        return $db->fetchByAssoc($result) !== false;
    }
    
    private function removeRefreshToken($userId) {
        global $db;
        $db->query("DELETE FROM api_refresh_tokens WHERE user_id = '$userId'");
    }
    
    private function createRefreshTokenTable() {
        global $db;
        
        $db->query("CREATE TABLE IF NOT EXISTS api_refresh_tokens (
            id CHAR(36) NOT NULL PRIMARY KEY,
            user_id CHAR(36) NOT NULL,
            token TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }
}