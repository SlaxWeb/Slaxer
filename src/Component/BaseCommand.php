<?php
/**
 * Slaxer Component Base Command
 *
 * Base Command for the Component set of commands includes functionality that is
 * same and/or similar accross all different types of Component commands.
 *
 * @package   SlaxWeb\Slaxer
 * @author    Tomaz Lovrec <tomaz.lovrec@gmail.com>
 * @copyright 2016 (c) Tomaz Lovrec
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://github.com/slaxweb/
 * @version   0.1
 *
 * @todo: introduce some abstraction, right now it's just too procedural
 * @todo: needs a complete rewrite in the future, structure of the code here is catastrophic! Author: slax0r
 */
namespace SlaxWeb\Slaxer\Component;

use SlaxWeb\Bootstrap\Application;
use Symfony\Component\Console\Command\Command;

abstract class BaseCommand extends Command
{
    /**
     * Input
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input = null;

    /**
     * Output
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output = null;

    /**
     * Composer executable
     *
     * @var string
     */
    protected $composer = "";

    /**
     * SlaxWeb Framework Instance
     *
     * @var \SlaxWeb\Bootstrap\Application
     */
    protected $app = null;

    /**
     * Guzzle Client
     *
     * @var \GuzzleHttp\Client
     */
    protected $client = null;

    /**
     * Error string
     *
     * @var string
     */
    protected $error = "";

    /**
     * Packagist Base Url
     *
     * @var string
     */
    protected $baseUrl = "";

    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * Providers mapping
     *
     * Configuration file mapping for providers and their key names
     *
     * @var array
     */
    protected $providersMap = [
        "app" =>  [
            "file"  =>  "app.php",
            "key"   =>  "providerList"
        ],
        "commands"  =>  [
            "file"  =>  "app.php",
            "key"   =>  "commandsList"
        ],
        "hooks"     =>  [
            "file"  =>  "app.php",
            "key"   =>  "hooksList"
        ]
    ];

    /**
     * Component meta data
     *
     * @var string
     */
    protected $metaData = [];

    /**
     * Init Command
     *
     * Store the GuzzleHTTP Client object to the class property.
     *
     * @param \SlaxWeb\Bootstrap\Application $app Framework instance
     * @param \GuzzleHttp\Client $client Guzzle Client
     * @return void
     */
    public function init(Application $app, \GuzzleHttp\Client $client)
    {
        $this->app = $app;
        $this->client = $client;

        $this->baseUrl = $this->app["config.service"]["slaxer.baseUrl"];
        $this->logger = $this->app["logger.service"]("Slaxer");
    }

    /**
     * Finalize component info
     *
     * Obtain component info from configuration if it exists, and was not passed
     * in as command line arguments.
     *
     * @param array $component Component data
     * @return array
     */
    protected function finalizeComponent(array $component): array
    {
        $config = $this->app["config.service"]["slaxer.componentSettings"][$component["name"]] ?? [];
        $defVer = $this->app["config.service"]["slaxer.defaultVersion"]
                ?? "dev-master";

        if (strpos($component["name"], "/") === false) {
            $component["name"] = "slaxweb/{$component["name"]}";
        }

        if ($component["version"] === "") {
            $component["version"] = $config["version"] ?? $defVer;
        }

        $component["installFlags"] = $config["installFlags"] ?? "";

        return $component;
    }

    /**
     * Check Composer Command
     *
     * Set the composer command. Returns bool(false) if no composer found.
     *
     * @return bool
     *
     * @todo: Install composer locally if not found
     */
    protected function checkComposer(): bool
    {
        $this->output->writeln("<comment>Checking if composer exists ...</>");

        ($this->composer = trim(`which composer`)) || ($this->composer = trim(`which composer.phar`));
        if ($this->composer === "") {
            $this->output->writeln(
                "<error>ERROR: Composer not found. Make sure you have it installed, and is executable in your PATH</>"
            );
            return false;
        }

        $this->output->writeln("<comment>OK</>");
        return true;
    }

    /**
     * Check Component Exists
     *
     * Try to find the component on packagist.
     *
     * @param string $component Component name to check for existance.
     * @return bool
     */
    protected function componentExists(string $component): bool
    {
        $this->output->writeln("<comment>Checking if component {$component} exists ...</>");

        $response = $this->client->request(
            "GET",
            "{$this->baseUrl}{$component}",
            ["allow_redirects" => false]
        );
        if ($response->getStatusCode() !== 200) {
            $this->output->writeln("<error>ERROR: Component {$component} not found.</>");
            return false;
        }

        $this->output->writeln("<comment>OK</>");
        return true;
    }

    /**
     * Check component installed
     *
     * Check if component directory exists. If it does not, consider it removed.
     *
     * @param string $component Name of the component
     * @return bool
     */
    protected function isInstalled(string $component): bool
    {
        $this->output->writeln("<comment>Checking if component {$component} exists ...</>");

        if (file_exists("{$this->app["appDir"]}../vendor/{$component}/") === false) {
            $this->output->writeln(
                "<error>ERROR: Component directory not found, component '{$component}' not installed.</>"
            );
            return false;
        }

        $this->output->writeln("<comment>OK</>");
        return true;
    }

