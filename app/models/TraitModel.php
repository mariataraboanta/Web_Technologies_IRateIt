<?php

class TraitModel extends Controller
{

    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function getTraits()
    {
        try {
            $stmt = $this->pdo->prepare($query = "SELECT * FROM traits");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $categories;
        } catch (PDOException $e) {
            error_log("Error in getTraits: " . $e->getMessage());
            return false;
        }
    }

    public function getTraitsByCategory($category)
    {
        try {
            $query = "SELECT t.id as id, t.name as name FROM traits t JOIN categories c ON t.category_id = c.id WHERE c.name = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $category);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getTraitsByCategory: " . $e->getMessage());
            return false;
        }
    }

    public function createTrait($category, $name)
    {
        try {
            $query = "INSERT INTO traits (name, category) VALUES (?, ?)";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $name);
            $stmt->bindParam(2, $category);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in createTrait: " . $e->getMessage());
            return false;
        }
    }

    public function categoryExists($category)
    {
        try {
            $query = "SELECT COUNT(*) FROM categories WHERE name = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $category);
            $stmt->execute();
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error in categoryExists: " . $e->getMessage());
            return false;
        }
    }
}
?>