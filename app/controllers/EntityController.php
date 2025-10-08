<?php

class EntityController extends Controller
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

    private function sanitizeInput($input)
    {
        if (is_string($input)) {
            // Eliminam tag-urile HTML si apoi encodam caracterele speciale
            return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }


    public function getEntities()
    {
        $entities = $this->model->getEntities();
        $sanitizedEntities = $this->sanitizeData($entities);
        $this->sendResponse(200, $sanitizedEntities);
    }

    public function getApprovedEntities()
    {
        $entities = $this->model->getApprovedEntities();
        $sanitizedEntities = $this->sanitizeData($entities);
        $this->sendResponse(200, $sanitizedEntities);
    }

    public function deleteEntity()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing entity ID']);
            return;
        }

        $entityId = (int) $this->params['id'];
        $deleted = $this->model->deleteEntity($entityId);

        if ($deleted) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Entity deleted successfully']);
        } else {
            $this->sendResponse(404, ['error' => 'Not Found', 'message' => 'Entity not found']);
        }
    }

    public function getEntitiesByCategory()
    {
        $category = $this->sanitizeInput($this->params["category"]);
        $entities = $this->model->getByCategory($category);
        $sanitizedEntities = $this->sanitizeData($entities);
        $this->sendResponse(200, $sanitizedEntities);
    }

    public function getApprovedEntitiesByCategory()
    {
        $category = $this->sanitizeInput($this->params["category"]);
        $entities = $this->model->getApprovedByCategory($category);
        $sanitizedEntities = $this->sanitizeData($entities);
        $this->sendResponse(200, $sanitizedEntities);
    }

    public function createEntity()
    {
        if (!isset($this->params['name']) || trim($this->params['name']) === '') {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing entity name']);
            exit();
        }

        if (!isset($this->params['description']) || trim($this->params['description']) === '') {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing entity description']);
            exit();
        }

        // Sanitizam datele de intrare
        $name = $this->sanitizeInput($this->params['name']);
        $description = $this->sanitizeInput($this->params['description']);
        $category = $this->sanitizeInput($this->params['category']);
        
        if ($this->model->entityExists($name)) {
            $this->sendResponse(409, [
                'success' => false,
                'message' => 'An entity with this name already exists'
            ]);
            return;
        }

        try {
            //cream entitatea in baza de date
            $entityResult = $this->model->createEntity($name, $description, $category);

            if (!$entityResult['success']) {
                $this->sendResponse(500, [
                    'success' => false,
                    'message' => $entityResult['message'] ?? 'Failed to create entity'
                ]);
                return;
            }

            $entityId = $entityResult['entity']['id'];
            $imagePath = null;

            //procesam imaginea daca exista
            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $imagePath = $this->processEntityImage($entityId, $_FILES['image']);

                if ($imagePath) {
                    //actualizam calea imaginii in entitate
                    $imageInfo = [
                        'filename' => htmlspecialchars(basename($imagePath), ENT_QUOTES, 'UTF-8'),
                        'filepath' => htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'),
                        'filetype' => htmlspecialchars($_FILES['image']['type'], ENT_QUOTES, 'UTF-8'),
                        'filesize' => (int) $_FILES['image']['size']
                    ];

                    $this->model->saveImageReference($entityId, $imageInfo);
                }
            }

            $entityResult['entity']['image_path'] = $imagePath ? htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') : null;
            $sanitizedEntity = $this->sanitizeData($entityResult['entity']);

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Entity created successfully',
                'entity' => $sanitizedEntity
            ]);

        } catch (Exception $e) {
            $this->sendResponse(500, [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    private function processEntityImage($entityId, $imageData)
    {
        //validare imagine
        $validImage = $this->validateImageFile($imageData);
        if (!$validImage) {
            return false;
        }

        //pregatire cale pentru salvare
        $baseUploadDir = $_SERVER['DOCUMENT_ROOT'] . '/IRI_Ballerina_Cappuccina/app/uploads/';
        $entityUploadDir = $baseUploadDir . 'entities/';

        if (
            !$this->ensureFolderExists($baseUploadDir) ||
            !$this->ensureFolderExists($entityUploadDir)
        ) {
            return false;
        }

        //generare nume unic pentru fisier
        $newFilename = $entityId . '_' . $this->generateUniqueFilename() . '.' . $validImage['extension'];
        $fullUploadPath = $entityUploadDir . $newFilename;
        $relativePath = '/IRI_Ballerina_Cappuccina/app/uploads/entities/' . $newFilename;

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

        //validare extensie si tip
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


    public function approveEntity()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing entity ID']);
            return;
        }

        $entityId = (int) $this->params['id'];
        $approved = $this->model->approveEntity($entityId);

        if ($approved) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Entity approved successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to approve entity']);
        }
    }

    public function rejectEntity()
    {
        if (!isset($this->params['id'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Missing entity ID']);
            return;
        }

        $entityId = (int) $this->params['id'];
        $rejected = $this->model->rejectEntity($entityId);

        if ($rejected) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Entity rejected successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to reject entity']);
        }
    }
}
?>