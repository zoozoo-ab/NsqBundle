<?php
namespace Socloz\NsqBundle\Topic;

use nsqphp\Message\Message;
use nsqphp\NsqSubscriber;
use Socloz\NsqBundle\Consumer\ConsumerInterface;

class TopicSubscriber
{
    /**
     * @var NsqSubscriber
     */
    protected $subscriber;

    /**
     * @var string
     */
    protected $name;
    /**
     *
     * @var ConsumerInterface[]
     */
    private $consumers;

    /**
     * @param string        $name
     * @param NsqSubscriber $subscriber
     */
    public function __construct($name, NsqSubscriber $subscriber)
    {
        $this->name = $name;
        $this->subscriber = $subscriber;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->subscriber->getEventDispatcher();
    }

    /**
     * @param string            $channel
     * @param ConsumerInterface $consumer
     */
    public function addConsumer($channel, ConsumerInterface $consumer)
    {
        if (isset($this->consumers[$channel])) {
            throw new \Exception(sprintf('Consumer for topic "%s" channel "%s" has been already registered', $this->name, $channel));
        }

        $this->consumers[$channel] = $consumer;
    }

    /**
     * @param array $channels
     * @param null $exitAfterTimeout
     * @param null $exitAfterMessages
     *
     * @throws \Exception
     */
    public function consume(array $channels = array(), $exitAfterTimeout = null, $exitAfterMessages = null)
    {
        if (false == $this->consumers) {
            throw new \Exception(sprintf('Topic "%s" has no consumers', $this->name));
        }

        if (null !== $exitAfterTimeout) {
            $this->subscriber->setExitAfterTimeout($exitAfterTimeout);
        }

        if (null !== $exitAfterMessages) {
            $this->subscriber->setExitAfterMessages($exitAfterMessages);
        }

        $topic = $this->name;
        foreach ($this->consumers as $channel => $consumer) {
            if ($channels && !in_array($channel, $channels)) {
                continue;
            }

            $this->subscriber->subscribe($this->name, $channel, function(Message $message) use ($topic, $channel, $consumer) {
                $consumer->consume($topic, $channel, $message->getPayload());
            });
        }

        $this->subscriber->run();
    }
}
