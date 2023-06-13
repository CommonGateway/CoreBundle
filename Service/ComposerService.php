<?php

namespace CommonGateway\CoreBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function PHPUnit\Framework\throwException;

/**
 * @Author Ruben van der Linde <ruben@conduction.nl>, Barry Brands <barry@conduction.nl>, Wilco Louwerse <wilco@conduction.nl>
 *
 * @license EUPL <https://github.com/ConductionNL/contactcatalogus/blob/master/LICENSE.md>
 *
 * @category Service
 */
class ComposerService
{
    private function arrayEnum(array $array, array $enum): bool
    {
        // Lets see if the values in the array arry pressent in the enum
        foreach ($array as $value) {
            if (!in_array($value, $enum)) {
                return false;
            }
        }

        return true;
    }//end arrayEnum()

    /**
     * Make a call to composer.
     *
     * @param string      $call    The call that you want to make to composer shoul be one of show, init, install
     * @param string|null $package
     * @param array       $options
     *
     * @return array|string
     */
    private function composerCall(string $call, array $options = [], string $package = '')
    {
        $optionsList = [];
        // Let's check for valid calls.
        switch ($call) {
            case 'init':
                $optionsList = [];
                // name: Name of the package.
                //--description: Description of the package.
                //--author: Author name of the package.
                //--type: Type of package.
                //--homepage: Homepage of the package.
                //--require: Package to require with a version constraint. Should be in format foo/bar:1.0.0.
                //--require-dev: Development requirements, see --require.
                //--stability (-s): Value for the minimum-stability field.
                //--license (-l): License of package.
                //--repository: Provide one (or more) custom repositories. They will be stored in the generated composer.json, and used for auto-completion when prompting for the list of requires. Every repository can be either an HTTP URL pointing to a composer repository or a JSON string which similar to what the repositories key accepts.
                //--autoload (-a): Add a PSR-4 autoload mapping to
                break;
            case 'install':
                $optionsList = [];
                // --prefer-install: There are two ways of downloading a package: source and dist. Composer uses dist by default. If you pass --prefer-install=source (or --prefer-source) Composer will install from source if there is one. This is useful if you want to make a bugfix to a project and get a local git clone of the dependency directly. To get the legacy behavior where Composer use source automatically for dev versions of packages, use --prefer-install=auto. See also config.preferred-install. Passing this flag will override the config value.
                //--dry-run: If you want to run through an installation without actually installing a package, you can use --dry-run. This will simulate the installation and show you what would happen.
                //--download-only: Download only, do not install packages.
                //--dev: Install packages listed in require-dev (this is the default behavior).
                //--no-dev: Skip installing packages listed in require-dev. The autoloader generation skips the autoload-dev rules. Also see COMPOSER_NO_DEV.
                //--no-autoloader: Skips autoloader generation.
                //--no-progress: Removes the progress display that can mess with some terminals or scripts which don't handle backspace characters.
                //--audit: Run an audit after installation is complete.
                //--audit-format: Audit output format. Must be "table", "plain", "json", or "summary" (default).
                //--optimize-autoloader (-o): Convert PSR-0/4 autoloading to classmap to get a faster autoloader. This is recommended especially for production, but can take a bit of time to run so it is currently not done by default.
                //--classmap-authoritative (-a): Autoload classes from the classmap only. Implicitly enables --optimize-autoloader.
                //--apcu-autoloader: Use APCu to cache found/not-found classes.
                //--apcu-autoloader-prefix: Use a custom prefix for the APCu autoloader cache. Implicitly enables --apcu-autoloader.
                //--ignore-platform-reqs: ignore all platform requirements (php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill these. See also the platform config option.
                //--ignore-platform-req: ignore a specific platform requirement(php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill it. Multiple requirements can be ignored via wildcard. Appending a + makes it only ignore the upper-bound of the requirements. For example, if a package requires php: ^7, then the option --ignore-platform-req=php+ would allow installing on PHP 8, but installation on PHP 5.6 would still fail.
                break;
            case 'update':
                $optionsList = [];
                // --prefer-install: There are two ways of downloading a package: source and dist. Composer uses dist by default. If you pass --prefer-install=source (or --prefer-source) Composer will install from source if there is one. This is useful if you want to make a bugfix to a project and get a local git clone of the dependency directly. To get the legacy behavior where Composer use source automatically for dev versions of packages, use --prefer-install=auto. See also config.preferred-install. Passing this flag will override the config value.
                //--dry-run: Simulate the command without actually doing anything.
                //--dev: Install packages listed in require-dev (this is the default behavior).
                //--no-dev: Skip installing packages listed in require-dev. The autoloader generation skips the autoload-dev rules. Also see COMPOSER_NO_DEV.
                //--no-install: Does not run the install step after updating the composer.lock file.
                //--no-audit: Does not run the audit steps after updating the composer.lock file. Also see COMPOSER_NO_AUDIT.
                //--audit-format: Audit output format. Must be "table", "plain", "json", or "summary" (default).
                //--lock: Only updates the lock file hash to suppress warning about the lock file being out of date.
                //--with: Temporary version constraint to add, e.g. foo/bar:1.0.0 or foo/bar=1.0.0
                //--no-autoloader: Skips autoloader generation.
                //--no-progress: Removes the progress display that can mess with some terminals or scripts which don't handle backspace characters.
                //--with-dependencies (-w): Update also dependencies of packages in the argument list, except those which are root requirements.
                //--with-all-dependencies (-W): Update also dependencies of packages in the argument list, including those which are root requirements.
                //--optimize-autoloader (-o): Convert PSR-0/4 autoloading to classmap to get a faster autoloader. This is recommended especially for production, but can take a bit of time to run, so it is currently not done by default.
                //--classmap-authoritative (-a): Autoload classes from the classmap only. Implicitly enables --optimize-autoloader.
                //--apcu-autoloader: Use APCu to cache found/not-found classes.
                //--apcu-autoloader-prefix: Use a custom prefix for the APCu autoloader cache. Implicitly enables --apcu-autoloader.
                //--ignore-platform-reqs: ignore all platform requirements (php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill these. See also the platform config option.
                //--ignore-platform-req: ignore a specific platform requirement(php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill it. Multiple requirements can be ignored via wildcard. Appending a + makes it only ignore the upper-bound of the requirements. For example, if a package requires php: ^7, then the option --ignore-platform-req=php+ would allow installing on PHP 8, but installation on PHP 5.6 would still fail.
                //--prefer-stable: Prefer stable versions of dependencies. Can also be set via the COMPOSER_PREFER_STABLE=1 env var.
                //--prefer-lowest: Prefer lowest versions of dependencies. Useful for testing minimal versions of requirements, generally used with --prefer-stable. Can also be set via the COMPOSER_PREFER_LOWEST=1 env var.
                //--interactive: Interactive interface with autocompletion to select the packages to update.
                //--root-reqs: Restricts the update to your first degree dependencies.+ makes it only ignore the upper-bound of the requirements. For example, if a package requires php: ^7, then the option --ignore-platform-req=php+ would allow installing on PHP 8, but installation on PHP 5.6 would still fail.
                break;
            case 'require':
                $optionsList = [];
                // --dev: Add packages to require-dev.
                //--dry-run: Simulate the command without actually doing anything.
                //--prefer-install: There are two ways of downloading a package: source and dist. Composer uses dist by default. If you pass --prefer-install=source (or --prefer-source) Composer will install from source if there is one. This is useful if you want to make a bugfix to a project and get a local git clone of the dependency directly. To get the legacy behavior where Composer use source automatically for dev versions of packages, use --prefer-install=auto. See also config.preferred-install. Passing this flag will override the config value.
                //--no-progress: Removes the progress display that can mess with some terminals or scripts which don't handle backspace characters.
                //--no-update: Disables the automatic update of the dependencies (implies --no-install).
                //--no-install: Does not run the install step after updating the composer.lock file.
                //--no-audit: Does not run the audit steps after updating the composer.lock file. Also see COMPOSER_NO_AUDIT.
                //--audit-format: Audit output format. Must be "table", "plain", "json", or "summary" (default).
                //--update-no-dev: Run the dependency update with the --no-dev option. Also see COMPOSER_NO_DEV.
                //--update-with-dependencies (-w): Also update dependencies of the newly required packages, except those that are root requirements.
                //--update-with-all-dependencies (-W): Also update dependencies of the newly required packages, including those that are root requirements.
                //--ignore-platform-reqs: ignore all platform requirements (php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill these. See also the platform config option.
                //--ignore-platform-req: ignore a specific platform requirement(php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill it. Multiple requirements can be ignored via wildcard.
                //--prefer-stable: Prefer stable versions of dependencies. Can also be set via the COMPOSER_PREFER_STABLE=1 env var.
                //--prefer-lowest: Prefer lowest versions of dependencies. Useful for testing minimal versions of requirements, generally used with --prefer-stable. Can also be set via the COMPOSER_PREFER_LOWEST=1 env var.
                //--sort-packages: Keep packages sorted in composer.json.
                //--optimize-autoloader (-o): Convert PSR-0/4 autoloading to classmap to get a faster autoloader. This is recommended especially for production, but can take a bit of time to run, so it is currently not done by default.
                //--classmap-authoritative (-a): Autoload classes from the classmap only. Implicitly enables --optimize-autoloader.
                //--apcu-autoloader: Use APCu to cache found/not-found classes.
                //--apcu-autoloader-prefix: Use a custom prefix for the APCu autoloader cache. Implicitly enables --apcu-autoloader.ard. Appending a + makes it only ignore the upper-bound of the requirements. For example, if a package requires php: ^7, then the option --ignore-platform-req=php+ would allow installing on PHP 8, but installation on PHP 5.6 would still fail.
                break;
            case 'remove':
                $optionsList = [];
                // --dev: Remove packages from require-dev.
                //--dry-run: Simulate the command without actually doing anything.
                //--no-progress: Removes the progress display that can mess with some terminals or scripts which don't handle backspace characters.
                //--no-update: Disables the automatic update of the dependencies (implies --no-install).
                //--no-install: Does not run the install step after updating the composer.lock file.
                //--no-audit: Does not run the audit steps after installation is complete. Also see COMPOSER_NO_AUDIT.
                //--audit-format: Audit output format. Must be "table", "plain", "json", or "summary" (default).
                //--update-no-dev: Run the dependency update with the --no-dev option. Also see COMPOSER_NO_DEV.
                //--update-with-dependencies (-w): Also update dependencies of the removed packages. (Deprecated, is now default behavior)
                //--update-with-all-dependencies (-W): Allows all inherited dependencies to be updated, including those that are root requirements.
                //--ignore-platform-reqs: ignore all platform requirements (php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill these. See also the platform config option.
                //--ignore-platform-req: ignore a specific platform requirement(php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill it. Multiple requirements can be ignored via wildcard.
                //--optimize-autoloader (-o): Convert PSR-0/4 autoloading to classmap to get a faster autoloader. This is recommended especially for production, but can take a bit of time to run so it is currently not done by default.
                //--classmap-authoritative (-a): Autoload classes from the classmap only. Implicitly enables --optimize-autoloader.
                //--apcu-autoloader: Use APCu to cache found/not-found classes.
                //--apcu-autoloader-prefix: Use a custom prefix for the APCu autoloader cache. Implicitly enables --apcu-autoloader.x: Use a custom prefix for the APCu autoloader cache. Implicitly enables --apcu-autoloader.ard. Appending a + makes it only ignore the upper-bound of the requirements. For example, if a package requires php: ^7, then the option --ignore-platform-req=php+ would allow installing on PHP 8, but installation on PHP 5.6 would still fail.
                break;
            case 'bump':
                $optionsList = [];
                //
                //--dev-only: Only bump requirements in "require-dev".
                //--no-dev-only: Only bump requirements in "require".
                //--dry-run: Outputs the packages to bump, but will not execute anything.
                break;
            case 'check-platform-reqs':
                $optionsList = [];
                //--lock: Checks requirements only from the lock file, not from installed packages.
                //--no-dev: Disables checking of require-dev packages requirements.
                //--format (-f): Format of the output: text (default) or json
                break;
            case 'remove':
                $optionsList = [];
                // --dev: Remove packages from require-dev.
                //--dry-run: Simulate the command without actually doing anything.
                //--no-progress: Removes the progress display that can mess with some terminals or scripts which don't handle backspace characters.
                //--no-update: Disables the automatic update of the dependencies (implies --no-install).
                //--no-install: Does not run the install step after updating the composer.lock file.
                //--no-audit: Does not run the audit steps after installation is complete. Also see COMPOSER_NO_AUDIT.
                //--audit-format: Audit output format. Must be "table", "plain", "json", or "summary" (default).
                //--update-no-dev: Run the dependency update with the --no-dev option. Also see COMPOSER_NO_DEV.
                //--update-with-dependencies (-w): Also update dependencies of the removed packages. (Deprecated, is now default behavior)
                //--update-with-all-dependencies (-W): Allows all inherited dependencies to be updated, including those that are root requirements.
                //--ignore-platform-reqs: ignore all platform requirements (php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill these. See also the platform config option.
                //--ignore-platform-req: ignore a specific platform requirement(php, hhvm, lib-* and ext-*) and force the installation even if the local machine does not fulfill it. Multiple requirements can be ignored via wildcard.
                //--optimize-autoloader (-o): Convert PSR-0/4 autoloading to classmap to get a faster autoloader. This is recommended especially for production, but can take a bit of time to run so it is currently not done by default.
                //--classmap-authoritative (-a): Autoload classes from the classmap only. Implicitly enables --optimize-autoloader.
                //--apcu-autoloader: Use APCu to cache found/not-found classes.
                //--apcu-autoloader-prefix: Use a custom prefix for the APCu autoloader cache. Implicitly enables --apcu-autoloader.x: Use a custom prefix for the APCu autoloader cache. Implicitly enables --apcu-autoloader.ard. Appending a + makes it only ignore the upper-bound of the requirements. For example, if a package requires php: ^7, then the option --ignore-platform-req=php+ would allow installing on PHP 8, but installation on PHP 5.6 would still fail.
                break;
            case 'search':
                $optionsList = ['--format'];
                // --only-name (-N): Search only in package names.
                //--only-vendor (-O): Search only for vendor / organization names, returns only "vendor" as a result.
                //--type (-t): Search for a specific package type.
                //--format (-f): Lets you pick between text (default) or json output format. Note that in the json, only the name and description keys are guaranteed to be present. The rest (url, repository, downloads and favers) are available for Packagist.org search results and other repositories may return more or less data.
                break;
            case 'show':
                $optionsList = ['--all', '--installed', '--locked', '--platform ', '--available', '--self', '--name-only', '--path', '--tree', '--latest', '--outdated', '--latest', '--ignore', '--no-dev', '--major-only', '--minor-only', '--patch-only', '--direct', '--strict', '--ignore-platform-reqs', '--ignore-platform-req', '--format'];
                break;
            case 'home':
                $optionsList = [];
                // --homepage (-H): Open the homepage instead of the repository URL.
                //--show (-s): Only show the homepage or repository URL.
                break;
            case 'suggests':
                $optionsList = [];
                // --by-package: Groups output by suggesting package (default).
                //--by-suggestion: Groups output by suggested package.
                //--all: Show suggestions from all dependencies, including transitive ones (by default only direct dependencies' suggestions are shown).
                //--list: Show only list of suggested package names.
                //--no-dev: Excludes suggestions from require-dev packages.
                break;
            case 'fund':
                $optionsList = [];
                //--format (-f): Lets you pick between text (default) or json output format.
                break;
            case 'depends ':
                $optionsList = [];
                //--recursive (-r): Recursively resolves up to the root package.
                //--tree (-t): Prints the results as a nested tree, implies -r.
                break;
            case 'prohibits ':
                $optionsList = [];
                //--recursive (-r): Recursively resolves up to the root package.
                //--tree (-t): Prints the results as a nested tree, implies -r.
                break;
            case 'validate ':
                $optionsList = [];
                //--no-check-all: Do not emit a warning if requirements in composer.json use unbound or overly strict version constraints.
                //--no-check-lock: Do not emit an error if composer.lock exists and is not up to date.
                //--no-check-publish: Do not emit an error if composer.json is unsuitable for publishing as a package on Packagist but is otherwise valid.
                //--with-dependencies: Also validate the composer.json of all installed dependencies.
                //--strict: Return a non-zero exit code for warnings as well as errors.
                break;
            case 'status ':
                $optionsList = [];
                //--recursive (-r): Recursively resolves up to the root package.
                //--tree (-t): Prints the results as a nested tree, implies -r.
                break;
            case 'config ':
                $optionsList = [];
                //--global (-g): Operate on the global config file located at $COMPOSER_HOME/config.json by default. Without this option, this command affects the local composer.json file or a file specified by --file.
                //--editor (-e): Open the local composer.json file using in a text editor as defined by the EDITOR env variable. With the --global option, this opens the global config file.
                //--auth (-a): Affect auth config file (only used for --editor).
                //--unset: Remove the configuration element named by setting-key.
                //--list (-l): Show the list of current config variables. With the --global option this lists the global configuration only.
                //--file="..." (-f): Operate on a specific file instead of composer.json. Note that this cannot be used in conjunction with the --global option.
                //--absolute: Returns absolute paths when fetching *-dir config values instead of relative.
                //--json: JSON decode the setting value, to be used with extra.* keys.
                //--merge: Merge the setting value with the current value, to be used with extra.* keys in combination with --json.
                //--append: When adding a repository, append it (lowest priority) to the existing ones instead of prepending it (highest priority).
                //--source: Display where the config value is loaded from.
                break;
            case 'diagnose ':
                $optionsList = [];
                break;
            case 'archive ':
                $optionsList = [];
                //--no-dev: Disables auditing of require-dev packages.
                //--format (-f): Audit output format. Must be "table" (default), "plain", "json", or "summary".
                //--locked: Audit packages from the lock file, regardless of what is currently in vendor dir.
                break;
            case 'audit ':
                $optionsList = ['--format'];
                break;
        }//end switch

        // Prepare the comand
        $cmd = ['composer', $call];

        if ($package != '') {
            $cmd[] = strtolower($package);
        }

        // Check the enums
        if ($options and !$this->arrayEnum($options, $optionsList)) {
            // @todo throwException();
        }

        // Force JSON output where supported
        if (in_array('--format', $optionsList) && !in_array('--format json', $options)) {
            $options[] = '--format=json';
        }

        // Include options
        $cmd = array_merge_recursive($cmd, $options);

        // Start the procces
        $process = new Process($cmd);
        $process->setWorkingDirectory('/srv/api');
        $process->setTimeout(3600);
        $process->run();

        // executes after the command finishes
        if (!$process->isSuccessful()) {
            //throw new ProcessFailedException($process);
            //var_dump('error');
            $content = $process->getErrorOutput();
        } else {
            $content = $process->getOutput();
        }

        // Turn in into simpethin workable
        if (in_array('--format=json', $options)) {
            $content = json_decode($content, true);
        } else {
            $content = explode(PHP_EOL, $content);
        }

        return $content;
    }//end composerCall()

