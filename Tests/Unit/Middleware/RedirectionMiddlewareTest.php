<?php
declare(strict_types = 1);
namespace UrbanTrout\SiteLanguageRedirection\Tests\Unit\Middleware;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use UrbanTrout\SiteLanguageRedirection\Middleware\RedirectionMiddleware;

class RedirectionMiddlewareTest extends UnitTestCase
{

    /**
     * @var RequestHandlerInterface
     */
    protected $responseOutputHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // A request handler which expects a site with some more details are found.
        $this->responseOutputHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                /** @var SiteInterface $site */
                $site = $request->getAttribute('site');
                /** @var SiteLanguage $site */
                $language = $request->getAttribute('language');
                /** @var PageArguments $routeResult */
                $routeResult = $request->getAttribute('routing', false);
                if ($routeResult) {
                    return new JsonResponse(
                        [
                            'site' => $site->getIdentifier(),
                            'language-id' => $language->getLanguageId(),
                        ]
                    );
                }
                return new NullResponse();
            }
        };
    }

    /**
     * @test
     */
    public function redirectBasedOnBrowserLanguage()
    {
        $incomingUrl = 'https://example.com/';

        /** @var MockObject|Site $site */
        $site = $this->getMockBuilder(Site::class)->setConstructorArgs([
            'default', 23, [
                'base' => '/',
                'languages' => [
                    0 => [
                        'languageId' => 0,
                        'locale' => 'en_US.UTF-8',
                        'base' => '/',
                    ],
                    1 => [
                        'languageId' => 1,
                        'locale' => 'de_AT.UTF-8',
                        'base' => '/de/',
                        'iso-639-1' => 'de',
                    ],
                ]
            ]
        ])->setMethods(['getRouter'])->getMock();
        $language = $site->getDefaultLanguage();

        $request = new ServerRequest($incomingUrl, 'GET');
        $request = $request->withAttribute('site', $site);
        $request = $request->withAttribute('routing', new SiteRouteResult($request->getUri(), $site, $language, ''));
        $request = $request->withAttribute('language', $language);
        $request = $request->withAttribute(
            'normalizedParams',
            new NormalizedParams(
                // array_merge($request->getServerParams(), ['HTTP_REFERER' => 'https://example.com/en/']),
                $request->getServerParams(),
                $GLOBALS['TYPO3_CONF_VARS']['SYS'],
                Environment::getCurrentScript(),
                Environment::getPublicPath()
            )
        );
        $request = $request->withHeader('Accept-Language', 'de');

        // $pageArgumentsMock = $this->getMockBuilder(PageArguments::class)->disableOriginalConstructor()->setMethods(['getPageId'])->getMock();
        // $pageArgumentsMock->expects($this->once())->method('getPageId')->will($this->returnValue(23));
        // $request->expects($this->any())->method('getPageId')->willReturn($pageArgumentsMock);

        $subject = new RedirectionMiddleware();
        $response = $subject->process($request, $this->responseOutputHandler);
        $result = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('1', $result['language-id']);
    }
}
