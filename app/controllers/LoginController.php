<?php
class LoginController extends Controller
{
    private function sanitizeData($data) 
    {
        if (is_array($data)) {
            // Parcurge fiecare element din array
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeData($value);
                } elseif (is_string($value)) {
                    // Sanitizeaza valorile string
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
        } elseif (is_string($data)) {
            // Sanitizeaza string-uri simple
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    private function sanitizeInput($input)
    {
        if (is_string($input)) {
            // Sanitizeaza input-ul
            return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

    public function verifyUser()
    {
        if (!isset($this->params['email'], $this->params['password'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing credentials']);
            exit();
        }

         $email = $this->sanitizeInput($this->params['email']);
        $password = $this->params['password'];

        $user = $this->model->verifyEmail($email);
        if ($user) {
            //verifica parola cu hashul stocat in baza de date
            if (password_verify($password, $user['password_hash'])) {
                //seteaza coookieul JWT httpOnly
                (new AuthMiddleware())->setAuthData($user);

                $this->sendResponse(200, [
                    'success' => true,
                    'message' => 'Login successful',
                    'redirect' => '/IRI_Ballerina_Cappuccina/public/categories'
                ]);
            } else {
                $this->sendResponse(401, ['error' => 'Unauthorized', 'message' => 'Parola incorectă!']);
            }
        } else {
            $this->sendResponse(404, ['error' => 'Invalid credentials', 'message' => 'Utilizatorul nu există!']);
        }
    }

    public function logout()
    {
        try {
            error_log("Logout method called");

            //verifica daca utilizatorul este autentificat inainte de logout
            $authMiddleware = new AuthMiddleware();

            //sterge cookieul JWT
            $clearResult = $authMiddleware->clearAuthData();
            error_log("Clear auth data result: " . ($clearResult ? 'success' : 'failed'));

            //adauga headere pentru a preveni cache-ul
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Logout successful',
                'redirect' => '/IRI_Ballerina_Cappuccina/public/login'
            ]);

        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            $this->sendResponse(500, [
                'success' => false,
                'error' => 'Server error',
                'message' => 'Logout failed'
            ]);
        }
    }

    public function getUser()
    {
        $authMiddleware = new AuthMiddleware();
        $userData = $authMiddleware->checkAuth();

        if (!$userData) {
            $this->sendResponse(401, ['error' => 'Unauthorized', 'message' => 'Not authenticated']);
            return;
        }

        $user = $this->model->getUserInfo($userData->id);
        $sanitizedUser = $this->sanitizeData($user);
        $this->sendResponse(200, ['user' =>  $sanitizedUser]);
    }
}
?>