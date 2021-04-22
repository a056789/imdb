<?php

return [
    'roles' => [
        [
            'name' => 'users',
            'extends' => 'users',
            'default' => true,
            'permissions' => [
                'titles.view',
                'videos.view',
                'videos.rate',
                'videos.play',
                'people.view',
                'reviews.view',
                'reviews.create',
                'news.view',
                'lists.create',
                'lists.view',
                'plans.view',
            ]

        ],
        [
            'name' => 'guests',
            'guests' => true,
            'extends' => 'guests',
            'permissions' => [
                'titles.view',
                'videos.view',
                'videos.play',
                'people.view',
                'reviews.view',
                'news.view',
                'lists.view',
                'plans.view',
            ]
        ]
    ],
    'all' => [
        'titles' => [
            'titles.view',
            'titles.create',
            'titles.update',
            'titles.delete',
        ],
        'reviews' => [
            'reviews.view',
            'reviews.create',
            'reviews.update',
            'reviews.delete',
        ],
        'people' => [
            'people.view',
            'people.create',
            'people.update',
            'people.delete',
        ],
        'news' => [
            'news.view',
            'news.create',
            'news.update',
            'news.delete',
        ],
        'videos' => [
            [
                'name' => 'videos.rate',
                'description' => 'Allow rating videos.',
            ],
            [
                'name' => 'videos.play',
                'description' => 'Whether user should be able to play videos.',
            ],
            'videos.view',
            'videos.create',
            'videos.update',
            'videos.delete',
        ],
        'lists' => [
            'lists.view',
            'lists.create',
            'lists.update',
            'lists.delete',
        ],
    ]
];
