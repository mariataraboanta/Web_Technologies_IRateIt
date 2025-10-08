<?php
class ReviewController extends Controller
{

    private function sanitizeData($data) 
    {
        //protejate impotriva xss
        if (is_array($data)) {
             // Process each element in the array
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeData($value);
                } elseif (is_string($value)) {
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
        } elseif (is_string($data)) {
             // Handle direct string input
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    //luam toate recenziile
    public function getReviews()
    {
        $reviews = $this->model->getReviews();
        $sanitizedReviews = $this->sanitizeData($reviews);
        $this->sendResponse(200,  $sanitizedReviews);
    }

    public function deleteReview()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing review ID']);
            return;
        }

        $reviewId = (int) $this->params['id'];
        $deleted = $this->model->deleteREview($reviewId);

        if ($deleted) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Review deleted successfully']);
        } else {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'Review not found']);
        }
    }

    public function getReviewsUser()
    {
        if (!isset($this->params['user_name'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing user name']);
            return;
        }

        $userName = htmlspecialchars($this->params['user_name'], ENT_QUOTES, 'UTF-8');
        
        $reviews = $this->model->getReviewsUser($userName);
        $sanitizedReviews = $this->sanitizeData($reviews);
        $this->sendResponse(200, $sanitizedReviews);
    }

    public function getReviewsforEntity()
    {
        if (!isset($this->params['entity_id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing entity ID']);
            return;
        }

        $reviews = $this->model->getReviewsForEntity($this->params['entity_id']);
        $sanitizedReviews = $this->sanitizeData($reviews);
        $this->sendResponse(200, $sanitizedReviews);
    }

    public function saveReview()
    {
        if (
            !isset($this->params['entity_id']) ||
            !isset($this->params['user_name']) ||
            !isset($this->params['traits']) ||
            !is_array($this->params['traits'])
        ) {
            $this->sendResponse(400, [
                'error' => 'Bad request',
                'message' => 'Lipsesc datele necesare sau câmpul traits nu este un array.'
            ]);
            return;
        }

        $entity_id = (int) $this->params['entity_id'];
        $user_name = strip_tags($this->params['user_name']);
        $traits = $this->params['traits'];

        try {
            $this->model->beginTransaction();
            $timestamp = date('Y-m-d H:i:s');
            $imageUploadErrors = [];
            $reviewIds = [];

            foreach ($traits as $index => $trait) {
                $trait_id = isset($trait['trait_id']) ? (int) $trait['trait_id'] : null;
                $rating = isset($trait['rating']) ? (int) $trait['rating'] : null;

                //dubla protectie impotriva xss
                $comment = isset($trait['comment']) ? 
                    htmlspecialchars(strip_tags($trait['comment']), ENT_QUOTES, 'UTF-8') : '';

                $reviewId = $this->model->insertReview($entity_id, $user_name, $trait_id, $rating, $comment, $timestamp);

                if (!$reviewId) {
                    $this->model->rollbackTransaction();
                    $this->sendResponse(500, [
                        'error' => 'Internal Server Error',
                        'message' => 'Eroare la salvarea recenziilor!'
                    ]);
                    return;
                }

                $reviewIds[] = $reviewId;
            }

            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] === 0) {
                        $imageFile = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error' => $_FILES['images']['error'][$i],
                            'size' => $_FILES['images']['size'][$i]
                        ];

                        $reviewId = $reviewIds[0];
                        $imagePath = $this->processReviewImage($entity_id, $user_name, $reviewId, $imageFile);

                        if ($imagePath) {
                            // Sanitize image metadata before storing
                            $imageInfo = [
                                'filename' => htmlspecialchars(basename($imagePath), ENT_QUOTES, 'UTF-8'),
                                'filepath' => htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'),
                                'filetype' => htmlspecialchars($imageFile['type'], ENT_QUOTES, 'UTF-8'),
                                'filesize' => (int) $imageFile['size']
                            ];

                            $saved = $this->model->saveImageReference($reviewId, $imageInfo);
                            if (!$saved) {
                                $imageUploadErrors[] = "Nu s-a putut salva referința imaginii #{$i} în baza de date.";
                            }
                        } else {
                            $imageUploadErrors[] = "Nu s-a putut procesa imaginea #{$i}.";
                        }
                    }
                }
            }

            $this->model->commitTransaction();

            $response = [
                'success' => true,
                'message' => 'Recenzia a fost adăugată cu succes!'
            ];

            if (!empty($imageUploadErrors)) {
                $response['warnings'] = [
                    'images' => $imageUploadErrors,
                    'message' => 'Unele imagini nu au putut fi încărcate.'
                ];
            }

            $this->sendResponse(200, $response);

        } catch (Exception $e) {
            $this->model->rollbackTransaction();
            $this->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'Eroare la salvarea recenziei: ' . $e->getMessage()
            ]);
        }
    }

    private function processReviewImage($entityId, $userName, $reviewId, $imageData)
    {

        $validImage = $this->validateImageFile($imageData);
        if (!$validImage) {
            return false;
        }

        $baseUploadDir = $_SERVER['DOCUMENT_ROOT'] . '/IRI_Ballerina_Cappuccina/app/uploads/';
        $reviewsUploadDir = $baseUploadDir . 'reviews/';
        $entityUploadDir = $reviewsUploadDir . $entityId . '/';
        $userUploadDir = $entityUploadDir . $userName . '/';

        if (
            !$this->ensureFolderExists($baseUploadDir) ||
            !$this->ensureFolderExists($reviewsUploadDir) ||
            !$this->ensureFolderExists($entityUploadDir) ||
            !$this->ensureFolderExists($userUploadDir)
        ) {
            return false;
        }

        $newFilename = $reviewId . '_' . $this->generateUniqueFilename() . '.' . $validImage['extension'];
        $fullUploadPath = $userUploadDir . $newFilename;
        $relativePath = '/IRI_Ballerina_Cappuccina/app/uploads/reviews/' . $entityId . '/' . $userName . '/' . $newFilename;

        if (move_uploaded_file($imageData['tmp_name'], $fullUploadPath)) {
            return $relativePath;
        }

        return false;
    }

    private function ensureFolderExists($path, $permissions = 0755)
    {
        if (file_exists($path)) {
            return is_dir($path) && is_writable($path);
        }

        return mkdir($path, $permissions, true);
    }

    private function generateUniqueFilename($prefix = '')
    {
        return uniqid($prefix);
    }

    private function validateImageFile($fileData, $maxSize = 5242880)
    {
        $allowed = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];

        if (!isset($fileData) || $fileData['error'] !== 0) {
            return false;
        }

        $filename = $fileData['name'];
        $filetype = $fileData['type'];
        $filesize = $fileData['size'];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed) || !in_array($filetype, $allowed)) {
            return false;
        }

        if ($filesize > $maxSize) {
            return false;
        }

        return [
            'type' => $filetype,
            'extension' => $ext
        ];
    }
}