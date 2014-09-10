<?php
namespace Socloz\NsqBundle\Event;

use nsqphp\Event\Event;
use nsqphp\Event\Events;
use nsqphp\Event\MessageErrorEvent;
use nsqphp\Event\MessageEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OutputLoggerSubscriber implements EventSubscriberInterface
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param Event $event
     */
    public function onHearbeat(Event $event)
    {
        $this->output->writeln(sprintf('HEARTBEAT topic[<comment>%s</comment>] channel[<comment>%s</comment>]', $event->getTopic(), $event->getChannel()));
    }

    /**
     * @param MessageEvent $event
     */
    public function onMessage(MessageEvent $event)
    {
        $this->output->writeln(sprintf('MESSAGE topic[<comment>%s</comment>] channel[<comment>%s</comment>] id[<comment>%s</comment>] attempts[<comment>%d</comment>]', $event->getTopic(), $event->getChannel(), $event->getMessage()->getId(), $event->getMessage()->getAttempts()));
        $this->output->writeln(sprintf('--> PAYLOAD: %s', $event->getMessage()->getPayload()));
    }

    /**
     * @param MessageEvent $event
     */
    public function onMessageSuccess(MessageEvent $event)
    {
        $this->output->writeln('--> SUCCESS');
    }

    /**
     * @param MessageErrorEvent $event
     */
    public function onMessageError(MessageErrorEvent $event)
    {
        $this->output->writeln(sprintf('--> ERROR "%s"', get_class($event->getException())));
        $this->output->writeln(sprintf('--> ERROR "%s"', $event->getException()->getMessage()));
    }

    /**
     * @param MessageErrorEvent $event
     */
    public function onMessageRequeue(MessageErrorEvent $event)
    {
        $this->output->writeln(sprintf('--> REQUEUE DELAY: %d', $event->getRequeueDelay()));
    }

    /**
     * @param MessageErrorEvent $event
     */
    public function onMessageDrop(MessageErrorEvent $event)
    {
        $this->output->writeln('--> DROP');
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::HEARTBEAT => array('onHearbeat', -1000),
            Events::MESSAGE => array('onMessage', -1000),
            Events::MESSAGE_SUCCESS => array('onMessageSuccess', -1000),
            Events::MESSAGE_ERROR => array('onMessageError', -1000),
            Events::MESSAGE_REQUEUE => array('onMessageRequeue', -1000),
            Events::MESSAGE_DROP => array('onMessageDrop', -1000),
        );
    }
}
