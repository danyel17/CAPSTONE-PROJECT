<?php
/**
 * Media Management Functions
 * Handles all media operations including upload, organization, and display
 */

require_once 'config.php';

class MediaManager {
    private $conn;
    private $upload_paths = [
        'video' => 'assets/videos/',
        'image' => 'assets/imgs/',
        'thumbnail' => 'assets/thumbnails/'
    ];
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Get media settings from database
     */
    public function getMediaSettings() {
        // Safe defaults ensure functionality even if DB table doesn't exist yet
        $settings = [
            'allowed_video_formats' => ['mp4','avi','mov','wmv','webm'],
            'allowed_image_formats' => ['jpg','jpeg','png','gif','webp'],
            'max_file_size_video' => 50 * 1024 * 1024,
            'max_file_size_image' => 10 * 1024 * 1024,
            'auto_generate_thumbnails' => true
        ];
        
        try {
            $result = $this->conn->query("SELECT setting_key, setting_value, setting_type FROM media_settings");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $value = $row['setting_value'];
                    switch ($row['setting_type']) {
                        case 'number':
                            $value = (int)$value;
                            break;
                        case 'boolean':
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'json':
                            $value = json_decode($value, true);
                            break;
                    }
                    $settings[$row['setting_key']] = $value;
                }
            }
        } catch (Exception $e) {
            // fall back to defaults silently
        }
        
        return $settings;
    }
    
    /**
     * Get all media categories
     */
    public function getCategories() {
        $result = $this->conn->query("
            SELECT * FROM media_categories 
            WHERE is_active = TRUE 
            ORDER BY display_order ASC, category_name ASC
        ");
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get all media sections
     */
    public function getSections() {
        $result = $this->conn->query("
            SELECT * FROM media_sections 
            WHERE is_active = TRUE 
            ORDER BY display_order ASC, section_name ASC
        ");
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get media files by category and section
     */
    public function getMediaFiles($category = null, $section = null, $file_type = null, $limit = null) {
        $sql = "
            SELECT mf.*, mc.category_name, ms.section_name, u.full_name as uploaded_by_name
            FROM media_files mf
            LEFT JOIN media_categories mc ON mf.category_id = mc.category_id
            LEFT JOIN media_sections ms ON mf.section = ms.section_name
            LEFT JOIN users u ON mf.uploaded_by = u.user_id
            WHERE mf.is_active = TRUE
        ";
        
        $params = [];
        $types = '';
        
        if ($category) {
            $sql .= " AND mf.category_id = ?";
            $params[] = $category;
            $types .= 'i';
        }
        
        if ($section) {
            $sql .= " AND mf.section = ?";
            $params[] = $section;
            $types .= 's';
        }
        
        if ($file_type) {
            $sql .= " AND mf.file_type = ?";
            $params[] = $file_type;
            $types .= 's';
        }
        
        $sql .= " ORDER BY mf.display_order ASC, mf.uploaded_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $files = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $files;
    }
    
    /**
     * Get featured media for homepage
     */
    public function getFeaturedMedia($section = 'homepage') {
        return $this->getMediaFiles(null, $section, null, 1);
    }
    
    /**
     * Upload and organize media file
     */
    public function uploadMedia($file, $category_id, $section, $title = '', $description = '', $uploaded_by = 1) {
        try {
            $settings = $this->getMediaSettings();
            
            // Validate file
            $validation = $this->validateFile($file, $settings);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            $file_type = $validation['file_type'];
            $upload_dir = $this->upload_paths[$file_type];
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    return ['success' => false, 'message' => 'Failed to create upload directory'];
                }
            }
            
            // Generate unique filename
            $file_info = pathinfo($file['name']);
            $extension = strtolower($file_info['extension']);
            $filename = $file_type . '_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_path = $upload_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                return ['success' => false, 'message' => 'Failed to upload file'];
            }
            
            // Generate thumbnail for images
            $thumbnail_path = null;
            if ($file_type === 'image' && $settings['auto_generate_thumbnails']) {
                $thumbnail_path = $this->generateThumbnail($upload_path, $filename);
            }
            
            // Save to database
            $stmt = $this->conn->prepare("
                INSERT INTO media_files 
                (file_name, file_path, file_type, file_size, mime_type, title, description, 
                 category_id, section, alt_text, file_extension, thumbnail_path, uploaded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $alt_text = $title ?: $file_info['filename'];
            $stmt->bind_param(
                "sssisssissssi",
                $filename,
                $upload_path,
                $file_type,
                $file['size'],
                $file['type'],
                $title,
                $description,
                $category_id,
                $section,
                $alt_text,
                $extension,
                $thumbnail_path,
                $uploaded_by
            );
            
            if ($stmt->execute()) {
                $media_id = $this->conn->insert_id;
                
                // Log media usage
                $this->logMediaUsage($media_id, 'upload', $uploaded_by);
                
                return [
                    'success' => true, 
                    'message' => ucfirst($file_type) . ' uploaded successfully!',
                    'media_id' => $media_id,
                    'file_path' => $upload_path,
                    'thumbnail_path' => $thumbnail_path
                ];
            } else {
                // Clean up uploaded file if database insert fails
                unlink($upload_path);
                return ['success' => false, 'message' => 'Failed to save file information to database'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file, $settings) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'File upload error'];
        }
        
        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);
        
        // Determine file type
        $file_type = null;
        if (in_array($extension, $settings['allowed_video_formats'])) {
            $file_type = 'video';
        } elseif (in_array($extension, $settings['allowed_image_formats'])) {
            $file_type = 'image';
        } else {
            return ['valid' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', array_merge($settings['allowed_video_formats'], $settings['allowed_image_formats']))];
        }
        
        // Check file size
        $max_size = $file_type === 'video' ? $settings['max_file_size_video'] : $settings['max_file_size_image'];
        if ($file['size'] > $max_size) {
            $max_size_mb = round($max_size / (1024 * 1024), 1);
            return ['valid' => false, 'message' => "File size too large. Maximum size: {$max_size_mb}MB"];
        }
        
        return ['valid' => true, 'file_type' => $file_type];
    }
    
    /**
     * Generate thumbnail for image
     */
    private function generateThumbnail($image_path, $filename) {
        try {
            $thumbnail_dir = $this->upload_paths['thumbnail'];
            
            // Create thumbnail directory if it doesn't exist
            if (!file_exists($thumbnail_dir)) {
                mkdir($thumbnail_dir, 0755, true);
            }
            
            $thumbnail_path = $thumbnail_dir . 'thumb_' . $filename;
            
            // Get image info
            if (!function_exists('getimagesize')) {
                return null;
            }
            $image_info = @getimagesize($image_path);
            if (!$image_info) {
                return null;
            }
            
            $width = $image_info[0];
            $height = $image_info[1];
            $type = $image_info[2];
            
            // Calculate thumbnail dimensions (max 300x300)
            $max_size = 300;
            $ratio = min($max_size / $width, $max_size / $height);
            $new_width = intval($width * $ratio);
            $new_height = intval($height * $ratio);
            
            // Create source image
            if (!function_exists('imagecreatetruecolor')) {
                // GD not available
                return null;
            }
            switch ($type) {
                case IMAGETYPE_JPEG:
                    if (!function_exists('imagecreatefromjpeg')) return null;
                    $source = @imagecreatefromjpeg($image_path);
                    break;
                case IMAGETYPE_PNG:
                    if (!function_exists('imagecreatefrompng')) return null;
                    $source = @imagecreatefrompng($image_path);
                    break;
                case IMAGETYPE_GIF:
                    if (!function_exists('imagecreatefromgif')) return null;
                    $source = @imagecreatefromgif($image_path);
                    break;
                case IMAGETYPE_WEBP:
                    if (!function_exists('imagecreatefromwebp')) return null;
                    $source = @imagecreatefromwebp($image_path);
                    break;
                default:
                    return null;
            }
            
            if (!$source) {
                return null;
            }
            
            // Create thumbnail
            $thumbnail = imagecreatetruecolor($new_width, $new_height);
            
            // Preserve transparency for PNG and GIF
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefill($thumbnail, 0, 0, $transparent);
            }
            
            // Resize image
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            // Save thumbnail
            $success = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $success = imagejpeg($thumbnail, $thumbnail_path, 85);
                    break;
                case IMAGETYPE_PNG:
                    $success = imagepng($thumbnail, $thumbnail_path, 8);
                    break;
                case IMAGETYPE_GIF:
                    $success = imagegif($thumbnail, $thumbnail_path);
                    break;
                case IMAGETYPE_WEBP:
                    $success = imagewebp($thumbnail, $thumbnail_path, 85);
                    break;
            }
            
            // Clean up
            imagedestroy($source);
            imagedestroy($thumbnail);
            
            return $success ? $thumbnail_path : null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Update media file information
     */
    public function updateMedia($media_id, $data) {
        $allowed_fields = ['title', 'description', 'alt_text', 'category_id', 'section', 'display_order', 'is_featured'];
        $updates = [];
        $params = [];
        $types = '';
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
                $types .= is_int($value) ? 'i' : 's';
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }
        
        $sql = "UPDATE media_files SET " . implode(', ', $updates) . " WHERE media_id = ?";
        $params[] = $media_id;
        $types .= 'i';
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Media updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update media'];
        }
    }
    
    /**
     * Delete media file
     */
    public function deleteMedia($media_id) {
        try {
            // Get file information
            $stmt = $this->conn->prepare("SELECT file_path, thumbnail_path FROM media_files WHERE media_id = ?");
            $stmt->bind_param("i", $media_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $media = $result->fetch_assoc();
            $stmt->close();
            
            if (!$media) {
                return ['success' => false, 'message' => 'Media not found'];
            }
            
            // Delete files from filesystem
            if (file_exists($media['file_path'])) {
                unlink($media['file_path']);
            }
            
            if ($media['thumbnail_path'] && file_exists($media['thumbnail_path'])) {
                unlink($media['thumbnail_path']);
            }
            
            // Delete from database
            $stmt = $this->conn->prepare("DELETE FROM media_files WHERE media_id = ?");
            $stmt->bind_param("i", $media_id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Media deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete media from database'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Delete error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Log media usage
     */
    private function logMediaUsage($media_id, $page_accessed, $user_id = null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $this->conn->prepare("
            INSERT INTO media_usage_log (media_id, page_accessed, user_id, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isis", $media_id, $page_accessed, $user_id, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Get media statistics
     */
    public function getMediaStats() {
        $stats = [];
        
        // Total media count
        $result = $this->conn->query("SELECT COUNT(*) as total FROM media_files WHERE is_active = TRUE");
        $stats['total'] = $result->fetch_assoc()['total'];
        
        // Media by type
        $result = $this->conn->query("
            SELECT file_type, COUNT(*) as count 
            FROM media_files 
            WHERE is_active = TRUE 
            GROUP BY file_type
        ");
        $stats['by_type'] = $result->fetch_all(MYSQLI_ASSOC);
        
        // Media by category
        $result = $this->conn->query("
            SELECT mc.category_name, COUNT(mf.media_id) as count 
            FROM media_categories mc
            LEFT JOIN media_files mf ON mc.category_id = mf.category_id AND mf.is_active = TRUE
            GROUP BY mc.category_id, mc.category_name
        ");
        $stats['by_category'] = $result->fetch_all(MYSQLI_ASSOC);
        
        // Recent uploads
        $result = $this->conn->query("
            SELECT mf.*, u.full_name as uploaded_by_name
            FROM media_files mf
            LEFT JOIN users u ON mf.uploaded_by = u.user_id
            WHERE mf.is_active = TRUE
            ORDER BY mf.uploaded_at DESC
            LIMIT 10
        ");
        $stats['recent'] = $result->fetch_all(MYSQLI_ASSOC);
        
        return $stats;
    }
    
    /**
     * Clean up old media files
     */
    public function cleanupOldMedia($days = 30) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get inactive media older than cutoff date
        $stmt = $this->conn->prepare("
            SELECT media_id, file_path, thumbnail_path 
            FROM media_files 
            WHERE is_active = FALSE AND updated_at < ?
        ");
        $stmt->bind_param("s", $cutoff_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_media = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $deleted_count = 0;
        
        foreach ($old_media as $media) {
            // Delete files
            if (file_exists($media['file_path'])) {
                unlink($media['file_path']);
            }
            if ($media['thumbnail_path'] && file_exists($media['thumbnail_path'])) {
                unlink($media['thumbnail_path']);
            }
            
            // Delete database record
            $stmt = $this->conn->prepare("DELETE FROM media_files WHERE media_id = ?");
            $stmt->bind_param("i", $media['media_id']);
            $stmt->execute();
            $stmt->close();
            
            $deleted_count++;
        }
        
        return $deleted_count;
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Helper functions for easy access
function getMediaManager() {
    return new MediaManager();
}

function getMediaByCategory($category_name, $section = 'homepage', $limit = 1) {
    $manager = new MediaManager();
    $categories = $manager->getCategories();
    
    $category_id = null;
    foreach ($categories as $cat) {
        if ($cat['category_name'] === $category_name) {
            $category_id = $cat['category_id'];
            break;
        }
    }
    
    if (!$category_id) {
        return [];
    }
    
    return $manager->getMediaFiles($category_id, $section, null, $limit);
}

function getFeaturedVideo($section = 'homepage') {
    return getMediaByCategory('market_overview', $section, 1);
}

function getFeaturedImage($section = 'homepage') {
    return getMediaByCategory('price_updates', $section, 1);
}
?>
