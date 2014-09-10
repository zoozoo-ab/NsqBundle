<?php
namespace Socloz\NsqBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;

class ConsumerCollection implements \Iterator, \Countable
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $consumers;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->consumers = array();
    }

    /**
     * @param string $channel
     * @param string $consumerServiceId
     */
    public function addConsumerId($channel, $consumerServiceId)
    {
        if (isset($this->consumers[$channel])) {
            throw new \LogicException(sprintf('Channel "%s" has been already registered', $channel));
        }

        $this->consumers[$channel] = $consumerServiceId;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        if (false == $this->valid()) {
            return false;
        }

        return $this->container->get(current($this->consumers));
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return null !== key($this->consumers);
    }

    /**
     * @inheritdoc
     */
    public function rewind()
    {
        reset($this->consumers);
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        next($this->consumers);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return key($this->consumers);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->consumers);
    }
}
