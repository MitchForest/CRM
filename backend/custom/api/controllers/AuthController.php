<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Api\Auth\JWT;

class AuthController extends BaseController {
    
    public function login(Request $request) {
        $username = $request->get('username');
        $password = $request->get('password');
        
        if (!$username || !$password) {
            return Response::error('Username and password required', 400);
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
            return Response::unauthorized('User not found');
        }
        
        if (!password_verify($password, $user_row['user_hash'])) {
            return Response::unauthorized('Invalid password');
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
        
        return Response::success([
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
    
    public function refresh(Request $request) {
        $refreshToken = $request->get('refreshToken');
        
        if (!$refreshToken) {
            return Response::error('Refresh token required', 400);
        }
        
        try {
            $payload = JWT::decode($refreshToken);
            
            if ($payload['type'] !== 'refresh') {
                return Response::error('Invalid token type', 400);
            }
            
            // Verify refresh token in database
            if (!$this->verifyRefreshToken($payload['user_id'], $refreshToken)) {
                return Response::unauthorized('Invalid refresh token');
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
            
            return Response::success([
                'accessToken' => $newToken
            ]);
            
        } catch (\Exception $e) {
            return Response::unauthorized($e->getMessage());
        }
    }
    
    public function logout(Request $request) {
        $token = $request->getAuthToken();
        
        if ($token) {
            try {
                $payload = JWT::decode($token);
                $this->removeRefreshToken($payload['user_id']);
            } catch (\Exception $e) {
                // Token might be invalid, but we still return success
            }
        }
        
        return Response::success(['message' => 'Logged out successfully']);
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