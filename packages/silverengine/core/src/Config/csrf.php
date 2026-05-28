<?php

return [
    'cookie_name'  => 'XSRF-TOKEN',
    'header_names' => ['X-XSRF-TOKEN', 'X-CSRF-TOKEN'],
    'field_name'   => '_token',
    /** fnmatch patterns matched against the request URI; skip verification on hit. */
    'except'       => [],
];
