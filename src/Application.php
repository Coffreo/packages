<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages;

use Nice\Application as BaseApplication;
use Nice\Extension\DoctrineOrmExtension;
use Nice\Extension\LogExtension;
use Nice\Extension\SecurityExtension;
use Nice\Extension\SessionExtension;
use Nice\Extension\TemplatingExtension;
use Nice\Extension\TwigExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Yaml\Yaml;
use Terramar\Packages\DependencyInjection\ApiExtension;
use Terramar\Packages\DependencyInjection\PackagesExtension;
use Terramar\Packages\Plugin\CloneProject\Plugin as CloneProjectPlugin;
use Terramar\Packages\Plugin\GitHub\Plugin as GitHubPlugin;
use Terramar\Packages\Plugin\GitLab\Plugin as GitLabPlugin;
use Terramar\Packages\Plugin\Bitbucket\Plugin as BitbucketPlugin;
use Terramar\Packages\Plugin\PluginInterface;
use Terramar\Packages\Plugin\Sami\Plugin as SamiPlugin;
use Terramar\Packages\Plugin\Satis\Plugin as SatisPlugin;

class Application extends BaseApplication
{
    /**
     * @var array|PluginInterface[]
     */
    private $plugins = [];

    /**
     * @var array
     */
    private $securityOptions;

    /**
     * @var string encoded api token
     */
    private $apiToken;

    /**
     * Register default extensions.
     */
    protected function registerDefaultExtensions()
    {
        parent::registerDefaultExtensions();

        $this->registerDefaultPlugins();

        AnnotationLoader::loadAnnotations();

        $config = Yaml::parse(file_get_contents($this->getRootDir() . '/config.yml'));
        $security = isset($config['security']) ? $config['security'] : [];
        $doctrine = isset($config['doctrine']) ? $config['doctrine'] : [];
        $packages = isset($config['packages']) ? $config['packages'] : [];
        $logger = isset($config['logger']) ? $config['logger'] : [];
        $api = isset($config['api']) ? $config['api'] : [];
        if (!isset($packages['resque'])) {
            $packages['resque'] = [];
        }

        $this->securityOptions = $security;
        $this->apiToken = array_key_exists('token', $api) ? $api['token'] : null;

        $this->appendExtension(new PackagesExtension($this->plugins, $packages));
        $this->appendExtension(new ApiExtension($api));
        $this->appendExtension(new LogExtension($logger));
        $this->appendExtension(new DoctrineOrmExtension($doctrine));
        $this->appendExtension(new SessionExtension());
        $this->appendExtension(new TemplatingExtension());
        $this->appendExtension(new TwigExtension());
        $this->appendExtension(new SecurityExtension([
            'authenticator' => [
                'type'     => 'closure',
            ],
            'firewall'      => '^/manage|api',
            'success_path'  => '/manage',
        ]));
    }

    public function handle(Request $request, $type = BaseApplication::MASTER_REQUEST, $catch = true)
    {
        $this->set('security.authenticator', [$this, 'checkAuthentication']);

        return BaseApplication::handle($request, $type, $catch);
    }

    public function checkAuthentication(Request $request) {
        $username = isset($this->securityOptions['username']) ? $this->securityOptions['username'] : null;
        $password = isset($this->securityOptions['password']) ? $this->securityOptions['password'] : null;
        if ($username && $password && $request->get('username') && $request->get('password')) {
            return $request->get('username') === $username &&
                password_verify($request->get('password') , $password);
        }

        /** @var RequestMatcherInterface $matcher */
        $matcher = $this->get('packages.api.request_matcher');
        if ($matcher->matches($request)) {
            $apiToken = trim(preg_replace('/^Bearer\s*/', '', $request->headers->get('Authorization')));
            return password_verify($apiToken, $this->apiToken);
        }

        return false;
    }

    /**
     * Register default plugins.
     */
    protected function registerDefaultPlugins()
    {
        $this->registerPlugin(new GitLabPlugin());
        $this->registerPlugin(new GitHubPlugin());
        $this->registerPlugin(new BitbucketPlugin());
        $this->registerPlugin(new CloneProjectPlugin());
        $this->registerPlugin(new SamiPlugin());
        $this->registerPlugin(new SatisPlugin());
    }

    /**
     * Register a Plugin with the Application.
     *
     * @param PluginInterface $plugin
     */
    public function registerPlugin(PluginInterface $plugin)
    {
        $this->plugins[$plugin->getName()] = $plugin;
    }
}
