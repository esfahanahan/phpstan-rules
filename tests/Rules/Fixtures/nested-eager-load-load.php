<?php

function chainedLoad($users): void
{
    $users->load([
        'posts' => function ($inner): void {
            $inner->where('published', true);
        },
    ])->load([
        'posts.comments' => function ($inner): void {
            $inner->where('approved', true);
        },
    ]);
}

function singleLoadArray($users): void
{
    $users->load([
        'posts' => function ($inner): void {
            $inner->where('published', true);
        },
        'posts.comments' => function ($inner): void {
            $inner->where('approved', true);
        },
    ]);
}

function separateLoadStatements($users): void
{
    $users->load('posts', function ($inner): void {
        $inner->where('published', true);
    });

    $users->load('posts.comments', function ($inner): void {
        $inner->where('approved', true);
    });
}

function loadThenLoadMissing($users): void
{
    $users->load([
        'posts' => function ($inner): void {
            $inner->where('published', true);
        },
    ]);

    $users->loadMissing([
        'posts.comments' => function ($inner): void {
            $inner->where('approved', true);
        },
    ]);
}

function loadMissingThenLoad($users): void
{
    $users->loadMissing([
        'posts' => function ($inner): void {
            $inner->where('published', true);
        },
    ]);

    $users->load([
        'posts.comments' => function ($inner): void {
            $inner->where('approved', true);
        },
    ]);
}

function reassignedModels($users): void
{
    $users->load([
        'posts' => function ($inner): void {
            $inner->where('published', true);
        },
    ]);

    $users = $fresh;

    $users->load([
        'posts.comments' => function ($inner): void {
            $inner->where('approved', true);
        },
    ]);
}

function withThenLoad(): void
{
    $users = User::query()
        ->with([
            'posts' => function ($inner): void {
                $inner->where('published', true);
            },
        ])
        ->get();

    $users->load([
        'posts.comments' => function ($inner): void {
            $inner->where('approved', true);
        },
    ]);
}

function chainedWithGetLoad(): void
{
    User::query()
        ->with([
            'posts' => function ($inner): void {
                $inner->where('published', true);
            },
        ])
        ->get()
        ->load([
            'posts.comments' => function ($inner): void {
                $inner->where('approved', true);
            },
        ]);
}

function parentLoadAfterNested($users): void
{
    $users->load([
        'posts.comments' => function ($inner): void {
            $inner->where('approved', true);
        },
    ])->load([
        'posts' => function ($inner): void {
            $inner->where('published', true);
        },
    ]);
}

function unrelatedLoad($users): void
{
    $users->load([
        'posts' => function ($inner): void {
            $inner->where('published', true);
        },
    ])->load([
        'company' => function ($inner): void {
            $inner->where('active', true);
        },
    ]);
}
