<?php
/**
 * article-image config.
 * Edit these values for your own site.
 */

declare(strict_types=1);

return [
    // Image files base (redirect target prefix, no trailing slash).
    // Same-server example: '/wp-content/removeable/article_images'
    // Remote example:      'https://cjsy.cc/wp-content/removeable/article_images'
    'base_url' => 'https://cjsy.cc/wp-content/removeable/article_images',

    // Local directory (relative to this project) used when check_local = true
    // or when auto-detecting the extension.
    // Leave '' to use ./article_images next to this script.
    'local_dir' => '',

    // If true and local files exist, 404 when the image is missing.
    // If false, always redirect (WordPress/remote style).
    'check_local' => false,

    // Allowed image name after ?res=
    //   cover-01        → auto ext (see allowed_exts / default_ext)
    //   cover-01.jpg    → explicit ext
    'key_pattern' => '/^[A-Za-z0-9_-]{1,64}(\.[A-Za-z0-9]{1,8})?$/',

    // Extensions we accept. First hit wins when auto-detecting locally.
    'allowed_exts' => ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif'],

    // Used when the request has no extension and auto-detect finds nothing
    // (typical remote / check_local = false deployment).
    'default_ext' => 'webp',

    // Show the friendly bilingual notice on bad input
    'show_mercy_note' => true,
];
