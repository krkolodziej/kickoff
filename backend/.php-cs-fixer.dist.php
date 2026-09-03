<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PHP82Migration' => true,
        'declare_strict_types' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_align' => false,

        // Constraint attributes read as documentation of the field they sit above, so they
        // stay on the same line as short parameters instead of being hoisted onto their own.
        'method_argument_space' => ['attribute_placement' => 'ignore'],

        // @Symfony forces every `throw` onto one line, which turns a constructed
        // exception with several arguments into a 200-character statement.
        'single_line_throw' => false,

        // Keep blank lines inside argument lists: a promoted constructor property carrying
        // three validation attributes needs the air around it to stay readable.
        'no_extra_blank_lines' => ['tokens' => [
            'attribute', 'case', 'continue', 'curly_brace_block', 'default', 'extra',
            'return', 'square_brace_block', 'switch', 'throw', 'use',
        ]],
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
