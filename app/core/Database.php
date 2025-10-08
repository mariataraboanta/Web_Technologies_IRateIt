<?php

class Database {
    public $pdo;
    private static $instance = NULL;
    private function __construct() {        
        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'review_app';
        $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    }

    public static function getInstance():Database {
        if(!static::$instance) {
            static::$instance = new Database();
        }
        return static::$instance;
    }
}