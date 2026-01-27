<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | FFmpeg Binary Path
    |--------------------------------------------------------------------------
    |
    | The path to the ffmpeg binary on your system. If ffmpeg is in your
    | system PATH, you can leave this as the default value.
    |
    */
    'ffmpeg_binary' => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),

    /*
    |--------------------------------------------------------------------------
    | FFprobe Binary Path
    |--------------------------------------------------------------------------
    |
    | The path to the ffprobe binary on your system. FFprobe is used to
    | analyze media files (e.g., get duration, codecs, etc.).
    |
    */
    'ffprobe_binary' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for video thumbnail generation.
    |
    */
    'thumbnail' => [
        'width' => 640,
        'height' => 360,
        'quality' => 85,
    ],
];
