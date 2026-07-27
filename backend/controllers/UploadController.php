<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class UploadController {

    public static function upload(): void {
        AuthMiddleware::authenticate();

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload failed with error code: ' . $file['error'], 400);
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            Response::error('File exceeds maximum size of ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB', 413);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes, true)) {
            Response::error('Invalid file type. Allowed: JPG, PNG, GIF, PDF', 400);
        }

        $ext = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $filename = uniqid('receipt_', true) . '.' . $ext;
        $destPath = UPLOAD_DIR . $filename;

        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::error('Failed to save file', 500);
        }

        Response::success([
            'filename'    => $filename,
            'original_name' => $file['name'],
            'size'        => $file['size'],
            'mime_type'   => $mimeType,
            'url'         => '/uploads/' . $filename,
        ], 'File uploaded successfully');
    }
}
