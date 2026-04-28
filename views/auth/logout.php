<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';

$authController = new AuthController();
$authController->logout();
?>
