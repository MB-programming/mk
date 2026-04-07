<?php
/**
 * On-the-fly image resizer + WebP converter + proper cache headers
 * Usage: /api/img.php?src=logob.webp&w=340
 *        /api/img.php?src=pattern-1.webp&w=1440&q=60
 *
 * - Resizes to requested width (height auto) when GD is available
 * - Sets 1-year Cache-Control via PHP header() (works even if mod_headers disabled)
 * - Caches result to disk so subsequent requests skip resizing
 * - Falls back to serving the original file if GD/WebP support is missing
 */

// Suppress all PHP notices/warnings so they never corrupt image output
error_reporting(0);
ini_set('display_errors', 0);

// Disable zlib output compression — images are already compressed;
// zlib would corrupt the Content-Length header set below
ini_set('zlib.output_compression', 0);

// Bump memory limit to handle large source images (e.g. high-res logos)
// A 10415×4802 RGBA image needs ~200 MB; default PHP limit is often 128 MB
ini_set('memory_limit', '512M');

// Allowed source files (prevent path traversal)
$ALLOWED_EXTS = ['webp', 'jpg', 'jpeg', 'png', 'gif'];

$src  = trim($_GET['src'] ?? '');
$w    = max(10, min(3000, intval($_GET['w'] ?? 0)));
$q    = max(30, min(100, intval($_GET['q'] ?? 82)));

// ── Validate ──────────────────────────────────────────────────
if (!$src) { http_response_code(400); exit('Missing src'); }

// Strip directory traversal
$src = ltrim(str_replace(['..', '\\'], '', $src), '/');
$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));

if (!in_array($ext, $ALLOWED_EXTS, true)) {
    http_response_code(403); exit('Forbidden');
}

$root    = dirname(__DIR__);   // /home/user/karl-makha
$srcPath = $root . '/' . $src;

if (!is_file($srcPath)) { http_response_code(404); exit('Not found'); }

$srcMtime = filemtime($srcPath);

// ── MIME helpers ───────────────────────────────────────────────
function mimeForExt(string $ext): string {
    $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',  'gif'  => 'image/gif',
            'webp' => 'image/webp'];
    return $map[$ext] ?? 'application/octet-stream';
}

// ── Serve helper (sets 1-year cache headers then outputs data) ─
function serveImage(string $data, string $mime, int $srcMtime): void {
    $etag = '"' . md5($data) . '"';

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $srcMtime) . ' GMT');
    header('ETag: ' . $etag);
    header('Vary: Accept-Encoding');
    header('Content-Length: ' . strlen($data));

    // Handle conditional GET
    $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH']  ?? '');
    $ifModSince  = trim($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
    if ($ifNoneMatch === $etag ||
        ($ifModSince && strtotime($ifModSince) >= $srcMtime)) {
        http_response_code(304);
        exit;
    }

    echo $data;
    exit;
}

// ── Detect GD + WebP support ───────────────────────────────────
$hasGD   = extension_loaded('gd');
$gdInfo  = $hasGD ? gd_info() : [];
$hasWebP = $hasGD && !empty($gdInfo['WebP Support']);

// Map ext → GD loader function name
$loaderMap = [
    'webp' => 'imagecreatefromwebp',
    'jpg'  => 'imagecreatefromjpeg',
    'jpeg' => 'imagecreatefromjpeg',
    'png'  => 'imagecreatefrompng',
    'gif'  => 'imagecreatefromgif',
];
$loader = $loaderMap[$ext] ?? null;
$canLoad = $hasGD && $loader && function_exists($loader);

// ── Cache lookup ───────────────────────────────────────────────
$cacheDir = $root . '/cache/img/';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

// Cache key includes whether we can do WebP conversion
$cacheExt  = ($canLoad && $hasWebP) ? 'webp' : $ext;
$cacheKey  = md5($src . '|' . $w . '|' . $q . '|' . $cacheExt) . '.' . $cacheExt;
$cachePath = $cacheDir . $cacheKey;

if (is_file($cachePath) && filemtime($cachePath) >= $srcMtime) {
    $mime = $cacheExt === 'webp' ? 'image/webp' : mimeForExt($ext);
    serveImage(file_get_contents($cachePath), $mime, $srcMtime);
}

// ── No GD or can't load this format → serve original as-is ────
if (!$canLoad) {
    serveImage(file_get_contents($srcPath), mimeForExt($ext), $srcMtime);
}

// ── Load source image via GD ───────────────────────────────────
$img = @$loader($srcPath);

if (!$img) {
    // GD loaded but failed to decode → serve original
    serveImage(file_get_contents($srcPath), mimeForExt($ext), $srcMtime);
}

$origW = imagesx($img);
$origH = imagesy($img);

// ── Resize if needed ──────────────────────────────────────────
if ($w > 0 && $w < $origW) {
    $newH    = intval($origH * ($w / $origW));
    $resized = imagecreatetruecolor($w, $newH);

    // Preserve transparency (PNG / WebP with alpha)
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefilledrectangle($resized, 0, 0, $w, $newH, $transparent);

    imagecopyresampled($resized, $img, 0, 0, 0, 0, $w, $newH, $origW, $origH);
    imagedestroy($img);
    $img = $resized;
}

// ── Encode output ─────────────────────────────────────────────
ob_start();
if ($hasWebP) {
    imagewebp($img, null, $q);
    $outMime = 'image/webp';
    $outExt  = 'webp';
} else {
    // WebP not available — output in original format
    switch ($ext) {
        case 'jpg': case 'jpeg': imagejpeg($img, null, $q); break;
        case 'png':  imagepng($img);  break;
        case 'gif':  imagegif($img);  break;
        default:     imagejpeg($img); break;
    }
    $outMime = mimeForExt($ext);
    $outExt  = $ext;
}
$data = ob_get_clean();
imagedestroy($img);

// Recalculate cache path with final ext (may differ if WebP unavailable)
$finalCacheKey  = md5($src . '|' . $w . '|' . $q . '|' . $outExt) . '.' . $outExt;
$finalCachePath = $cacheDir . $finalCacheKey;
@file_put_contents($finalCachePath, $data);

serveImage($data, $outMime, $srcMtime);
