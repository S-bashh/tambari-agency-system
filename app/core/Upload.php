<?php
declare(strict_types=1);

final class UploadException extends RuntimeException {}

final class Upload
{
    public const DOC_TYPES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'doc'  => ['application/msword', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    ];

    public static function maxBytes(): int { return (int)cfg('max_upload_mb', 8) * 1048576; }

    /** Turn $_FILES[$field] (single or multiple) into a list of single-file arrays, skipping empty slots. */
    public static function files(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f) return [];
        $out = [];
        if (is_array($f['name'])) {
            foreach ($f['name'] as $i => $n) {
                if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        } elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) {
            $out[] = $f;
        }
        return $out;
    }

    private static function check(array $f): void
    {
        $msgs = [
            UPLOAD_ERR_INI_SIZE => 'is larger than the server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'is too large',
            UPLOAD_ERR_PARTIAL => 'was only partly uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'could not be saved (no temp folder)',
            UPLOAD_ERR_CANT_WRITE => 'could not be written to disk',
        ];
        $name = (string)($f['name'] ?? 'file');
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new UploadException('"' . $name . '" ' . ($msgs[$f['error']] ?? 'failed to upload') . '.');
        }
        if (!is_uploaded_file($f['tmp_name']) && !(defined('TEAMS_TESTING') && is_file($f['tmp_name']))) {
            throw new UploadException('"' . $name . '" was not uploaded correctly.');
        }
        if ((int)$f['size'] <= 0) throw new UploadException('"' . $name . '" is empty.');
        if ((int)$f['size'] > self::maxBytes()) {
            throw new UploadException('"' . $name . '" is larger than ' . (int)cfg('max_upload_mb', 8) . ' MB.');
        }
    }

    private static function mime(string $path): string
    {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        return (string)$fi->file($path);
    }

    /** @return array{stored_name:string,original_name:string,mime_type:string,file_size:int} */
    public static function saveDocument(array $f): array
    {
        self::check($f);
        $orig = self::cleanName((string)$f['name']);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!isset(self::DOC_TYPES[$ext])) {
            throw new UploadException('"' . $orig . '" is not an allowed file type. Allowed: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX.');
        }
        $mime = self::mime($f['tmp_name']);
        if (!in_array($mime, self::DOC_TYPES[$ext], true)) {
            throw new UploadException('"' . $orig . '" does not look like a genuine .' . $ext . ' file.');
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && @getimagesize($f['tmp_name']) === false) {
            throw new UploadException('"' . $orig . '" is not a valid image.');
        }
        $stored = bin2hex(random_bytes(16)) . '.dat';
        $dest = TEAMS_ROOT . '/storage/documents/' . $stored;
        if (!self::move($f['tmp_name'], $dest)) {
            throw new UploadException('Could not store "' . $orig . '". Check that the storage folder is writable.');
        }
        return ['stored_name' => $stored, 'original_name' => $orig, 'mime_type' => $mime, 'file_size' => (int)$f['size']];
    }

    /** Re-encodes to JPEG (strips EXIF/embedded payloads), max 1600px, plus a thumbnail. */
    public static function saveImage(array $f): array
    {
        self::check($f);
        $orig = self::cleanName((string)$f['name']);
        $mime = self::mime($f['tmp_name']);
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new UploadException('"' . $orig . '" must be a JPG, PNG or WebP image.');
        }
        if ($info[0] * $info[1] > 40_000_000) {
            throw new UploadException('"' . $orig . '" has too many pixels. Please use a smaller photo.');
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
            'image/png'  => @imagecreatefrompng($f['tmp_name']),
            default      => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        };
        if (!$src) throw new UploadException('"' . $orig . '" could not be read as an image.');

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($f['tmp_name']);
            $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
            if ($rot) {
                $r = imagerotate($src, $rot, 0);
                if ($r) { $src = $r; }
            }
        }
        $name = bin2hex(random_bytes(16));
        $dir = TEAMS_ROOT . '/storage/images/';
        $full = self::scaleToJpeg($src, 1600);
        $thumb = self::scaleToJpeg($src, 520);
        $ok = imagejpeg($full, $dir . $name . '.jpg', 82) && imagejpeg($thumb, $dir . $name . '_t.jpg', 78);
        if (!$ok) throw new UploadException('Could not store "' . $orig . '". Check that the storage folder is writable.');
        return ['stored_name' => $name, 'original_name' => $orig];
    }

    public static function saveLogo(array $f): string
    {
        self::check($f);
        $mime = self::mime($f['tmp_name']);
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new UploadException('The logo must be a PNG or JPG image.');
        }
        $src = $mime === 'image/png' ? @imagecreatefrompng($f['tmp_name']) : @imagecreatefromjpeg($f['tmp_name']);
        if (!$src) throw new UploadException('The logo could not be read.');
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, 600 / max($w, $h));
        $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $name = 'logo_' . bin2hex(random_bytes(6)) . '.png';
        if (!imagepng($dst, TEAMS_ROOT . '/storage/images/' . $name)) throw new UploadException('Could not store the logo.');
        return $name;
    }

    private static function scaleToJpeg(GdImage $src, int $max): GdImage
    {
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private static function move(string $from, string $to): bool
    {
        if (move_uploaded_file($from, $to)) return true;
        return defined('TEAMS_TESTING') && copy($from, $to);
    }

    public static function cleanName(string $n): string
    {
        $n = basename(str_replace('\\', '/', $n));
        $n = preg_replace('/[^\p{L}\p{N}\.\-_ ()]+/u', '_', $n) ?? 'file';
        return mb_substr($n === '' ? 'file' : $n, 0, 150);
    }
}
