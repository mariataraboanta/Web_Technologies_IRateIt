<?php
class CategoriesModel
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }
    
    public function getCategories()
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM categories");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getCategories: " . $e->getMessage());
            return [];
        }
    }

    public function getApprovedCategories()
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE status = 'approved'");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getApprovedCategories: " . $e->getMessage());
            return [];
        }
    }

    public function approveCategory($categoryId)
    {
        try {
            $this->pdo->beginTransaction();
            
            $query = "UPDATE categories SET status = 'approved' WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $affected = $stmt->rowCount() > 0;
            
            $this->pdo->commit();
            return $affected;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Database error in approveCategory: " . $e->getMessage());
            return false;
        }
    }

    public function rejectCategory($categoryId)
    {
        try {
            $this->pdo->beginTransaction();
            
            $query = "UPDATE categories SET status = 'rejected' WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $categoryId, PDO::PARAM_INT);
            $stmt->execute();
            
            $affected = $stmt->rowCount() > 0;
            
            $this->pdo->commit();
            return $affected;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Database error in rejectCategory: " . $e->getMessage());
            return false;
        }
    }

    public function deleteCategory($categoryId)
    {
        try {
            $this->pdo->beginTransaction();
            
            //stergerea traiturilor asociate cu categoria
            $traitStmt = $this->pdo->prepare("DELETE FROM traits WHERE category_id = ?");
            $traitStmt->bindParam(1, $categoryId, PDO::PARAM_INT);
            $traitStmt->execute();
            
            // stergerea categoriei
            $categoryStmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
            $categoryStmt->bindParam(1, $categoryId, PDO::PARAM_INT);
            $categoryStmt->execute();
            
            $affected = $categoryStmt->rowCount() > 0;
            
            $this->pdo->commit();
            return $affected;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Database error in deleteCategory: " . $e->getMessage());
            return false;
        }
    }

    public function searchCategories($searchTerm)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE name LIKE ?");
            $searchParam = "%" . $searchTerm . "%";
            $stmt->bindParam(1, $searchParam, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in searchCategories: " . $e->getMessage());
            return [];
        }
    }
    
    public function createCategory($name, $traits)
    {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("INSERT INTO categories (name, status) VALUES (?, 'pending')");
            $stmt->bindParam(1, $name, PDO::PARAM_STR);
            $stmt->execute();
            
            $categoryId = $this->pdo->lastInsertId();

            if (is_string($traits)) {
                $traits = json_decode($traits, true);
            }

            if (!empty($traits) && is_array($traits)) {
                $stmt2 = $this->pdo->prepare("INSERT INTO traits (category_id, name) VALUES (?, ?)");
                
                foreach ($traits as $trait) {
                    $stmt2->bindValue(1, $categoryId, PDO::PARAM_INT);
                    $stmt2->bindValue(2, $trait, PDO::PARAM_STR);
                    $stmt2->execute();
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Database error in createCategory: " . $e->getMessage());
            return false;
        }
    }

    public function categoryExists($name)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$name]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Database error in categoryExists: " . $e->getMessage());
            return false;
        }
    }

    public function importCategory($name, $traits = []) {
        try {
            $this->pdo->beginTransaction();
            
            // verificare daca categoria exista deja
            $stmt = $this->pdo->prepare("INSERT INTO categories (name, status) VALUES (?, 'approved')");
            $stmt->execute([$name]);
            $categoryId = $this->pdo->lastInsertId();
            
            if (!empty($traits)) {
                $traitStmt = $this->pdo->prepare("INSERT INTO traits (category_id, name) VALUES (?, ?)");
                foreach ($traits as $trait) {
                    $traitStmt->execute([$categoryId, $trait]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Database error in importCategory: " . $e->getMessage());
            return false;
        }
    }
    
    public function importCategories($categories) {
        try {
            $this->pdo->beginTransaction();
            
            $categoryStmt = $this->pdo->prepare("INSERT INTO categories (name, status) VALUES (?, 'approved')");
            $traitStmt = $this->pdo->prepare("INSERT INTO traits (category_id, name) VALUES (?, ?)");
            
            foreach ($categories as $category) {
                $categoryStmt->execute([$category['name']]);
                $categoryId = $this->pdo->lastInsertId();
                
                if (!empty($category['traits'])) {
                    foreach ($category['traits'] as $trait) {
                        $traitStmt->execute([$categoryId, $trait]);
                    }
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Database error in importCategories: " . $e->getMessage());
            return false;
        }
    }
}
?>