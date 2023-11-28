<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Site Language Redirection',
    'description' => 'PSR-15 middleware to redirect user to correct site language via HTTP headers (Accept-Language) and IP address.',
    'category' => 'misc',
    'shy' => 0,
    'version' => '2.4.0',
    'state' => 'stable',
    'uploadfolder' => 0,
    'clearCacheOnLoad' => 0,
    'author' => 'Harald Atteneder',
    'author_email' => 'harald@urbantrout.io',
    'author_company' => 'UT urban trout Software e.U.',
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-8.9.99',
            'typo3' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
