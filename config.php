<?php
/**
 * article-image config.
 * Edit these values for your own site.
 */

declare(strict_types=1);

return [
    // Where the .webp files live (redirect target base, no trailing slash).
    // Same-server example: '/wp-content/removeable/article_images'
    // Remote example:      'https://cjsy.cc/wp-content/removeable/article_images'
    'base_url' => 'https://cjsy.cc/wp-content/removeable/article_images',

    // Local directory (relative to this project) used when check_local = true.
    // Leave '' when the PHP file is dropped next to the image folder on WP.
    'local_dir' => '',

    // If true and local_dir exists, 404 when the file is missing.
    // If false, always redirect (WordPress/remote style).
    'check_local' => false,

    // Image key charset after ?res=
    // Allows: letters, digits, _ and -
    'key_pattern' => '/^[A-Za-z0-9_-]{1,64}$/',

    // Extension appended to the key
    'ext' => 'webp',

    // Show the friendly bilingual notice on bad input
    'show_mercy_note' => true,
];
