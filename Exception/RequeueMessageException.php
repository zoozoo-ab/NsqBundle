<?php
namespace Socloz\NsqBundle\Exception;

class RequeueMessageException extends \Exception implements MessageExceptionInterface
{
    /**
     * @var int
     */
    protected $delay;

    public function __construct($delay, $message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        if (false == is_int($delay) || 0 > $delay) {
            throw new \InvalidArgumentException('Delay have to be int greater than zero');
        }

        $this->delay = $delay;
    }

    /**
     * @return int
     */
    public function getDelay()
    {
        return $this->delay;
    }
}
