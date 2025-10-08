<?php
class Router
{
    private function isAjaxRequest()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }   

    private function requireAjax()
    {
        if (!$this->isAjaxRequest()) {
            $this->sendError(400, "This endpoint requires AJAX request");
            return false;
        }
        return true;
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
    private function sendError($code, $message)
    {
        http_response_code($code);
        header('Access-Control-Allow-Origin: ' . $this->getAllowedOrigin());
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');

        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
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

    public function dispatch($requestURL, $requestMethod)
    {
        $auth = new AuthMiddleware();
        $authorized = $auth->checkAuth();
        $matches = [];
        $params = [];
        $path = parse_url($requestURL, PHP_URL_PATH);

        if (preg_match("/login$/", $path)) {
            if ($authorized) {
                $this->sendError(400, "Already logged in");
                return;
            }

            if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $loginController = new LoginController($_POST);
                $loginController->verifyUser();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/register$/", $path)) {
            if ($authorized) {
                $this->sendError(400, "Already logged in");
                return;
            }
            
            if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $registerController = new RegisterController($_POST);
                $registerController->createUser();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/logout$/", $path)) {
            if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $loginController = new LoginController();
                $loginController->logout();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/categories\/approved$/", $path)) {
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $categoriesController = new CategoriesController();

                if (isset($_GET['search'])) {
                    $searchTerm = $_GET['search'];
                    $categoriesController->searchCategories($searchTerm);
                } else {
                    $categoriesController->getApprovedCategories();
                }
            } else {
                $this->sendError(405, "Method not allowed");
            }
        }
        // Ruta categories poate fi văzuta fara autentificare (doar GET)
        else if (preg_match("/categories(\?.*)?$/", $path)) {
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $categoriesController = new CategoriesController();

                if (isset($_GET['search'])) {
                    $searchTerm = $_GET['search'];
                    $categoriesController->searchCategories($searchTerm);
                } else {
                    $categoriesController->getCategories();
                }
            }
            // POST pentru categories necesita autentificare
            else if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                if (!$authorized) {
                    $this->sendError(401, "Authentication required to create categories");
                    return;
                }
                $categoriesController = new CategoriesController($_POST);
                $categoriesController->createCategory();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/traits$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $traitsController = new TraitController($params);
                $traitsController->getTraits();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        }
        // RUTE PROTEJATE - necesita autentificare
        else if (preg_match("/traits\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            $params["category"] = $matches[1];
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $traitsController = new TraitController($params);
                $traitsController->getTraitsByCategory();
            } else if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $params["name"] = $_POST["name"];
                $traitsController = new TraitController($params);
                $traitsController->createTrait();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/entities\/([A-Za-z0-9_]+)\/approve$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }

            $params["id"] = $matches[1];

            if ($requestMethod == 'PATCH') {
                if (!$this->requireAjax()) return;
                $entityController = new EntityController($params);
                $entityController->approveEntity();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/entities\/([A-Za-z0-9_]+)\/reject$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }

            $params["id"] = $matches[1];

            if ($requestMethod == 'PATCH') {
                if (!$this->requireAjax()) return;
                $entityController = new EntityController($params);
                $entityController->rejectEntity();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/entities\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }

            $params["id"] = $matches[1];

            if ($requestMethod == 'DELETE') {
                if (!$this->requireAjax()) return;
                $entityController = new EntityController($params);
                $entityController->deleteEntity();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/reviews$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $reviewController = new ReviewController();
                $reviewController->getReviews();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/reviews\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }

            $params["id"] = $matches[1];

            if ($requestMethod == 'DELETE') {
                if (!$this->requireAjax()) return;
                $reviewController = new ReviewController($params);
                $reviewController->deleteReview();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/entities\/([A-Za-z0-9_]+)\/approved$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            $params["category"] = $matches[1];
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $entityController = new EntityController($params);
                $entityController->getApprovedEntitiesByCategory();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/entities\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            $params["category"] = $matches[1];

            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $entityController = new EntityController($params);
                $entityController->getEntitiesByCategory();
            } else if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $params["name"] = $_POST["name"] ?? '';
                $params["description"] = $_POST["description"] ?? '';
                $params["hasImage"] = isset($_FILES['image']) && $_FILES['image']['error'] === 0;

                $entityController = new EntityController($params);
                $entityController->createEntity();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/entities\/([A-Za-z0-9_]+)\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            $params["category"] = $matches[1];
            $params["entity_id"] = $matches[2];
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $reviewController = new ReviewController($params);
                $reviewController->getReviewsforEntity();
            } else if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $params["user_name"] = $_POST["user_name"];
                $params["traits"] = $_POST["traits"];

                $reviewController = new ReviewController($params);
                $reviewController->saveReview();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/reviews$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $reviewController = new ReviewController();
                $reviewController->getReviewsUser();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/reviews\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            $params["id"] = $matches[1];
            $params["user_id"] = $authorized->id;

            if ($requestMethod == 'DELETE') {
                if (!$this->requireAjax()) return;
                $reviewController = new ReviewController($params);
                $reviewController->deleteReview();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/account$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $usersController = new UsersController();
                $usersController->getAccountData();
            } else if ($requestMethod == 'PUT') {
                if (!$this->requireAjax()) return;
                $usersController = new UsersController();
                $usersController->updateAccount();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/account\/profile-picture$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }

            if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $usersController = new UsersController();
                $usersController->uploadProfilePicture();
            } else if ($requestMethod == 'DELETE') {
                if (!$this->requireAjax()) return;
                $usersController = new UsersController();
                $usersController->deleteProfilePicture();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/auth\/status$/", $path)) {
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                if ($authorized) {
                    $this->sendResponse(200, [
                        'authenticated' => true,
                        'user' => [
                            'id' => $authorized->id,
                            'username' => $authorized->username,
                            'role' => $authorized->role
                        ]
                    ]);
                } else {
                    $this->sendResponse(200, ['authenticated' => false]);
                }
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/check$/", $path)) {
            if (!$this->requireAjax()) return;
            if (!$authorized) {
                $this->sendError(401, "You should login first");
            } else {
                echo json_encode(['authenticated' => true, 'user' => $authorized]);
            }
        } else if (preg_match("/users$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $usersController = new UsersController();
                $usersController->getUsers();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/users\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }

            $params["id"] = $matches[1];

            if ($requestMethod == 'DELETE') {
                if (!$this->requireAjax()) return;
                $usersController = new UsersController($params);
                $usersController->deleteUser();
            } else {
                $this->sendError(405, "Method not allowed");
            }

        } else if (preg_match("/admin\/categories$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $categoriesController = new CategoriesController();
                $categoriesController->getCategories();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/categories\/import$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
            if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $categoriesController = new CategoriesController();
                $categoriesController->importCategory();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/categories\/([A-Za-z0-9_]+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
            
            $params["id"] = $matches[1];

            if ($requestMethod == 'DELETE') {
                if (!$this->requireAjax()) return;
                $categoriesController = new CategoriesController($params);
                $categoriesController->deleteCategory();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/categories\/([A-Za-z0-9_]+)\/approve$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
           
            $params["id"] = $matches[1];

            if ($requestMethod == 'PATCH') {
                if (!$this->requireAjax()) return;
                $categoriesController = new CategoriesController($params);
                $categoriesController->approveCategory();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/categories\/([A-Za-z0-9_]+)\/reject$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }

            $params["id"] = $matches[1];

            if ($requestMethod == 'PATCH') {
                if (!$this->requireAjax()) return;
                $categoriesController = new CategoriesController($params);
                $categoriesController->rejectCategory();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/stats/", $path)) {

            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $statsController = new StatsController();
                $statsController->getStats();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/admin\/entities$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $entityController = new EntityController();
                $entityController->getEntities();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/categories-stats$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($authorized->role !== 'admin') {
                $this->sendError(403, "Admin access required");
                return;
            }

            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $statsController = new StatsController();
                $statsController->getCategoryStats();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        } else if (preg_match("/rss-json\/?$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($requestMethod == 'GET') {
                $type = $_GET['type'] ?? 'most_detestable';

                $validTypes = ['most_detestable', 'least_detestable'];
                if (!in_array($type, $validTypes)) {
                    $this->sendError(400, "Invalid type parameter. Use one of: " . implode(", ", $validTypes));
                    return;
                }

                $params = ["type" => $type];
                $params = ["format" => "json"];
                $rankingController = new RankingController($params);
                $rankingController->getRankings();
                return;
            } else {
                $this->sendError(405, "Method not allowed");
            }
        }else if (preg_match("/rss-rankings\/?$/", $path)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            if ($requestMethod == 'GET') {
                $type = $_GET['type'] ?? 'most_detestable';

                // Validare tip
                $validTypes = ['most_detestable', 'least_detestable'];
                if (!in_array($type, $validTypes)) {
                    $this->sendError(400, "Invalid type parameter. Use one of: " . implode(", ", $validTypes));
                    return;
                }

                $params = ["type" => $type];
                $params = ["format" => "rss"];
                $rankingController = new RankingController($params);
                $rankingController->getRankings();
                return;
            } else {
                $this->sendError(405, "Method not allowed");
            }
        }
        // Rută pentru a adăuga un răspuns la o întrebare
        else if (preg_match("/api\/qa\/answer\/(\d+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            
            $params["questionId"] = $matches[1];
            
            if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $qaController = new QAController($params);
                $qaController->addAnswer();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        }
        
        // Rută pentru a adăuga un vot la un răspuns
        else if (preg_match("/api\/qa\/vote\/(\d+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            
            $params["answerId"] = $matches[1];
            $params["user_id"] = $authorized->id;
            if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $qaController = new QAController($params);
                $qaController->addVote();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        }// Rută pentru a obține întrebările sau a adăuga o întrebare nouă
        else if (preg_match("/api\/qa\/([^\/]+)\/(\d+)$/", $path, $matches)) {
            if (!$authorized) {
                $this->sendError(401, "Authentication required");
                return;
            }
            
            $params["categoryName"] = $matches[1];
            $params["entityId"] = $matches[2];
            
            if ($requestMethod == 'GET') {
                if (!$this->requireAjax()) return;
                $qaController = new QAController($params);
                $qaController->getQuestions();
            } else if ($requestMethod == 'POST') {
                if (!$this->requireAjax()) return;
                $qaController = new QAController($params);
                $qaController->addQuestion();
            } else {
                $this->sendError(405, "Method not allowed");
            }
        }else {
            $this->sendError(404, "Page not found");
        }
    }
}
?>