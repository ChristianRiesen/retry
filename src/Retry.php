<?php

namespace ChristianRiesen\Retry;

/**
 * Wraps a operation, represented as a callable, in retry logic.
 *
 * The class implements the retry logic for you by re-executing your callable
 * in case of temporary errors where retrying the failed operation, after a
 * short delay usually resolves the problem. Just wrap your operation in this
 * class and invoke it. You can also pass arguments when invoking the wrapper
 * which will be passed through to the underlying callable.
 *
 * @author Tobias Schultze <http://tobion.de>
 * @author Christian Riesen <http://christianriesen.com>
 */
class Retry
{
    /**
     * The operation to execute that can be retried on failure.
     *
     * @var callable
     */
    private $callable;

    /**
     * Maximum number of retries.
     *
     * @var integer
     */
    private $maxRetries;

    /**
     * Delay between retries in milliseconds.
     *
     * @var integer
     */
    private $retryDelay;

    /**
     * Actual number of retries.
     *
     * @var integer|null
     */
    private $retries;

    /**
     * Exceptions to catch and retry on.
     *
     * @var array
     */
    private $exceptions = array();

    /**
     * Constructor to wrap a callable.
     *
     * @param callable     $callable   The operation to execute that can be retried on failure.
     * @param string|array $exceptions Exceptions to catch and retry
     * @param integer      $maxRetries Maximum number of retries.
     * @param integer      $retryDelay Delay between retries in milliseconds
     */
    public function __construct($callable, $exceptions = 'Exception', $maxRetries = 3, $retryDelay = 100)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Callable parameter needs to be a callable');
        }

        $this->callable = $callable;

        if (is_string($exceptions)) {
            $exceptions = array($exceptions);
        }

        if (is_array($exceptions)) {
            if (count($exceptions) == 0) {
                throw new \InvalidArgumentException('Exceptions given as array but is empty');
            }
        } else {
            throw new \InvalidArgumentException('Exceptions needs to be string or array');
        }

        $this->exceptions = $exceptions;

        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
    }

    /**
     * Returns the number of retries used.
     *
     * @return integer|null The number of retries used or null if wrapper has not been invoked yet
     */
    public function getRetries()
    {
        return $this->retries;
    }

    /**
     * Executes the callable and retries it in case of a configured exception happening
     *
     * Will execute again if configured exceptions happen. Other exceptions will be ignored and run up the stack.
     *
     * All arguments given will be passed through to the wrapped callable.
     *
     * @return mixed The return value of the wrapped callable
     *
     * @throws \Exception When retries are exceeded or retry is not configured for it
     */
    public function __invoke()
    {
        $this->retries = 0;
        $args = func_get_args();

        do {
            try {
                return call_user_func_array($this->callable, $args);
            } catch (\Exception $e) {
                // Catching all then checking what exception it is
                $found = false;

                foreach ($this->exceptions as $retryableException) {
                    if ($e instanceof $retryableException) {
                        $found = true;
                        break;
                    }
                }

                if (false === $found) {
                    throw $e;
                }

                if ($this->retries < $this->maxRetries) {
                    $this->retries++;
                    usleep($this->retryDelay * 1000);
                } else {
                    throw $e;
                }
            }
        } while (true);
    }
}
