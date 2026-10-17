<?php

declare(strict_types=1);

namespace EsfahanAhan\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A later with() for a child relation replaces a parent eager-load callback.
 *
 * Eloquent merges each with() into the same map. A dotted name such as
 * posts.comments also stores an empty callback for posts, and that empty
 * callback overwrites a constraint registered by an earlier with().
 *
 * @implements Rule<Node>
 */
final class NestedEagerLoadWithOverwritesConstraintRule implements Rule
{
    private const IDENTIFIER = 'esfahanahan.nestedEagerLoadOverwritesConstraint';

    /**
     * @param  class-string<Node>  $nodeType
     */
    public function __construct(private readonly string $nodeType) {}

    public function getNodeType(): string
    {
        return $this->nodeType;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node instanceof ClassMethod && ! $node instanceof Function_ && ! $node instanceof Closure && ! $node instanceof ArrowFunction) {
            return [];
        }

        /** @var array<string, array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>> $scopes */
        $scopes = [];
        $errors = [];

        if ($node instanceof ArrowFunction) {
            $this->walk($node->expr, $scopes, $errors);

            return $errors;
        }

        foreach ($node->getStmts() ?? [] as $statement) {
            $this->walk($statement, $scopes, $errors);
        }

        return $errors;
    }

    /**
     * @param  array<string, array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>>  $scopes
     * @param  list<IdentifierRuleError>  $errors
     */
    private function walk(Node $node, array &$scopes, array &$errors): void
    {
        if ($node instanceof Closure || $node instanceof ArrowFunction || $node instanceof ClassMethod || $node instanceof Function_) {
            return;
        }

        if ($node instanceof Assign) {
            $loaded = $this->eagerLoadsFrom($node->expr, $scopes, $errors);
            $target = $this->targetId($node->var);

            if ($target !== null) {
                $scopes[$target] = $loaded;
            }

            return;
        }

        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            $this->eagerLoadsFrom($node, $scopes, $errors);

            return;
        }

        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};

            if ($child instanceof Node) {
                $this->walk($child, $scopes, $errors);

                continue;
            }

            if (! is_array($child)) {
                continue;
            }

            foreach ($child as $item) {
                if ($item instanceof Node) {
                    $this->walk($item, $scopes, $errors);
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>>  $scopes
     * @param  list<IdentifierRuleError>  $errors
     * @return array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>
     */
    private function eagerLoadsFrom(Node $expression, array &$scopes, array &$errors): array
    {
        if (! $expression instanceof MethodCall && ! $expression instanceof StaticCall) {
            $this->walk($expression, $scopes, $errors);

            return [];
        }

        foreach ($this->ordinaryArguments($expression) as $argument) {
            $this->walk($argument->value, $scopes, $errors);
        }

        $calls = $this->withChain($expression);
        $rootId = $this->rootId($expression);
        $loaded = $rootId !== null ? ($scopes[$rootId] ?? []) : [];

        foreach ($calls as $call) {
            $parsed = $this->parseWithArguments($call);

            if ($parsed === null) {
                $loaded = [];

                continue;
            }

            foreach ($parsed as $name => $spec) {
                if ($spec['kind'] !== 'implicit' || ($loaded[$name]['kind'] ?? null) !== 'closure') {
                    continue;
                }

                $line = $call->name instanceof Identifier ? $call->name->getStartLine() : $call->getStartLine();

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Calling with() for [%s] replaces the existing [%s] eager-load callback with an empty constraint. Pass [%s] and [%s] in the same with() array.',
                    $spec['introducedBy'],
                    $name,
                    $name,
                    $spec['introducedBy'],
                ))
                    ->identifier(self::IDENTIFIER)
                    ->line($line)
                    ->build();
            }

            foreach ($parsed as $name => $spec) {
                $loaded[$name] = $spec;
            }
        }

        if ($rootId !== null) {
            $scopes[$rootId] = $loaded;
        }

        return $loaded;
    }

    /**
     * @return list<MethodCall|StaticCall>
     */
    private function withChain(MethodCall|StaticCall $outer): array
    {
        $calls = [];
        $current = $outer;

        while (true) {
            if ($this->isWithCall($current)) {
                $calls[] = $current;
            }

            if (! $current instanceof MethodCall) {
                break;
            }

            $receiver = $current->var;

            if (! $receiver instanceof MethodCall && ! $receiver instanceof StaticCall) {
                break;
            }

            $current = $receiver;
        }

        return array_reverse($calls);
    }

    /**
     * @return array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>|null
     */
    private function parseWithArguments(MethodCall|StaticCall $call): ?array
    {
        $arguments = $this->ordinaryArguments($call);

        if ($arguments === []) {
            return [];
        }

        $first = $arguments[0]->value;

        if ($first instanceof String_) {
            if (isset($arguments[1]) && $this->isClosure($arguments[1]->value)) {
                return $this->addRelation([], $first->value, 'closure');
            }

            $results = [];

            foreach ($arguments as $argument) {
                if (! $argument->value instanceof String_) {
                    return null;
                }

                $results = $this->addRelation($results, $argument->value->value, 'plain');
            }

            return $results;
        }

        if ($first instanceof Array_) {
            return $this->parseRelationArray($first, '');
        }

        return null;
    }

    /**
     * @return array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>|null
     */
    private function parseRelationArray(Array_ $array, string $prefix): ?array
    {
        $results = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            if ($item->key instanceof String_) {
                $name = $prefix.$item->key->value;

                if ($item->value instanceof Array_) {
                    $relation = $this->relationName($name);

                    if (! isset($results[$relation])) {
                        $results[$relation] = [
                            'kind' => 'implicit',
                            'introducedBy' => $relation,
                        ];
                    }

                    $children = $this->parseRelationArray($item->value, $relation.'.');

                    if ($children === null) {
                        return null;
                    }

                    $results = $this->mergeParsed($results, $children);

                    continue;
                }

                if ($this->isClosure($item->value)) {
                    $results = $this->addRelation($results, $name, 'closure');

                    continue;
                }

                return null;
            }

            if ($item->key === null && $item->value instanceof String_) {
                $results = $this->addRelation($results, $prefix.$item->value->value, 'plain');

                continue;
            }

            return null;
        }

        return $results;
    }

    /**
     * @param  array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>  $results
     * @param  'closure'|'plain'  $kind
     * @return array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>
     */
    private function addRelation(array $results, string $name, string $kind): array
    {
        $relation = $this->relationName($name);
        $progress = [];

        foreach (explode('.', $relation) as $segment) {
            if ($segment === '') {
                continue;
            }

            $progress[] = $segment;
            $path = implode('.', $progress);

            if (! isset($results[$path])) {
                $results[$path] = [
                    'kind' => 'implicit',
                    'introducedBy' => $relation,
                ];
            }
        }

        $results[$relation] = [
            'kind' => $kind,
            'introducedBy' => $relation,
        ];

        return $results;
    }

    /**
     * @param  array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>  $current
     * @param  array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>  $next
     * @return array<string, array{kind: 'closure'|'implicit'|'plain', introducedBy: string}>
     */
    private function mergeParsed(array $current, array $next): array
    {
        foreach ($next as $name => $spec) {
            if ($spec['kind'] === 'implicit' && isset($current[$name])) {
                continue;
            }

            $current[$name] = $spec;
        }

        return $current;
    }

    private function relationName(string $name): string
    {
        return explode(':', $name, 2)[0];
    }

    /**
     * @return list<Arg>
     */
    private function ordinaryArguments(MethodCall|StaticCall $call): array
    {
        $arguments = [];

        foreach ($call->getRawArgs() as $argument) {
            if ($argument instanceof Arg) {
                $arguments[] = $argument;
            }
        }

        return $arguments;
    }

    private function isClosure(Node $node): bool
    {
        return $node instanceof Closure || $node instanceof ArrowFunction;
    }

    private function isWithCall(Node $node): bool
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return false;
        }

        return $node->name instanceof Identifier && $node->name->toString() === 'with';
    }

    private function rootId(MethodCall|StaticCall $call): ?string
    {
        $current = $call instanceof MethodCall ? $call->var : $call;

        while ($current instanceof MethodCall) {
            $current = $current->var;
        }

        if ($current instanceof Variable && is_string($current->name)) {
            return 'var:'.$current->name;
        }

        if ($current instanceof PropertyFetch
            && $current->var instanceof Variable
            && $current->var->name === 'this'
            && $current->name instanceof Identifier
        ) {
            return 'prop:'.$current->name->toString();
        }

        return null;
    }

    private function targetId(Node $node): ?string
    {
        if ($node instanceof Variable && is_string($node->name)) {
            return 'var:'.$node->name;
        }

        if ($node instanceof PropertyFetch
            && $node->var instanceof Variable
            && $node->var->name === 'this'
            && $node->name instanceof Identifier
        ) {
            return 'prop:'.$node->name->toString();
        }

        return null;
    }
}
