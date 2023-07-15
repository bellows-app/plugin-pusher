<?php

use Bellows\Plugins\Pusher;
use Illuminate\Support\Facades\Http;

it('can select a pusher app from the list', function () {
    Http::fake([
        'apps.json' => Http::response([
            [
                'id'      => 123,
                'name'    => 'dm2-staging',
                'cluster' => 'us2',
            ],
        ]),
        'apps/123/tokens.json' => Http::response([
            [
                'app_id' => 123,
                'key'    => 'appkeyhere',
                'secret' => 'secretstuff',
            ],
        ]),
    ]);

    $result = $this->plugin(Pusher::class)
        ->expectsQuestion('Which app do you want to use?', 'dm2-staging')
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'BROADCAST_DRIVER'   => 'pusher',
        'PUSHER_APP_ID'      => 123,
        'PUSHER_APP_KEY'     => 'appkeyhere',
        'PUSHER_APP_SECRET'  => 'secretstuff',
        'PUSHER_APP_CLUSTER' => 'us2',
    ]);
});

it('can refresh the list', function () {
    Http::fake([
        'apps.json' => Http::sequence([
            [
                [
                    'id'      => 123,
                    'name'    => 'dm2-staging',
                    'cluster' => 'us2',
                ],
            ],
            [
                [
                    'id'      => 123,
                    'name'    => 'dm2-staging',
                    'cluster' => 'us2',
                ],
            ],
        ]),
        'apps/123/tokens.json' => Http::response([
            [
                'app_id' => 123,
                'key'    => 'appkeyhere',
                'secret' => 'secretstuff',
            ],
        ]),
    ]);

    $result = $this->plugin(Pusher::class)
        ->expectsQuestion('Which app do you want to use?', 'Refresh App List')
        ->expectsQuestion('Which app do you want to use?', 'dm2-staging')
        ->deploy();

    expect($result->getEnvironmentVariables())->toBe([
        'BROADCAST_DRIVER'   => 'pusher',
        'PUSHER_APP_ID'      => 123,
        'PUSHER_APP_KEY'     => 'appkeyhere',
        'PUSHER_APP_SECRET'  => 'secretstuff',
        'PUSHER_APP_CLUSTER' => 'us2',
    ]);
});
