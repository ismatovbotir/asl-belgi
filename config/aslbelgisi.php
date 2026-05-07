<?php

return [
    'base_url'      => env('ASLBELGISI_BASE_URL', 'https://xtrace.stage.aslbelgisi.uz'),
    'api_key'  => env('ASLBELGISI_API_KEY'),
    'tin'      => env('ASLBELGISI_TIN'),
    'timeout'  => env('ASLBELGISI_TIMEOUT', 30),
    'retry_times'   => 3,
    'retry_sleep'   => 1000,
];
