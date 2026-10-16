<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attachment Disk
    |--------------------------------------------------------------------------
    |
    | Attachments are stored on a private disk so their object keys are never
    | publicly addressable. Downloads are served only through an authorized
    | controller action (SRS §0.27 file security).
    |
    */

    'disk' => env('ATTACHMENT_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size (KB)
    |--------------------------------------------------------------------------
    |
    | The largest accepted attachment, enforced alongside the content-signature
    | and extension allow-list checks.
    |
    */

    'max_size_kb' => (int) env('ATTACHMENT_MAX_SIZE_KB', 10240),

    /*
    |--------------------------------------------------------------------------
    | Allowed Extensions
    |--------------------------------------------------------------------------
    |
    | Only these extensions may be uploaded. Validation is content-signature
    | based, so a file's detected type must map to one of these extensions.
    |
    */

    'allowed_extensions' => [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
        'csv', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'zip',
    ],

];
