<?php

declare(strict_types=1);

namespace EsfahanAhan\PHPStan\Tests\Rules;

use EsfahanAhan\PHPStan\Rules\NestedEagerLoadWithOverwritesConstraintRule;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NestedEagerLoadWithOverwritesConstraintRule>
 */
final class NestedEagerLoadWithOverwritesConstraintRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NestedEagerLoadWithOverwritesConstraintRule(Function_::class);
    }

    public function test_nested_with_calls_do_not_drop_an_earlier_parent_callback(): void
    {
        $this->analyse([__DIR__.'/Fixtures/nested-eager-load-with.php'], [
            [
                'Calling with() for [variations.attributeItems] replaces the existing [variations] eager-load callback with an empty constraint. Pass [variations] and [variations.attributeItems] in the same with() array.',
                9,
            ],
            [
                'Calling with() for [variations.attributeItems] replaces the existing [variations] eager-load callback with an empty constraint. Pass [variations] and [variations.attributeItems] in the same with() array.',
                47,
            ],
        ]);
    }

    public function test_nested_load_calls_do_not_reload_a_constrained_parent(): void
    {
        $message = 'Calling load() for [posts.comments] reloads [posts] with an empty constraint and drops its eager-load callback. Pass [posts] and [posts.comments] in the same load() array, or use loadMissing().';

        $this->analyse([__DIR__.'/Fixtures/nested-eager-load-load.php'], [
            [$message, 9],
            [$message, 34],
            [$message, 62],
            [$message, 96],
            [$message, 112],
        ]);
    }
}
