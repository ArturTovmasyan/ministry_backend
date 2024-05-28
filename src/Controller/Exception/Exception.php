<?php

namespace App\Controller\Exception;

use Throwable;

/**
 * Class Exception
 * @package AppBundle\Controller\Rest\Exception
 */
class Exception extends \Exception
{
    /**
     * @var array
     */
    private $data;

    /**
     * Exception constructor.
     * @param string $message
     * @param int $code
     * @param array $data
     * @param Throwable|null $previous
     */
    public function __construct(string $message = '', int $code = 0, array $data = [], Throwable $previous = null)
    {
        $this->data = $data;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}