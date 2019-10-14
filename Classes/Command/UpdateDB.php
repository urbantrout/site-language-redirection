<?php
namespace UrbanTrout\SiteLanguageRedirection\Command;

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
        $filename = 'GeoLite2-Country.mmdb';

        /** @var RequestFactory $requestFactory */
        $requestFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Http\\RequestFactory');
        $url = 'https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.mmdb.gz';

        // Return a PSR-7 compliant response object.
        $response = $requestFactory->request($url, 'GET');

        // Get the content as a stream on a successful request.
        if ($response->getStatusCode() === 200) {
            if (strpos($response->getHeaderLine('Content-Type'), 'application/octet-stream') === 0) {
                $content = gzdecode($response->getBody()->getContents());
                $result = GeneralUtility::writeFileToTypo3tempDir($path . $filename, $content);

                if (!empty($result)) {
                    $logMessage = 'Couldn\'t save DB file.';
                    $io->error($logMessage);
                    $this->logger->error($logMessage);
                    throw new Exception($logMessage);
                }

                $logMessage = 'DB file successfully saved to: ' . $path . $filename;
                $this->logger->info($logMessage);
                $io->success($logMessage);
            } else {
                $logMessage = 'Couldn\'t fetch file from geolite.maxmind.com.';
                $io->error($logMessage);
                $this->logger->error($logMessage);
            }
        }
    }
}
