<?php

/*
|--------------------------------------------------------------------------
| PSR-4 autoload mapping
|--------------------------------------------------------------------------
| Prefix → directory pairs consumed by Bootstrap/Autoload.php. The custom
| autoloader runs *before* Composer's, so anything resolvable here skips
| Composer entirely. Composer's autoload still fires for everything else
| (vendor/, package src/).
|
| Accessed via Env::get('autoload').
*/

return [
    'App\\Local' => 'app/Local',
    'App'        => 'App',
    'Database'   => 'Database',
];
