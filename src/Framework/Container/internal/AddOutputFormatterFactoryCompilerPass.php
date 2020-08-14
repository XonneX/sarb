<?php

/**
 * Static Analysis Results Baseliner (sarb).
 *
 * (c) Dave Liddament
 *
 * For the full copyright and licence information please view the LICENSE file distributed with this source code.
 */

declare(strict_types=1);

namespace DaveLiddament\StaticAnalysisResultsBaseliner\Framework\Container\internal;

use DaveLiddament\StaticAnalysisResultsBaseliner\Framework\Container\Container;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AddOutputFormatterFactoryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(OutputFormatter::class);
        $taggedServices = $container->findTaggedServiceIds(Container::OUTPUT_FORMATTER_TAG);
        $services = [];
        foreach ($taggedServices as $id => $tags) {
            $services[] = new Reference($id);
        }
        $definition->setArgument(0, $services);
    }
}
