<?php
class ReviewModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commitTransaction()
    {
        return $this->pdo->commit();
    }

    public function rollbackTransaction()
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return true;
    }

    public function getReviews()
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT e.name, tr.*, t.name AS trait_name 
             FROM trait_reviews tr
             JOIN traits t ON tr.trait_id = t.id
             JOIN  entities e ON tr.entity_id = e.id
             ORDER BY tr.created_at DESC"
            );
            $stmt->execute();
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->attachReviewImages($reviews);
        } catch (Exception $e) {
            error_log("Error fetching reviews: " . $e->getMessage());
            return [];
        }
    }

    public function deleteReview($reviewId)
    {
        try {
            $this->beginTransaction();

            $query = "DELETE FROM trait_reviews WHERE id = ?";
            $query = "SELECT user_name, entity_id, created_at FROM trait_reviews WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $reviewId, PDO::PARAM_INT);
            $stmt->execute();
            $review = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$review) {
                $this->rollbackTransaction();
                return false;
            }

            $stmt = $this->pdo->prepare("DELETE FROM images WHERE entity_type = 'review' AND entity_id = ?");
            $stmt->bindParam(1, $reviewId, PDO::PARAM_INT);
            $stmt->execute();

            $query = "DELETE FROM trait_reviews WHERE user_name = ? AND entity_id = ? AND created_at = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $review['user_name'], PDO::PARAM_STR);
            $stmt->bindParam(2, $review['entity_id'], PDO::PARAM_INT);
            $stmt->bindParam(3, $review['created_at']);
            $success = $stmt->execute();

            if ($success) {
                $this->commitTransaction();
                return true;
            } else {
                $this->rollbackTransaction();
                return false;
            }
        } catch (Exception $e) {
            $this->rollbackTransaction();
            error_log("Error deleting review: " . $e->getMessage());
            return false;
        }
    }

    public function getReviewsUser($user_name)
    {
        try {
            $query = "SELECT tr.id, tr.entity_id, tr.user_name, tr.trait_id, tr.rating, tr.comment, tr.created_at,
                     t.name AS trait_name
              FROM trait_reviews tr
              JOIN traits t ON tr.trait_id = t.id
              WHERE tr.user_name = ?
              ORDER BY tr.created_at DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $user_name, PDO::PARAM_STR);
            $stmt->execute();
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->attachReviewImages($reviews);
        } catch (PDOException $e) {
            error_log("Database error in getReviewsUser: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log("General error in getReviewsUser: " . $e->getMessage());
            return [];
        }
    }

    public function getReviewsForEntity($entity_id)
    {
        try {
            $query = "SELECT e.name, tr.id, tr.entity_id, tr.user_name, tr.trait_id, tr.rating, tr.comment, tr.created_at,
                     t.name AS trait_name,
                     i.filename AS profile_picture_filename,
                     i.filepath AS profile_picture_path
              FROM trait_reviews tr
              JOIN traits t ON tr.trait_id = t.id
              JOIN users u ON tr.user_name = u.username
              JOIN entities e ON tr.entity_id = e.id
              LEFT JOIN images i ON u.id = i.entity_id AND i.entity_type = 'user' AND i.status = 'active'
              WHERE tr.entity_id = ?
              ORDER BY tr.created_at DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $entity_id, PDO::PARAM_INT);
            $stmt->execute();
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->attachReviewImages($reviews);
        } catch (PDOException $e) {
            error_log("Database error in getReviewsForEntity: " . $e->getMessage());
            return [];
        } catch (Exception $e) {
            error_log("General error in getReviewsForEntity: " . $e->getMessage());
            return [];
        }
    }

    private function attachReviewImages($reviews)
    {
        if (empty($reviews)) {
            return $reviews;
        }

        $reviewIds = array_column($reviews, 'id');
        $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));

        $query = "SELECT entity_id, filepath FROM images WHERE entity_type = 'review' AND entity_id IN ($placeholders)";
        $stmt = $this->pdo->prepare($query);

        foreach ($reviewIds as $index => $id) {
            $stmt->bindValue($index + 1, $id);
        }

        $stmt->execute();
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reviewImages = [];
        foreach ($images as $image) {
            $reviewId = $image['entity_id'];
            if (!isset($reviewImages[$reviewId])) {
                $reviewImages[$reviewId] = [];
            }
            $reviewImages[$reviewId][] = $image['filepath'];
        }

        foreach ($reviews as &$review) {
            $reviewId = $review['id'];
            $review['images'] = isset($reviewImages[$reviewId]) ? $reviewImages[$reviewId] : [];
        }

        return $reviews;
    }

    public function insertReview($entity_id, $user_name, $trait_id, $rating, $comment, $timestamp = null)
    {
        try {
            if ($timestamp === null) {
                $timestamp = date('Y-m-d H:i:s');
            }

            $sql = "INSERT INTO trait_reviews (entity_id, user_name, trait_id, rating, comment, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);

            if (!$stmt) {
                error_log("ERROR: Prepare failed: " . implode(", ", $this->pdo->errorInfo()));
                return false;
            }

            $entity_id = htmlspecialchars(strip_tags($entity_id));
            $user_name = htmlspecialchars(strip_tags($user_name));
            $trait_id = htmlspecialchars(strip_tags($trait_id));
            $rating = htmlspecialchars(strip_tags($rating));
            $comment = htmlspecialchars(strip_tags($comment));

            $stmt->bindParam(1, $entity_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $user_name, PDO::PARAM_STR);
            $stmt->bindParam(3, $trait_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $rating, PDO::PARAM_INT);
            $stmt->bindParam(5, $comment, PDO::PARAM_STR);
            $stmt->bindParam(6, $timestamp);

            $result = $stmt->execute();

            if (!$result) {
                error_log("ERROR: Execute failed: " . implode(", ", $stmt->errorInfo()));
                return false;
            }

            return $this->pdo->lastInsertId();

        } catch (Exception $e) {
            error_log("EXCEPTION in insertReview: " . $e->getMessage());
            return false;
        }
    }

    public function saveImageReference($reviewId, $imageInfo)
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO images (filename, filepath, filetype, filesize, entity_type, entity_id, status) 
                 VALUES (?, ?, ?, ?, 'review', ?, 'active')"
            );

            $stmt->bindParam(1, $imageInfo['filename']);
            $stmt->bindParam(2, $imageInfo['filepath']);
            $stmt->bindParam(3, $imageInfo['filetype']);
            $stmt->bindParam(4, $imageInfo['filesize']);
            $stmt->bindParam(5, $reviewId);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error saving image reference: " . $e->getMessage());
            return false;
        }
    }
}