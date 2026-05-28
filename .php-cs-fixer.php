<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                                  => true,
        '@PHP82Migration'                         => true,
        'declare_strict_types'                    => true,
        'ordered_imports'                         => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'                       => true,
        'trailing_comma_in_multiline'             => true,
        'array_syntax'                            => ['syntax' => 'short'],
        'single_quote'                            => true,
        'no_extra_blank_lines'                    => true,
        'blank_line_before_statement'             => ['statements' => ['return', 'throw', 'try']],
    ])
    ->setFinder($finder);
