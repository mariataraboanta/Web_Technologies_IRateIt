<?php
class StatsModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function getTotalEntities()
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM entities");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getTotalReviews()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT CONCAT(user_name, '-', entity_id, '-', DATE(created_at))) as count 
                FROM trait_reviews
            ");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getActiveUsers()
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(DISTINCT user_name) as count FROM trait_reviews");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getAverageRating()
    {
        try {
            $stmt = $this->pdo->query("SELECT NVL(AVG(rating),0) as avg_rating FROM trait_reviews");
            return round($stmt->fetchColumn(), 2);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getTopEntities()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT e.name, 
                       c.name as category,
                       ROUND(COALESCE(AVG(r.rating), 0), 2) as avg_rating,
                       ROUND(COALESCE(AVG(r.rating), 0), 2) as rating,
                       COUNT(DISTINCT r.trait_id) as traits_reviewed,
                       COUNT(r.id) as total_trait_reviews,
                       COUNT(DISTINCT CONCAT(r.user_name, '-', r.entity_id, '-', DATE(r.created_at))) as reviews,
                       COUNT(DISTINCT CONCAT(r.user_name, '-', r.entity_id, '-', DATE(r.created_at))) as review_count
                FROM entities e
                LEFT JOIN categories c ON e.category_id = c.id
                LEFT JOIN trait_reviews r ON e.id = r.entity_id
                GROUP BY e.id, e.name, c.name
                ORDER BY avg_rating DESC, e.name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getWorstEntities()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT e.name, 
                       c.name as category,
                       ROUND(COALESCE(AVG(r.rating), 0), 2) as avg_rating,
                       ROUND(COALESCE(AVG(r.rating), 0), 2) as rating,
                       COUNT(DISTINCT r.trait_id) as traits_reviewed,
                       COUNT(r.id) as total_trait_reviews,
                       COUNT(DISTINCT CONCAT(r.user_name, '-', r.entity_id, '-', DATE(r.created_at))) as reviews,
                       COUNT(DISTINCT CONCAT(r.user_name, '-', r.entity_id, '-', DATE(r.created_at))) as review_count
                FROM entities e
                LEFT JOIN categories c ON e.category_id = c.id
                LEFT JOIN trait_reviews r ON e.id = r.entity_id
                GROUP BY e.id, e.name, c.name
                ORDER BY avg_rating ASC, e.name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getTopEntity()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT e.name, ROUND(AVG(r.rating), 2) as avg_rating
                FROM entities e
                LEFT JOIN trait_reviews r ON e.id = r.entity_id
                GROUP BY e.id, e.name
                HAVING avg_rating IS NOT NULL
                ORDER BY avg_rating DESC
                LIMIT 1
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getWorstEntity()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT e.name, ROUND(AVG(r.rating), 2) as avg_rating
                FROM entities e
                LEFT JOIN trait_reviews r ON e.id = r.entity_id
                GROUP BY e.id, e.name
                HAVING avg_rating IS NOT NULL
                ORDER BY avg_rating ASC
                LIMIT 1
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getAllEntities()
    {
        try {
            $sql = "
                SELECT 
                    e.id,
                    e.name,
                    e.description,
                    c.name AS category_name,
                    c.id AS category_id,
                    ROUND(AVG(r.rating), 2) AS avg_rating,
                    COUNT(DISTINCT r.trait_id) AS traits_reviewed,
                    COUNT(r.id) AS total_trait_reviews,
                    COUNT(DISTINCT CONCAT(r.user_name, '-', r.entity_id, '-', DATE(r.created_at))) AS review_count,
                    MIN(r.rating) AS min_rating,
                    MAX(r.rating) AS max_rating,
                    MAX(r.created_at) AS last_review_date
                FROM 
                    entities e
                    JOIN categories c ON e.category_id = c.id
                    JOIN trait_reviews r ON e.id = r.entity_id
                WHERE 
                    e.status = 'approved' AND
                    c.status = 'approved'
                GROUP BY 
                    e.id, e.name, e.description, c.name, c.id
                HAVING 
                    COUNT(DISTINCT r.trait_id) >= 1
            ";

            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getEntityTraits($entityId)
    {
        try {
            $sql = "
                SELECT 
                    t.id,
                    t.name,
                    AVG(r.rating) AS avg_rating,
                    COUNT(r.id) AS review_count
                FROM 
                    traits t
                    JOIN trait_reviews r ON t.id = r.trait_id
                WHERE 
                    r.entity_id = :entity_id
                GROUP BY 
                    t.id, t.name
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getAllStats()
    {
        try {
            $stats = [];

            $stats[] = [
                'label' => 'Total Entități',
                'value' => $this->getTotalEntities()
            ];

            $stats[] = [
                'label' => 'Total Evaluări',
                'value' => $this->getTotalReviews()
            ];

            $stats[] = [
                'label' => 'Utilizatori Activi',
                'value' => $this->getActiveUsers()
            ];

            $stats[] = [
                'label' => 'Rating Mediu General',
                'value' => $this->getAverageRating()
            ];

            return $stats;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getCategoryStats()
    {
        try {
            $stmt = $this->pdo->query("
            SELECT c.name,
                   COUNT(DISTINCT e.id) as count,
                   COUNT(DISTINCT CONCAT(r.user_name, '-', r.entity_id, '-', DATE(r.created_at))) as reviews,
                   ROUND(COALESCE(AVG(r.rating), 0), 2) as avg_rating
            FROM categories c
            LEFT JOIN entities e ON c.id = e.category_id
            LEFT JOIN trait_reviews r ON e.id = r.entity_id
            GROUP BY c.id, c.name
            ORDER BY count DESC, c.name ASC
        ");

            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
?>