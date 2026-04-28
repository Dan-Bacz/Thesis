<?php
require_once '../../config/config.php';
require_once '../../controllers/AuthController.php';
require_once '../../controllers/DocumentController.php';

// Check authentication
$authController = new AuthController();
$authController->validateSession();

$documentId = $_GET['id'] ?? '';

if (empty($documentId)) {
    setFlashMessage('error', 'Document ID is required');
    redirect('index.php');
}

$documentController = new DocumentController();
$result = $documentController->downloadDocument($documentId);

if (!$result['success']) {
    setFlashMessage('error', $result['message']);
    redirect('index.php');
}

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: ' . $result['file_type']);
header('Content-Disposition: attachment; filename="' . $result['file_name'] . '"');
header('Content-Length: ' . filesize($result['file_path']));
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($result['file_path']);
exit();
?>
