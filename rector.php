<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withoutParallel()
        ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
        ->withComposerBased(
            twig: true,
            doctrine: true,
            phpunit: true,
            symfony: true
        )
        ->withPhpSets(
            php82: true
        )
        ->withDeadCodeLevel(50)
        ->withCodeQualityLevel(50)
        ->withPaths([
            __DIR__ . '/src',
            __DIR__ . '/tests',
            __DIR__ . '/migrations',
        ])
        ->withRules([
            InlineConstructorDefaultToPropertyRector::class,
        ])
    ;
} catch (InvalidConfigurationException $e) {
}
