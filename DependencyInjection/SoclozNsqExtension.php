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

        $this->loadPublishers($config, $container);
        $this->loadSubscribers($config, $container);
        $this->loadConsumers($config, $container);
        $this->loadRegistry($config, $container);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function loadPublishers(array $config, ContainerBuilder $container)
    {
        foreach ($config['topics'] as $name => $topic) {
            if (false == isset($config['connections'][$topic['connection']])) {
                throw new \Exception(sprintf('Connection "%s" is missing', $topic['connection']));
            }

            $connectionConf = $config['connections'][$topic['connection']];

            // Connection
            list($host, $port) = explode(':', $connectionConf['publish_to']);

            $connection = new Definition('nsqphp\\Connection\\Connection');
            $connection->addArgument($host);
            $connection->addArgument($port);
            $connection->setPublic(false);

            $connectionId = $this->getTopicId($name) . '.connection';
            $container->setDefinition($connectionId, $connection);

            // NsqPublisher
            $nsqPublisher = new Definition('nsqphp\\NsqPublisher');
            $nsqPublisher->addArgument(new Reference($connectionId));
            $nsqPublisher->addArgument($connectionConf['retries']);
            $nsqPublisher->addArgument($connectionConf['retry_delay']);
            $nsqPublisher->setPublic(false);

            $nsqPublisherId = $this->getTopicId($name) . '.nsq.pub';
            $container->setDefinition($nsqPublisherId, $nsqPublisher);

            // TopicPublisher
            $topicPublisher = new Definition('Socloz\\NsqBundle\\Topic\\TopicPublisher');
            $topicPublisher->addArgument($name);
            $topicPublisher->addArgument(new Reference($nsqPublisherId));

            $topicPublisherId = $this->getTopicId($name) . '.publisher';
            $container->setDefinition($topicPublisherId, $topicPublisher);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function loadSubscribers(array $config, ContainerBuilder $container)
    {
        foreach ($config['topics'] as $name => $topic) {
            if (false == isset($config['connections'][$topic['connection']])) {
                throw new \Exception(sprintf('Connection "%s" is missing', $topic['connection']));
            }

            $connectionConf = $config['connections'][$topic['connection']];

            // event dispatcher
            $eventDispatcher = new Definition('Symfony\\Component\\EventDispatcher\\ContainerAwareEventDispatcher');
            $eventDispatcher->addArgument(new Reference('service_container'));
            $eventDispatcher->addTag('socloz.nsq.event_dispatcher', array('topic' => $name));

            $eventDispatcherId = $this->getTopicId($name) . '.event_dispatcher';
            $container->setDefinition($eventDispatcherId, $eventDispatcher);

            // lookupd
            if ($connectionConf['lookupd_hosts']) {
                $lookupd = new Definition('nsqphp\Lookup\Nsqlookupd', array($connectionConf['lookupd_hosts']));
            } else {
                $lookupd = new Definition('nsqphp\Lookup\FixedHosts', array($connectionConf['publish_to']));
            }

            $lookupd->setPublic(false);

            $lookupdId = $this->getTopicId($name) . '.lookupd';
            $container->setDefinition($lookupdId, $lookupd);

            // requeue_strategy
            if ($connectionConf['requeue_strategy']['enabled']) {
                $requeueStrategy = new Definition('nsqphp\RequeueStrategy\DelaysList');
                $requeueStrategy->addArgument($connectionConf['requeue_strategy']['max_attempts']);
                $requeueStrategy->addArgument($connectionConf['requeue_strategy']['delays']);
                $requeueStrategy->setPublic(false);

                $requeueStrategyId = $this->getTopicId($name) . '.requeue_strategy';
                $container->setDefinition($requeueStrategyId, $requeueStrategy);

                $requeueStrategySubscriber = new Definition('nsqphp\Event\Subscriber\RequeueSubscriber');
                $requeueStrategySubscriber->addArgument(new Reference($requeueStrategyId));
                $requeueStrategySubscriber->addTag('socloz.nsq.event_subscriber');

                $requeueStrategySubscriberId = $this->getTopicId($name) . '.subscriber.requeue_strategy';
                $container->setDefinition($requeueStrategySubscriberId, $requeueStrategySubscriber);
            }

            // connection factory
            $connectionFactory = new Definition('nsqphp\\Connection\\ConnectionFactory');
            $connectionFactory->setArguments(array(true));
            $connectionFactory->setPublic(false);

            $connectionFactoryId = $this->getTopicId($name) . '.connection_factory';
            $container->setDefinition($connectionFactoryId, $connectionFactory);

            // NsqSubscriber
            $nsqSubscriber = new Definition('nsqphp\\NsqSubscriber');
            $nsqSubscriber->addArgument(new Reference($lookupdId));
            $nsqSubscriber->addArgument(new Reference($connectionFactoryId));
            $nsqSubscriber->addMethodCall('setEventDispatcher', array(new Reference($eventDispatcherId)));
            $nsqSubscriber->setPublic(false);

            $nsqSubscriberId = $this->getTopicId($name) . '.nsq.sub';
            $container->setDefinition($nsqSubscriberId, $nsqSubscriber);

            // TopicSubscriber
            $topicSubscriber = new Definition('Socloz\\NsqBundle\\Topic\\TopicSubscriber');
            $topicSubscriber->addArgument($name);
            $topicSubscriber->addArgument(new Reference($nsqSubscriberId));

            $topicSubscriberId = $this->getTopicId($name) . '.subscriber';
            $container->setDefinition($topicSubscriberId, $topicSubscriber);
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function loadConsumers(array $config, ContainerBuilder $container)
    {
        foreach ($config['topics'] as $topicName => $topicConfig) {
            $subscriber = $container->getDefinition($this->getTopicId($topicName) . '.subscriber');

            foreach ($topicConfig['consumers'] as $channelName => $consumerServiceId) {
                $subscriber->addMethodCall('addConsumer', [$channelName, new Reference($consumerServiceId)]);
            }
        }
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function loadRegistry(array $config, ContainerBuilder $container)
    {
        $registry = $container->getDefinition('socloz.nsq.registry');

        foreach ($config['topics'] as $topicName => $topicConfig) {
            $publisherId = $this->getTopicId($topicName) . '.publisher';
            $subscriberId = $this->getTopicId($topicName) . '.subscriber';

            $registry->addMethodCall('addPublisherServiceId', array($topicName, $publisherId));
            $registry->addMethodCall('addSubscriberServiceId', array($topicName, $subscriberId));
        }
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
