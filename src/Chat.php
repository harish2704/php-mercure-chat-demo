<?php

namespace App;

class Chat {
    private string $mercureHubUrl;
    private string $mercurePublisherJwtKey;
    private string $mercureSubscriberJwtKey;
    
    public function __construct() {
        $this->mercureHubUrl = '/.well-known/mercure';
        $this->mercurePublisherJwtKey = $_ENV['PUBLISHER_JWT'];
        $this->mercureSubscriberJwtKey = $_ENV['SUBSCRIBER_JWT'];
    }
    
    public function generateJwt(bool $publish = true): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'mercure' => [
                'publish' => $publish ? ['*'] : [],
                'subscribe' => ['*']
            ]
        ]);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $jwtKey = $publish? $this->mercurePublisherJwtKey : $this->mercureSubscriberJwtKey;
        
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, $jwtKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }
    
    public function publishMessage(string $username, string $message): bool {
        $data = json_encode([
            'username' => htmlspecialchars($username),
            'message' => htmlspecialchars($message),
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
        
        $jwt = $this->generateJwt(true); // Generate a token with publish rights
        
        $mercureUrl = 'http://' . $_SERVER['HTTP_HOST'] . $this->mercureHubUrl;
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: Bearer ' . $jwt
                ],
                'content' => http_build_query(['topic' => 'https://chat.example.com/messages', 'data' => $data])
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($mercureUrl, false, $context);
        
        return $result !== false;
    }
}
