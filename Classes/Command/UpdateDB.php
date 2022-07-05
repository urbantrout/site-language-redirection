<?php
namespace UrbanTrout\SiteLanguageRedirection\Command;

use Archive_Tar;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Error\Exception;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UrbanTrout\SiteLanguageRedirection\Middleware\RedirectionMiddleware;

class UpdateDB extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected function configure()
    {
        $this->setDescription('Update GeoIP2 database.');
        $this->setHelp(
            'This command fetches the mmdb file from GeoIP and saves it to disk. This step is mandatory for IP based redirection.' . LF .
            'Please consider adding this command to your TYPO3 scheduler.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fetching database');
        $path = Environment::getVarPath() . '/sitelanguageredirection/';
        $tarFilename = 'GeoLite2-Country.tar';
        $filename = 'GeoLite2-Country.mmdb';

        /** @var \TYPO3\CMS\Core\Site\SiteFinder $siteFinder  */
        $siteFinder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class);

        $sites = $siteFinder->getAllSites();

        foreach ($sites as $site) {
            $method = $site->getConfiguration()['SiteLanguageRedirectionMethod'];
            if ($method !== RedirectionMiddleware::REDIRECT_METHOD_IPADDRESS) {
                continue; //Skip this site
            }
            $licenseKey =  $site->getConfiguration()['SiteLanguageMaxmindLicenseKey'];
            if (empty($licenseKey)) {
                $logMessage = 'Maxmind license key not given.';
                $io->error($logMessage);
                $this->logger->error($logMessage);
                throw new Exception($logMessage);
            }

            /** @var RequestFactory $requestFactory */
            $requestFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Http\\RequestFactory');
            $url = 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=' . $licenseKey . '&suffix=tar.gz';
            // Return a PSR-7 compliant response object.
            $response = $requestFactory->request($url, 'GET');

            // Get the content as a stream on a successful request.
            if ($response->getStatusCode() === 200 && strpos($response->getHeaderLine('Content-Type'), 'application/gzip') === 0) {
                $content = gzdecode($response->getBody()->getContents());
                $result = GeneralUtility::writeFileToTypo3tempDir($path . $tarFilename, $content);

                if (!empty($result)) {
                    $logMessage = 'Couldn\'t extract GZIP file.';
                    $io->error($logMessage);
                    $this->logger->error($logMessage);
                    throw new Exception($logMessage);
                }

                $tar = new Archive_Tar($path . $tarFilename);
                $tarContent = $tar->listContent();
                $dbFiles = array_filter($tarContent, function ($content) use ($filename) {
                    $baseName = pathinfo($content['filename'], PATHINFO_BASENAME);
                    return $baseName === $filename;
                });

                if (empty($dbFiles)) {
                    $logMessage = "Couldn\'t find file '{$filename}' TAR file.";
                    $io->error($logMessage);
                    $this->logger->error($logMessage);
                    throw new Exception($logMessage);
                }
                $dbFile = reset($dbFiles);
                /** @var string $dbFilePath Contains the directory path containing the database file */
                $dbFilePath = pathinfo($dbFile['filename'], PATHINFO_DIRNAME);

                $result = $tar->extractList([$dbFile['filename']], $path, $dbFilePath, false, false);

                if (!$result) {
                    $logMessage = 'Couldn\'t extract TAR file.';
                    $io->error($logMessage);
                    $this->logger->error($logMessage);
                    throw new Exception($logMessage);
                }

                $logMessage = 'DB file successfully saved to: ' . $path . $filename;
                $this->logger->info($logMessage);
                $io->success($logMessage);
            } else {
                $logMessage = 'Couldn\'t fetch file from download.maxmind.com.';
                $io->error($logMessage);
                $this->logger->error($logMessage);
                throw new Exception($logMessage);
            }
        }
        return 0;
    }
}
