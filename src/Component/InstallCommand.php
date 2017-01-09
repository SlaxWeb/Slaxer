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
 * @todo: needs a complete rewrite in the future, structure of the code here is catastrophic! Author: slax0r
 */
namespace SlaxWeb\Slaxer\Component;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $component = $this->getComponent([
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
        if ($this->install($component) === false) {
            $this->output->writeln("<error>ERROR: {$this->error}</>");
            return;
        }
        $this->output->writeln("<comment>Component installed. Starting configuration of component</>");

        if ($this->configComponent($component["name"]) === false) {
            $this->output->writeln("<error>ERROR: {$this->error}</>");
            return;
        }
        $this->output->writeln("<comment>OK</>");

        if ($this->installSub($component["name"]) === false) {
            $this->output->writeln("<error>WARNING: {$this->error}</>");
        }

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
    protected function install(array $component, bool $isMain = true): bool
    {
        $exit = 0;
        system(
            "{$this->composer} require {$component["installFlags"]} {$component["name"]} {$component["version"]}",
            $exit
        );
        if ($exit !== 0) {
            $this->error = "Composer command did not complete succesfully.";
            $this->logger->error($this->error);
            return false;
        }

        if ($this->parseMetaData($component["name"]) === false) {
            return false;
        }

        if ($isMain && $this->metaData->type !== "main") {
            $this->remove($component["name"]);
            $this->error = "Only components with type 'main' can be installed directly. Package removed.";
            $this->logger->error($this->error);
            return false;
        }

        return true;
    }

    /**
     * Install SubComponent
     *
     * Installs the sub-component and parses its config, just as when installing
     * a main component.
     *
     * @param string $name Component name
     * @return bool
     */
    protected function installSub(string $name): bool
    {
        $this->logger->debug("Installing subcomponents for '{$name}'");
        $this->output->writeln("<comment>Component configured. Attempting to install Sub-Components...</>");

        $subComponents = (array)$this->metaData->subcomponents->list;
        if (empty($subComponents)) {
            $this->logger->info("No subcomponents found for component '{$name}'.");
            $this->output->writeln("<comment>No sub components found for current component.</>");
            return true;
        }
        $helper = $this->getHelper("question");
        $list = array_keys($subComponents);
        if ($this->metaData->subcomponents->required === false) {
            $this->logger->debug(
                "Component '{$name}' does not require subcomponents to be installed, adding 'None' to choice list"
            );
            $list[] = "None";
        }
        $questionList = implode(", ", $list);
        $question = "Component '{$name}' provides the following sub-components to choose from.\n{$questionList}\n";
        if ($this->metaData->subcomponents->multi) {
            $this->logger->debug("Multiple subcomponents may be installed.");
            $installSub = new ChoiceQuestion("{$question}\nChoice (multiple choices, separated by comma): ", $list);
            $installSub->setMultiselect(true);
        } else {
            $this->logger->debug("Only one subcomponent can be installed for this component.");
            $installSub = new Question("{$question}\nChoice: ", $list);
        }

        $subs = $helper->ask($this->input, $this->output, $installSub);
        $subs = is_string($subs) ? [$subs] : $subs;

        if (in_array("None", $subs) === false) {
            foreach ($subs as $sub) {
                $version = $subComponents[$sub];
                $name = strpos($sub, "/") === false ? "slaxweb/{$sub}" : $sub;
                $subComponent = ["name" => $name, "version" => $version, "installFlags" => ""];
                $this->logger->info("Installing subcomponent.", $subComponent);
                if ($this->install($subComponent, false) === false) {
                    $this->error = "Error installing sub component. Leaving main component installed";
                    $this->logger->error("Subcomponent installation failed, aborting further installation.");
                    return false;
                }
                if ($this->configComponent($name) === false) {
                    $this->error = "Subcomponent configuration failed. Leaving main component installed";
                    $this->logger->error("Subcomponent configuration failed, aborting further installation.");
                    return false;
                }
            }
        }
        $this->output->writeln("<comment>OK</>");
        return true;
    }
}
