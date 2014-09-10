<?php
namespace Socloz\NsqBundle\Event;

use nsqphp\Event\Events;
use nsqphp\Event\MessageErrorEvent;
use Socloz\NsqBundle\Exception\DropMessageException;
use Socloz\NsqBundle\Exception\RequeueMessageException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MessageExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public function onMessageError(MessageErrorEvent $event)
    {
        if ($event->getException() instanceof DropMessageException) {
            $event->setRequeueDelay(null);

            return;
        }

        if ($event->getException() instanceof RequeueMessageException) {
            $event->setRequeueDelay($event->getException()->getDelay());

            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::MESSAGE_ERROR => array('onMessageError', -100),
        );
    }
}
