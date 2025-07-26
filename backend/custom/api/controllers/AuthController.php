<?php
namespace Api\Controllers;

use Api\Request;
use Api\Response;
use Api\Auth\JWT;

class AuthController extends BaseController {
    
    public function login(Request $request) {
        $data = $request->getData();
        // Support both email and username fields
        $username = $data['email'] ?? $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$username || !$password) {
            return Response::error('Email and password required', 400);
        }
        
        // Authenticate user
        global $current_user, $db;
        
        // Find user - check if input is email or username
        $username_escaped = $db->quote($username);
        // Ensure quotes are added if not present
        if (strpos($username_escaped, "'") !== 0) {
            $username_escaped = "'" . $username_escaped . "'";
        }
        
        // First check if it's an email
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            // Search by email
            $query = "SELECT u.* 
                     FROM users u
                     JOIN email_addr_bean_rel eabr ON u.id = eabr.bean_id AND eabr.bean_module = 'Users' AND eabr.deleted = 0
                     JOIN email_addresses ea ON eabr.email_address_id = ea.id AND ea.deleted = 0
                     WHERE ea.email_address = $username_escaped AND u.deleted = 0 AND u.status = 'Active'";
        } else {
            // Search by username
            $query = "SELECT * FROM users WHERE user_name = $username_escaped AND deleted = 0 AND status = 'Active'";
        }
        
        $result = $db->query($query);
        $user_row = $db->fetchByAssoc($result);
        
        if (!$user_row) {
            return Response::error('Invalid username or password', 401);
        }
        
        if (!password_verify($password, $user_row['user_hash'])) {
            return Response::error('Invalid username or password', 401);
        }
        
        // Use user data directly from database
        $userId = $user_row['id'];
        $userName = $user_row['user_name'];
        $firstName = $user_row['first_name'] ?? '';
        $lastName = $user_row['last_name'] ?? '';
        
        // Get user email from email_addresses table
        $emailQuery = "SELECT ea.email_address 
                      FROM email_addresses ea
                      JOIN email_addr_bean_rel eabr ON ea.id = eabr.email_address_id
                      WHERE eabr.bean_id = '$userId' AND eabr.bean_module = 'Users' 
                      AND eabr.deleted = 0 AND ea.deleted = 0
                      AND eabr.primary_address = 1
                      LIMIT 1";
        $emailResult = $db->query($emailQuery);
        $emailRow = $db->fetchByAssoc($emailResult);
        $userEmail = $emailRow['email_address'] ?? '';
        
        // Generate JWT token
        $payload = [
            'user_id' => $userId,
            'username' => $userName,
            'email' => $userEmail,
            'exp' => time() + (15 * 60), // 15 minutes
            'iat' => time()
        ];
        
        $token = JWT::encode($payload);
        
        // Generate refresh token
        $refreshPayload = [
            'user_id' => $userId,
            'type' => 'refresh',
            'exp' => time() + (30 * 24 * 60 * 60), // 30 days
            'iat' => time()
        ];
        
        $refreshToken = JWT::encode($refreshPayload);
        
        // Store refresh token in database
        $this->storeRefreshToken($userId, $refreshToken);
        
        return Response::success([
            'accessToken' => $token,
            'refreshToken' => $refreshToken,
            'user' => [
                'id' => $userId,
                'username' => $userName,
                'email' => $userEmail,
                'firstName' => $firstName,
                'lastName' => $lastName
            ]
        ]);
    }
    
    public function refresh(Request $request) {
        $data = $request->getData();
        $refreshToken = $data['refreshToken'] ?? '';
        
        if (!$refreshToken) {
            return Response::error('Refresh token required', 400);
        }
        
        try {
            $payload = JWT::decode($refreshToken);
            
            if (($payload['type'] ?? '') !== 'refresh') {
                return Response::error('Invalid token type', 400);
            }
            
            // Verify refresh token in database
            if (!$this->verifyRefreshToken($payload['user_id'], $refreshToken)) {
                return Response::error('Invalid refresh token', 401);
            }
            
            // Generate new access token - get user data from DB
            global $db;
            $userId = $payload['user_id'];
            $query = "SELECT user_name FROM users WHERE id = '$userId' AND deleted = 0";
            $result = $db->query($query);
            $userData = $db->fetchByAssoc($result);
            
            // Get email separately
            $emailQuery = "SELECT ea.email_address 
                          FROM email_addresses ea
                          JOIN email_addr_bean_rel eabr ON ea.id = eabr.email_address_id
                          WHERE eabr.bean_id = '$userId' AND eabr.bean_module = 'Users' 
                          AND eabr.deleted = 0 AND ea.deleted = 0
                          AND eabr.primary_address = 1
                          LIMIT 1";
            $emailResult = $db->query($emailQuery);
            $emailRow = $db->fetchByAssoc($emailResult);
            $userData['email'] = $emailRow['email_address'] ?? '';
            
            if (!$userData) {
                return Response::error('User not found', 404);
            }
            
            $newPayload = [
                'user_id' => $userId,
                'username' => $userData['user_name'],
                'email' => $userData['email'] ?? '',
                'exp' => time() + (15 * 60), // 15 minutes
                'iat' => time()
            ];
            
            $newToken = JWT::encode($newPayload);
            
            return Response::success([
                'accessToken' => $newToken
            ]);
            
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 401);
        }
    }
    
    public function logout(Request $request) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
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
        $id = $this->generateUUID();
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
    
    public function getCurrentUser(Request $request, Response $response) {
        $userId = $this->getCurrentUserId();
        
        if (!$userId) {
            return Response::error('Not authenticated', 401);
        }
        
        global $db;
        $query = "SELECT id, user_name, first_name, last_name, title, department, phone_work, phone_mobile 
                  FROM users WHERE id = '$userId' AND deleted = 0";
        $result = $db->query($query);
        $user = $db->fetchByAssoc($result);
        
        // Get email separately
        $emailQuery = "SELECT ea.email_address 
                      FROM email_addresses ea
                      JOIN email_addr_bean_rel eabr ON ea.id = eabr.email_address_id
                      WHERE eabr.bean_id = '$userId' AND eabr.bean_module = 'Users' 
                      AND eabr.deleted = 0 AND ea.deleted = 0
                      AND eabr.primary_address = 1
                      LIMIT 1";
        $emailResult = $db->query($emailQuery);
        $emailRow = $db->fetchByAssoc($emailResult);
        $user['email'] = $emailRow['email_address'] ?? '';
        
        if (!$user) {
            return Response::error('User not found', 404);
        }
        
        return Response::success([
            'id' => $user['id'],
            'username' => $user['user_name'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            'email' => $user['email'],
            'title' => $user['title'],
            'department' => $user['department'],
            'phoneWork' => $user['phone_work'],
            'phoneMobile' => $user['phone_mobile']
        ]);
    }
    
    public function updateProfile(Request $request, Response $response) {
        $userId = $this->getCurrentUserId();
        
        if (!$userId) {
            return Response::error('Not authenticated', 401);
        }
        
        $data = $request->getData();
        $allowedFields = ['first_name', 'last_name', 'title', 'department', 'phone_work', 'phone_mobile'];
        
        $updates = [];
        foreach ($allowedFields as $field) {
            if (isset($data[lcfirst(str_replace('_', '', ucwords($field, '_')))])) {
                $value = $data[lcfirst(str_replace('_', '', ucwords($field, '_')))];
                $updates[] = "$field = '" . $GLOBALS['db']->quote($value) . "'";
            }
        }
        
        if (empty($updates)) {
            return Response::error('No fields to update', 400);
        }
        
        global $db;
        $updateQuery = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = '$userId'";
        $db->query($updateQuery);
        
        return Response::success(['message' => 'Profile updated successfully']);
    }
}