<?php
namespace UrbanTrout\SiteLanguageRedirection\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Error\Exception;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UpdateDB extends Command
{

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
                    throw new Exception('Couldn\'t save DB file.');
                }
            }
        }
    }
}
