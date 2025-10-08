<?php

class Controller
{
    protected $model;
    protected $params;

    public function __construct($params = [])
    {
        $this->params = $params;
        $modelName = str_replace('Controller', '', $this::class) . 'Model';

        if(class_exists($modelName)) {

                $this->model = new $modelName();
        }
    }

    public function sendResponse(int $statusCode, $body)
    {
        http_response_code($statusCode);
        
        // CORS headers
        header('Access-Control-Allow-Origin: ' . $this->getAllowedOrigin());
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');

        header('Content-Type: application/json');
        
        echo json_encode($body);
        exit();
    }
    private function getAllowedOrigin()
    {
        $allowedOrigins = [
            'http://localhost'
        ];
        
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        
        if (in_array($origin, $allowedOrigins)) {
            return $origin;
        }
        
        return $allowedOrigins[0];
    }
}
?>