    /**
     * Parse component meta data
     *
     * Load the meta data of the component and parse it. If the meta data file is
     * not found, an error is set, and bool(false) is returned.
     *
     * @param string $name Name of the component
     * @return bool
     */
    protected function parseMetaData(string $name): bool
    {
        $metaFile = "{$this->app["appDir"]}../vendor/{$name}/component.json";
        if (file_exists($metaFile) === false) {
            system("{$this->composer} remove {$name}", $exit);
            $this->error = "Not a valid component. 'component.json' meta data file is missing. Package removed.";
            return false;
        }

        $this->metaData = json_decode(file_get_contents($metaFile));
        return true;
    }

    /**
     * Remove component
     *
     * Removes the component, its config files, and set providers with the help
     * of composer.
     *
     * @param string $name Name of the component
     * @return bool
     */
    protected function remove(string $name): bool
    {
        if ($this->parseMetaData($component["name"]) === false) {
            return false;
        }

        // remove providers from configuration
        if (empty($this->metaData->providers) === false) {
            foreach ($this->providersMap as $providerName => $map) {
                if (empty($this->metaData->providers->{$providerName}) === false) {
                    $this->removeProviders($map, $this->metaData->providers->{$providerName});
                }
            }
        }

        // Remove configuration files from framework configuration directory
        foreach ($this->metaData->configFiles as $file) {
            unlink("{$this->app["appDir"]}Config/{$file}");
        }

        $exit = 0;
        system("{$this->composer} remove {$name}", $exit);
        return $exit === 0;
    }

    /**
     * Configure installed component
     *
     * Add providers, hooks, configuration files, and install sub-components if user
     * requests it.
     *
     * @param string $name Component name
     * @return bool
     */
    protected function configComponent(string $name): bool
    {
        // add providers to configuration
        if (empty($this->metaData->providers) === false) {
            foreach ($this->providersMap as $providerName => $map) {
                if (empty($this->metaData->providers->{$providerName}) === false) {
                    $this->addProviders($map, $this->metaData->providers->{$providerName});
                }
            }
        }

        // Add configuration files to framework configuration directory
        foreach ($this->metaData->configFiles as $file) {
            copy(
                "{$this->app["appDir"]}../vendor/{$name}/config/{$file}",
                "{$this->app["appDir"]}Config/{$file}"
            );
        }

        // run post configure script
        if (empty($this->metaData->scripts->postConfigure) === false) {
            require "{$this->app["appDir"]}../vendor/{$name}/scripts/{$this->metaData->scripts->postConfigure}";
        }

        return true;
    }

    /**
     * Add providers to config
     *
     * Add providers to provided config file, and the provided configuration key
     * name.
     *
     * @param array $config Configuration for provider including the file name and
     *                      configuration key name
     * @param array $providers List of providers to be added to configuration
     * @return void
     */
    protected function addProviders(array $config, array $providers)
    {
        // load config file
        $configFile = "{$this->app["appDir"]}Config/{$config["file"]}";
        $appConfig = file_get_contents($configFile);

        // get current providerList body
        preg_match("~\[[\"']{$config["key"]}['\"]\].+?\[(.*?)\];~s", $appConfig, $matches);
        $providerList = $matches[1];

        // append comma to last provider in list if needed
        preg_match_all("~^\s*?(['\"\\\\:\w\d_]+)(,?).*~m", $providerList, $matches);
        if (end($matches[2]) === "") {
            $newList = str_replace(end($matches[1]), end($matches[1]). ",", $providerList);
        } else {
            $newList = $providerList;
        }

        foreach ($providers as $provider) {
            if (strpos($newList, $provider) === false) {
                $newList .= "\n{$provider}::class,";
            }
        }
        $newList = rtrim($newList, ",") . "\n";

        $appConfig = str_replace($providerList, $newList, $appConfig);

        file_put_contents($configFile, $appConfig);
    }

    /**
     * Remove providers from config
     *
     * Remove providers from the provided config file, and the provided configuration
     * key name.
     *
     * @param array $config Configuration for provider including the file name and
     *                      configuration key name
     * @param array $providers List of providers to be added to configuration
     * @return void
     */
    protected function removeProviders(array $config, array $providers)
    {
        // load config file
        $configFile = "{$this->app["appDir"]}Config/{$config["file"]}";
        $appConfig = file_get_contents($configFile);

        foreach ($providers as $provider) {
            // remove each provider from the list
           $appConfig =  preg_replace(
                "~(\[[\"']{$config["key"]}['\"]\].+?\[.*){$provider}::class,?\n?(.*\];)~s",
                "$1$2",
                $appConfig
            );
        }

        // rewrite config file
        file_put_contents($configFile, $appConfig);
    }
}
