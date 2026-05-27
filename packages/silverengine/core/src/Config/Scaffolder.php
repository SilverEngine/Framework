<?php

/*
|--------------------------------------------------------------------------
| Scaffolder (dev-only page generator)
|--------------------------------------------------------------------------
| Drives the /new page and the POST /__silver/scaffold endpoint. Both are
| additionally gated by APP_ENV=local + APP_DEBUG=true — disabling them in
| any other environment is automatic. Override in config/Scaffolder.php to
| change the route, or set 'enabled' => false to free up /new for an actual
| application page.
|
| Accessed via Env::get('scaffolder.*').
*/

return [

    // Master switch. Even when true, the routes only mount under
    // APP_ENV=local + APP_DEBUG=true.
    'enabled' => true,

    // Path the scaffolder UI is mounted at. Change this if your app needs
    // /new for its own purposes.
    'route' => '/new',
];
