<?php

class QAModel
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->pdo;
    }

    public function getQuestions($entityId, $page = 1, $limit = 10)
    {
        try {
            $offset = ($page - 1) * $limit;

            //obtine intrebarile pentru entitatea specificata
            $query = "SELECT q.id, q.user_name as user, q.question_text as questionText, q.created_at as date
                      FROM questions q
                      WHERE q.entity_id = :entity_id
                      ORDER BY q.created_at DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $questions = $stmt->fetchAll(PDO::FETCH_OBJ);

            //pentru fiecare intrebare, obtinem raspunsurile asociate
            foreach ($questions as &$question) {
                $question->answers = $this->getAnswersForQuestion($question->id);
                $question->showAnswers = !empty($question->answers);
            }

            return $questions;
        } catch (Exception $e) {
            error_log("Error in getQuestions: " . $e->getMessage());
            return [];
        }
    }

    private function getAnswersForQuestion($questionId)
    {
        try {
            $query = "SELECT a.id, a.user_name as user, a.answer_text as answerText, a.created_at as date, 
                      a.upvotes, a.downvotes
                      FROM answers a
                      WHERE a.question_id = :question_id
                      ORDER BY a.upvotes DESC, a.created_at ASC";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error in getAnswersForQuestion: " . $e->getMessage());
            return [];
        }
    }

    public function addQuestion($entityId, $userName, $questionText)
    {
        try {
            $query = "INSERT INTO questions (entity_id, user_name, question_text) 
                      VALUES (:entity_id, :user_name, :question_text)";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
            $stmt->bindValue(':user_name', $userName, PDO::PARAM_STR);
            $stmt->bindValue(':question_text', $questionText, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in addQuestion: " . $e->getMessage());
            return false;
        }
    }

    public function addAnswer($questionId, $userName, $answerText)
    {
        try {
            $query = "INSERT INTO answers (question_id, user_name, answer_text) 
                      VALUES (:question_id, :user_name, :answer_text)";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
            $stmt->bindValue(':user_name', $userName, PDO::PARAM_STR);
            $stmt->bindValue(':answer_text', $answerText, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in addAnswer: " . $e->getMessage());
            return false;
        }
    }

    public function hasUserVoted($answerId, $userId)
    {
        try {
            $query = "SELECT id FROM answer_votes 
                      WHERE answer_id = :answer_id AND user_id = :user_id";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':answer_id', $answerId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error in hasUserVoted: " . $e->getMessage());
            return false;
        }
    }

    public function addVote($answerId, $userId, $voteType)
    {
        try {
            //mai intai verificam daca utilizatorul a votat deja
            if ($this->hasUserVoted($answerId, $userId)) {
                return false;
            }

            //inseram votul
            $query = "INSERT INTO answer_votes (answer_id, user_id, vote_type) 
                      VALUES (:answer_id, :user_id, :vote_type)";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':answer_id', $answerId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':vote_type', $voteType, PDO::PARAM_STR);

            $insertResult = $stmt->execute();

            if (!$insertResult) {
                return false;
            }

            //actualizam numarul de voturi in tabelul raspunsurilor
            if ($voteType === 'upvote') {
                $query = "UPDATE answers SET upvotes = upvotes + 1 WHERE id = :answer_id";
            } else {
                $query = "UPDATE answers SET downvotes = downvotes + 1 WHERE id = :answer_id";
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':answer_id', $answerId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error in addVote: " . $e->getMessage());
            return false;
        }
    }

    public function getEntityByCategoryName($categoryName, $entityId)
    {
        try {
            $query = "SELECT e.id, e.name 
                      FROM entities e
                      JOIN categories c ON e.category_id = c.id
                      WHERE c.name = :category_name AND e.id = :entity_id AND e.status = 'approved'";

            $stmt = $this->pdo->prepare($query);
            $stmt->bindValue(':category_name', $categoryName, PDO::PARAM_STR);
            $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            error_log("Error in getEntityByCategoryName: " . $e->getMessage());
            return false;
        }
    }
}