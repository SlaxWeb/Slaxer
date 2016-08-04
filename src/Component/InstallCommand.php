<?php
/**
 * Slaxer Install Component Command
 *
 * Install Component command contains functionality to install the command into
 * the Framework.
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

class InstallCommand extends BaseCommand
{


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
        $this->input = $input;
        $this->output = $output;

        $component = $this->finalizeComponent([
            "name"          =>  strtolower($this->input->getArgument("name")),
            "version"       =>  $this->input->getArgument("version") ?? "",
            "installFlags"  =>  ""
        ]);

        if ($this->componentExists($component["name"]) === false) {
            return;
        }

        if ($this->checkComposer() === false) {
            return;
        }

        $this->output->writeln("<comment>Trying to install component {$component["name"]} ...</>");
        if ($this->_install($component) === false) {
            $this->output->writeln("<error>{$this->_error}</>");
            return;
        }
        $this->output->writeln("<comment>Component installed. Starting configuration of component</>");

        if ($this->_configure($component["name"]) === false) {
            $this->output->writeln("<error>{$this->_error}</>");
            return;
        }
        $this->output->writeln("<comment>OK</>");

        $this->output->writeln("<comment>Component {$component["name"]} installed successfully.</>");
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
            "{$this->composer} require {$component["installFlags"]} {$component["name"]} {$component["version"]}",
            $exit
        );
        if ($exit !== 0) {
            $this->_error = "Composer command did not complete succesfully.";
            return false;
        }

        if ($this->parseMetaData($component["name"]) === false) {
            return false;
        }

        if ($isMain && $this->metaData->type !== "main") {
            $this->remove($component["name"]);
            $this->_error = "Only components with type 'main' can be installed directly. Package removed.";
            return false;
        }

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
        if (empty($this->metaData->providers) === false) {
            foreach ($this->providersMap as $providerName => $map) {
                if (empty($this->metaData->providers->{$providerName}) === false) {
                    $this->_addProviders($map, $this->metaData->providers->{$providerName});
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

        // install subcomponents
        if (empty($this->metaData->subcomponents->list) === false) {
            $helper = $this->getHelper("question");
            $list = array_keys((array)$this->metaData->subcomponents->list);
            if ($this->metaData->subcomponents->required === false) {
                $list[] = "None";
            }
            $questionList = implode(", ", $list);
            $question = "Component '{$name}' provides the following sub-components to choose from.\n{$questionList}\n";
            if ($this->metaData->subcomponents->multi) {
                $installSub = new ChoiceQuestion("{$question}\nChoice (multiple choices, separated by comma): ", $list);
                $installSub->setMultiselect(true);
            } else {
                $installSub = new Question("{$question}\nChoice: ", $list);
            }

            $subs = $helper->ask($this->input, $this->output, $installSub);
            $subs = is_string($subs) ? [$subs] : $subs;

            if (in_array("None", $subs) === false) {
                foreach ($subs as $sub) {
                    $version = $this->metaData->subcomponents->list->{$sub};
                    $name = strpos($sub, "/") === false ? "slaxweb/{$sub}" : $sub;
                    $subComponent = ["name" => $name, "version" => $version, "installFlags" => ""];
                    if ($this->_install($subComponent, false) === false) {
                        $this->_error = "Error installing sub component. Leaving main component installed";
                        return false;
                    }
                    if ($this->_configure($name) === false) {
                        $this->_error = "Subcomponent configuration failed. Leaving main component installed";
                        return false;
                    }
                }
            }
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
    protected function _addProviders(array $config, array $providers)
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
}
