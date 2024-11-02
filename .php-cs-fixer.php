<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->exclude([
        'vendor',
        'var',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        // @see https://cs.symfony.com/doc/rules/import/no_unused_imports.html
        'no_unused_imports' => false,
        'trailing_comma_in_multiline' => true,
        'phpdoc_align' => true,
        'phpdoc_order' => true,
        // @see https://cs.symfony.com/doc/rules/phpdoc/phpdoc_separation.html
        'phpdoc_separation' => [
            'groups' => [
                ['ORM\\*'],
                ['Assert\\*'],
            ],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_var_without_name' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder);