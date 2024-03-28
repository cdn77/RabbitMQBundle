<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->enableAnalysisOfUnusedDevDependencies()
    ->addPathToScan('./composer-dependency-analyser.php', true)

    ->ignoreErrorsOnPackage('cdn77/coding-standard', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('ergebnis/composer-normalize', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('infection/infection', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('phpstan/extension-installer', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('phpstan/phpstan', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('phpstan/phpstan-phpunit', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('phpstan/phpstan-strict-rules', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('symfony/framework-bundle', [ErrorType::UNUSED_DEPENDENCY])

    ->ignoreErrorsOnPackage('symfony/yaml', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
