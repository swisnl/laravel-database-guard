<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\BooleanNot\NegatedAndsToPositiveOrsRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEmptyNullableObjectToAssertInstanceofRector;
use Rector\TypeDeclaration\Rector\BooleanAnd\BinaryOpNullableToInstanceofRector;
use Rector\TypeDeclaration\Rector\While_\WhileNullableToInstanceofRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
        __DIR__.'/tests',
        __DIR__.'/workbench',
    ])
    // Maintain syntax of PHP (using version from composer.json).
    ->withPhpSets()
    // Maintain usage of dependencies (using versions from composer.json).
    ->withComposerBased(
        phpunit: true,
        laravel: true,
    )
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        phpunitCodeQuality: true,
        phpunitNarrowAsserts: true,
    )
    ->withSkip([
        // Null checks for typed variables are fine.
        FlipTypeControlToUseExclusiveTypeRector::class,
        BinaryOpNullableToInstanceofRector::class,
        WhileNullableToInstanceofRector::class,
        AssertEmptyNullableObjectToAssertInstanceofRector::class,
        // Not so sure about code quality improvement.
        NegatedAndsToPositiveOrsRector::class,
        // Not so sure about coding style improvement.
        CatchExceptionNameMatchingTypeRector::class,
    ]);
