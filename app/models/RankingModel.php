<?php
class RankingModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
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
        } catch (PDOException $e) {
            error_log("Error in getAllEntities: " . $e->getMessage());
            return [];
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
        } catch (PDOException $e) {
            error_log("Error in getEntityTraits: " . $e->getMessage());
            return [];
        }
    }
}
?>