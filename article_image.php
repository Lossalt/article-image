<?php
/**
 * article-image: named WordPress article image redirect.
 *
 *   /article_image.php?res=cover-01   → 302 to {base_url}/cover-01.webp
 *   /article_image.php?res=cover-01&json → JSON metadata
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

function ai_normalize_key(): ?string
{
    $res = $_GET['res'] ?? null;
    if (!is_string($res)) {
        return null;
    }

    // Reject raw control chars early.
    if (preg_match('/[\x00-\x1F\x7F]/', $res)) {
        return null;
    }

    $cfg = ai_config();
    $pattern = $cfg['key_pattern'] ?? '/^[A-Za-z0-9_-]{1,64}$/';
    if (!is_string($pattern) || !preg_match($pattern, $res)) {
        return null;
    }

    return $res;
}

function ai_build_url(string $key): string
{
    $cfg = ai_config();
    $base = rtrim((string) ($cfg['base_url'] ?? ''), '/');
    $ext = (string) ($cfg['ext'] ?? 'webp');
    $ext = ltrim($ext, '.');
    return $base . '/' . rawurlencode($key) . '.' . $ext;
}

function ai_local_path(string $key): ?string
{
    $cfg = ai_config();
    $dir = (string) ($cfg['local_dir'] ?? '');
    if ($dir === '') {
        // Default: sibling folder article_images/ next to this script.
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'article_images';
    } elseif (!preg_match('#^(?:[A-Za-z]:)?[\\\\/]#', $dir) && !str_starts_with($dir, '..')) {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . $dir;
    }

    $ext = ltrim((string) ($cfg['ext'] ?? 'webp'), '.');
    $file = $dir . DIRECTORY_SEPARATOR . $key . '.' . $ext;
    $realDir = realpath($dir);
    if ($realDir === false) {
        return null;
    }
    $realFile = realpath($file);
    if ($realFile === false || !str_starts_with($realFile, $realDir)) {
        return null;
    }
    return $realFile;
}

function ai_handle(): void
{
    $key = ai_normalize_key();
    if ($key === null) {
        ai_fail(400, '参数错误。', 'Parameter Error.');
        return;
    }

    $cfg = ai_config();
    $url = ai_build_url($key);

    if (!empty($cfg['check_local'])) {
        $local = ai_local_path($key);
        if ($local === null || !is_file($local)) {
            ai_fail(404, '图片不存在。', 'Image not found.');
            return;
        }
    }

    if (ai_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Origin: *');
        echo json_encode([
            'key' => $key,
            'url' => $url,
            'ext' => ltrim((string) ($cfg['ext'] ?? 'webp'), '.'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return;
    }

    header('Cache-Control: no-store');
    header('X-Article-Image-Key: ' . $key);
    header('Location: ' . $url, true, 302);
    if (ai_is_head()) {
        exit;
    }
    exit;
}

ai_handle();
