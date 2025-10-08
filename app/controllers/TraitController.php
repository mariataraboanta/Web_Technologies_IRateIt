<?php

class TraitController extends Controller
{

    //traiturile unei categorii
    public function getTraits()
    {
        $traits= $this->model->getTraits();

        foreach ($traits as &$trait) {
            foreach ($trait as $key => $value) {
                $trait[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }
        $this->sendResponse(200, $traits);
    }

    public function getTraitsByCategory()
    {
        if (!isset($this->params['category']) || empty($this->params['category'])) {
            $this->sendResponse(400, ['error' => 'Parametrul category este necesar']);
            return;
        }

        $category = htmlspecialchars($this->params['category'], ENT_QUOTES, 'UTF-8');

        if (!$this->model->categoryExists( $category)) {
            $this->sendResponse(404, ['error' => 'Category not found']);
            return;
        }

        $traits = $this->model->getTraitsByCategory($category);

         // Sanitize output data
         foreach ($traits as &$trait) {
            foreach ($trait as $key => $value) {
                $trait[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        }

        $this->sendResponse(200, $traits);
    }

    public function createTrait()
    {
        if (!isset($this->params['name']) || empty($this->params['name'])) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Trait name is required']);
            return;
        }

        $traitName = htmlspecialchars(strip_tags($this->params['name']), ENT_QUOTES, 'UTF-8');
        $category = isset($this->params['category']) ? 
            htmlspecialchars($this->params['category'], ENT_QUOTES, 'UTF-8') : '';
        
        if (empty($category)) {
            $this->sendResponse(400, ['error' => 'Bad request', 'message' => 'Category is required']);
            return;
        }
        
        if ($this->model->createTrait($this->params['category'], $traitName)) {
            $this->sendResponse(201, ['success' => true, 'message' => 'Trait created successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Internal Server Error', 'message' => 'Failed to create trait']);
        }
    }
}
?>