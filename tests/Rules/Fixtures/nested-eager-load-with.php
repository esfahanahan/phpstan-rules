<?php

function chained($query): void
{
    $query->with([
        'variations' => function ($inner): void {
            $inner->where('table_show', true);
        },
    ])->with([
        'variations.attributeItems' => function ($inner): void {
            $inner->where('is_show', true);
        },
    ]);
}

function singleArray($query): void
{
    $query->with([
        'variations' => function ($inner): void {
            $inner->where('table_show', true);
        },
        'variations.attributeItems' => function ($inner): void {
            $inner->where('is_show', true);
        },
    ]);
}

function parentConstraintAfterNested($query): void
{
    $query->with([
        'variations.attributeItems' => function ($inner): void {
            $inner->where('is_show', true);
        },
    ])->with([
        'variations' => function ($inner): void {
            $inner->where('table_show', true);
        },
    ]);
}

function separateStatements($query): void
{
    $query->with('variations', function ($inner): void {
        $inner->where('table_show', true);
    });

    $query->with('variations.attributeItems', function ($inner): void {
        $inner->where('is_show', true);
    });
}

function reassignedQuery($query): void
{
    $query->with([
        'variations' => function ($inner): void {
            $inner->where('table_show', true);
        },
    ]);

    $query = other();

    $query->with([
        'variations.attributeItems' => function ($inner): void {
            $inner->where('is_show', true);
        },
    ]);
}

function unrelatedRelations($query): void
{
    $query->with([
        'company' => function ($inner): void {
            $inner->where('active', true);
        },
    ])->with([
        'media' => function ($inner): void {
            $inner->where('public', true);
        },
    ]);
}

function other()
{
    return new stdClass;
}
