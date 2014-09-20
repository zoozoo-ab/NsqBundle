<?php
namespace Socloz\NsqBundle;

use Socloz\NsqBundle\Topic\TopicPublisher;
use Socloz\NsqBundle\Topic\TopicSubscriber;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Registry
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var TopicPublisher[]
     */
    protected $publishers;

    /**
     * @var TopicSubscriber[]
     */
    protected $subscribers;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->topics = array();
    }

    public function addPublisherServiceId($name, $serviceId)
    {
        if (isset($this->publishers[$name])) {
            throw new \Exception(sprintf('Publisher for topic "%s" has been already registered', $name));
        }

        $this->publishers[$name] = $serviceId;
    }

    public function addSubscriberServiceId($name, $serviceId)
    {
        if (isset($this->subscribers[$name])) {
            throw new \Exception(sprintf('Subscriber for topic "%s" has been already registered', $name));
        }

        $this->subscribers[$name] = $serviceId;
    }

    /**
     * @param string $name
     *
     * @return TopicPublisher
     */
    public function getPublisher($name)
    {
        if (false == isset($this->publishers[$name])) {
            throw new \Exception();
        }

        return $this->container->get($this->publishers[$name]);
    }

    /**
     * @param string $name
     *
     * @return TopicSubscriber
     */
    public function getSubscriber($name)
    {
        if (false == isset($this->subscribers[$name])) {
            throw new \Exception();
        }

        return $this->container->get($this->subscribers[$name]);
    }
}
