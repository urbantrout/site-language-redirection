<?php
declare(strict_types = 1);
namespace UrbanTrout\SiteLanguageRedirection\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RedirectionMiddleware implements MiddlewareInterface
{
    /**
     * Adds an instance of TYPO3\CMS\Core\Http\NormalizedParams as
     * attribute to $request object
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookieName = 'site-language-preference';

        $response = $this->setCookieOnLanguageChange($request, $handler, $cookieName);
        if ($response) {
            return $response;
        }

        $response = $this->getRedirectResponseIfCookieIsSet($request, $cookieName);
        if ($response) {
            return $response;
        }

        $response = $this->getRedirectResponseByBrowserLanguage($request, $cookieName);
        if ($response) {
            return $response;
        }

        $response = $handler->handle($request);
        return $response;
    }

    /**
     * Returns redirect response based on users browser language.
     *
     * @param ServerRequestInterface $request
     * @param string $cookieName
     *
     * @return ResponseInterface|null
     */
    protected function getRedirectResponseByBrowserLanguage(ServerRequestInterface $request, $cookieName)
    {
        // Do not redirect if preferred language is set as cookie.
        if (array_key_exists($cookieName, $request->getCookieParams())) {
            return null;
        }

        /** @var Site $site */
        $site = $request->getAttribute('site');
        /** @var PageArguments $pageArguments */
        $pageArguments = $request->getAttribute('routing');
        /** @var SiteLanguage $requestLanguage */
        $requestLanguage = $request->getAttribute('language');
        /** @var SiteLanguage[] $siteLanguages */
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
            // Do not redirect if no accept languages are set.
            return null;
        }

        /** @var SiteLanguage[] $matchingSiteLanguages */
        $matchingSiteLanguages = array_filter(
            $siteLanguages,
            function ($language) use ($acceptLanguages) {
                /** @var SiteLanguage $language */
                return in_array($language->getTwoLetterIsoCode(), $acceptLanguages);
            }
        );

        // Do not redirect if language is not available.
        if (empty($matchingSiteLanguages)) {
            return null;
        }
        /** @var SiteLanguage $language */
        $language = array_shift($matchingSiteLanguages);

        // Do not redirect if the page is already requested in the correct language
        if ($language === $requestLanguage) {
            return null;
        }

        $uri = $site->getRouter()->generateUri(
            $pageArguments->getPageId(),
            ['_language' => $language]
        );

        return new RedirectResponse($uri, 302);
    }

    /**
     * Sets cookie with preferred language when user changes language in the frontend.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @param string $cookieName
     *
     * @return ResponseInterface|null
     */
    protected function setCookieOnLanguageChange(ServerRequestInterface $request, RequestHandlerInterface $handler, $cookieName): ?ResponseInterface
    {
        /** @var Site $site */
        $site = $request->getAttribute('site');
        /** @var SiteLanguage $requestLanguage */
        $requestLanguage = $request->getAttribute('language');
        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        /** @var SiteLanguage[] $siteLanguages */
        $siteLanguages = $site->getLanguages();

        if ($normalizedParams->getHttpReferer()) {
            /** @var Uri $refererUri */
            $refererUri = GeneralUtility::makeInstance(Uri::class, $normalizedParams->getHttpReferer());
            /** @var string[] $siteLanguageBasePaths */
            $siteLanguageBasePaths = array_filter(array_map(function ($language) {
                /** @var SiteLanguage $language */
                return $language->getBase()->getPath();
            }, $siteLanguages), function ($language) {
                return $language !== '/';
            });

            /** @var Response $response */
            $response = $handler->handle($request);
            if (
                strpos($refererUri->getPath(), $requestLanguage->getBase()->getPath()) === false ||
                strpos($refererUri->getPath(), $requestLanguage->getBase()->getPath()) !== 0 ||
                ($requestLanguage->getBase()->getPath() === '/' && !in_array($requestLanguage->getBase()->getPath(), $siteLanguageBasePaths))
            ) {
                $response = $response->withAddedHeader('Set-Cookie', $cookieName . '=' . $requestLanguage->getLanguageId() . '; Path=/; Max-Age=' . (time()+60*60*24*30));
            }
            return $response;
        }
        return null;
    }

    /**
     * Returns redirect response for the requested page in correct translation of the site if cookie is set and user visits the "wrong" translation.
     *
     * @param ServerRequestInterface $request
     * @param string $cookieName
     *
     * @return ResponseInterface|null
     */
    protected function getRedirectResponseIfCookieIsSet(ServerRequestInterface $request, $cookieName): ?ResponseInterface
    {
        /** @var Site $site */
        $site = $request->getAttribute('site');
        /** @var PageArguments $pageArguments */
        $pageArguments = $request->getAttribute('routing');
        /** @var SiteLanguage $requestLanguage */
        $requestLanguage = $request->getAttribute('language');

        if (array_key_exists($cookieName, $request->getCookieParams())) {
            /** @var int[] $availableLanguageIds */
            $availableLanguageIds = array_map(function ($language): int {
                return (int)$language->getLanguageId();
            }, $site->getLanguages());
            $languageId = (int)$request->getCookieParams()[$cookieName];

            if (in_array($languageId, $availableLanguageIds)) {
                $preferredSiteLanguage = $site->getLanguageById($languageId);

                if ($preferredSiteLanguage !== $requestLanguage) {
                    $uri = $site->getRouter()->generateUri(
                        $pageArguments->getPageId(),
                        ['_language' => $preferredSiteLanguage]
                    );
                    return new RedirectResponse($uri, 302);
                }
            }
        }
        return null;
    }
}