    /**
     * Gets all installed plugins from the lock file.
     */
    public function getLockFile(): array
    {
        $filesystem = new Filesystem();

        if (!$plugins = @file_get_contents('../composer.lock')) {
            if (!$plugins = @file_get_contents('composer.lock')) {
                return [];
            }
        }

        $plugins = json_decode($plugins, true);

        return $plugins['packages'];
    }//end getLockFile()

    /**
     * Show al packages installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param array $options
     *
     * @return array
     */
    public function getAll(array $options = []): array
    {
        $lockFile = $this->getLockFile();
        $plugins = [];
        foreach ($lockFile as $result) {
            // Remove non gateway plugins from the result
            if (!isset($result['keywords']) || !in_array('common-gateway-plugin', $result['keywords'])) {
                continue;
            }

            $plugins[] = array_merge($result, $this->getSingle($result['name']));
        }

        return $plugins;
    }//end getAll()

    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param string $package
     * @param array $options
     *
     * @return array
     */
    public function require(string $package, array $options = []): array
    {
        return $this->composerCall('require', $options, $package);
    }//end require()
    
    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param string $package
     * @param array  $options
     *
     * @return array
     */
    public function upgrade(string $package, array $options = []): array
    {
        return $this->composerCall('upgrade', $options, $package);
    }//end upgrade()

    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param string $package
     * @param array  $options
     *
     * @return array
     */
    public function remove(string $package, array $options = []): array
    {
        return $this->composerCall('remove', $options, $package);
    }//end remove()

