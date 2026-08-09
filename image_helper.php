<?php
// image_helper.php
//
// Shared helper -- automatically resizes/compresses any uploaded photo
// before saving it, so page speed stays good no matter how large a
// photo the agent originally uploads from their phone/camera. Used by
// save_hotel.php (hotel photo, room photo) and save_gallery.php
// (gallery photos).

/**
 * Resize (if needed) and compress an uploaded image, then save it to
 * $target_path. Returns true on success, false on failure (caller
 * should fall back to a plain move_uploaded_file() copy if this fails,
 * so a photo never gets silently lost just because compression failed).
 */
function compressAndSaveImage($tmp_path, $target_path, $mime, $max_width = 1600, $quality = 80) {
    if (!function_exists('imagecreatetruecolor')) {
        // GD extension not available -- caller should fall back to a
        // plain copy rather than lose the photo entirely.
        return false;
    }

    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($tmp_path); break;
        case 'image/png':  $src = @imagecreatefrompng($tmp_path); break;
        case 'image/webp': $src = @imagecreatefromwebp($tmp_path); break;
        default: return false;
    }
    if (!$src) return false;

    $orig_width = imagesx($src);
    $orig_height = imagesy($src);

    if ($orig_width > $max_width) {
        $new_width = $max_width;
        $new_height = (int)round($orig_height * ($max_width / $orig_width));
    } else {
        // Already small enough -- just re-compress at the target
        // quality without resizing, still saves real space on
        // uncompressed camera photos.
        $new_width = $orig_width;
        $new_height = $orig_height;
    }

    $resized = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency for PNG/WEBP instead of turning it black.
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }

    imagecopyresampled($resized, $src, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height);

    // Always save as JPEG -- smallest file size for photos, and every
    // browser supports it universally (transparency isn't needed for
    // hotel/room/gallery photos).
    if ($mime === 'image/png' || $mime === 'image/webp') {
        // Flatten onto a white background before converting to JPEG,
        // so transparent areas don't turn black.
        $flat = imagecreatetruecolor($new_width, $new_height);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $resized, 0, 0, 0, 0, $new_width, $new_height);
        imagedestroy($resized);
        $resized = $flat;
    }

    $ok = imagejpeg($resized, $target_path, $quality);

    imagedestroy($src);
    imagedestroy($resized);

    return $ok;
}

/**
 * Full helper: validates an uploaded file, compresses it, and returns
 * the relative path to store in the database -- or null if the upload
 * was invalid/empty. $upload_dir must end with a slash.
 *
 * $max_width / $quality let a caller ask for higher fidelity for
 * large hero/background photos (theme images) than the default used
 * for smaller UI elements like gallery thumbnails -- still always
 * compressed, just with a higher ceiling.
 */
function handleImageUpload($file, $upload_dir, $filename_prefix, $max_width = 1600, $quality = 80) {
    if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime]) || $file['size'] > 8 * 1024 * 1024) return null;

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    // Always .jpg since compressAndSaveImage() re-encodes everything
    // as JPEG for the smallest file size.
    $filename = $filename_prefix . '-' . time() . '-' . mt_rand(1000, 9999) . '.jpg';
    $target_path = $upload_dir . $filename;

    if (!compressAndSaveImage($file['tmp_name'], $target_path, $mime, $max_width, $quality)) {
        // GD unavailable or compression failed for some reason -- fall
        // back to saving the original file as-is rather than losing
        // the photo entirely.
        $filename = $filename_prefix . '-' . time() . '-' . mt_rand(1000, 9999) . '.' . $allowed[$mime];
        $target_path = $upload_dir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target_path)) return null;
    }

    return $filename;
}