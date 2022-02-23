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
	protected $botPattern = '#(360Spider)|(Aboundex)|(AcoonBot)|(AddThis\.com)|(AhrefsBot)|(ia_archiver|alexabot|verifybot)|(alexa site audit)|(Amazonbot)|(Amazon[ -]Route ?53[ -]Health[ -]Check[ -]Service)|(AmorankSpider)|(ApacheBench)|(Applebot)|(AppSignalBot)|(Arachni)|(AspiegelBot)|(Castro 2, Episode Duration Lookup)|(Curious George)|(archive\.org_bot|special_archiver)|(Ask Jeeves/Teoma)|(Backlink-Check\.de)|(BacklinkCrawler)|(Baidu.*spider|baidu Transcoder)|(BazQux)|(Better Uptime Bot)|(MSNBot|msrbot|bingbot|BingPreview|msnbot-(UDiscovery|NewsBlogs)|adidxbot)|(Blekkobot)|(BLEXBot)|(Bloglovin)|(Blogtrottr)|(BoardReader Blog Indexer)|(BountiiBot)|(Browsershots)|(BUbiNG)|((?<!HTC)[ _]Butterfly/)|(CareerBot)|(CCBot)|(Cliqzbot)|(Cloudflare-AMP)|(CloudflareDiagnostics)|(CloudFlare-AlwaysOnline)|(coccoc.com)|(collectd)|(CommaFeed)|(CSS Certificate Spider)|(Datadog Agent)|(Datanyze)|(Dataprovider)|(Daum(oa)?[ /][0-9])|(Dazoobot)|(discobot)|(Domain Re-Animator Bot|support@domainreanimator.com)|(DotBot)|(DuckDuck(?:Go-Favicons-)?Bot)|(EasouSpider)|(eCairn-Grabber)|(EMail Exractor)|(evc-batch)|(Exabot|ExaleadCloudview)|(ExactSeek Crawler)|(Ezooms)|(facebookexternalhit|facebookplatform|facebookexternalua|facebookcatalog)|(Feedbin)|(FeedBurner)|(Feed Wrangler)|(Feedly)|(Feedspot)|(Fever/[0-9])|(FlipboardProxy|FlipboardRSS)|(Findxbot)|(FreshRSS)|(Genieo)|(GigablastOpenSource)|(Gluten Free Crawler)|(gobuster)|(ichiro/mobile goo)|(Storebot-Google)|(Google Favicon)|(Google Search Console)|(Google Page Speed Insights)|(google_partner_monitoring)|(Google-Cloud-Scheduler)|(Google-Structured-Data-Testing-Tool)|(GoogleStackdriverMonitoring)|(via ggpht\.com GoogleImageProxy)|(SeznamEmailProxy)|(Seznam-Zbozi-robot)|(Heurekabot-Feed)|(ShopAlike)|(AdsBot-Google|Adwords-(DisplayAds|Express|Instant)|Google Web Preview|Google[ -]Publisher[ -]Plugin|Google-(Ads-Qualify|Adwords|AMPHTML|Assess|HotelAdsVerifier|Read-Aloud|Shopping-Quality|Site-Verification|speakr|Test|Youtube-Links)|(APIs|DuplexWeb|Feedfetcher|Mediapartners)-Google|Googlebot|GoogleProducer|Google.*/\+/web/snippet)|(heritrix)|(HubSpot )|(HTTPMon)|(ICC-Crawler)|(inoreader.com)|(iisbot)|(ips-agent)|(IP-Guide\.com)|(k6/[0-9\.]+)|(kouio)|(larbin)|(([A-z0-9]*)-Lighthouse)|(linkdexbot|linkdex\.com)|(LinkedInBot)|(ltx71)|(Mail\.RU)|(magpie-crawler)|(MagpieRSS)|(masscan)|(Mastodon/)|(meanpathbot)|(MetaJobBot)|(MetaInspector)|(MixrankBot)|(MJ12bot)|(Mnogosearch)|(MojeekBot)|(munin)|(NalezenCzBot)|(check_http/v)|(nbertaupete95\(at\)gmail.com)|(Netcraft( Web Server Survey| SSL Server Survey|SurveyAgent))|(netEstate NE Crawler)|(Netvibes)|(NewsBlur .*(Fetcher|Finder))|(NewsGatorOnline)|(nlcrawler)|(Nmap Scripting Engine)|(Nuzzel)|(Octopus [0-9])|(omgili)|(OpenindexSpider)|(spbot)|(OpenWebSpider)|(OrangeBot|VoilaBot)|(PaperLiBot)|(phantomas/)|(phpservermon)|(PocketParser)|(PritTorrent)|(PRTG Network Monitor)|(psbot)|(Pingdom(?:\.com|TMS))|(Quora Link Preview)|(Quora-Bot)|(RamblerMail)|(QuerySeekerSpider)|(Qwantify)|(Rainmeter)|(redditbot)|(Riddler)|(rogerbot)|(ROI Hunter)|(SafeDNSBot)|(Scrapy)|(Screaming Frog SEO Spider)|(ScreenerBot)|(SemrushBot)|(SensikaBot)|(SEOENG(World)?Bot)|(SEOkicks-Robot)|(seoscanners\.net)|(SkypeUriPreview)|(SeznamBot|SklikBot|Seznam screenshot-generator)|(shopify-partner-homepage-scraper)|(ShopWiki)|(SilverReader)|(SimplePie)|(SISTRIX Crawler)|(compatible; (?:SISTRIX )?Optimizer)|(SiteSucker)|(sixy.ch)|(Slackbot|Slack-ImgProxy)|((Sogou (web|inst|Pic) spider)|New-Sogou-Spider)|(Sosospider|Sosoimagespider)|(Sprinklr)|(sqlmap/)|(SSL Labs)|(StatusCake)|(Superfeedr bot)|(Sparkler/[0-9])|(Spinn3r)|(SputnikBot)|(SputnikFaviconBot)|(SputnikImageBot)|(SurveyBot)|(TarmotGezgin)|(TelegramBot)|(TLSProbe)|(TinEye-bot)|(Tiny Tiny RSS)|(theoldreader.com)|(trendictionbot)|(TurnitinBot)|(TweetedTimes Bot)|(TweetmemeBot)|(Twingly Recon)|(Twitterbot)|(UniversalFeedParser)|(via secureurl\.fwdcdn\.com)|(Uptimebot)|(UptimeRobot)|(URLAppendBot)|(Vagabondo)|(vkShare; )|(VSMCrawler)|(Jigsaw)|(W3C_I18n-Checker)|(W3C-checklink)|(W3C_Validator|Validator.nu)|(W3C-mobileOK)|(W3C_Unicorn)|(Wappalyzer)|(PTST/)|(WeSEE)|(WebbCrawler)|(websitepulse[+ ]checker)|(WordPress)|(Wotbox)|(XenForo)|(yacybot)|(Yahoo! Slurp|Yahoo!-AdCrawler)|(Yahoo Link Preview|Yahoo:LinkExpander:Slingstone)|(YahooMailProxy)|(YahooCacheSystem)|(Y!J-BRW)|(Yandex(SpravBot|ScreenshotBot|MobileBot|AccessibilityBot|ForDomain|Vertis|Market|Catalog|Calendar|Sitelinks|AdNet|Pagechecker|Webmaster|Media|Video|Bot|Images|Antivirus|Direct|Blogs|Favicons|ImageResizer|Verticals|News|Metrika|\.Gazeta Bot)|YaDirectFetcher|YandexTurbo|YandexTracker|YandexSearchShop|YandexRCA|YandexPartner|YandexOntoDBAPI|YandexOntoDB|YandexMobileScreenShotBot)|(Yeti|NaverJapan)|(YoudaoBot)|(YOURLS v[0-9])|(YRSpider|YYSpider)|(zgrab)|(Zookabot)|(ZumBot)|(YottaaMonitor)|(Yahoo Ad monitoring.*yahoo-ad-monitoring-SLN24857.*)|(.*Java.*outbrain)|(HubPages.*crawlingpolicy)|(Pinterest(bot)?/\d\.\d.*www\.pinterest\.com.*)|(Site24x7)|(s~snapchat-proxy)|(Let\'s Encrypt validation server)|(GrapeshotCrawler)|(www\.monitor\.us)|(Catchpoint)|(bitlybot)|(Zao/)|(lycos)|(Slurp)|(Speedy Spider)|(ScoutJet)|(nrsbot|netresearch)|(scooter)|(gigabot)|(charlotte)|(Pompos)|(ichiro)|(PagePeeker)|(WebThumbnail)|(Willow Internet Crawler)|(EmailWolf)|(NetLyzer FastProbe)|(AdMantX.*admantx\.com)|(Server Density Service Monitoring.*)|(RSSRadio \(Push Notification Scanner;support@dorada\.co\.uk\))|((A6-Indexer|nuhk|TsolCrawler|Yammybot|Openbot|Gulper Web Bot|grub-client|Download Demon|SearchExpress|Microsoft URL Control|borg|altavista|dataminr.com|tweetedtimes.com|TrendsmapResolver|teoma|blitzbot|oegp|furlbot|http%20client|polybot|htdig|mogimogi|larbin|scrubby|searchsight|seekbot|semanticdiscovery|snappy|vortex(?! Build)|zeal|fast-webcrawler|converacrawler|dataparksearch|findlinks|BrowserMob|HttpMonitor|ThumbShotsBot|URL2PNG|ZooShot|GomezA|Google SketchUp|Read%20Later|RackspaceBot|robots|SeopultContentAnalyzer|7Siters|centuryb.o.t9|InterNaetBoten|EasyBib AutoCite|Bidtellect|tomnomnom/meg|My User Agent))|(^sentry)|(^Spotify)|(The Knowledge AI)|(Embedly)|(BrandVerity)|(Kaspersky Lab CFR link resolver)|(eZ Publish Link Validator)|(woorankreview)|((Match|LinkCheck) by Siteimprove.com)|(CATExplorador)|(Buck)|(tracemyfile)|(zelist.ro feed parser)|(weborama-fetcher)|(BoardReader Favicon Fetcher)|(IDG/IT)|(Bytespider)|(WikiDo)|(AwarioSmartBot)|(AwarioRssBot)|(oBot)|(SMTBot)|(LCC)|(Startpagina-Linkchecker)|(GTmetrix)|(Nutch)|(Seobility)|(Vercelbot)|(Grammarly)|(Robozilla)|(Domains Project)|(PetalBot)|(SerendeputyBot)|(ias-va.*admantx.*service-fetcher)|(SemanticScholarBot)|(VelenPublicWebCrawler)|(Barkrowler)|(BDCbot)|(adbeat)|(BW/(?:(\d+[\.\d]+)))|(https://whatis.contentkingapp.com)|(MicroAdBot)|(PingAdmin.Ru)|(notifyninja.+monitoring)|(WebDataStats)|(parse.ly scraper)|(Nimbostratus-Bot)|(HeartRails_Capture/\d)|(Project-Resonance)|(DataXu/\d)|(Cocolyzebot)|(veryhip)|(LinkpadBot)|(MuscatFerret)|(PageThing.com)|(ArchiveBox)|(Choosito)|(datagnionbot)|(WhatCMS)|(httpx)|(scaninfo@expanseinc.com)|(HuaweiWebCatBot)|(Hatena-Favicon)|(RyowlEngine/(\d+))|(OdklBot/(\d+))|(Mediatoolkitbot)|(ZoominfoBot)|(WeViKaBot/([\d+\.]))|(SEOkicks)|(Plukkie/([\d+\.]))|(proximic;)|(SurdotlyBot/([\d+\.]))|(Gowikibot/([\d+\.]))|(SabsimBot/([\d+\.]))|(LumtelBot/([\d+\.]))|(PiplBot)|(woobot/([\d+\.]))|(Cookiebot/([\d+\.]))|(NetSystemsResearch)|(CensysInspect/([\d+\.]))|(gdnplus.com)|(WellKnownBot/([\d+\.]))|(Adsbot/([\d+\.]))|(MTRobot/([\d+\.]))|(serpstatbot/([\d+\.]))|(colly)|(l9tcpid/v([\d+\.]))|(MegaIndex.ru/([\d+\.]))|(Seekport)|(seolyt/([\d+\.]))|(YaK/([\d+\.]))|(KomodiaBot/([\d+\.]))|(Neevabot/([\d+\.]))|(LinkPreview/([\d+\.]))|(JungleKeyThumbnail/([\d+\.]))|(rocketmonitor(?: |bot/)([\d+\.]))|(SitemapParser-VIPnytt/([\d+\.]))|(^Turnitin)|(DMBrowser/\d+|DMBrowser-[UB]V)|(ThinkChaos/)|(DataForSeoBot)|(Discordbot/([\d+.]+))|([a-z0-9\-_]*((?<!cu|power[ _]|m[ _])bot(?![ _]TAB|[ _]?5[0-9])|crawler|crawl|checker|archiver|transcoder|spider)([^a-z]|$))#i';

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
        if (is_array($site->getConfiguration()['SiteLanguageRedirectionFallbacks']) && !empty($site->getConfiguration()['SiteLanguageRedirectionFallbacks'])) {
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
