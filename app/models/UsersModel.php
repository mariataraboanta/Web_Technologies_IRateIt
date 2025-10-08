<?php

class UsersModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function getUsers()
    {
        try {
            $query = "SELECT id, username, email, created_at FROM users ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUsers: " . $e->getMessage());
            return false;
        }
    }

    public function getUserById($userId)
    {
        try {
            $query = "SELECT id, username, email, created_at FROM users WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserById: " . $e->getMessage());
            return false;
        }
    }

    public function getUserByUsername($username)
    {
        try {
            $query = "SELECT id, username, email, created_at FROM users WHERE username = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserByUsername: " . $e->getMessage());
            return false;
        }
    }

    public function updateUser($userId, $username, $email)
    {
        try {
            $oldUser = $this->getUserById($userId);
            if (!$oldUser) {
                return ['success' => false, 'error' => 'User not found'];
            }
            $oldUsername = $oldUser['username'];

            $checkQuery = "SELECT id FROM users WHERE username = ? AND id != ?";
            $checkStmt = $this->pdo->prepare($checkQuery);
            $checkStmt->bindParam(1, $username, PDO::PARAM_STR);
            $checkStmt->bindParam(2, $userId, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->fetch()) {
                return ['success' => false, 'error' => 'Username already exists'];
            }

            $query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->bindParam(2, $email, PDO::PARAM_STR);
            $stmt->bindParam(3, $userId, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                return ['success' => false, 'error' => 'Database error'];
            }

            if ($oldUsername !== $username) {
                $updateTraitReviewsQuery = "UPDATE trait_reviews SET user_name = ? WHERE user_name = ?";
                $updateStmt = $this->pdo->prepare($updateTraitReviewsQuery);
                $updateStmt->bindParam(1, $username, PDO::PARAM_STR);
                $updateStmt->bindParam(2, $oldUsername, PDO::PARAM_STR);
                $updateStmt->execute();
            }

            return ['success' => true];
        } catch (PDOException $e) {
            error_log("Error in updateUser: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error'];
        }
    }

    public function getUserReviews($username)
    {
        try {
            $query = "SELECT tr.id, tr.entity_id, e.name AS entity_name, tr.user_name, tr.trait_id,
                         tr.rating, tr.comment, tr.created_at, t.name AS trait_name
                  FROM trait_reviews tr
                  JOIN traits t ON tr.trait_id = t.id
                  JOIN entities e ON tr.entity_id = e.id
                  WHERE tr.user_name = ?
                  ORDER BY tr.created_at DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $username, PDO::PARAM_STR);
            $stmt->execute();
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $reviews;
        } catch (PDOException $e) {
            error_log("Error in getUserReviews: " . $e->getMessage());
            return false;
        }
    }

    public function deleteUser($userId)
{
    try {
        // 1. Obține username-ul asociat ID-ului
        $query = "SELECT username FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->execute();
        $username = $stmt->fetchColumn();

        if (!$username) {
            return false; // utilizatorul nu există
        }

        // 2. Șterge review-urile din trait_reviews cu acel username
        $query = "DELETE FROM trait_reviews WHERE user_name = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(1, $username, PDO::PARAM_STR);
        $stmt->execute();

        // 3. Șterge utilizatorul
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error in deleteUser: " . $e->getMessage());
        return false;
    }
}


    public function saveImage($imageData)
    {
        try {
            $query = "INSERT INTO images (filename, filepath, filetype, filesize, entity_type, entity_id, status) 
                      VALUES (?, ?, ?, ?, ?, ?, 'active')";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $imageData['filename'], PDO::PARAM_STR);
            $stmt->bindParam(2, $imageData['filepath'], PDO::PARAM_STR);
            $stmt->bindParam(3, $imageData['filetype'], PDO::PARAM_STR);
            $stmt->bindParam(4, $imageData['filesize'], PDO::PARAM_INT);
            $stmt->bindParam(5, $imageData['entity_type'], PDO::PARAM_STR);
            $stmt->bindParam(6, $imageData['entity_id'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                return $this->pdo->lastInsertId();
            }

            return false;
        } catch (PDOException $e) {
            error_log("Error in saveImage: " . $e->getMessage());
            return false;
        }
    }

    public function getUserProfilePicture($userId)
    {
        try {
            $query = "SELECT id, filename, filepath, filetype, filesize, upload_date 
                      FROM images 
                      WHERE entity_type = 'user' AND entity_id = ? AND status = 'active' 
                      ORDER BY upload_date DESC 
                      LIMIT 1";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getUserProfilePicture: " . $e->getMessage());
            return false;
        }
    }

    public function deleteUserProfilePicture($userId)
    {
        try {
            $query = "UPDATE images SET status = 'deleted' 
                      WHERE entity_type = 'user' AND entity_id = ? AND status = 'active'";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $userId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in deleteUserProfilePicture: " . $e->getMessage());
            return false;
        }
    }

    public function getImageById($imageId)
    {
        try {
            $query = "SELECT * FROM images WHERE id = ? AND status = 'active'";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $imageId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getImageById: " . $e->getMessage());
            return false;
        }
    }

    public function getEntityImages($entityType, $entityId)
    {
        try {
            $query = "SELECT * FROM images 
                      WHERE entity_type = ? AND entity_id = ? AND status = 'active' 
                      ORDER BY upload_date DESC";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $entityType, PDO::PARAM_STR);
            $stmt->bindParam(2, $entityId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getEntityImages: " . $e->getMessage());
            return false;
        }
    }

    public function deleteImage($imageId)
    {
        try {
            $query = "UPDATE images SET status = 'deleted' WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $imageId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in deleteImage: " . $e->getMessage());
            return false;
        }
    }

    public function cleanupDeletedImages($daysOld = 30)
    {
        try {
            $query = "DELETE FROM images 
                      WHERE status = 'deleted' 
                      AND upload_date < DATE_SUB(NOW(), INTERVAL ? DAY)";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindParam(1, $daysOld, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error in cleanupDeletedImages: " . $e->getMessage());
            return false;
        }
    }

    public function getStorageStats()
    {
        try {
            $query = "SELECT 
                        entity_type,
                        COUNT(*) as count,
                        SUM(filesize) as total_size,
                        AVG(filesize) as avg_size
                      FROM images 
                      WHERE status = 'active' 
                      GROUP BY entity_type";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getStorageStats: " . $e->getMessage());
            return false;
        }
    }
}