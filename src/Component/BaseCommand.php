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

class BaseCommand extends Command
{
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
}
