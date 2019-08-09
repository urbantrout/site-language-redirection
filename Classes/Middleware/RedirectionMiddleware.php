<?php
declare(strict_types = 1);
namespace UrbanTrout\SiteLanguageRedirection\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class RedirectionMiddleware implements MiddlewareInterface
{
    /**
     * Adds an instance of TYPO3\CMS\Core\Http\NormalizedParams as
     * attribute to $request object
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Site $site */
        $site = $request->getAttribute('site');
        $siteLanguages = $site->getLanguages();
        $acceptLanguages = $request->getHeader('accept-language');

        if (!empty($acceptLanguages)) {
            $acceptLanguages = array_unique(
                array_map(function ($language) {
                    return explode('-', $language)[0];
                }, array_map(function ($language) {
                    return explode(';', $language)[0];
                }, explode(',', $acceptLanguages[0])))
            );
        } else {
            return $handler->handle($request);
        }

        /** @var SiteLanguage[] $matchingSiteLanguages */
        $matchingSiteLanguages = array_filter($siteLanguages, function ($language) use ($acceptLanguages) {
            /** @var SiteLanguage $language */
            return in_array($language->getTwoLetterIsoCode(), $acceptLanguages);
        });

        if (empty($matchingSiteLanguages)) {
            return $handler->handle($request);
        }
        /** @var SiteLanguage $language */
        $language = array_shift($matchingSiteLanguages);

        // Do not redirect if the page is already requested in the correct language
        if ($language === $request->getAttribute('language')) {
            return $handler->handle($request);
        }

        /** @var PageArguments $pageArguments */
        $pageArguments = $request->getAttribute('routing');
        $uri = $site->getRouter()->generateUri(
            $pageArguments->getPageId(),
            ['_language' => $language]
        );

        return new RedirectResponse($uri, 302, $request->getHeaders());
    }
}
