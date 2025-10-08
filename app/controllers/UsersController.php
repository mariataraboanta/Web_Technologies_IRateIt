<?php

class UsersController extends Controller
{
    private $authMiddleware;

    public function getUsers()
    {
        $users = $this->model->getUsers();
        if ($users) {
            //sanitizare
            $sanitizedUsers = array_map(function ($user) {
                return $this->sanitizeUserData($user);
            }, $users);

            $this->sendResponse(200, $sanitizedUsers);
        } else {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'No users found']);
        }
    }

    public function deleteUser()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing user ID']);
            return;
        }

        $userId = (int) $this->params['id'];
        $deleted = $this->model->deleteUser($userId);

        if ($deleted) {
            $this->sendResponse(200, ['success' => true, 'message' => 'User deleted successfully']);
        } else {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'User not found']);
        }
    }

    //informatii despre contul utilizatorului curent
    public function getAccountData()
    {
        if (!$this->authMiddleware) {
            $this->authMiddleware = new AuthMiddleware();
        }

        $userData = $this->authMiddleware->checkAuth();
        if (!$userData) {
            $this->sendResponse(401, ['error' => 'Unauthorized', 'message' => 'Authentication required']);
            return;
        }

        $userId = $userData->id;
        $user = $this->model->getUserById($userId);

        if (!$user) {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'User not found']);
            return;
        }

        $profilePicture = $this->model->getUserProfilePicture($userId);

        $reviews = $this->model->getUserReviews($user['username']);

        $sanitizedUser = $this->sanitizeUserData($user);

        if ($profilePicture) {
            $sanitizedUser['profile_picture'] = htmlspecialchars($profilePicture['filepath'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $sanitizedUser['profile_picture_id'] = $profilePicture['id'];
        } else {
            $sanitizedUser['profile_picture'] = null;
            $sanitizedUser['profile_picture_id'] = null;
        }

        $sanitizedReviews = [];
        foreach ($reviews as $review) {
            $sanitizedReview = [];
            foreach ($review as $key => $value) {
                if (is_string($value)) {
                    $sanitizedReview[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                } else {
                    $sanitizedReview[$key] = $value;
                }
            }
            $sanitizedReviews[] = $sanitizedReview;
        }

        $this->sendResponse(200, [
            'user' => $sanitizedUser,
            'reviews' => $sanitizedReviews
        ]);
    }

    public function updateAccount()
    {
        if (!$this->authMiddleware) {
            $this->authMiddleware = new AuthMiddleware();
        }

        $userData = $this->authMiddleware->checkAuth();
        if (!$userData) {
            $this->sendResponse(401, ['error' => 'Unauthorized', 'message' => 'Authentication required']);
            return;
        }

        $userId = $userData->id;
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['username']) || !isset($input['email'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing username or email']);
            return;
        }

        //sanitizare input
        $rawUsername = trim($input['username']);
        $rawEmail = trim($input['email']);

        $username = htmlspecialchars($rawUsername, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $email = filter_var($rawEmail, FILTER_SANITIZE_EMAIL);

        if (empty($username) || empty($email)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Username and email cannot be empty']);
            return;
        }

        if (strlen($rawUsername) < 3 || strlen($rawUsername) > 50) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Username must be between 3 and 50 characters']);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $rawUsername)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Username contains invalid characters']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Invalid email format']);
            return;
        }

        $result = $this->model->updateUser($userId, $username, $email);

        if ($result['success']) {
            $updatedUser = $this->model->getUserById($userId);
            $this->authMiddleware->setAuthData($updatedUser);
            $this->sendResponse(200, ['success' => true, 'message' => 'User updated successfully']);
        } else {
            if ($result['error'] === 'Username already exists') {
                $this->sendResponse(409, ['error' => 'Conflict', 'message' => 'Username already exists']);
            } else {
                $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to update user']);
            }
        }
    }
    private function sanitizeUserData($user)
    {
        $sanitized = [];
        foreach ($user as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    public function uploadProfilePicture()
    {
        if (!$this->authMiddleware) {
            $this->authMiddleware = new AuthMiddleware();
        }

        $userData = $this->authMiddleware->checkAuth();
        if (!$userData) {
            $this->sendResponse(401, ['error' => 'Unauthorized', 'message' => 'Authentication required']);
            return;
        }

        if (!isset($_FILES['profile_picture'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'No file uploaded']);
            return;
        }

        $file = $_FILES['profile_picture'];
        $userId = $userData->id;

        $validation = $this->validateUploadedImage($file);
        if (!$validation['valid']) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => $validation['message']]);
            return;
        }

        try {
            $oldPicture = $this->model->getUserProfilePicture($userId);
            if ($oldPicture) {
                $this->deleteImageFile($oldPicture['filepath']);
                $this->model->deleteUserProfilePicture($userId);
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'profile_' . $userId . '_' . uniqid() . '.' . $extension;
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/IRI_Ballerina_Cappuccina/app/uploads/users/';
            $filepath = $uploadDir . $filename;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to save file']);
                return;
            }

            //salveaza calea relativa, nu absoluta
            $imageData = [
                'filename' => $filename,
                'filepath' => '/IRI_Ballerina_Cappuccina/app/uploads/users/' . $filename,
                'filetype' => $file['type'],
                'filesize' => $file['size'],
                'entity_type' => 'user',
                'entity_id' => $userId
            ];

            $imageId = $this->model->saveImage($imageData);
            if (!$imageId) {
                unlink($filepath);
                $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to save image data']);
                return;
            }

            $imageUrl = '/IRI_Ballerina_Cappuccina/app/uploads/users/' . $filename;
            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'image_url' => $imageUrl,
                'image_id' => $imageId
            ]);

        } catch (Exception $e) {
            error_log("Profile picture upload error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Upload failed']);
        }
    }

    public function deleteProfilePicture()
    {
        if (!$this->authMiddleware) {
            $this->authMiddleware = new AuthMiddleware();
        }

        $userData = $this->authMiddleware->checkAuth();
        if (!$userData) {
            $this->sendResponse(401, ['error' => 'Unauthorized', 'message' => 'Authentication required']);
            return;
        }

        $userId = $userData->id;

        try {
            $profilePicture = $this->model->getUserProfilePicture($userId);
            if (!$profilePicture) {
                $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'No profile picture found']);
                return;
            }

            //construieste calea absoluta pentru stergerea fisierului
            $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $profilePicture['filepath'];
            $this->deleteImageFile($absolutePath);

            $deleted = $this->model->deleteUserProfilePicture($userId);
            if ($deleted) {
                $this->sendResponse(200, ['success' => true, 'message' => 'Profile picture deleted successfully']);
            } else {
                $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to delete profile picture']);
            }

        } catch (Exception $e) {
            error_log("Profile picture delete error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Delete failed']);
        }
    }

    private function validateUploadedImage($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'File upload error: ' . $this->getUploadErrorMessage($file['error'])];
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'message' => 'File too large. Maximum size is 5MB'];
        }

        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed'];
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'message' => 'File is not a valid image'];
        }

        $maxWidth = 2000;
        $maxHeight = 2000;
        if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
            return ['valid' => false, 'message' => 'Image dimensions too large. Maximum is ' . $maxWidth . 'x' . $maxHeight . ' pixels'];
        }

        return ['valid' => true, 'message' => 'Valid image'];
    }

    private function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File too large';
            case UPLOAD_ERR_PARTIAL:
                return 'File upload incomplete';
            case UPLOAD_ERR_NO_FILE:
                return 'No file uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload blocked by extension';
            default:
                return 'Unknown upload error';
        }
    }

    private function deleteImageFile($filepath)
    {
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}