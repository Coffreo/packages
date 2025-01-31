<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\GitLab;

use Doctrine\ORM\EntityManager;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\HttpClient\Builder;
use Gitlab\Model\Project;
use Nice\Router\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Terramar\Packages\Entity\Package;
use Terramar\Packages\Entity\Remote;
use Terramar\Packages\Helper\SyncAdapterInterface;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;

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
     * @param EntityManager         $entityManager
     * @param UrlGeneratorInterface $urlGenerator
     * @param LoggerInterface       $logger
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
        return 'GitLab';
    }

    /**
     * @param Remote $remote
     *
     * @return Package[]
     */
    public function synchronizePackages(Remote $remote)
    {
        $config = $this->getRemoteConfig($remote);
        $allowedPathes = $config->getAllowedPaths() ? array_map('trim', explode(',', $config->getAllowedPaths())) : [];

        /** @var []Package $existingPackages */
        $existingPackages = $this->entityManager->getRepository('Terramar\Packages\Entity\Package')->findBy(['remote' => $remote]);

        $projects = $this->getAllProjects($remote);

        $packages = [];
        foreach ($projects as $project) {
            if (!empty($allowedPathes) && !\in_array($project['namespace']['full_path'], $allowedPathes, true)) {
                continue;
            }
            if ( ! $this->packageExists($existingPackages, $project['id'])) {
                $package = new Package();
                $package->setExternalId($project['id']);
                $package->setRemote($remote);
            } else {
                $package = $this->getExistingPackage($existingPackages, $project['id']);
            }
            $package->setName($project['name']);
            $package->setDescription($project['description']);
            $package->setFqn($project['path_with_namespace']);
            $package->setWebUrl($project['web_url']);
            $package->setSshUrl($project['ssh_url_to_repo']);
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

    /**
     * Enable a GitLab webhook for the given Package.
     *
     * @param Package $package
     *
     * @return bool
     */
    public function enableHook(Package $package)
    {
        $config = $this->getConfig($package);
        $this->logger->info('GitLab/SyncAdapter::enableHook - Enabling hook...');

        try {
            $client = $this->getClient($package->getRemote());
            $project = Project::fromArray($client, (array)$client->api('projects')->show($package->getExternalId()));
            $hook = $project->addHook(
                $this->urlGenerator->generate('webhook_receive', ['id' => $package->getId()], true), [
                    'push_events'     => true,
                    'tag_push_events' => true,
                    'push_events_branch_filter' => false,
                    'issues_events' => false,
                    'confidential_issues_events' => false,
                    'merge_requests_events' => false,
                    'note_events' => false,
                    'job_events' => false,
                    'pipeline_events' => false,
                    'wiki_page_events' => false,
                ]
            );
            $package->setHookExternalId($hook->id);
            $config->setEnabled(true);

            $this->logger->info('GitLab/SyncAdapter::enableHook - Hook enabled', ['hook_id' => $hook->id]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('GitLab/SyncAdapter::enableHook - An error occured while enabling hook', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Disable a GitLab webhook for the given Package.
     *
     * @param Package $package
     *
     * @return bool
     * @throws \Exception
     */
    public function disableHook(Package $package)
    {
        $config = $this->getConfig($package);

        if ($package->getHookExternalId()) {
            $logInfos = [
                'package' => $package->getFqn(),
                'remote' => $package->getRemote()->getName()
            ];

            try {
                // get project on gitlab
                $client = $this->getClient($package->getRemote());
                $this->logger->info('GitLab/SyncAdapter::disableHook - Disabling hook...', $logInfos);
                $project = Project::fromArray($client, (array)$client->api('projects')->show($package->getExternalId()));
            } catch (\Exception $e) {
                if (404 === $e->getCode()) {
                    $this->logger->warning(
                        'GitLab/SyncAdapter::disableHook - Unable to find project. '.
                        'You should double check package name and ensure configured GitLab user has access right to this repository',
                        $logInfos
                    );
                } else {
                    $this->logger->warning(
                        'GitLab/SyncAdapter::disableHook - An error occured while getting project on Gitlab',
                        array_merge(['exception' => $e], $logInfos)
                    );
                }

                return false;
            }

            try {
                // remove hook
                $project->removeHook($package->getHookExternalId());
                $package->setHookExternalId(null);
                $this->logger->info('GitLab/SyncAdapter::disableHook - Hook disabled', $logInfos);

                return true;

            } catch (RuntimeException $e) {
                // it's ok if it's already gone
                if ($e->getCode() === 404) {
                    $this->logger->info('GitLab/SyncAdapter::disableHook - Hook doesn\'t exist. Considering success.', $logInfos);
                    $package->setHookExternalId(null);

                    return true;
                }
                $this->logger->error('GitLab/SyncAdapter::disableHook - An error occured while disabling hook',
                    array_merge(['exception' => $e], $logInfos)
                );
                $config->setEnabled(false);

                return false;

            } catch (\Exception $e) {
                $this->logger->error('GitLab/SyncAdapter::disableHook - An error occured while disabling hook',
                    array_merge(['exception' => $e], $logInfos)
                );
                $config->setEnabled(false);

                return false;
            }
        }

        return true;
    }

    private function getConfig(Package $package)
    {
        return $this->entityManager->getRepository('Terramar\Packages\Plugin\GitLab\PackageConfiguration')->findOneBy(['package' => $package]);
    }

    /**
     * @param Remote $remote
     *
     * @return RemoteConfiguration
     */
    private function getRemoteConfig(Remote $remote)
    {
        return $this->entityManager->getRepository('Terramar\Packages\Plugin\GitLab\RemoteConfiguration')->findOneBy(['remote' => $remote]);
    }

    public function getAllProjects(Remote $remote)
    {
        $client = $this->getClient($remote);

        $user = $client->api('users')->me();
        $isAdmin = isset($user['is_admin']) ? $user['is_admin'] : false;
        $projects = [];
        $page = 1;
        while (true) {
            /*
             * there is a difference when accessing /projects (accessible) and /projects/all (all)
             * http://doc.gitlab.com/ce/api/projects.html
             */
            if ($isAdmin) {
                $visibleProjects = $client->api('projects')->all([
                    'page'     => $page,
                    'per_page' => 100,
                ]);
            } else {
                $visibleProjects = $client->api('projects')->all([
                    'page'       => $page,
                    'per_page'   => 100,
                    'membership' => true,
                ]);
            }

            $projects = array_merge($projects, $visibleProjects);
            $linkHeader = $client->getResponseHistory()->getLastResponse()->getHeader('Link');

            if (strpos($linkHeader[0], 'rel="next"') === false) {
                break;
            }

            ++$page;
        }

        return $projects;
    }

    private function getClient(Remote $remote)
    {
        $config = $this->getRemoteConfig($remote);

        $client = new Client(new Builder());
        $client->setUrl(rtrim($config->getUrl(), '/') . '/api/v4/');
        $client->authenticate($config->getToken(), Client::AUTH_HTTP_TOKEN);

        return $client;
    }

    private function getExistingPackage($existingPackages, $gitlabId)
    {
        foreach ($existingPackages as $package) {
            if ($package->getExternalId() === (string)$gitlabId) {
                return $package;
            }
        }
        throw new DatabaseObjectNotFoundException("Package is not an existing package in the database. Id: ".$gitlabId);
    }

    private function packageExists($existingPackages, $gitlabId)
    {
        return count(
                array_filter($existingPackages, function (Package $package) use ($gitlabId) {
                    return (string)$package->getExternalId() === (string)$gitlabId;
                })
            ) > 0;
    }
}
