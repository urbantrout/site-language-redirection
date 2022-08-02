<?php
declare(strict_types = 1);
namespace UrbanTrout\SiteLanguageRedirection\Middleware;

use GeoIp2\Database\Reader;
use GeoIp2\Model\Country;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
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
    const REDIRECT_METHOD_BROWSER = 1;
    const REDIRECT_METHOD_IPADDRESS = 2;

    /**
     * @var string
     */
    protected $botPattern = '/bot|google|baidu|bing|msn|teoma|slurp|yandex|Chrome-Lighthouse/i';

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
        /**
         * TODO: add setting for cookieName.
         */
        $cookieName = 'site-language-preference';

		// stop if somewhere in our rootline tx_sitelanguageredirection_stop has been set
		$pageArguments = $request->getAttribute('routing');
		$rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageArguments->getPageId());
		/** @var RootlineUtility $rootlineUtility */
		$pageRootline = $rootlineUtility->get();
		foreach ($pageRootline as $pageItem) {
			if (isset($pageItem['tx_sitelanguageredirection_stop']) && $pageItem['tx_sitelanguageredirection_stop'] === 1) {
				return $handler->handle($request);
			}
		}

        /**
         * Do not redirect search engine bots.
         */
        if ($this->isBot($request)) {
            return $handler->handle($request);
        }

        $response = $this->setCookieOnLanguageChange($request, $handler, $cookieName);
        if ($response) {
            return $response;
        }

        $response = $this->getRedirectResponseIfCookieIsSet($request, $cookieName);
        if ($response) {
            return $response;
        }

        /** @var Site $site */
        $site = $request->getAttribute('site');
        // Set default method in case site configuration isn't yet updated.
        $method = $site->getConfiguration()['SiteLanguageRedirectionMethod'] ?? self::REDIRECT_METHOD_BROWSER;

        if ($method === self::REDIRECT_METHOD_BROWSER) {
            $response = $this->getRedirectResponseByBrowserLanguage($request, $cookieName);
        }
        if ($method === self::REDIRECT_METHOD_IPADDRESS) {
            $response = $this->getRedirectResponseByIPAddress($request, $cookieName);
        }

        if ($response) {
            return $response;
        }

        $response = $handler->handle($request);
        return $response;
    }

    /**
     * Returns redirect response based on users browser language.
     * Sets the cookie for 30 days.
     *
     * @param ServerRequestInterface $request
     * @param string $cookieName
     *
     * @return ResponseInterface|null
     */
    protected function getRedirectResponseByBrowserLanguage(ServerRequestInterface $request, $cookieName): ?ResponseInterface
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

        $siteLanguagesFallbacks = [];
        if (is_array($site->getConfiguration()['SiteLanguageRedirectionFallbacks'] ?? null) && !empty($site->getConfiguration()['SiteLanguageRedirectionFallbacks'])) {
            $siteLanguagesFallbacks = $site->getConfiguration()['SiteLanguageRedirectionFallbacks'];
        }

        $acceptLanguages = $request->getHeader('accept-language');
        if (!empty($acceptLanguages)) {
            $acceptLanguages = array_unique(
                array_map(function ($language) {
                    return strtolower(explode(';', $language)[0]);
                }, explode(',', $acceptLanguages[0]))
            );
        } else {
            // Do not redirect if no accept languages are set.
            return null;
        }

        /** @var array $acceptLanguagesWithFallbacks */
        $acceptLanguagesWithFallbacks = array_reduce($acceptLanguages, function ($accumulator, $item) {
            array_push($accumulator, $item);

            // Adds an additional entry to the array if $item looks like 'de-AT'.
            // Redirects if browser language is 'de-AT' and site languages are 'de' and 'en'.
            preg_match('/(.{2})\-/', $item, $matches);
            if ($matches) {
                array_push($accumulator, $matches[1]);
            }
            return $accumulator;
        }, []);

        /** @var array $matchingSiteLanguageCodes Array in the form of: ['de-at', 'de', 'en'] */
        $matchingSiteLanguageCodes = array_filter(
            $acceptLanguagesWithFallbacks,
            function ($language) use ($siteLanguages, $siteLanguagesFallbacks) {
                return in_array(
                    $language,
                    array_merge(
                        array_map(function ($siteLanguage) {
                            /** @var SiteLanguage $siteLanguage */
                            return strtolower($siteLanguage->getHreflang());
                        }, $siteLanguages),
                        array_map(function ($siteLanguage) {
                            /** @var SiteLanguage $siteLanguage */
                            return $siteLanguage->getTwoLetterIsoCode();
                        }, $siteLanguages),
                        array_map(function ($siteLanguagesFallback) {
                            return $siteLanguagesFallback;
                        }, array_keys($siteLanguagesFallbacks))
                    )
                );
            }
        );

        /** @var SiteLanguage[] $matchingSiteLanguages */
        $matchingSiteLanguages = array_map(function ($item) {
            return array_shift($item);
        }, array_map(function ($matchingSiteLanguageCode) use ($siteLanguages, $siteLanguagesFallbacks) {
            return array_filter($siteLanguages, function ($siteLanguage) use ($matchingSiteLanguageCode, $siteLanguagesFallbacks) {
                /** @var SiteLanguage $siteLanguage */
                return strtolower($siteLanguage->getHreflang()) === $matchingSiteLanguageCode || $siteLanguage->getTwoLetterIsoCode() === $matchingSiteLanguageCode || strtolower($siteLanguage->getHreflang()) === $siteLanguagesFallbacks[$matchingSiteLanguageCode] || $siteLanguage->getTwoLetterIsoCode() === $siteLanguagesFallbacks[$matchingSiteLanguageCode];
            });
        }, $matchingSiteLanguageCodes));

        // Do not redirect if language is not available.
        if (empty($matchingSiteLanguages)) {
            return null;
        }
        /** @var SiteLanguage $matchingSiteLanguage */
        $matchingSiteLanguage = array_shift($matchingSiteLanguages);

        // Do not redirect if the page is already requested in the correct language
        if ($matchingSiteLanguage === $requestLanguage) {
            return null;
        }

        /** @var array $parameters Array in the form of: ['utm_source' => 'google', 'utm_medium' => 'social'] */
        $parameters = $request->getQueryParams();

        $parameters['_language'] = $matchingSiteLanguage;

        $uri = $site->getRouter()->generateUri(
            $pageArguments->getPageId(),
            $parameters
        );

        /** @var RedirectResponse $response */

        $response = new RedirectResponse($uri, 307);
        return $response->withAddedHeader('Set-Cookie', $cookieName . '=' . $matchingSiteLanguage->getLanguageId() . '; Path=/; Max-Age=' . (60*60*24*30));
    }

    /**
     * Returns redirect response based on users IP address. GeoIP2 is used to
     * get the country based on the visitors IP address. Sets the cookie for 30 days.
     *
     * @param ServerRequestInterface $request
     * @param string $cookieName
     *
     * @return ResponseInterface|null
     */
    protected function getRedirectResponseByIPAddress(ServerRequestInterface $request, $cookieName): ?ResponseInterface
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

        $path = Environment::getVarPath() . '/sitelanguageredirection/';
        $filename = 'GeoLite2-Country.mmdb';

        $reader = new Reader($path . $filename);
        try {
            $ipAddress = $request->getAttribute('normalizedParams')->getRemoteAddress();

            /** @var Country $country */
            $country = $reader->country($ipAddress);
            $geocodedIsoCode = $country->country->isoCode;

            /** @var SiteLanguage[] $matchingSiteLanguageCodes */
            $matchingSiteLanguages = array_filter(
                $siteLanguages,
                function ($siteLanguage) use ($geocodedIsoCode) {

                    /**
                     * Only use last 2 characters of hreflang (de-at, en, en-uk).
                     *
                     * TODO: Use something like static_info_tables to map countries to languages.
                     */
                    $siteLanguageIsoCode = strtoupper(substr($siteLanguage->getHreflang(), -2));
                    return $siteLanguageIsoCode === $geocodedIsoCode;
                }
            );

            // Do not redirect if language is not available.
            if (empty($matchingSiteLanguages)) {
                return null;
            }

            /** @var SiteLanguage $matchingSiteLanguage */
            $matchingSiteLanguage = array_shift($matchingSiteLanguages);

            // Do not redirect if the page is already requested in the correct language
            if ($matchingSiteLanguage === $requestLanguage) {
                return null;
            }

            /** @var array $parameters Array in the form of: ['utm_source' => 'google', 'utm_medium' => 'social'] */
            $parameters = $request->getQueryParams();

            $parameters['_language'] = $matchingSiteLanguage;

            $uri = $site->getRouter()->generateUri(
                $pageArguments->getPageId(),
                $parameters
            );

            /** @var RedirectResponse $response */
            $response = new RedirectResponse($uri, 307);
            return $response->withAddedHeader('Set-Cookie', $cookieName . '=' . $matchingSiteLanguage->getLanguageId() . '; Path=/; Max-Age=' . (60*60*24*30));
        } catch (\Throwable $e) {
            // IP address is not in database. Do not redirect.
            return null;
        }
    }

    /**
     * Sets cookie with preferred language when user changes language in the frontend for 30 days.
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

        // Check if referrer is present and scheme is not `android-app`.
        if ($normalizedParams->getHttpReferer() && substr($normalizedParams->getHttpReferer(), 0, 11) !== 'android-app') {
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
                $response = $response->withAddedHeader('Set-Cookie', $cookieName . '=' . $requestLanguage->getLanguageId() . '; Path=/; Max-Age=' . (60*60*24*30));
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
                    /** @var array $parameters Array in the form of: ['utm_source' => 'google', 'utm_medium' => 'social'] */
                    $parameters = $request->getQueryParams();

                    $parameters['_language'] = $preferredSiteLanguage;

                    $uri = $site->getRouter()->generateUri(
                        $pageArguments->getPageId(),
                        $parameters
                    );
                    return new RedirectResponse($uri, 307);
                }
            }
        }
        return null;
    }

    /**
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function isBot(ServerRequestInterface $request): bool
    {
        $userAgent = $request->getHeader('user-agent');

        if (is_array($userAgent) && !empty($userAgent)) {
            $userAgent = array_shift($userAgent);
        }

        return is_string($userAgent) && preg_match($this->botPattern, $userAgent);
    }
}
