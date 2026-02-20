<?php
declare(strict_types=1);


$uri   = $_SERVER['REQUEST_URI'] ?? '/';
$asset = $_GET['asset'] ?? '';

if ($asset === '') {
  if (preg_match('#/robots\.txt$#', $uri))  $asset = 'robots';
  if (preg_match('#/sitemap\.xml$#', $uri)) $asset = 'sitemap';
}

if ($asset === 'robots') {
  header('Content-Type: text/plain; charset=utf-8');
  $base = canonical_base();
  echo "User-agent: *\n";
  echo "Allow: /\n";
  echo "Sitemap: {$base}sitemap.xml\n";
  exit;
}

if ($asset === 'sitemap') {
  header('Content-Type: application/xml; charset=utf-8');
  $base  = canonical_base();
  $today = gmdate('Y-m-d');

  echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
  echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
  echo "  <url>\n";
  echo "    <loc>" . xml($base) . "</loc>\n";
  echo "    <lastmod>{$today}</lastmod>\n";
  echo "    <changefreq>weekly</changefreq>\n";
  echo "    <priority>1.0</priority>\n";
  echo "  </url>\n";
  echo "</urlset>\n";
  exit;
}

header('Content-Type: text/html; charset=utf-8');

$templateFile = __DIR__ . '/005-plantillaSEO.html';
$jsonFile     = __DIR__ . '/datos.json';

if (!is_file($templateFile)) {
  http_response_code(500);
  echo "ERROR: Missing template file: 005-plantillaSEO.html";
  exit;
}
if (!is_file($jsonFile)) {
  http_response_code(500);
  echo "ERROR: Missing json file: datos.json";
  exit;
}

$template = file_get_contents($templateFile);
if ($template === false) {
  http_response_code(500);
  echo "ERROR: Cannot read 005-plantillaSEO.html";
  exit;
}

$raw = file_get_contents($jsonFile);
if ($raw === false) {
  http_response_code(500);
  echo "ERROR: Cannot read datos.json";
  exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(500);
  echo "ERROR: Invalid JSON in datos.json";
  exit;
}

if (empty($data['canonical'])) {
  $data['canonical'] = canonical_base();
}

$out = apply_placeholders($template, $data);

$out = preg_replace('/\{\{[^}]+\}\}/', '', $out) ?? $out;

$etag = '"' . sha1($out) . '"';
header('ETag: ' . $etag);
( !headers_sent() ) && header('Cache-Control: public, max-age=300');

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
if (etag_matches($ifNoneMatch, $etag)) {
  http_response_code(304);
  exit;
}

echo $out;


function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xml(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function canonical_base(): string {
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $host = preg_replace('/\s+/', '', $host) ?: 'localhost';
  $host = preg_replace('/[^a-zA-Z0-9\.\-\:\[\]]/', '', $host);
  return 'https://' . $host . '/';
}

function starts_with(string $haystack, string $needle): bool {
  return $needle === '' || strpos($haystack, $needle) === 0;
}

function apply_placeholders(string $template, array $data): string {
  foreach ($data as $key => $value) {
    if (is_array($value) || is_object($value)) continue;

    $k = (string)$key;
    $v = (string)$value;

    if (starts_with($k, 'raw:')) {
      $realKey = substr($k, 4);
      $template = str_replace('{{' . $realKey . '}}', $v, $template);
    } else {
      $template = str_replace('{{' . $k . '}}', e($v), $template);
    }
  }
  return $template;
}

function etag_matches(string $ifNoneMatch, string $etag): bool {
  if ($ifNoneMatch === '') return false;
  $parts = array_map('trim', explode(',', $ifNoneMatch));
  foreach ($parts as $p) {
    $p = preg_replace('#^W/#', '', $p);
    if ($p === $etag) return true;
  }
  return false;
}
