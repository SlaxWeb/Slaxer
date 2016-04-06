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
namespace SlaxWeb\Slaxer\Component;

use SlaxWeb\Bootstrap\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
    /**
     * Guzzle Client
     *
     * @var \GuzzleHttp\Client
     */
    protected $_client = null;

    /**
     * SlaxWeb Framework Instance
     *
     * @var \SlaxWeb\Bootstrap\Application
     */
    protected $_app = null;

    /**
     * Packagist Base Url
     *
     * @var string
     */
    protected $_baseUrl = "https://packagist.org/packages/";

    /**
     * Construct Class
     *
     * Store the GuzzleHTTP Client object to the class property.
     *
     * @param \GuzzleHttp\Client $client Guzzle Client
     * @param \SlaxWeb\Bootstrap\Application $app Framework instance
     * @return void
     */
    public function __construct(\GuzzleHttp\Client $client, Application $app)
    {
        $this->_client = $client;
        $this->_app = $app;

        parent::__construct();
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
        $component = strtolower($input->getArgument("name"));
        $version = $input->getArgument("version") ?? "dev-master";
        if (strpos($component, "/") === false) {
            $component = "slaxweb/{$component}";
        }

        $output->writeln(
            "<comment>Checking if component {$component} exists ...</>"
        );
        if ($this->_checkComponentExists($component) === false) {
            $output->writeln("<error>Component {$component} not found.</>");
            return;
        }
        $output->writeln(
            "<comment>OK</>"
        );

        $output->writeln(
            "<comment>Checking if composer exists ...</>"
        );
        if (($cmd = $this->_getComposer()) === "") {
            $output->writeln("<error>Composer not found.</>");
            return;
        }
        $output->writeln(
            "<comment>OK</>"
        );

        $output->writeln(
            "<comment>Trying to install component {$component} ...</>"
        );
        $exit = 0;
        system("{$cmd} require {$component} {$version}", $exit);
        if ($exit !== 0) {
            $output->writeln("<error>Composer did not exit as expected.</>");
            return;
        }
        $output->writeln(
            "<comment>OK</>"
        );

        $output->writeln(
            "<comment>Check 'PostInstall' script exists and run it</>"
        );
        if (file_exists("{$this->_app["appDir"]}../vendor/{$component}/install/PostInstall.php")) {
            require "{$this->_app["appDir"]}../vendor/{$component}/install/PostInstall.php";
            if (run($this->_app) !== 0) {
                $output->writeln(
                    "<error>'PostInstall' ran with errors</>"
                );
            } else {
                $output->writeln(
                    "<comment>'PostInstall' found and executed successfuly</>"
                );
            }
        }
        $output->writeln(
            "<comment>OK</>"
        );

        $output->writeln(
            "<comment>Component {$component} installed successfully.</>"
        );
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
     * Get Composer Command
     *
     * Get the composer command. Returns an empty string if no composer found.
     *
     * @return string
     *
     * @todo Install composer locally if not found
     */
    protected function _getComposer(): string
    {
        ($cmd = trim(`which composer`)) || ($cmd = trim(`which composer.phar`));
        return $cmd;
    }
}
