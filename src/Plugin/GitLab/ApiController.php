<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\GitLab;

use Nice\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Terramar\Packages\Controller\Api\AbstractApiController;

class ApiController extends AbstractApiController
{
    function getSensitiveDataKeys()
    {
        return [
            'gitlab_token'
        ];
    }

    public function getAction(Application $app, Request $request, $id)
    {
        $config = $this->getRemoteConfiguration($id);
        if (!$config) {
            return new JsonResponse();
        }

        return new JsonResponse($this->handleSensitiveDataOutput([
            'gitlab_allowed_paths' => $config->getAllowedPaths(),
            'gitlab_token' => $config->getToken(),
            'gitlab_url' => $config->getUrl()
        ]));
    }

    public function updateAction(Application $app, Request $request, $id)
    {
        $this->logger->debug('GitLab\ApiController::update', ['remote_id' => $id]);
        $config = $this->getRemoteConfiguration($id);
        if (!$config) {
            return new Response();
        }

        $data = $this->handleSensitiveDataInput($request->query->all());

        if (array_key_exists('gitlab_token', $data)) {
            $config->setToken($data['gitlab_token']);
        }
        if (array_key_exists('gitlab_url', $data)) {
            $config->setUrl($data['gitlab_url']);
        }
        if (array_key_exists('gitlab_allowedPaths', $data)) {
            $config->setAllowedPaths($data['gitlab_allowedPaths']);
        }

        $config->setEnabled($config->getRemote()->isEnabled());

        $this->em->persist($config);

        $this->logger->info('GitLab\ApiController::update - remote updated', ['remote_id' => $id]);

        return new Response();
    }

    /**
     * @param $id
     *
     * @return RemoteConfiguration|null
     */
    protected function getRemoteConfiguration($id)
    {
        return $this->getRepository()->findOneBy(['remote' => $id]);
    }

    protected function getRepository()
    {
        return $this->em->getRepository('Terramar\Packages\Plugin\GitLab\RemoteConfiguration');
    }
}
