<?php
/**
 * Slaxer Service Provider
 *
 * Initiate the Symfony Console Component, and expose it to the DIC as a
 * service.
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
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class InstallCommand extends Command
{
    /**
     * Input
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $_input = null;

    /**
     * Output
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $_output = null;

    /**
     * Composer executable
     *
     * @var string
     */
    protected $_composer = "";

    /**
     * SlaxWeb Framework Instance
     *
     * @var \SlaxWeb\Bootstrap\Application
     */
    protected $_app = null;

    /**
     * Guzzle Client
     *
     * @var \GuzzleHttp\Client
     */
    protected $_client = null;

    /**
     * Packagist Base Url
     *
     * @var string
     */
    protected $_baseUrl = "";

    /**
     * Error string
     *
     * @var string
     */
    protected $_error = "";

    /**
     * Providers mapping
     *
     * Configuration file mapping for providers and their key names
     *
     * @var array
     */
    protected $_providersMap = [
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
    protected $_metaData = [];

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
        $this->_app = $app;
        $this->_client = $client;

        $this->_baseUrl = $this->_app["config.service"]["slaxer.baseUrl"];
    }

    /**
     * Configure the command
     *
     * Prepare the command for inclussion into the CLI Application Slaxer.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName("component:install")
            ->setDescription("Install SlaxWeb Framework Component")
            ->addArgument(
                "name",
                InputArgument::REQUIRED,
                "Which component do you want to install?"
            )->addArgument(
                "version",
                InputArgument::OPTIONAL,
                "Version to install"
            );
    }

    /**
     * Execute the command
     *
     * Check that the component exists on packagist. If no slash is found in the
     * name, component name is automatically prepended by 'slaxweb/', so that
     * SlaxWeb components are installed by default. If the package exists, it
     * checks that the 'composer' command is found and then proceeds by
     * installing the package with 'composer'.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Command Input Object
     * @param \Symfony\Component\Console\Output\OutputInterface $output Command Output Object
     * @return void
     *
     * @todo Run PostInstall.php script after package has been installed.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_input = $input;
        $this->_output = $output;

        $component = $this->_finalizeComponent([
            "name"          =>  strtolower($this->_input->getArgument("name")),
            "version"       =>  $this->_input->getArgument("version") ?? "",
            "installFlags"  =>  ""
        ]);

        $this->_output->writeln("<comment>Checking if component {$component["name"]} exists ...</>");
        if ($this->_checkComponentExists($component["name"]) === false) {
            $this->_output->writeln("<error>Component {$component["name"]} not found.</>");
            return;
        }
        $this->_output->writeln("<comment>OK</>");

        $this->_output->writeln("<comment>Checking if composer exists ...</>");
        if ($this->_setComposer() === false) {
            $this->_output->writeln(
                "<error>Composer not found. Make sure you have it installed, and is executable in your PATH</>"
            );
            return;
        }
        $this->_output->writeln("<comment>OK</>");

        $this->_output->writeln("<comment>Trying to install component {$component["name"]} ...</>");
        if ($this->_install($component) === false) {
            $this->_output->writeln("<error>{$this->_error}</>");
            return;
        }
        $this->_output->writeln("<comment>Component installed. Starting configuration of component</>");

        if ($this->_configure($component["name"]) === false) {
            $this->_output->writeln("<error>{$this->_error}</>");
            return;
        }
        $this->_output->writeln("<comment>OK</>");

        $this->_output->writeln("<comment>Component {$component["name"]} installed successfully.</>");
    }

    /**
     * Check Component Exists
     *
     * Try to find the component on packagist.
     *
     * @param string $component Component name to check for existance.
     * @return bool
     */
    protected function _checkComponentExists(string $component): bool
    {
        $response = $this->_client->request(
            "GET",
            "{$this->_baseUrl}{$component}",
            ["allow_redirects" => false]
        );
        return $response->getStatusCode() === 200;
    }

    /**
     * Set Composer Command
     *
     * Set the composer command. Returns bool(false) if no composer found.
     *
     * @return bool
     *
     * @todo: Install composer locally if not found
     */
    protected function _setComposer(): bool
    {
        ($this->_composer = trim(`which composer`)) || ($this->_composer = trim(`which composer.phar`));
        return $this->_composer !== "";
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
    protected function _finalizeComponent(array $component): array
    {
        $config = $this->_app["config.service"]["slaxer.componentSettings"][$component["name"]] ?? [];
        $defVer = $this->_app["config.service"]["slaxer.defaultVersion"]
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
     * Install component
     *
     * Installs the component and parses the meta data. If the meta data file does
     * not exist, or the component is not of type 'main' the component is removed.
     *
     * @param array $component Component data
     * @param bool $isMain If component is main
     * @return bool
     */
    protected function _install(array $component, bool $isMain = true): bool
    {
        $exit = 0;
        system(
            "{$this->_composer} require {$component["installFlags"]} {$component["name"]} {$component["version"]}",
            $exit
        );
        if ($exit !== 0) {
            $this->_error = "Composer command did not complete succesfully.";
            return false;
        }

        if ($this->_parseMetaData($component["name"]) === false) {
            return false;
        }

        if ($isMain && $this->_metaData->type !== "main") {
            $this->_remove($component["name"]);
            $this->_error = "Only components with type 'main' can be installed directly. Package removed.";
            return false;
        }

        return true;
    }

    /**
     * Remove component
     *
     * Removes the component with the help of composer.
     *
     * @param string $name Name of the component
     * @return bool
     */
    protected function _remove(string $name): bool
    {
        $exit = 0;
        system("{$this->_composer} remove {$name}", $exit);
        return $exit === 0;
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
    protected function _parseMetaData(string $name): bool
    {
        $metaFile = "{$this->_app["appDir"]}../vendor/{$name}/component.json";
        if (file_exists($metaFile) === false) {
            $this->_remove($name);
            $this->_error = "Not a valid component. 'component.json' meta data file is missing. Package removed.";
            return false;
        }

        $this->_metaData = json_decode(file_get_contents($metaFile));
        return true;
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
    protected function _configure(string $name): bool
    {
        // add providers to configuration
        if (empty($this->_metaData->providers) === false) {
            foreach ($this->_providersMap as $providerName => $map) {
                if (empty($this->_metaData->providers->{$providerName}) === false) {
                    $this->_addProviders($map, $this->_metaData->providers->{$providerName});
                }
            }
        }

        // Add configuration files to framework configuration directory
        foreach ($this->_metaData->configFiles as $file) {
            copy(
                "{$this->_app["appDir"]}../vendor/{$name}/config/{$file}",
                "{$this->_app["appDir"]}Config/{$file}"
            );
        }

        // install subcomponents
        if (empty($this->_metaData->subcomponents->list) === false) {
            $helper = $this->getHelper("question");
            $list = array_keys((array)$this->_metaData->subcomponents->list);
            if ($this->_metaData->subcomponents->required === false) {
                $list[] = "None";
            }
            $questionList = implode(", ", $list);
            $question = "Component '{$name}' provides the following sub-components to choose from.\n{$questionList}\n";
            if ($this->_metaData->subcomponents->multi) {
                $installSub = new ChoiceQuestion("{$question}\nChoice (multiple choices, separated by comma): ", $list);
                $installSub->setMultiselect(true);
            } else {
                $installSub = new Question("{$question}\nChoice: ", $list);
            }

            $subs = $helper->ask($this->_input, $this->_output, $installSub);
            $subs = is_string($subs) ? [$subs] : $subs;

            if (in_array("None", $subs) === false) {
                foreach ($subs as $sub) {
                    $version = $this->_metaData->subcomponents->list->{$sub};
                    $name = strpos($sub, "/") === false ? "slaxweb/{$sub}" : $sub;
                    $subComponent = ["name" => $name, "version" => $version, "installFlags" => ""];
                    if ($this->_install($subComponent, false) === false) {
                        $this->_error = "Error installing sub component. Leaving main component installed";
                        return false;
                    }
                }
            }
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
    protected function _addProviders(array $config, array $providers)
    {
        // load config file
        $configFile = "{$this->_app["appDir"]}Config/{$config["file"]}";
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
}
