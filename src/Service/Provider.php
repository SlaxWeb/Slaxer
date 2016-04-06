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
 */
namespace SlaxWeb\Slaxer\Service;

use Pimple\Container;
use SlaxWeb\Slaxer\InstallComponentCommand;
use Symfony\Component\Console\Application as CLIApp;

class Provider implements \Pimple\ServiceProviderInterface
{
    /**
     * Register provider
     *
     * Register is called by the container, when the provider gets registered.
     *
     * @param \Pimple\Container $container Dependency Injection Container
     * @return void
     *
     * @todo load the Install Package Command
     */
    public function register(Container $container)
    {
        $container["slaxer.service"] = function (Container $cont) {
            $app = new CLIApp("Slaxer", "0.1.0");

            $app->add(new InstallComponentCommand(new \GuzzleHttp\Client));

            if (isset($cont["slaxerCommands"]) === false) {
                return $app;
            }

            foreach ($cont["slaxerCommands"] as $key => $value) {
                $command = "";
                $params = [];
                if (is_int($key)) {
                    $command = $value;
                } else {
                    $command = $key;
                    $params = $value;
                }

                $app->add(new $command(...$params));
            }

            return $app;
        };
    }
}
