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
        $this->setName("component:remove")
            ->setDescription("Remove SlaxWeb Framework Component")
            ->addArgument(
                "name",
                InputArgument::REQUIRED,
                "Which component do you want to remove?"
            );
    }

    /**
     * Execute the command
     *
     * Check that the component exists on packagist. If no slash is found in the
     * name, component name is automatically prepended by 'slaxweb/', if the package
     * exists, it checks that the 'composer' command is found and then proceeds by
     * removing the package with 'composer' if the component is installed.
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

        if ($this->checkComposer() === false) {
            return;
        }

        if ($this->isInstalled($component["name"]) === false) {
            return;
        }

        $this->output->writeln("<comment>Removing component</>");
        if ($this->remove($component["name"]) === false) {
            $this->output->writeln(
                "<error>ERROR: An error occured while removing component. Check the output above.</>"
            );
        }
        $this->output->writeln("<comment>Component {$component["name"]} removed.</>");
    }
}
