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
     * Packagist Base Url
     *
     * @var string
     */
    protected $baseUrl = "";

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

        ($this->_composer = trim(`which composer`)) || ($this->_composer = trim(`which composer.phar`));
        if ($this->_composer === "") {
            $this->output->writeln(
                "<error>Composer not found. Make sure you have it installed, and is executable in your PATH</>"
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
        $this->output->writeln("<comment>Checking if component {$component["name"]} exists ...</>");

        $response = $this->client->request(
            "GET",
            "{$this->baseUrl}{$component}",
            ["allow_redirects" => false]
        );
        if ($response->getStatusCode() !== 200) {
            $this->output->writeln("<error>Component {$component["name"]} not found.</>");
            return false;
        }

        $this->output->writeln("<comment>OK</>");
        return true;
    }
}
