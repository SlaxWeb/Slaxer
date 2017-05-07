<?php
namespace SlaxWeb\Slaxer;

use Psr\Log\LoggerInterface as Logger;
use Symfony\Component\Console\Command\Command;

/**
 * Base Command
 *
 * Abstract Command to simplify and unify initialization of the command. All SlaxWeb
 * Framework commands should extend from this base command.
 *
 * @package   SlaxWeb\Bootstrap
 * @author    Tomaz Lovrec <tomaz.lovrec@gmail.com>
 * @copyright 2017 (c) SlaxWeb
 * @license   MIT <https://opensource.org/licenses/MIT>
 * @link      https://github.com/slaxweb
 * @version   0.4
 */
abstract class AbstractCommand extends Command
{
    /**
     * Logger instance
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger = null;

    /**
     * Constructor
     *
     * Set the logger instance to class properties.
     *
     * @param \Psr\Log\LoggerInterface $logger Logger instance
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;

        parent::__construct();

        $this->logger->info(
            "Custom command has been initialized and configured",
            ["class" => get_class($this), "name" => $this->getName()]
        );
    }
}
