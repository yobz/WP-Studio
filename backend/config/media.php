<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk (see config/filesystems.php) that all media the
    | platform stores — uploads, WordPress-synced assets, future AI-generated
    | images — is written to. Deliberately independent of FILESYSTEM_DISK
    | (the app's generic default) so media stays reachable at a public URL
    | regardless of what that default is used for elsewhere. Pointing this at
    | "s3" (or a future CDN-backed disk) is the only change a production
    | move to object storage requires.
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    */

    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 10240),

    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],

];
