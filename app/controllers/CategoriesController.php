<?php
class CategoriesController extends Controller
{
    private function sanitizeData($data) 
    {
        if (is_array($data)) {
            // Parcurge fiecare element din array
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeData($value);
                } elseif (is_string($value)) {
                    // Sanitizeaza valorile string
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
        } elseif (is_string($data)) {
            // Sanitizeaza string-uri simple
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    public function getCategories()
    {
        $categories = $this->model->getCategories();
        $sanitizedCategories = $this->sanitizeData($categories);
        $this->sendResponse(200, $sanitizedCategories);
    }

    public function getApprovedCategories()
    {
        $categories = $this->model->getApprovedCategories();
        // Sanitizam datele inainte de a le trimite
        $sanitizedCategories = $this->sanitizeData($categories);
        $this->sendResponse(200, $sanitizedCategories);
    }

    public function approveCategory()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing category ID']);
            return;
        }

        $categoryId = filter_var($this->params['id'], FILTER_VALIDATE_INT);
        if ($categoryId === false) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Invalid category ID']);
            return;
        }

        $approved = $this->model->approveCategory($categoryId);

        if ($approved) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Category approved successfully']);
        } else {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'Category not found']);
        }
    }

    public function rejectCategory()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing category ID']);
            return;
        }

        $categoryId = filter_var($this->params['id'], FILTER_VALIDATE_INT);
        if ($categoryId === false) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Invalid category ID']);
            return;
        }
        $rejected = $this->model->rejectCategory($categoryId);

        if ($rejected) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Category rejected successfully']);
        } else {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'Category not found']);
        }
    }

    public function deleteCategory()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing category ID']);
            return;
        }

        $categoryId = filter_var($this->params['id'], FILTER_VALIDATE_INT);
        if ($categoryId === false) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Invalid category ID']);
            return;
        }
        $deleted = $this->model->deleteCategory($categoryId);

        if ($deleted) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Category deleted successfully']);
        } else {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'Category not found']);
        }
    }

    

    public function searchCategories($searchTerm)
    {
        if (!isset($searchTerm)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing search term']);
            return;
        }

        $searchTerm = trim(htmlspecialchars($searchTerm));
        if (empty($searchTerm)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Search term cannot be empty']);
            return;
        }

        $categories = $this->model->searchCategories($searchTerm);

        // Sanitizam rezultatele inainte de a le trimite
        $sanitizedCategories = $this->sanitizeData($categories);

        if (empty($categories)) {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'No categories found']);
        } else {
            $this->sendResponse(200, $sanitizedCategories);
        }
    }

    public function createCategory()
    {
        if (!isset($this->params['name']) || empty(trim($this->params['name']))) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing or empty category name']);
            return;
        }

        $name = trim(htmlspecialchars($this->params['name']));
        
        // Check name length
        if (strlen($name) > 100) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Category name is too long (max 100 characters)']);
            return;
        }

        $traits = $this->params['traits'] ?? null;
        
        // Validate traits if provided
        if ($traits !== null) {
            if (is_string($traits)) {
                $traits = json_decode($traits, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Invalid traits format']);
                    return;
                }
            }
            
            if (!is_array($traits)) {
                $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Traits must be an array']);
                return;
            }
            
            // Sanitize each trait
            foreach ($traits as $key => $trait) {
                $traits[$key] = trim(htmlspecialchars($trait));
            }
        }

        // Check if category already exists
        if ($this->model->categoryExists($name)) {
            $this->sendResponse(409, ['error' => 'Conflict', 'message' => 'Category already exists']);
            return;
        }

        if ($this->model->createCategory($name, $traits)) {
            $this->sendResponse(201, ['success' => true, 'message' => 'Category created successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to create category']);
        }
    }

    public function importCategory()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['name'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing category name']);
            return;
        }

        $categoryName = trim(htmlspecialchars($input['name']));
        if (empty($categoryName)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Category name cannot be empty']);
            return;
        }

        // verificare lungime categorie
        if (strlen($categoryName) > 100) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Category name is too long (max 100 characters)']);
            return;
        }

        $traits = isset($input['traits']) ? $input['traits'] : [];

        // validare de trasaturi
        if (!is_array($traits)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Traits must be an array']);
            return;
        }

        foreach ($traits as $key => $trait) {
            $traits[$key] = trim(htmlspecialchars($trait));
        }

        if ($this->model->categoryExists($categoryName)) {
            $this->sendResponse(409, ['error' => 'Conflict', 'message' => 'Category already exists']);
            return;
        }

        if ($this->model->importCategory($categoryName, $traits)) {
            $this->sendResponse(201, ['success' => true, 'message' => 'Category imported successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to import category']);
        }
    }
}
?>