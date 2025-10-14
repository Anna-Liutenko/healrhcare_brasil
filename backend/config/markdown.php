<?php
/**
 * Markdown Renderer Configuration
 * Used by league/commonmark for safe HTML rendering
 */
return [
    // Strip dangerous HTML tags
    'html_input' => 'strip',

    // Block javascript: and data: URLs
    'allow_unsafe_links' => false,

    // Prevent DoS via deeply nested structures
    'max_nesting_level' => 10,

    // Renderer options
    'renderer' => [
        // Convert \n to <br>
        'soft_break' => "<br>\n",
    ],
];
