<?php

namespace Socloz\NsqBundle;

use Socloz\NsqBundle\DependencyInjection\Compiler\RegisterEventListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SoclozNsqBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new RegisterEventListenerPass());
    }
}
