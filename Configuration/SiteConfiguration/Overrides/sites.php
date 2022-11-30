<?php

$GLOBALS['SiteConfiguration']['site']['columns']['SiteLanguageRedirectionMethod'] = [
    'label' => 'Redirect Method',
    'description' => 'Which method should be used to redirect users?',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => [
            [ 'Browser language (HTTP headers)', 1 ],
            [ 'IP address', 2 ],
        ],
        'default' => 1,
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['SiteLanguageMaxmindLicenseKey'] = [
    'label' => 'Maxmind license key Method',
    'description' => 'Enter license key for Maxmind GeoLite2-Country database when using IP address',
    'config' => [
        'type' => 'input'
    ],
];

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] = $GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] . ',--div--;Site Language Redirection,SiteLanguageRedirectionMethod,SiteLanguageMaxmindLicenseKey';
