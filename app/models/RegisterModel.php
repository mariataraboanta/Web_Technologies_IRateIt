<?php

class RegisterModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function verifyEmail($email)
    {
        try {
            $query = "SELECT 1 FROM users WHERE email = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $email, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log("Eroare la verificarea emailului: " . $e->getMessage());
            return false;
        }
    }

    public function verifyUser($username)
    {
        try {
            $query = "SELECT 1 FROM users WHERE username = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log("Eroare la verificarea username-ului: " . $e->getMessage());
            return false;
        }
    }

    public function createUser($username, $email, $password)
    {
        try {
            $query = "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->bindParam(2, $email, PDO::PARAM_STR);
            $stmt->bindParam(3, $password, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Eroare la crearea userului: " . $e->getMessage());
            return false;
        }
    }
}