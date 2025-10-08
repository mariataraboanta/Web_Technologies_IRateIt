<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/IRI_Ballerina_Cappuccina/vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    private $cookieName = 'auth_token';

    public function checkAuth()
    {
        // Verifica daca exista cookie-ul JWT
        if (!isset($_COOKIE[$this->cookieName])) {
            return false; // Nu e logat
        }

        // Valideaza token-ul
        $payload = $this->validateToken($_COOKIE[$this->cookieName]);

        if ($payload === false) {
            // Token invalid - sterge cookie-ul
            $this->clearAuthData();
            return false;
        }

        return $payload; // Returneaza datele utilizatorului
    }

    private function validateToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($_ENV["JWT_SECRET"], 'HS256'));

            // Verifica daca token-ul nu a expirat (dacă folosesti exp)
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return false;
            }

            return $decoded;
        } catch (Exception $e) {
            error_log("JWT validation error: " . $e->getMessage());
            return false;
        }
    }

    public function generateToken($user)
    {
        $role = 'user'; // valoare default
        if (isset($user['role']) && !empty($user['role'])) {
            $role = $user['role'];
        } elseif (isset($user['username']) && $user['username'] === 'admin') {
            // Daca username-ul este 'admin', seteaza rolul ca admin
            $role = 'admin';
        }

        $payload = [
            'iss' => 'review_app', // issuer
            'iat' => time(), // issued at
            'nbf' => time(), // not before
            'exp' => time() + (86400 * 7), // expiration time (7 days)
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'] ?? null,
            'role' => $role // Asigura-te că ai rolul utilizatorului
        ];

        return JWT::encode($payload, $_ENV["JWT_SECRET"], 'HS256');
    }

    public function setAuthData($user)
    {
        $token = $this->generateToken($user);

        $cookieOptions = [
            'expires' => time() + (86400 * 7), // 7 zile
            'path' => '/',
            'domain' => '', // Seteaza domeniul tau aici daca e necesar
            'secure' => false,
            'httponly' => true, // Foarte important pentru securitate
            'samesite' => 'Strict' // Protectie CSRF
        ];

        return setcookie($this->cookieName, $token, $cookieOptions);
    }

    public function clearAuthData()
    {
        //Verifica daca cookie-ul exista inainte sa il stergi
        if (!isset($_COOKIE[$this->cookieName])) {
            error_log("Logout: Cookie nu există deja");
            return true; // Consider că e deja sters
        }

        //Seteaza cookie-ul cu valoare goala si expirare in trecut
        $result1 = setcookie($this->cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/IRI_Ballerina_Cappuccina',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        //Incearcă si cu unset (pentru compatibilitate)
        if (isset($_COOKIE[$this->cookieName])) {
            unset($_COOKIE[$this->cookieName]);
        }

        //Setează si cu timpul curent - 1 zi (fallback)
        setcookie($this->cookieName, '', time() - 86400, '/');

        error_log("Logout: Cookie cleared - result: " . ($result1 ? 'success' : 'failed'));

        return $result1;
    }

    // Middleware pentru protejarea rutelor
    public function requireAuth()
    {
        $userData = $this->checkAuth();

        if (!$userData) {
            // Redirect la login sau returneaza eroare JSON
            if ($this->isApiRequest()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized', 'message' => 'Authentication required']);
                exit();
            } else {
                header('Location: /login');
                exit();
            }
        }

        return $userData;
    }

    private function isApiRequest()
    {
        return strpos($_SERVER['REQUEST_URI'], '/api/') === 0 ||
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }

    public function checkRole($requiredRole)
    {
        // Obtine JWT din cookie
        $jwt = $_COOKIE['jwt'] ?? null;
        if (!$jwt) {
            return false;
        }

        try {
            $decoded = JWT::decode($jwt, new Key($_ENV["JWT_SECRET"], 'HS256'));

            // Verifica rolul din JWT
            if (!isset($decoded->role)) {
                return false;
            }

            // Verifica daca utilizatorul are rolul necesar
            if ($requiredRole === 'admin' && $decoded->role !== 'admin') {
                return false;
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>