<?php
namespace Socloz\NsqBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RegisterEventListenerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $eventDispatchers = [];
        foreach ($container->findTaggedServiceIds('socloz.nsq.event_dispatcher') as $id => $arguments) {
            $eventDispatchers[$arguments[0]['connection']] = $container->getDefinition($id);
        }

        foreach ($container->findTaggedServiceIds('socloz.nsq.event_subscriber') as $id => $arguments) {
            $eventSubscriber = $container->getDefinition($id);

            if (false == isset($arguments[0]['connection'])) {
                foreach ($eventDispatchers as $eventDispatcher) {
                    $eventDispatcher->addMethodCall('addSubscriberService', array($id, $eventSubscriber->getClass()));
                }

                continue;
            }

            if (false == isset($eventDispatchers[$arguments[0]['connection']])) {
                throw new \InvalidArgumentException(sprintf('Unknown connection name "%s" for subscriber "%s"', $arguments[0]['connection'], $id));
            }

            $eventDispatchers[$arguments[0]['connection']]->addMethodCall('addSubscriberService', array($id, $eventSubscriber->getClass()));
        }
    }
}
