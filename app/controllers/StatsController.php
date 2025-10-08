<?php
class StatsController extends Controller
{
    public function getStats()
    {
        $stats = $this->model->getAllStats();
        $sanitizedStats = $this->sanitizeData($stats);
        $this->sendResponse(200, $sanitizedStats);
    }

    public function getTopEntities()
    {
        $topEntities = $this->model->getTopEntities();
        if ($topEntities) {
            $sanitizedEntities = $this->sanitizeData($topEntities);
            $this->sendResponse(200, $sanitizedEntities);
        } else {
            $this->sendResponse(404, ['message' => 'No top entities found']);
        }
    }

    public function getWorstEntities()
    {
        $worstEntities = $this->model->getWorstEntities();
        if ($worstEntities) {
            $sanitizedEntities = $this->sanitizeData($worstEntities);
            $this->sendResponse(200, $sanitizedEntities);
        } else {
            $this->sendResponse(404, ['message' => 'No worst entities found']);
        }
    }

    public function getDashboardStats()
    {
        $dashboardData = [
            'totals' => [
                'entities' => $this->model->getTotalEntities(),
                'reviews' => $this->model->getTotalReviews(),
                'users' => $this->model->getActiveUsers(),
                'avg_rating' => $this->model->getAverageRating()
            ],
            'highlights' => [
                'top_entity' => $this->model->getTopEntity(),
                'worst_entity' => $this->model->getWorstEntity()
            ]
        ];

        $sanitizedData = $this->sanitizeData($dashboardData);
        $this->sendResponse(200, $sanitizedData );
    }

    public function getCategoryStats()
    {
        $categoryStats = $this->model->getCategoryStats();
        $sanitizedStats = $this->sanitizeData($categoryStats);
        $this->sendResponse(200, $sanitizedStats);
    }
    private function sanitizeData($data) 
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->sanitizeData($value);
                } elseif (is_string($value)) {
                    $data[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            }
        } elseif (is_string($data)) {
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }
}

?>