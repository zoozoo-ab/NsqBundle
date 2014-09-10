<?php
namespace Socloz\NsqBundle;

use Socloz\NsqBundle\Topic\Topic;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TopicRegistry
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $topics;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->topics = array();
    }

    /**
     * @param string $topic
     * @param string $topicServiceId
     */
    public function addTopicId($topic, $topicServiceId)
    {
        if (isset($this->topics[$topic])) {
            throw new \LogicException(sprintf('Topic "%s" has been already registered', $topic));
        }

        $this->topics[$topic] = $topicServiceId;
    }

    /**
     * @param $name
     *
     * @return Topic
     */
    public function getTopic($name)
    {
        if (false == isset($this->topics[$name])) {
            throw new \LogicException(sprintf('Topic "%s" is not registered', $name));
        }

        return $this->container->get($this->topics[$name]);
    }
}
