<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSP (Sehat Sahulat Program) module
    |--------------------------------------------------------------------------
    |
    | Set SSP=true in .env to show the SSP / Insurance Claims report,
    | SSP=false to hide it (the report route is not registered either).
    |
    */
    'ssp' => (bool) env('SSP', true),

];
