<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->append([
        __DIR__ . '/tests/Unit/ValidatorTest.php',
        __DIR__ . '/tests/Unit/AdminAuditServiceTest.php',
        __DIR__ . '/tests/Feature/FeatureTestCase.php',
        __DIR__ . '/tests/Feature/AuthEndpointsTest.php',
        __DIR__ . '/tests/Feature/CatalogEndpointsTest.php',
        __DIR__ . '/tests/Feature/WatchProgressEndpointsTest.php',
        __DIR__ . '/tests/Feature/E2ESmokeTest.php',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setUsingCache(false)
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder($finder);
