<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SymfonySetList::SYMFONY_71,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::PHP_82,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ]);
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/migrations',
    ]);

    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
};
