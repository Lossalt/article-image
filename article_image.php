<?php
/**
 * article-image: named WordPress article image redirect.
 *
 *   /article_image.php?res=cover-01        → 302 to {base_url}/cover-01.webp (or detected ext)
 *   /article_image.php?res=cover-01.jpg    → 302 to {base_url}/cover-01.jpg
 *   /article_image.php?res=cover-01&json   → JSON metadata
 */

declare(strict_types=1);

const AI_CONFIG_FILE = __DIR__ . '/config.php';

function ai_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = is_file(AI_CONFIG_FILE)
            ? require AI_CONFIG_FILE
            : [];
    }
    return $config;
}

function ai_wants_json(): bool
{
    if (isset($_GET['json'])) {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json') && !str_contains($accept, 'image/');
}

function ai_is_head(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
}

function ai_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ai_fail(int $code, string $messageZh, string $messageEn): void
{
    http_response_code($code);
    $cfg = ai_config();
    $note = (bool) ($cfg['show_mercy_note'] ?? true);

    if (ai_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $payload = [
            'error' => $messageEn,
            'error_zh' => $messageZh,
        ];
        if ($note) {
            $payload['note'] = 'This is a hobby site. Please be kind.';
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    if (ai_is_head()) {
        exit;
    }

    $html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . ai_escape($messageZh) . '</title></head><body>'
        . '<p>' . ai_escape($messageZh) . '</p>'
        . '<p>' . ai_escape($messageEn) . '</p>';
    if ($note) {
        $html .= '<hr><p>您好，看到这个页面说明您应该是一名相关从业者。这是一个业余爱好者的无名小站，请手下留情!</p>'
            . '<p>Hello, seeing this page shows that you should be a relevant practitioner. This is an anonymous site for an amateur. Please be merciful!</p>';
    }
    $html .= '</body></html>';
    echo $html;
}

/**
 * @return list<string>
 */
function ai_allowed_exts(): array
{
    $cfg = ai_config();
    $exts = $cfg['allowed_exts'] ?? ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif'];
    if (!is_array($exts) || $exts === []) {
        $exts = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif'];
    }

    $out = [];
    foreach ($exts as $ext) {
        $ext = strtolower(ltrim((string) $ext, '.'));
        if ($ext !== '') {
            $out[] = $ext;
        }
    }
    return $out;
}

function ai_default_ext(): string
{
    $cfg = ai_config();
    $ext = strtolower(ltrim((string) ($cfg['default_ext'] ?? 'webp'), '.'));
    $allowed = ai_allowed_exts();
    return in_array($ext, $allowed, true) ? $ext : ($allowed[0] ?? 'webp');
}

function ai_is_allowed_ext(string $ext): bool
{
    return in_array(strtolower(ltrim($ext, '.')), ai_allowed_exts(), true);
}

/**
 * Parse ?res= into [basename, ext|null].
 *
 * @return array{0:string,1:?string}|null
 */
function ai_parse_res(): ?array
{
    $res = $_GET['res'] ?? null;
    if (!is_string($res)) {
        return null;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $res)) {
        return null;
    }

    $cfg = ai_config();
    $pattern = $cfg['key_pattern'] ?? '/^[A-Za-z0-9_-]{1,64}(\.[A-Za-z0-9]{1,8})?$/';
    if (!is_string($pattern) || !preg_match($pattern, $res)) {
        return null;
    }

    $dot = strrpos($res, '.');
    if ($dot === false || $dot === 0) {
        return [$res, null];
    }

    $base = substr($res, 0, $dot);
    $ext = substr($res, $dot + 1);
    if ($base === '' || $ext === '') {
        return null;
    }
    if (!ai_is_allowed_ext($ext)) {
        return null;
    }

    return [$base, strtolower($ext)];
}

function ai_image_dir(): string
{
    $cfg = ai_config();
    $dir = (string) ($cfg['local_dir'] ?? '');
    if ($dir === '') {
        return __DIR__ . DIRECTORY_SEPARATOR . 'article_images';
    }
    if (!preg_match('#^(?:[A-Za-z]:)?[\\\\/]#', $dir) && !str_starts_with($dir, '..')) {
        return __DIR__ . DIRECTORY_SEPARATOR . $dir;
    }
    return $dir;
}

/**
 * Find an existing local file for basename, optionally forcing one ext.
 */
function ai_find_local(string $base, ?string $ext): ?array
{
    $dir = ai_image_dir();
    $realDir = realpath($dir);
    if ($realDir === false) {
        return null;
    }

    $candidates = $ext === null ? ai_allowed_exts() : [$ext];
    foreach ($candidates as $try) {
        $file = $realDir . DIRECTORY_SEPARATOR . $base . '.' . $try;
        $realFile = realpath($file);
        if ($realFile !== false
            && str_starts_with($realFile, $realDir)
            && is_file($realFile)
        ) {
            return ['path' => $realFile, 'ext' => $try, 'file' => $base . '.' . $try];
        }
    }
    return null;
}

/**
 * Resolve final redirect target.
 *
 * @return array{key:string,base:string,ext:string,url:string,file:string,source:string}|null
 */
function ai_resolve(string $base, ?string $ext): ?array
{
    $cfg = ai_config();
    $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');

    // Prefer a real local file when possible (correct ext even in redirect mode).
    $local = ai_find_local($base, $ext);
    if ($local !== null) {
        return [
            'key' => $ext === null ? $base : $base . '.' . $ext,
            'base' => $base,
            'ext' => $local['ext'],
            'url' => $baseUrl . '/' . rawurlencode($base) . '.' . $local['ext'],
            'file' => $local['file'],
            'source' => 'local',
        ];
    }

    if (!empty($cfg['check_local'])) {
        return null;
    }

    $useExt = $ext ?? ai_default_ext();
    return [
        'key' => $ext === null ? $base : $base . '.' . $ext,
        'base' => $base,
        'ext' => $useExt,
        'url' => $baseUrl . '/' . rawurlencode($base) . '.' . $useExt,
        'file' => $base . '.' . $useExt,
        'source' => 'default-ext',
    ];
}

function ai_handle(): void
{
    $parsed = ai_parse_res();
    if ($parsed === null) {
        ai_fail(400, '参数错误。', 'Parameter Error.');
        return;
    }

    [$base, $ext] = $parsed;
    $pick = ai_resolve($base, $ext);
    if ($pick === null) {
        ai_fail(404, '图片不存在。', 'Image not found.');
        return;
    }

    if (ai_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Origin: *');
        echo json_encode([
            'key' => $pick['key'],
            'base' => $pick['base'],
            'url' => $pick['url'],
            'ext' => $pick['ext'],
            'file' => $pick['file'],
            'source' => $pick['source'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return;
    }

    header('Cache-Control: no-store');
    header('X-Article-Image-Key: ' . $pick['base']);
    header('X-Article-Image-Ext: ' . $pick['ext']);
    header('Location: ' . $pick['url'], true, 302);
    if (ai_is_head()) {
        exit;
    }
    exit;
}

ai_handle();
