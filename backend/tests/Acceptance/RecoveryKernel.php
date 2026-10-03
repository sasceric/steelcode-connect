<?php

use App\Kernel;
use App\Service\CatalogueSyncService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Test-only container: production handlers, separate cache and owned queues. */
final class RecoveryKernel extends Kernel
{
    public function __construct(private readonly string $queuePrefix)
    {
        if (!preg_match('/^acceptance_recovery_[a-f0-9]{32}$/', $queuePrefix)) {
            throw new InvalidArgumentException('A unique acceptance queue prefix is required.');
        }
        parent::__construct('dev', false);
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->queuePrefix;
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class($this->queuePrefix) implements CompilerPassInterface {
            public function __construct(private readonly string $prefix)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                foreach (['async', 'catalogue', 'sales', 'stock', 'control', 'failed'] as $name) {
                    $definition = $container->getDefinition('messenger.transport.'.$name);
                    $options = $definition->getArgument(1);
                    $options['queue_name'] = $this->prefix.'_'.$name;
                    $definition->setArgument(1, $options);
                }
                foreach ([CatalogueSyncService::class, 'messenger.default_bus', 'messenger.transport.async'] as $id) {
                    if ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);
                    } else {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        });
    }
}
