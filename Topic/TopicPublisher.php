<?php
namespace Socloz\NsqBundle\Topic;

use nsqphp\Message\Message;
use nsqphp\NsqPublisher;

class TopicPublisher
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var NsqPublisher
     */
    protected $publisher;

    /**
     * @var NsqPublisher
     */
    protected $delayedPublisher;

    /**
     * @var string
     */
    protected $delayedTopicName;

    public function __construct($name, NsqPublisher $publisher)
    {
        $this->name = $name;
        $this->publisher = $publisher;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param NsqPublisher $delayedPublisher
     * @param string       $topicName
     */
    public function setDelayedPublisher(NsqPublisher $delayedPublisher, $topicName)
    {
        $this->delayedPublisher = $delayedPublisher;
        $this->delayedTopicName = $topicName;
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
        if (false == $this->delayedPublisher) {
            throw new \Exception(sprintf('Topic "%s" cannot handle delayed messages, delayed.connection was not set', $this->name));
        }

        $data = [
            'topic' => $this->name,
            'payload' => $payload,
            'delay' => time() + (int) $delay
        ];

        $this->delayedPublisher->publish($this->delayedTopicName, new Message(json_encode($data)));
    }
}
