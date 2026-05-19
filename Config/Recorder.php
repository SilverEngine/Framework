<?php

/*
|--------------------------------------------------------------------------
| Request Recorder (debug profiler)
|--------------------------------------------------------------------------
| Telescope/Nightwatch-style capture. When debug is on, each request's
| full lifecycle timeline is persisted to Storage/debug/recordings/ so it
| can be reviewed later in /debug?tab=recordings — including the phases
| the live /debug page cannot show about itself (controller action,
| view render, response sent).
|
| Accessed via Env::get('recorder.*').
*/

return [

    // Master switch (also requires APP debug to be on).
    'enabled' => true,

    // Ring buffer: keep only the newest N recordings, prune the rest.
    'limit' => 50,

    // Request paths whose prefix matches any of these are NOT recorded.
    'ignore' => [
        '/debug',
        '/build',
        '/favicon',
        '/robots',
    ],
];
