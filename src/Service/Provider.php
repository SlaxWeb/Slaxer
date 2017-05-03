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
 * @version   0.3
 */
namespace SlaxWeb\Slaxer\Service;

use Pimple\Container;
use Symfony\Component\Console\Application as CLIApp;
use SlaxWeb\Bootstrap\Commands\Component\InstallCommand;

class Provider implements \Pimple\ServiceProviderInterface
{
    /**
     * Register provider
     *
     * Register is called by the container, when the provider gets registered.
     *
     * @param \Pimple\Container $app Dependency Injection Container
     * @return void
     */
    public function register(Container $app)
    {
        $app["slaxer.service"] = function (Container $app) {
            $app = new CLIApp("Slaxer", "0.4.*-dev");

            $installCommand = new InstallCommand;
            $installCommand->init($app, new \GuzzleHttp\Client);
            $app->add($installCommand);

            if (isset($app["slaxerCommands"]) === false) {
                return $app;
            }

            foreach ($app["slaxerCommands"] as $key => $value) {
                $params = [];
                if (is_int($key)) {
                    $command = $value;
                } else {
                    $command = $key;
                    $params = $value;
                }

                $cmd = new $command;
                $cmd->init($app, ...$params);
                $app->add($cmd);
            }

            return $app;
        };
    }
}
