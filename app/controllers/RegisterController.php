<?php
class RegisterController extends Controller
{
    public function createUser()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method Not Allowed']);
            return;
        }

        if (!isset($this->params['email'], $this->params['password'], $this->params['username'])) {
            $this->sendResponse(400, ['error' => 'Bad Request', 'message' => 'Toate câmpurile sunt necesare!']);
            return;
        }

        //sanitizare
        $email = filter_var(trim($this->params['email']), FILTER_SANITIZE_EMAIL);
        $username = htmlspecialchars(trim($this->params['username']), ENT_QUOTES, 'UTF-8');
        $password = trim($this->params['password']);

        if (empty($email) || empty($password) || empty($username)) {
            $this->sendResponse(400, ['error' => 'Bad Request', 'message' => 'Toate câmpurile sunt necesare!']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(400, ['error' => 'Bad Request', 'message' => 'Email invalid!']);
            return;
        }

        if (strlen($password) < 8) {
            $this->sendResponse(400, ['error' => 'Bad Request', 'message' => 'Parola trebuie să aibă cel puțin 8 caractere!']);
            return;
        }

        if ($this->model->verifyUser($username)) {
            $this->sendResponse(409, ['error' => 'Conflict', 'message' => 'User deja folosit!']);
            return;
        }

        if ($this->model->verifyEmail($email)) {
            $this->sendResponse(409, ['error' => 'Conflict', 'message' => 'Email deja folosit!']);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);


        $created = $this->model->createUser($username, $email, $hashedPassword);

        if ($created) {
            $this->sendResponse(201, ['success' => true, 'message' => 'Cont creat cu succes!']);
        } else {
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Eroare la crearea contului!']);
        }
    }
}