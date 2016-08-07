<?php
/**
 * Slaxer Remove Component Command
 *
 * Remove Component command contains functionality to remove the command from the
 * Framework.
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

use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveCommand extends BaseCommand
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
        $this->setName("component:reconfigure")
            ->setDescription("Reconfigure SlaxWeb Framework Component")
            ->addArgument(
                "name",
                InputArgument::REQUIRED,
                "Which component do you want to reconfigure?"
            );
    }

    /**
     * Execute the command
     *
     * Ensure the component is indeed installed, and run component configuration
     * again. This will copy all the configuraiton over the existing one, so the
     * executor is warned about this, and must confirm this action.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input Command Input Object
     * @param \Symfony\Component\Console\Output\OutputInterface $output Command Output Object
     * @return void
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

        if ($this->isInstalled($component["name"]) === false) {
            return;
        }

        $confirm = new Question(
            "WARNING! This will run a complete reconfiguration of the component. "
            . "Any changes made in existing configuration of the component will be discarded."
            . "\nProceed? (y/N): ",
            ["y", "n"]
        );

        $reconfig = strtolower($this->getHelper("question")->ask($this->input, $this->output, $confirm)) === "y";
        if ($reconfig === false) {
            $this->output->writeln("<comment>Reconfiguration aborted.</>");
            return;
        }

        $this->output->writeln("<comment>Beginning reconfiguration.</>");
        if ($this->configComponent($component["name"]) === false) {
            $this->output->writeln("<error>ERROR: Component reconfiguration failed.</>");
        }
        $this->output->writeln("<comment>Component{$component["name"]} successfully reconfigured.</>");
    }
}
