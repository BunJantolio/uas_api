<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once "../controllers/UserController.php";
$user = new UserController();
$method = $_SERVER['REQUEST_METHOD'];


switch($method) {
    case 'GET':
        $user->index();
        break;


    case 'POST':
        $user->store();
        break;
}
