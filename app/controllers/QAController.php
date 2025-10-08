<?php
class QAController extends Controller
{
    private function sanitizeData($data) 
    {
        if (is_array($data) || is_object($data)) {
            // Parcurge recursiv array-ul sau obiectul
            foreach ($data as $key => &$value) {
                if (is_array($value) || is_object($value)) {
                    $value = $this->sanitizeData($value);
                } elseif (is_string($value)) {
                    // Sanitizeaza string-urile pentru a preveni XSS
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
            return $data;
        } elseif (is_string($data)) {
            // Sanitizeaza direct string-urile
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
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

    public function getQuestions()
    {
        //extragem parametrii din URL
        $categoryName = $this->sanitizeInput($this->getParam('categoryName'));
        $entityId = $this->getParam('entityId');

        //verificam daca entitatea exista
        $entity = $this->model->getEntityByCategoryName($categoryName, $entityId);
        if (!$entity) {
            $this->sendResponse(404, ['error' => 'Entity not found']);
            return;
        }

        //obtinem parametri pentru paginare
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

        //obtinem intrebarile pentru entitatea specificata
        $questions = $this->model->getQuestions($entityId, $page, $limit);

        $sanitizedQuestions = $this->sanitizeData($questions);
        
        $this->sendResponse(200, $sanitizedQuestions);
    }

    public function addQuestion()
    {

        //extragem parametrii din URL
        $categoryName = $this->sanitizeInput($this->getParam('categoryName'));
        $entityId = $this->getParam('entityId');

        //verificam daca entitatea exista
        $entity = $this->model->getEntityByCategoryName($categoryName, $entityId);
        if (!$entity) {
            $this->sendResponse(404, ['error' => 'Entity not found']);
            return;
        }

        if (
            !isset($_POST['user_name']) || !isset($_POST['question_text']) ||
            empty($_POST['question_text'])
        ) {
            $this->sendResponse(400, ['error' => 'Missing required fields']);
            return;
        }

        // Sanitizam datele de intrare
        $userName = $this->sanitizeInput($_POST['user_name']);
        $questionText = $this->sanitizeInput($_POST['question_text']);

        //adaugam intrebarea
        $result = $this->model->addQuestion($entityId, $userName, $questionText);

        if ($result) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Question added successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to add question']);
        }
    }

    public function addAnswer()
    {

        //extragem ID-ul intrebarii din URL
        $questionId = $this->getParam('questionId');

        //verificam datele primite
        if (
            !isset($_POST['user_name']) || !isset($_POST['answer_text']) ||
            empty($_POST['answer_text'])
        ) {
            $this->sendResponse(400, ['error' => 'Missing required fields']);
            return;
        }

       // Sanitizam datele de intrare
       $userName = $this->sanitizeInput($_POST['user_name']);
       $answerText = $this->sanitizeInput($_POST['answer_text']);

        //adaugam raspunsul
        $result = $this->model->addAnswer($questionId, $userName, $answerText);

        if ($result) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Answer added successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to add answer']);
        }
    }

    public function addVote()
    {
        //extragem ID-ul raspunsului din URL
        $answerId = $this->getParam('answerId');

        //obtinem id ul utilizatorului curent
        $userId = $this->params['user_id'];

        //obtinem datele din request
        $input = json_decode(file_get_contents('php://input'), true);

        if (
            !isset($input['vote_type']) ||
            !in_array($input['vote_type'], ['upvote', 'downvote'])
        ) {
            $this->sendResponse(400, ['error' => 'Invalid vote type']);
            return;
        }

        $voteType = $input['vote_type'];

        //verificam daca utilizatorul a votat deja acest raspuns
        if ($this->model->hasUserVoted($answerId, $userId)) {
            $this->sendResponse(200, [
                'success' => false,
                'message' => 'You have already voted for this answer'
            ]);
            return;
        }

        //adaugam votul
        $result = $this->model->addVote($answerId, $userId, $voteType);

        if ($result) {
            $this->sendResponse(200, ['success' => true, 'message' => 'Vote added successfully']);
        } else {
            $this->sendResponse(500, ['error' => 'Failed to add vote']);
        }
    }
    private function getParam($name)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }
        return null;
    }
}
?>