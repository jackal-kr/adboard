<?php
namespace Joomla\Component\Adboard\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Image upload and management helper.
 *
 * Security model:
 *   - MIME type verified from actual file bytes via finfo
 *   - Extension whitelisted and cross-checked against MIME
 *   - is_uploaded_file() confirms HTTP upload provenance
 *   - File size enforced against the component's max_image_size param
 *   - Pixel count checked BEFORE loading into GD to prevent OOM on huge photos
 *   - Re-encoded via GD: validates it is a real image AND strips EXIF/payloads
 *   - Always resized to MAX_SAVE_DIMENSION: reduces disk space AND encoder
 *     memory (critical for WebP, which needs ~2× raw-pixel RAM during encoding)
 *   - Saved filename is a random hex string — no user input in the path
 */
class ImageHelper
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const MIME_EXT_MAP = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
    ];

    private const GD_CREATE = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
    ];

    /**
     * Maximum pixel count (width × height) a source image may have before
     * being rejected.  Prevents out-of-memory errors during GD decode.
     *
     * 12 MP (≈ 4032 × 2976) covers virtually all consumer phone cameras.
     * At 4 bytes/pixel the raw in-memory size is ≈ 48 MB — well within a
     * 128 MB PHP limit even before resizing.
     */
    private const MAX_SOURCE_PIXELS = 12_000_000;

    /**
     * All saved images are scaled down to fit within this bounding box.
     *
     * Benefits:
     *   - Dramatically reduces the RAM needed for WebP / PNG encoding
     *     (encoder temporary buffers scale with image dimensions).
     *   - Reduces disk usage and front-end page weight.
     *   - For an ad board a 1920 px image is more than sufficient.
     */
    private const MAX_SAVE_DIMENSION = 1920;

    // ── Public API ────────────────────────────────────────────────────────

    public static function uploadDir(): string
    {
        return JPATH_ROOT . '/media/com_adboard/ads/';
    }

    /**
     * Validate and save uploaded files from $_FILES[$filesKey].
     *
     * @param  string $filesKey      Key in $_FILES ('images' or 'new_images').
     * @param  int    $maxSlots      Remaining image capacity for this ad.
     * @param  int    $maxSizeMb     Per-file size limit in megabytes.
     * @param  bool   $hadRejections Set to true if any file was skipped.
     * @return string[]              Saved filenames (random hex + extension).
     */
    public static function saveUploads(
        string $filesKey,
        int    $maxSlots,
        int    $maxSizeMb,
        bool   &$hadRejections = false
    ): array {
        $raw = $_FILES[$filesKey] ?? null;

        if (!$raw || empty($raw['name']) || !is_array($raw['name']) || $maxSlots < 1) {
            return [];
        }

        $dir      = self::uploadDir();
        $maxBytes = $maxSizeMb * 1024 * 1024;
        $saved    = [];
        $limit    = min(count($raw['name']), $maxSlots * 2);

        for ($i = 0; $i < $limit && count($saved) < $maxSlots; $i++) {

            // ── Basic checks ──────────────────────────────────────────────

            if (empty($raw['name'][$i]))                                continue;
            if ((int) $raw['error'][$i]  !== UPLOAD_ERR_OK)            continue;
            if ((int) $raw['size'][$i]   >   $maxBytes)                { $hadRejections = true; continue; }

            $tmp = $raw['tmp_name'][$i];
            if (!is_uploaded_file($tmp))                                { $hadRejections = true; continue; }

            // ── MIME + extension ──────────────────────────────────────────

            $mime = (new \finfo(\FILEINFO_MIME_TYPE))->file($tmp);
            if (!in_array($mime, self::ALLOWED_MIMES, true))            { $hadRejections = true; continue; }

            $ext = strtolower(pathinfo($raw['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, self::MIME_EXT_MAP[$mime] ?? [], true)) { $hadRejections = true; continue; }

            // ── Pixel-count pre-check (memory-safe, reads headers only) ──
            //
            // getimagesize() reads only a few hundred bytes from the file to
            // parse width/height — it does NOT load the image into GD memory.
            // This lets us reject photos that are too large to safely decode
            // (e.g. 48 MP phone images) before handing them to imagecreatefrom*().

            $size = @getimagesize($tmp);
            if ($size === false)                                         { $hadRejections = true; continue; }

            [$srcW, $srcH] = $size;
            if (($srcW * $srcH) > self::MAX_SOURCE_PIXELS)              { $hadRejections = true; continue; }

            // ── GD load + re-encode (strips EXIF / embedded payloads) ────

            $createFn = self::GD_CREATE[$mime] ?? null;
            if (!$createFn)                                              { $hadRejections = true; continue; }

            $resource = @$createFn($tmp);
            if (!$resource)                                              { $hadRejections = true; continue; }

            // ── Resize to MAX_SAVE_DIMENSION ──────────────────────────────
            //
            // Always resize before encoding.  Even when the source already fits
            // within the bounding box this is a no-op (returns the same resource).
            // When it IS resized, maybeResize() calls imagedestroy() on the large
            // source immediately, freeing RAM before the encoder needs its buffer.

            $resource = self::maybeResize($resource, self::MAX_SAVE_DIMENSION);

            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $filename = bin2hex(random_bytes(12)) . '.' . $ext;
            self::gdSave($resource, $dir . $filename, $mime);
            imagedestroy($resource);

            $saved[] = $filename;
        }

        return $saved;
    }

    /**
     * Delete a list of filenames from the upload directory.
     */
    public static function deleteFiles(array $filenames): void
    {
        $dir = self::uploadDir();
        foreach ($filenames as $name) {
            $path = $dir . basename((string) $name);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Sanitise a keep_images[] POST array: strip path traversal, enforce
     * the filename pattern generated by saveUploads().
     *
     * @param  array $raw  Raw POST values.
     * @return string[]
     */
    public static function filterKeepList(array $raw): array
    {
        $clean = [];
        foreach ($raw as $name) {
            $name = basename((string) $name);
            if ($name !== '' && preg_match('/^[a-f0-9]{24}\.[a-z]{3,4}$/', $name)) {
                $clean[] = $name;
            }
        }
        return $clean;
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * Resize a GD image resource to fit within $maxDim × $maxDim, preserving
     * aspect ratio and alpha transparency.
     *
     * If the image already fits, returns the original resource unchanged.
     * If a new resource is created, the ORIGINAL is destroyed immediately to
     * free its RAM before the encoder needs its working buffer.
     *
     * @param  \GdImage $resource  Source GD resource (may be destroyed here).
     * @param  int      $maxDim    Maximum width or height in pixels.
     * @return \GdImage            Resized (or original) resource.
     */
    private static function maybeResize(\GdImage $resource, int $maxDim): \GdImage
    {
        $srcW = imagesx($resource);
        $srcH = imagesy($resource);

        // Already fits — nothing to do
        if ($srcW <= $maxDim && $srcH <= $maxDim) {
            return $resource;
        }

        // Scale down preserving aspect ratio
        if ($srcW >= $srcH) {
            $dstW = $maxDim;
            $dstH = max(1, (int) round($srcH * $maxDim / $srcW));
        } else {
            $dstH = $maxDim;
            $dstW = max(1, (int) round($srcW * $maxDim / $srcH));
        }

        $resized = imagecreatetruecolor($dstW, $dstH);

        // Preserve alpha channel for PNG and WebP
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $dstW, $dstH, $transparent);
        imagealphablending($resized, true);

        imagecopyresampled($resized, $resource, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        // Free the large source NOW — before the encoder allocates its buffer
        imagedestroy($resource);

        return $resized;
    }

    private static function gdSave(\GdImage $resource, string $path, string $mime): void
    {
        match ($mime) {
            'image/jpeg' => imagejpeg($resource, $path, 90),
            'image/png'  => imagepng($resource, $path),
            'image/gif'  => imagegif($resource, $path),
            'image/webp' => imagewebp($resource, $path, 90),
            default      => null,
        };
    }
}
