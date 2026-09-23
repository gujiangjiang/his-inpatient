<?php
// api/core/helpers/upload_helper.php
class UploadHelper {
    public static function save($file, $dir) {
        if (!$file || !isset($file['tmp_name'])) return null;
        $uploadDir = BASE_DIR . '/public/uploads/' . $dir;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = StringHelper::random(16) . '_' . basename($file['name']);
        $dest = $uploadDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return $dir . '/' . $filename;
        }
        return null;
    }
}
