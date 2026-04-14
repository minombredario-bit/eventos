<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('var')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony'                        => true,
        '@Symfony:risky'                  => false,
        '@PHP83Migration'                 => true,

        // Imports
        'ordered_imports'                 => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'               => true,
        'global_namespace_import'         => [
            'import_classes'   => false,
            'import_constants' => false,
            'import_functions' => false,
        ],

        // Arrays
        'array_syntax'                    => ['syntax' => 'short'],
        'trailing_comma_in_multiline'     => ['elements' => ['arrays', 'arguments', 'parameters']],

        // Strings
        'single_quote'                    => true,
        'explicit_string_variable'        => true,

        // Clases y métodos
        'class_attributes_separation'     => [
            'elements' => ['method' => 'one', 'property' => 'none'],
        ],
        'ordered_class_elements'          => [
            'order' => [
                'use_trait',
                'case',
                'constant_public', 'constant_protected', 'constant_private',
                'property_public', 'property_protected', 'property_private',
                'construct',
                'destruct',
                'method_public', 'method_protected', 'method_private',
            ],
        ],
        'final_class'                     => false,

        // PHP 8+
        'use_arrow_functions'             => true,
        'modernize_strpos'                => true,
        'get_class_to_class_keyword'      => true,

        // Doctrine / Atributos
        'attribute_empty_parentheses'     => ['use_parentheses' => false],

        // Misc
        'concat_space'                    => ['spacing' => 'one'],
        'yoda_style'                      => false,
        'increment_style'                 => ['style' => 'post'],
        'phpdoc_align'                    => ['align' => 'left'],
        'phpdoc_summary'                  => false,
        'no_superfluous_phpdoc_tags'      => ['allow_mixed' => true],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
