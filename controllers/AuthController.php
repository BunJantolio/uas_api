<?php
require_once "../config/Database.php";
require_once "../models/User.php";


class AuthController {


    private $user;


    public function __construct() {
        $database = new Database();
        $db = $database->connect();


        $this->user = new User($db);
    }
    public function login() {
        $data = json_decode(file_get_contents("php://input"), true);


        $result = $this->user->login(
            $data['username'],
            $data['password']
        );


        if($result) {
            echo json_encode($result);
        } else {
            http_response_code(401);
            echo json_encode([
                "message" => "Login Failed"
            ]);
        }
    }
}
