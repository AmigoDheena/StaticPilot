<?php
require_once __DIR__ . '/content-store.php';
require_once __DIR__ . '/urls.php';
function content_media_url($id, $data = null) {
    $data = $data ?? content_read();
    if (!is_string($id) || !isset($data['media'][$id])) { return ''; }
    return content_site_path() . rawurlencode(basename(__DIR__)) . '/media-file.php?id=' . rawurlencode($id) . '&v=' . rawurlencode($data['media'][$id]['file']);
}
function content_image($id) {
    $data = content_read();
    if (!is_string($id) || !isset($data['media'][$id])) { return ''; }
    $image = $data['media'][$id];
    return '<img src="' . content_escape(content_media_url($id, $data)) . '" alt="' . content_escape($image['alt']) . '" width="' . (int)$image['width'] . '" height="' . (int)$image['height'] . '" loading="lazy">';
}
function media_upload($upload, &$stagedFile) {
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
        throw new ContentValidationException(['file'=>'Choose an image that uploaded successfully.']);
    }
    $path = $upload['tmp_name'];
    if (filesize($path) > 5 * 1024 * 1024) { throw new ContentValidationException(['file'=>'Images must be 5 MB or smaller.']); }
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $size = @getimagesize($path);
    $ext = strtolower(pathinfo(is_string($upload['name'] ?? null) ? $upload['name'] : '', PATHINFO_EXTENSION));
    if (!isset($allowed[$mime]) || !$size || $size['mime'] !== $mime || !in_array($ext, ['jpg','jpeg','png','webp'], true)) { throw new ContentValidationException(['file'=>'Upload a genuine JPEG, PNG, or WebP image. SVG and other file types are not accepted.']); }
    if ($size[0] < 1 || $size[1] < 1 || $size[0] > 8000 || $size[1] > 8000 || $size[0] * $size[1] > 12000000) { throw new ContentValidationException(['file'=>'Images must be at most 12 megapixels and 8,000 pixels on either side.']); }
    if (!function_exists('imagecreatefromstring')) { throw new RuntimeException('Enable PHP GD to upload images.'); }
    $image = @imagecreatefromstring(file_get_contents($path));
    if (!$image) { throw new ContentValidationException(['file'=>'This image could not be decoded. Choose a different file.']); }
    $directory = CONTENT_STORAGE . '/media';
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) { imagedestroy($image); throw new RuntimeException('Cannot create image storage.'); }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $stagedFile = $directory . '/' . $filename;
    try {
        imagealphablending($image, false); imagesavealpha($image, true);
        // Decode and re-encode; never publish the original upload or its metadata.
        if ($mime === 'image/jpeg') { $ok = imagejpeg($image, $stagedFile, 88); }
        elseif ($mime === 'image/png') { $ok = imagepng($image, $stagedFile); }
        else { $ok = function_exists('imagewebp') && imagewebp($image, $stagedFile, 88); }
        if (!$ok) { throw new RuntimeException('Unable to store this image.'); }
    } finally { imagedestroy($image); }
    return ['file'=>$filename,'mime'=>$mime,'width'=>$size[0],'height'=>$size[1]];
}
