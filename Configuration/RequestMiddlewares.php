<?php
declare(strict_types = 1);
use UrbanTrout\SiteLanguageRedirection\Middleware\RedirectionMiddleware;

return [
    'frontend' => [
        'urbantrout/site-language-redirection' => [
            'target' => RedirectionMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
