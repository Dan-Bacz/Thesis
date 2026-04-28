<?php
/**
 * Document Management Controller
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class DocumentController {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Upload document
     */
    public function uploadDocument($personnelId, $documentTypeId, $fileData, $expiryDate = null) {
        try {
            // Validate file
            $validation = $this->validateFile($fileData);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Create upload directory if not exists
            $uploadDir = UPLOAD_PATH . 'documents/' . date('Y/m/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '.' . pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Failed to upload file'];
            }
            
            // Save document record
            $documentData = [
                'personnel_id' => $personnelId,
                'document_type_id' => $documentTypeId,
                'document_name' => sanitize($fileData['name']),
                'file_path' => str_replace(APP_PATH . '/', '', $filePath),
                'file_size' => $fileData['size'],
                'file_type' => $fileData['type'],
                'expiry_date' => $expiryDate,
                'status' => 'pending'
            ];
            
            $documentId = $this->db->insert('personal_documents', $documentData);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'document_upload', 'personal_documents', $documentId);
            
            return ['success' => true, 'message' => 'Document uploaded successfully', 'document_id' => $documentId];
            
        } catch (Exception $e) {
            error_log("Document upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while uploading document'];
        }
    }
    
    /**
     * Get documents by personnel
     */
    public function getPersonnelDocuments($personnelId, $status = null) {
        try {
            $sql = "SELECT pd.*, dt.name as document_type_name, dt.category, dt.is_required, dt.expiry_required
                    FROM personal_documents pd
                    LEFT JOIN document_types dt ON pd.document_type_id = dt.id
                    WHERE pd.personnel_id = ?";
            
            $params = [$personnelId];
            
            if ($status) {
                $sql .= " AND pd.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY pd.upload_date DESC";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Get documents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verify document
     */
    public function verifyDocument($documentId, $status, $remarks = null) {
        try {
            $this->db->beginTransaction();
            
            // Get current document
            $current = $this->db->fetch(
                "SELECT * FROM personal_documents WHERE id = ?",
                [$documentId]
            );
            
            if (!$current) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Document not found'];
            }
            
            // Update document status
            $updateData = [
                'status' => $status,
                'verified_by' => $_SESSION['user_id'],
                'verified_date' => date('Y-m-d H:i:s'),
                'remarks' => $remarks
            ];
            
            $this->db->update('personal_documents', $updateData, 'id = ?', [$documentId]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'document_verification', 'personal_documents', $documentId, 
                       ['status' => $current['status']], ['status' => $status]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Document verified successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Document verification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while verifying document'];
        }
    }
    
    /**
     * Delete document
     */
    public function deleteDocument($documentId) {
        try {
            // Get document info
            $document = $this->db->fetch(
                "SELECT * FROM personal_documents WHERE id = ?",
                [$documentId]
            );
            
            if (!$document) {
                return ['success' => false, 'message' => 'Document not found'];
            }
            
            // Check permissions
            $user = getCurrentUser();
            if ($user['role'] !== 'admin' && $document['personnel_id'] !== $user['personnel_id']) {
                return ['success' => false, 'message' => 'Permission denied'];
            }
            
            // Delete file
            $filePath = APP_PATH . '/' . $document['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete database record
            $this->db->delete('personal_documents', 'id = ?', [$documentId]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'document_delete', 'personal_documents', $documentId);
            
            return ['success' => true, 'message' => 'Document deleted successfully'];
            
        } catch (Exception $e) {
            error_log("Document delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting document'];
        }
    }
    
    /**
     * Get document types
     */
    public function getDocumentTypes($category = null) {
        try {
            $sql = "SELECT * FROM document_types";
            $params = [];
            
            if ($category) {
                $sql .= " WHERE category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY name";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Get document types error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get documents expiring soon
     */
    public function getExpiringDocuments($days = 30) {
        try {
            $sql = "SELECT pd.*, p.first_name, p.last_name, dt.name as document_type_name
                    FROM personal_documents pd
                    LEFT JOIN personnel p ON pd.personnel_id = p.id
                    LEFT JOIN document_types dt ON pd.document_type_id = dt.id
                    WHERE pd.expiry_date IS NOT NULL 
                    AND pd.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                    AND pd.expiry_date >= CURDATE()
                    AND pd.status = 'verified'
                    ORDER BY pd.expiry_date ASC";
            
            return $this->db->fetchAll($sql, [$days]);
            
        } catch (Exception $e) {
            error_log("Get expiring documents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get expired documents
     */
    public function getExpiredDocuments() {
        try {
            $sql = "SELECT pd.*, p.first_name, p.last_name, dt.name as document_type_name
                    FROM personal_documents pd
                    LEFT JOIN personnel p ON pd.personnel_id = p.id
                    LEFT JOIN document_types dt ON pd.document_type_id = dt.id
                    WHERE pd.expiry_date IS NOT NULL 
                    AND pd.expiry_date < CURDATE()
                    AND pd.status = 'verified'
                    ORDER BY pd.expiry_date DESC";
            
            return $this->db->fetchAll($sql);
            
        } catch (Exception $e) {
            error_log("Get expired documents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check if file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'File upload failed'];
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['valid' => false, 'message' => 'File size exceeds maximum limit of 5MB'];
        }
        
        // Check file type
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, ALLOWED_FILE_TYPES)) {
            return ['valid' => false, 'message' => 'Invalid file type. Only PDF, JPG, PNG, DOC, and DOCX files are allowed'];
        }
        
        // Check if file is actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => 'Invalid file upload'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Download document
     */
    public function downloadDocument($documentId) {
        try {
            // Get document info
            $document = $this->db->fetch(
                "SELECT pd.*, p.first_name, p.last_name 
                 FROM personal_documents pd
                 LEFT JOIN personnel p ON pd.personnel_id = p.id
                 WHERE pd.id = ?",
                [$documentId]
            );
            
            if (!$document) {
                return ['success' => false, 'message' => 'Document not found'];
            }
            
            // Check permissions
            $user = getCurrentUser();
            if ($user['role'] === 'employee' && $document['personnel_id'] !== $user['personnel_id']) {
                return ['success' => false, 'message' => 'Permission denied'];
            }
            
            $filePath = APP_PATH . '/' . $document['file_path'];
            
            if (!file_exists($filePath)) {
                return ['success' => false, 'message' => 'File not found'];
            }
            
            // Log activity
            logActivity($_SESSION['user_id'], 'document_download', 'personal_documents', $documentId);
            
            return [
                'success' => true,
                'file_path' => $filePath,
                'file_name' => $document['document_name'],
                'file_type' => $document['file_type']
            ];
            
        } catch (Exception $e) {
            error_log("Document download error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while downloading document'];
        }
    }
}
