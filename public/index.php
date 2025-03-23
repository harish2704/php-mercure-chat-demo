<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Chat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

$request = Request::createFromGlobals();
$response = new Response();
$chat = new Chat();

// Enable CORS
$response->headers->set('Access-Control-Allow-Origin', '*');
$response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
$response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
$response->headers->set('Access-Control-Allow-Credentials', 'true');

if ($request->getMethod() === 'OPTIONS') {
    $response->send();
    exit;
}

// Handle routes
$path = $request->getPathInfo();

switch ($path) {
    case '/api/messages':
        if ($request->getMethod() === 'POST') {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['username']) || !isset($data['message'])) {
                $response = new JsonResponse(['error' => 'Username and message are required'], 400);
                break;
            }
            
            $success = $chat->publishMessage($data['username'], $data['message']);
            
            if ($success) {
                $response = new JsonResponse(['status' => 'Message sent!']);
            } else {
                $response = new JsonResponse(['error' => 'Failed to send message'], 500);
            }
        } else {
            $response = new JsonResponse(['error' => 'Method not allowed'], 405);
        }
        break;
        
    case '/api/auth':
        // Generate a new token with only subscribe rights
        $token = $chat->generateJwt(false);
        
        // Set the token as an HTTP-only cookie
        $cookie = new Cookie(
            'mercureAuthorization',
            $token,
            0, // Expires when browser is closed
            '/',
            null,
            false, // Use true in production with HTTPS
            true // HTTP only
        );
        
        $response = new JsonResponse(['status' => 'Authenticated']);
        $response->headers->setCookie($cookie);
        break;
        
    default:
        // Serve the frontend
        if ( $path !== '/' && file_exists(__DIR__ . '/../frontend' . $path)) {
            $response->setContent(file_get_contents(__DIR__ . '/../frontend' . $path));
            
            if (str_ends_with($path, '.js')) {
                $response->headers->set('Content-Type', 'application/javascript');
            } elseif (str_ends_with($path, '.css')) {
                $response->headers->set('Content-Type', 'text/css');
            } else {
                $response->headers->set('Content-Type', 'text/html');
            }
        } else {
            $response->setContent(file_get_contents(__DIR__ . '/../frontend/index.html'));
            $response->headers->set('Content-Type', 'text/html');
        }
}

$response->send();
