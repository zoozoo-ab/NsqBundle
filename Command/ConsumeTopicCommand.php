<?php

namespace Socloz\NsqBundle\Command;

use Socloz\NsqBundle\Event\OutputLoggerSubscriber;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand as BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsumeTopicCommand extends BaseCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addArgument('topic', InputArgument::REQUIRED, 'Topic')
            ->addOption('channel', 'c', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Subscribe to channel. You can repeat this option to subscribe to many channels.')
            ->addOption('exit-after-timeout', 't', InputOption::VALUE_OPTIONAL, 'Keep running only specified time', 0)
            ->addOption('exit-after-messages', 'm', InputOption::VALUE_REQUIRED, 'Consume n messages and exit', null)
            ->setName('socloz:nsq:topic:consume')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $quiet = $input->getOption('quiet');
        $channels = $input->getOption('channel');
        $topic = $this->getTopicRegistry()->getTopic($input->getArgument('topic'));

        false == $quiet && $topic->getEventDispatcher()->addSubscriber(new OutputLoggerSubscriber($output));

        $output->writeln(sprintf('Consume topic[<comment>%s</comment>] channels:[<comment>%s</comment>]', $topic->getName(), implode(', ', $channels)));
        $topic->consume($channels, (int) $input->getOption('exit-after-timeout'), (int) $input->getOption('exit-after-messages'));
    }

    /**
     * @return \Socloz\NsqBundle\TopicRegistry
     */
    public function getTopicRegistry()
    {
        return $this->getContainer()->get('socloz.nsq.registry');
    }
}
