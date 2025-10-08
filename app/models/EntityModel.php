<?php
class EntityModel
{
    private $pdo;
    public $id;
    public $name;
    public $description;
    public $category;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function getEntities()
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT entities.id, entities.name, entities.description, entities.status,
                categories.name as category_name 
                FROM entities 
                JOIN categories ON entities.category_id = categories.id"
            );
            $stmt->execute();
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $entities;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getApprovedEntities()
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT entities.id, entities.name, entities.description, 
                categories.name as category_name 
                FROM entities 
                JOIN categories ON entities.category_id = categories.id
                WHERE entities.status = 'approved'"
            );
            $stmt->execute();
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $entities;
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteEntity($entityId)
    {
        try {
            $query = "DELETE FROM entities WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $entityId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    public function entityExists($name)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM entities WHERE LOWER(name) = LOWER(?)");
            $stmt->execute([$name]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Database error in entityExists: " . $e->getMessage());
            return false;
        }
    }
    
    public function getApprovedByCategory($name)
    {
        try {
            $query = "
        SELECT 
            e.id, 
            e.name, 
            e.description, 
            e.status,
            c.name AS category_name, 
            i.filepath AS image_path,
            AVG(r.review_avg) AS rating, 
            COUNT(r.review_id) AS review_count
        FROM entities e
        JOIN categories c ON e.category_id = c.id
        LEFT JOIN images i ON i.entity_id = e.id 
        LEFT JOIN (
            SELECT 
                entity_id, 
                CONCAT(user_name, '|', created_at) AS review_id,
                AVG(rating) AS review_avg
            FROM trait_reviews
            GROUP BY entity_id, user_name, created_at
        ) r ON r.entity_id = e.id
        WHERE c.name = ? and e.status= 'approved'
        GROUP BY e.id
    ";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $name);
            $stmt->execute();
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $entities;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getByCategory($name)
    {
        try {
            $query = "
        SELECT 
            e.id, 
            e.name, 
            e.description, 
            e.status,
            c.name AS category_name, 
            i.filepath,
            AVG(r.review_avg) AS rating, 
            COUNT(r.review_id) AS review_count
        FROM entities e
        JOIN categories c ON e.category_id = c.id
        LEFT JOIN images i ON i.entity_id = e.id 
        LEFT JOIN (
            SELECT 
                entity_id, 
                CONCAT(user_name, '|', created_at) AS review_id,
                AVG(rating) AS review_avg
            FROM trait_reviews
            GROUP BY entity_id, user_name, created_at
        ) r ON r.entity_id = e.id
        WHERE c.name = ?
        GROUP BY e.id
    ";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $name);
            $stmt->execute();
            $entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $entities;
        } catch (Exception $e) {
            return false;
        }
    }

    public function createEntity($name, $description, $category)
    {
        try {
            $this->pdo->beginTransaction();

            //obtinem id-ul categoriei
            $stmt = $this->pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $stmt->bindParam(1, $category);
            $stmt->execute();
            $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$categoryData) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Category not found'];
            }

            $categoryId = $categoryData['id'];

            //inseram entitatea in baza de date
            $stmt = $this->pdo->prepare("INSERT INTO entities (name, description, category_id) VALUES (?, ?, ?)");
            $stmt->bindParam(1, $name);
            $stmt->bindParam(2, $description);
            $stmt->bindParam(3, $categoryId);
            $stmt->execute();

            $entityId = $this->pdo->lastInsertId();

            $this->pdo->commit();

            return [
                'success' => true,
                'entity' => [
                    'id' => $entityId,
                    'name' => $name,
                    'description' => $description,
                    'category_id' => $categoryId
                ]
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function saveImageReference($entityId, $imageInfo)
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO images (filename, filepath, filetype, filesize, entity_type, entity_id, status) 
                VALUES (?, ?, ?, ?, 'entity', ?, 'active')"
            );

            $stmt->bindParam(1, $imageInfo['filename']);
            $stmt->bindParam(2, $imageInfo['filepath']);
            $stmt->bindParam(3, $imageInfo['filetype']);
            $stmt->bindParam(4, $imageInfo['filesize']);
            $stmt->bindParam(5, $entityId);

            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    public function approveEntity($entityId)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE entities SET status = 'approved' WHERE id = ?");
            $stmt->bindParam(1, $entityId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    public function rejectEntity($entityId)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE entities SET status = 'rejected' WHERE id = ?");
            $stmt->bindParam(1, $entityId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}
?>