<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once "../controllers/AuthController.php";
$auth = new AuthController();
$auth->login();