    /**
     * Show a single package installed trough composer.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param string $package
     * @param array  $options
     *
     * @return array
     */
    public function getSingle(string $package, array $options = []): array
    {
        $url = 'https://packagist.org/packages/'.$package.'.json';

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', $url);
        $plugin = json_decode($response->getBody()->getContents(), true)['package'];

        $installedPlugins = $this->getLockFile();

        foreach ($installedPlugins as $installedPlugin) {
            if ($installedPlugin['name'] == $plugin['name']) {
                $plugin = array_merge($installedPlugin, $plugin);
                $plugin['update'] = false;

                // Lets see if we have newer versions than currently installer
                foreach ($plugin['versions']  as $version => $versionDetails) {
                    if (version_compare($plugin['version'], $version) < 0) {
                        if (!$plugin['update']) {
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
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param string|null $search
     * @param array       $options
     *
     * @return array
     */
    public function search(string $search = null, array $options = []): array
    {
        $url = 'https://packagist.org/search.json';
        $query = ['tags' => 'common-gateway-plugin'];
        if ($search) {
            $query['q'] = $search;
        }

        $client = new \GuzzleHttp\Client();
        $response = $client->request('GET', 'https://packagist.org/search.json', [
            'query' => $query,
        ]);

        $plugins = json_decode($response->getBody()->getContents(), true)['results'];

        // Lets pull the online detail datail
        foreach ($plugins as $key => $plugin) {
            $plugins[$key] = array_merge($plugin, $this->getSingle($plugin['name']));
        }

        return $plugins;
    }//end search()

    /**
     * Search for a given term.
     *
     * See https://getcomposer.org/doc/03-cli.md#show-info for a full list of al options and there function
     *
     * @param array $options
     *
     * @return array
     */
    public function audit(array $options = []): array
    {
        return $this->composerCall('audit', $options);
    }//end audit()
}//end class
