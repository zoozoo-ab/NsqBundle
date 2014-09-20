<?php

namespace Socloz\NsqBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class SoclozNsqExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yml');
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->loadConnections($config, $container);
        $this->loadPublishers($config, $container);
        $this->loadSubscribers($config, $container);
        $this->loadTopics($config, $container);
        $this->loadConsumers($config, $container);
    }

    protected function loadSubscribers(array $config, ContainerBuilder $container)
    {
        foreach ($config['topics'] as $name => $topic) {
            if (false == isset($config['connections'][$topic['connection']])) {
                throw new \Exception(sprintf('Connection "%s" is missing', $topic['connection']));
            }

            $connection = $config['connections'][$topic['connection']];

            // event dispatcher
            $eventDispatcher = new Definition('Symfony\\Component\\EventDispatcher\\ContainerAwareEventDispatcher');
            $eventDispatcher->addArgument(new Reference('service_container'));
            $eventDispatcher->addTag('socloz.nsq.event_dispatcher', array('connection' => $name));

            $eventDispatcherId = $this->getTopicId($name) . '.event_dispatcher';
            $container->setDefinition($eventDispatcherId, $eventDispatcher);

            // lookupd
            if ($connection['lookupd_hosts']) {
                $lookupd = new Definition('nsqphp\Lookup\Nsqlookupd', array($connection['lookupd_hosts']));
            } else {
                $lookupd = new Definition('nsqphp\Lookup\FixedHosts', array($connection['publish_to']));
            }

            $lookupd->setPublic(false);

            $lookupdId = $this->getTopicId($name) . '.lookupd';
            $container->setDefinition($lookupdId, $lookupd);

            // requeue_strategy
            if ($connection['requeue_strategy']['enabled']) {
                $requeueStrategy = new Definition('nsqphp\RequeueStrategy\DelaysList');
                $requeueStrategy->addArgument($connection['requeue_strategy']['max_attempts']);
                $requeueStrategy->addArgument($connection['requeue_strategy']['delays']);
                $requeueStrategy->setPublic(false);

                $requeueStrategyId = $this->getTopicId($name) . '.requeue_strategy';
                $container->setDefinition($requeueStrategyId, $requeueStrategy);

                $requeueStrategySubscriber = new Definition('nsqphp\Event\Subscriber\RequeueSubscriber');
                $requeueStrategySubscriber->addArgument(new Reference($requeueStrategyId));
                $requeueStrategySubscriber->addTag('socloz.nsq.event_subscriber', array('connection' => $name));

                $requeueStrategySubscriberId = $this->getTopicId($name) . '.subscriber.requeue_strategy';
                $container->setDefinition($requeueStrategySubscriberId, $requeueStrategySubscriber);
            }

            // connection factory
            $connectionFactory = new Definition('nsqphp\\Connection\\ConnectionFactory');
            $connectionFactory->setArguments(array(true));
            $connectionFactory->setPublic(false);

            $connectionFactoryId = $this->getTopicId($name) . '.connection_factory';
            $container->setDefinition($connectionFactoryId, $connectionFactory);

            // subscriber
            $subscriber = new Definition('nsqphp\\NsqSubscriber');
            $subscriber->addArgument(new Reference($lookupdId));
            $subscriber->addArgument(new Reference($connectionFactoryId));
            $subscriber->addMethodCall('setEventDispatcher', array(new Reference($eventDispatcherId)));
            $subscriber->setPublic(false);

            $subscriberId = $this->getTopicId($name) . '.subscriber';
            $container->setDefinition($subscriberId, $subscriber);
        }
    }

    protected function loadPublishers(array $config, ContainerBuilder $container)
    {
        foreach ($config['topics'] as $name => $topic) {
            $definition = new Definition('nsqphp\\NsqPublisher');
            $definition->addArgument(new Reference($this->getConnectionId($topic['connection'])));
            $definition->addArgument($topic['retries']);
            $definition->addArgument($topic['retry_delay']);
            $definition->setPublic(false);

            $container->setDefinition($this->getTopicId($name) . '.publisher', $definition);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function loadConnections(array $config, ContainerBuilder $container)
    {
        foreach ($config['connections'] as $name => $connection) {
            list($host, $port) = explode(':', $connection['publish_to'][0]);

            $nsq = new Definition('nsqphp\\Connection\\Connection');
            $nsq->addArgument($host);
            $nsq->addArgument($port);
            $nsq->setPublic(false);

            $container->setDefinition($this->getConnectionId($name), $nsq);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @throws \Exception
     */
    protected function loadTopics(array $config, ContainerBuilder $container)
    {
        $registry = $container->getDefinition('socloz.nsq.registry');

        foreach ($config['topics'] as $name => $topic) {
            $definition = new Definition('Socloz\\NsqBundle\\Topic\\Topic');
            $container->setDefinition($this->getTopicId($name), $definition);
            $registry->addMethodCall('addTopicId', array($name, $this->getTopicId($name)));

            $publisherId = $this->getTopicId($name) . '.publisher';
            $subscriberId = $this->getTopicId($name) . '.subscriber';

            $definition->addArgument(new Reference($publisherId));
            $definition->addArgument(new Reference($subscriberId));
            $definition->addArgument($name);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function loadConsumers(array $config, ContainerBuilder $container)
    {
        foreach ($config['topics'] as $topicName => $topicConfig) {
            $consumerCollectionDefinition = new Definition('Socloz\\NsqBundle\\ConsumerCollection');
            $consumerCollectionDefinition->addArgument(new Reference('service_container'));
            $consumerCollectionDefinition->setPublic(false);

            $consumerCollectionId = $this->getTopicId($topicName) . '.consumer_collection';
            $container->setDefinition($consumerCollectionId, $consumerCollectionDefinition);

            $topicDefinition = $container->getDefinition($this->getTopicId($topicName));
            $topicDefinition->addMethodCall('setConsumers', [new Reference($consumerCollectionId)]);

            foreach ($topicConfig['consumers'] as $channelName => $consumerServiceId) {
                $consumerCollectionDefinition->addMethodCall('addConsumerId', [$channelName, $consumerServiceId]);
            }
        }
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getConnectionId($name)
    {
        return 'socloz.nsq.connection.' . $name;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getTopicId($name)
    {
        return 'socloz.nsq.topic.' . $name;
    }
}
