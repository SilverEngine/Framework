<?php
declare(strict_types=1);

/*
 * Localization (Silver\Support\Translator singleton; `trans()` /
 * `trans_choice()` helpers; locale switching via the
 * Silver\Http\Middleware\SetLocale route/global middleware).
 *
 * Line files: {path}/{locale}/{file}.php returning a nested array;
 * keys are "file.dot.path". The same directory layout the legacy
 * Lang class reads.
 *
 * (Replaces the old config of the same name, which assigned $lang
 * without returning it — dead since nothing could read it.)
 */
return [
    // Relative paths resolve against the project root.
    'path' => 'storage/Lang',

    'locale'   => env('APP_LOCALE', 'en'),
    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    // null = any locale allowed; a list gates ?lang= / session /
    // Accept-Language switching (unknown locales are ignored).
    'supported' => null,
];
