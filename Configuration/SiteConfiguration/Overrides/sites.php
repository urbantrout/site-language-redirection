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

$GLOBALS['SiteConfiguration']['site']['columns']['SiteLanguageRedirectionDisable'] = [
    'label' => 'Disable Redirect',
    'description' => 'Disable the language redirect for this site.',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 0,
    ],
];

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] = $GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] . ',--div--;Site Language Redirection,SiteLanguageRedirectionDisable,SiteLanguageRedirectionMethod';
