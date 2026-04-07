<?php
/**
 * Minify CSS/JS on-the-fly with disk cache
 * Usage: /api/minify.php?f=assets/css/style.css
 */

$allowed = [
    'assets/css/style.css',
    'assets/js/main.js',
];

$file = trim($_GET['f'] ?? '');

if (!in_array($file, $allowed, true)) {
    http_response_code(403);
    exit('Forbidden');
}

$root     = dirname(__DIR__);
$fullPath = $root . '/' . $file;

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Not found');
}

$ext      = pathinfo($file, PATHINFO_EXTENSION);
$cacheDir = $root . '/cache/minify/';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

$cacheFile = $cacheDir . md5($file) . '.' . $ext;
$srcMtime  = filemtime($fullPath);

if (is_file($cacheFile) && filemtime($cacheFile) >= $srcMtime) {
    $content = file_get_contents($cacheFile);
} else {
    $content = file_get_contents($fullPath);
    if ($ext === 'css') {
        $content = minifyCSS($content);
    } elseif ($ext === 'js') {
        $content = minifyJS($content);
    }
    file_put_contents($cacheFile, $content);
}

$mime = ($ext === 'css') ? 'text/css' : 'application/javascript';
header("Content-Type: $mime; charset=utf-8");
header('Cache-Control: public, max-age=2592000');
header('Vary: Accept-Encoding');

$etag = '"' . md5($content) . '"';
header("ETag: $etag");
if (
    isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
    trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag
) {
    http_response_code(304);
    exit;
}

echo $content;

function minifyCSS(string $css): string {
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = str_replace(';}', '}', $css);
    return trim($css);
}

function minifyJS(string $js): string {
    // Remove block comments /* ... */ (not inside strings — safe for our codebase)
    $js = preg_replace('!/\*[\s\S]*?\*/!', '', $js);
    // Remove full-line // comments (lines that are only whitespace + comment)
    $js = preg_replace('/^[ \t]*\/\/[^\n]*$/m', '', $js);
    // Collapse runs of spaces/tabs to a single space
    $js = preg_replace('/[ \t]+/', ' ', $js);
    // Collapse 3+ consecutive newlines to one
    $js = preg_replace('/\n{3,}/', "\n\n", $js);
    // Trim leading/trailing whitespace per line
    $js = preg_replace('/^[ \t]+|[ \t]+$/m', '', $js);
    return trim($js);
}
