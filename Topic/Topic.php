<?php

namespace Socloz\NsqBundle\Topic;

use nsqphp\nsqphp;
use nsqphp\Message\Message;
use nsqphp\NsqPublisher;
use nsqphp\NsqSubscriber;
use Socloz\NsqBundle\ConsumerCollection;

class Topic
{
    /**
     * @var NsqPublisher
     */
    protected $publisher;

    /**
     * @var NsqSubscriber
     */
    protected $subscriber;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var nsqphp
     */
    protected $delayedNsq;

    /**
     * @var string
     */
    protected $delayedTopicName;

    /**
     *
     * @var ConsumerCollection
     */
    private $consumers;

    public function __construct(NsqPublisher $publisher, NsqSubscriber $subscriber, $name)
    {
        $this->publisher = $publisher;
        $this->subscriber = $subscriber;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function getEventDispatcher()
    {
        return $this->subscriber->getEventDispatcher();
    }

    /**
     * @param nsqphp $nsq
     * @param string $topicName
     */
    public function setDelayedNsq(nsqphp $nsq, $topicName)
    {
        $this->delayedNsq = $nsq;
        $this->delayedTopicName = $topicName;
    }

    /**
     * @param ConsumerCollection $consumers
     */
    public function setConsumers(ConsumerCollection $consumers)
    {
        $this->consumers = $consumers;
    }

    /**
     * @return ConsumerCollection
     */
    public function getConsumers()
    {
        return $this->consumers;
    }

    /**
     * @param string $payload
     * @param int    $delay
     *
     * @throws \Exception
     */
    public function publish($payload, $delay = 0)
    {
        $delay > 0 ? $this->doPublishDelayed($payload, $delay) : $this->doPublish($payload);
    }

    /**
     * @param string $payload
     */
    protected function doPublish($payload)
    {
        $message = new Message((string) $payload);
        $this->publisher->publish($this->name, $message);
    }

    /**
     * @param string $payload
     * @param int    $delay
     *
     * @throws \Exception
     */
    protected function doPublishDelayed($payload, $delay)
    {
        if (false == $this->delayedNsq) {
            throw new \Exception(sprintf('Topic "%s" cannot handle delayed messages, delayed.connection was not set', $this->name));
        }

        $data = [
            'topic' => $this->name,
            'payload' => $payload,
            'delay' => time() + (int) $delay
        ];

        $this->delayedNsq->publish($this->delayedTopicName, new Message(json_encode($data)));
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
        if (false == $this->consumers->count()) {
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
