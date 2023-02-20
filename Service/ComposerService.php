<?php

namespace CommonGateway\CoreBundle\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * The composer service functions as a wrapper for composer CLI.
 *
 * @Author Robert Zondervan <robert@conduction.nl>, Ruben van der Linde <ruben@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ComposerService
{
    /**
     * @var LoggerInterface The logger interface.
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $pluginLogger The logger interface.
     */
    public function __construct(
        LoggerInterface $pluginLogger
    ) {
        $this->logger = $pluginLogger;
    }//end __construct()

    /**
     *  Checks array values agains an enum.
     *
     * @param array $array An array.
     * @param array $enum  An enum.
     *
     * @return bool True is it is present false if otherwise
     */
    private function arrayEnum(array $array, array $enum): bool
    {
        // Let's see if the values in the array are present in the enum.
        foreach ($array as $value) {
            if (in_array($value, $enum) === false) {
                return false;
            }
        }

        return true;
    }//end arrayEnum()

    /**
     * Make a call to composer.
     *
     * @param string $call    The call that you want to make to composer shoul be one of show, init, install
     * @param array  $options Any options
     * @param string $package The packadge to make the call for
     *
     * @return array|string The packadge details or result text
     */
    private function composerCall(string $call, array $options = [], string $package = '')
    {
        $optionsList = [];

        // Lets check for valid calls.
        switch ($call) {
            case 'install':
                $optionsList = [];
                 break;
            case 'update':
                $optionsList = [];
                 break;
            case 'require':
                $optionsList = [];
                  break;
            case 'remove':
                $optionsList = [];
                break;
               break;
            case 'search':
                $optionsList = ['--format', '--type', '--only-vendor', '--only-name'];
                 break;
            case 'show':
                $optionsList = ['--all', '--installed', '--locked', '--platform ', '--available', '--self', '--name-only', '--path', '--tree', '--latest', '--outdated', '--latest', '--ignore', '--no-dev', '--major-only', '--minor-only', '--patch-only', '--direct', '--strict', '--ignore-platform-reqs', '--ignore-platform-req', '--format'];
                break;
            case 'audit ':
                $optionsList = ['--format'];
                break;
        }//end switch

        // Prepare the comand.
        $cmd = ['composer', $call];

        if ($package !== '') {
            $cmd[] = strtolower($package);
        }

        // Check the enums.
        if (empty($options) === false && $this->arrayEnum($options, $optionsList) === false) {
            $this->logger->error('Some options are not available for this call', ['cal' => $call, 'options' => $options, 'optionsList' => $optionsList]);
        }

        // Force JSON output where supported.
        if (in_array('--format', $optionsList) === true && in_array('--format json', $options) === false) {
            $options[] = '--format=json';
        }

        // Include options.
        $cmd = array_merge_recursive($cmd, $options);

        // Start the process.
        $process = new Process($cmd);
        $process->setWorkingDirectory('/srv/api');
        $process->setTimeout(3600);
        $process->run();

        // Executes after the command finishes.
        $content = $process->getOutput();
        if ($process->isSuccessful() === false) {
            $content = $process->getErrorOutput();
            $this->logger->error($content);
        }

        // Turn in into simpethin workable.
        if (in_array('--format=json', $options) === true) {
            $content = json_decode($content, true);
        } else {
            $content = explode(PHP_EOL, $content);
        }

        return $content;
    }//end composerCall()

    /**
     * Gets all installed plugins from the lock file.
     *
     * @return array The content of the lockfile as an array
     */
    public function getLockFile(): array
    {

        // Get the composer content.
        $plugins = ['packages' => []];
        $hits = new Finder();
        $hits = $hits->in('../')->name(['composer.lock'])->depth(1);

        // Lets hook al the composer lock contents together (if we have multiple).
        foreach ($hits as $file) {
            $plugins = array_merge(json_decode($file->getContents(), true));
        }

        return $plugins['packages'];
    }//end getLockFile()

    /**
     * Show al packages installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function.
     *
     * @return array An array of all packages.
     */
    public function getAll(): array
    {
        $results = $this->getLockFile();
        $plugins = [];
        foreach ($results as $result) {
            // Remove non gateway plugins from the result.
            if (isset($result['keywords']) === false || in_array('common-gateway-plugin', $result['keywords']) === false) {
                continue;
            }

            $plugins[] = array_merge($result, $this->getSingle($result['name']));
        }

        return $plugins;
    }//end getAll()

    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function.
     *
     * @param string $package A package.
     * @param array  $options Any options.
     *
     * @return array The packadges
     */
    public function require(string $package, array $options = []): array
    {
        return $this->composerCall('require', $options, $package);
    }//end require()

    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function.
     *
     * @param string $package A package.
     * @param array  $options Any options.
     *
     * @return array The result
     */
    public function upgrade(string $package, array $options = []): array
    {
        return $this->composerCall('upgrade', $options, $package);
    }//end upgrade()

    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function.
     *
     * @param string $package A package.
     * @param array  $options Any options.
     *
     * @return array The result
     */
    public function remove(string $package, array $options = []): array
    {
        return $this->composerCall('remove', $options, $package);
    }//end remove()

    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function.
     *
     * @param string $package A package.
     *
     * @return array A package as array.
     */
    public function getSingle(string $package): array
    {
        $url = 'https://packagist.org/packages/'.$package.'.json';

        $client = new Client();
        $response = $client->request('GET', $url);
        $plugin = json_decode($response->getBody()->getContents(), true)['package'];

        $installedPlugins = $this->getLockFile();

        foreach ($installedPlugins as $installedPlugin) {
            if ($installedPlugin['name'] === $plugin['name']) {
                $plugin = array_merge($installedPlugin, $plugin);
                $plugin['update'] = false;

                // Lets see if we have newer versions than currently installer (we don;t need versiond details but we want to force the key into $version).
                foreach ($plugin['versions']  as $version => $versionDetails) {
                    if (version_compare($plugin['version'], $version) < 0) {
                        if ($plugin['update'] === false) {
                            $plugin['update'] = $version;
                        } elseif (version_compare($plugin['update'], $version) < 0) {
                            $plugin['update'] = $version;
                        }
                    }
                }

                break;
            }
        }

        return $plugin;
    }//end getSingle()

    /**
     * Search for a given term.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function.
     *
     * @param string|null $search The search query
     *
     * @return array Any found packadges
     */
    public function search(string $search = null): array
    {
        $url = 'https://packagist.org/search.json';
        $query = ['tags' => 'common-gateway-plugin'];
        if ($search === true) {
            $query['q'] = $search;
        }

        $client = new Client();
        $response = $client->request('GET', 'https://packagist.org/search.json', ['query' => $query]);

        $plugins = json_decode($response->getBody()->getContents(), true)['results'];

        // Lets pull the online detail datail.
        foreach ($plugins as $key => $plugin) {
            $plugins[$key] = array_merge($plugin, $this->getSingle($plugin['name']));
        }

        return $plugins;
    }//end search()

    /**
     * Audit the installed packadges.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function.
     *
     * @param array $options The options
     *
     * @return array THe audit results
     */
    public function audit(array $options = []): array
    {
        return $this->composerCall('audit', $options);
    }//end audit()
}//end class
