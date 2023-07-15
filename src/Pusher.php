<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\HttpClient;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Data\AddApiCredentialsPrompt;
use Bellows\PluginSdk\Facades\Console;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeDeployed;
use Bellows\PluginSdk\PluginResults\CanBeInstalled;
use Bellows\PluginSdk\PluginResults\DeploymentResult;
use Bellows\PluginSdk\PluginResults\InstallationResult;
use Illuminate\Http\Client\PendingRequest;

class Pusher extends Plugin implements Deployable, Installable
{
    use CanBeDeployed, CanBeInstalled;

    protected const BROADCAST_DRIVER = 'pusher';

    protected array $appConfig;

    public function __construct(
        protected HttpClient $http,
    ) {
    }

    public function install(): ?InstallationResult
    {
        return InstallationResult::create();
    }

    public function deploy(): ?DeploymentResult
    {
        $this->http->createJsonClient(
            'https://cli.pusher.com/',
            fn (PendingRequest $request, $credentials) => $request->withToken($credentials['token']),
            new AddApiCredentialsPrompt(
                url: 'https://dashboard.pusher.com/accounts/api_key',
                credentials: ['token'],
                displayName: 'Pusher',
            ),
            fn (PendingRequest $request) => $request->get('apps.json'),
        );

        Console::info("Pusher API limitations don't allow Bellows to create an app for you.");
        Console::info(
            "If you'd like to create one head to <comment>https://dashboard.pusher.com/channels</comment> then refresh the list below."
        );

        $this->presentChoices();

        return DeploymentResult::create()->environmentVariables([
            'BROADCAST_DRIVER'   => self::BROADCAST_DRIVER,
            'PUSHER_APP_ID'      => $this->appConfig['app_id'],
            'PUSHER_APP_KEY'     => $this->appConfig['key'],
            'PUSHER_APP_SECRET'  => $this->appConfig['secret'],
            'PUSHER_APP_CLUSTER' => $this->appConfig['cluster'],
        ]);
    }

    public function requiredComposerPackages(): array
    {
        return [
            'pusher/pusher-php-server',
        ];
    }

    public function shouldDeploy(): bool
    {
        return Deployment::site()->env()->get('BROADCAST_DRIVER') !== self::BROADCAST_DRIVER
            || !Deployment::site()->env()->hasAll(
                'PUSHER_APP_ID',
                'PUSHER_APP_KEY',
                'PUSHER_APP_SECRET',
                'PUSHER_APP_CLUSTER',
            );
    }

    public function confirmDeploy(): bool
    {
        return Deployment::confirmChangeValueTo(
            Deployment::site()->env()->get('BROADCAST_DRIVER'),
            self::BROADCAST_DRIVER,
            'Change broadcast driver to Pusher'
        );
    }

    protected function presentChoices()
    {
        $apps = collect($this->http->client()->get('apps.json')->json());

        $refreshLabel = 'Refresh App List';

        $appName = Console::choice(
            'Which app do you want to use?',
            $apps->pluck('name')->concat([$refreshLabel])->toArray()
        );

        if ($appName === $refreshLabel) {
            $this->presentChoices();

            return;
        }

        $app = $apps->first(fn ($app) => $app['name'] === $appName);

        $this->appConfig = $this->http->client()->get("apps/{$app['id']}/tokens.json")->json()[0];

        $this->appConfig['cluster'] = $app['cluster'];
    }
}
