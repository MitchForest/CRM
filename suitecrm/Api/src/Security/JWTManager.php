<?php
declare(strict_types=1);

namespace Api\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTManager
{
    private string $secret;
    private string $algorithm = 'HS256';
    private int $expiration = 3600; // 1 hour
    private int $refreshExpiration = 604800; // 7 days

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Generate JWT token
     * 
     * @param array $payload
     * @return string
     */
    public function encode(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->expiration;

        $token = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire,
            'iss' => 'suitecrm-api'
        ]);

        return JWT::encode($token, $this->secret, $this->algorithm);
    }

    /**
     * Generate refresh token
     * 
     * @param string $userId
     * @return string
     */
    public function generateRefreshToken(string $userId): string
    {
        $payload = [
            'user_id' => $userId,
            'type' => 'refresh',
            'exp' => time() + $this->refreshExpiration
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Decode and validate JWT token
     * 
     * @param string $token
     * @return array
     * @throws \Exception
     */
    public function decode(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (\Exception $e) {
            throw new \Exception('Invalid token: ' . $e->getMessage());
        }
    }

    /**
     * Refresh access token using refresh token
     * 
     * @param string $refreshToken
     * @return array
     * @throws \Exception
     */
    public function refresh(string $refreshToken): array
    {
        $payload = $this->decode($refreshToken);
        
        if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
            throw new \Exception('Invalid refresh token');
        }

        // Generate new access token
        $newToken = $this->encode([
            'user_id' => $payload['user_id'],
            'username' => $payload['username'] ?? ''
        ]);

        // Generate new refresh token
        $newRefreshToken = $this->generateRefreshToken($payload['user_id']);

        return [
            'access_token' => $newToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => $this->expiration
        ];
    }

    /**
     * Extract token from Authorization header
     * 
     * @param string $authHeader
     * @return string|null
     */
    public function extractToken(string $authHeader): ?string
    {
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
}