<?php
class LoginModel
{
    private $pdo;

    public $username;
    public $email;
    public $password;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function verifyEmail($email)
    {
        try {
            $query = "SELECT * FROM users WHERE email = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>