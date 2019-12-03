<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\GitHub;

use Doctrine\ORM\EntityManager;
use Github\Client;
use Github\HttpClient\Message\ResponseMediator;
use Nice\Router\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Terramar\Packages\Entity\Package;
use Terramar\Packages\Entity\Remote;
use Terramar\Packages\Helper\SyncAdapterInterface;

class SyncAdapter implements SyncAdapterInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param EntityManager $entityManager
     * @param UrlGeneratorInterface $urlGenerator
     * @param LoggerInterface $logger
     */
    public function __construct(EntityManager $entityManager, UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    /**
     * @param Remote $remote
     *
     * @return bool
     */
    public function supports(Remote $remote)
    {
        return $remote->getAdapter() === $this->getName();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'GitHub';
    }

    /**
     * @param Remote $remote
     *
     * @return Package[]
     */
    public function synchronizePackages(Remote $remote)
    {
        $existingPackages = $this->entityManager->getRepository('Terramar\Packages\Entity\Package')->findBy(['remote' => $remote]);

        $projects = $this->getAllProjects($remote);

        $packages = [];
        foreach ($projects as $project) {
            $package = $this->getExistingPackage($existingPackages, $project['id']);
            if ($package === null) {
                $package = new Package();
                $package->setExternalId($project['id']);
                $package->setRemote($remote);
            }
            $package->setName($project['name']);
            $package->setDescription($project['description']);
            $package->setFqn($project['full_name']);
            $package->setWebUrl($project['clone_url']);
            $package->setSshUrl($project['ssh_url']);
            $packages[] = $package;
        }

        $removed = array_diff($existingPackages, $packages);
        /** @var Package $package */
        foreach ($removed as $package) {
            $package->setEnabled(false);
            $this->entityManager->persist($package);
        }

        return $packages;
    }

    private function getAllProjects(Remote $remote)
    {
        $client = $this->getClient($remote);

        $projects = [];
        $page = 1;
        while (true) {
            $response = $client->getHttpClient()->get('/user/repos', [
                'page'     => $page,
                'per_page' => 100,
            ]);
            $projects = array_merge($projects, ResponseMediator::getContent($response));
            $pageInfo = ResponseMediator::getPagination($response);
            if (!isset($pageInfo['next'])) {
                break;
            }

            ++$page;
        }

        return $projects;
    }

    private function getClient(Remote $remote)
    {
        $config = $this->getRemoteConfig($remote);

        $client = new Client();
        $client->authenticate($config->getToken(), Client::AUTH_HTTP_TOKEN);

        return $client;
    }

    /**
     * @param Remote $remote
     *
     * @return RemoteConfiguration|object
     */
    private function getRemoteConfig(Remote $remote)
    {
        return $this->entityManager
            ->getRepository('Terramar\Packages\Plugin\GitHub\RemoteConfiguration')
            ->findOneBy(['remote' => $remote]);
    }

    /**
     * @param $existingPackages []Package
     * @param $gitlabId
     * @return Package|null
     */
    private function getExistingPackage($existingPackages, $gitlabId)
    {
        $res = array_filter($existingPackages, function (Package $package) use ($gitlabId) {
            return (string)$package->getExternalId() === (string)$gitlabId;
        });
        if (count($res) === 0) {
            return null;
        }
        return array_shift($res);
    }


    /**
     * Enable a GitHub webhook for the given Package.
     *
     * @param Package $package
     *
     * @return bool
     */
    public function enableHook(Package $package)
    {
        $config = $this->getConfig($package);
        $this->logger->info('GitHub/SyncAdapter::enableHook - Enabling hook...');

        try {
            $client = $this->getClient($package->getRemote());
            $url = 'repos/' . $package->getFqn() . '/hooks';
            $response = $client->getHttpClient()->post($url, json_encode([
                'name'   => 'web',
                'config' => [
                    'url' => $this->urlGenerator->generate('webhook_receive', ['id' => $package->getId()], true),
                    'content_type' => 'json',
                ],
                'events' => ['push', 'create'],
            ]));

            $hook = ResponseMediator::getContent($response);

            $package->setHookExternalId($hook['id']);
            $config->setEnabled(true);
            $this->logger->info('GitHub/SyncAdapter::enableHook - Hook enabled', ['hook_id' => $hook['id']]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('GitHub/SyncAdapter::enableHook - An error occured while enabling hook', ['exception' => $e]);
            return false;
        }
    }

    /**
     * @param Package $package
     *
     * @return PackageConfiguration|null
     */
    private function getConfig(Package $package)
    {
        return $this->entityManager
            ->getRepository('Terramar\Packages\Plugin\GitHub\PackageConfiguration')
            ->findOneBy(['package' => $package]);
    }

    /**
     * Disable a GitHub webhook for the given Package.
     *
     * @param Package $package
     *
     * @return bool
     */
    public function disableHook(Package $package)
    {
        $config = $this->getConfig($package);

        try {
            if ($package->getHookExternalId()) {
                $this->logger->info('GitHub/SyncAdapter::disableHook - Disabling hook...');
                $client = $this->getClient($package->getRemote());
                $url = 'repos/' . $package->getFqn() . '/hooks/' . $package->getHookExternalId();
                $client->getHttpClient()->delete($url);
            }

            $package->setHookExternalId('');
            $config->setEnabled(false);
            $this->logger->info('GitHub/SyncAdapter::disableHook - Hook disabled');

            return true;

        } catch (\Exception $e) {
            $this->logger->error('GitHub/SyncAdapter::disableHook - An error occured while disabling hook', ['exception' => $e]);
            $config->setEnabled(false);

            return false;
        }
    }
}
