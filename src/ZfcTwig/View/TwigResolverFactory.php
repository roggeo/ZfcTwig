<?php

namespace ZfcTwig\View;

use Interop\Container\ContainerInterface;
use Twig\Environment;
use Zend\ServiceManager\Factory\FactoryInterface;

class TwigResolverFactory implements FactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array|null $options
     * @return TwigResolver
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TwigResolver($container->get(Environment::class));
    }

}
