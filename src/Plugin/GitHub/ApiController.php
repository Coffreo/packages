<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\GitHub;

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
            'github_token'
        ];
    }

    public function getAction(Application $app, Request $request, $id)
    {
        $config = $this->getRemoteConfiguration($id);
        if (!$config) {
            return new JsonResponse();
        }

        return new JsonResponse($this->handleSensitiveDataOutput([
            'github_token' => $config->getToken(),
            'github_username' => $config->getUsername()
        ]));
    }

    public function updateAction(Application $app, Request $request, $id)
    {
        $this->logger->debug('GitHub\ApiController::update', ['remote_id' => $id]);
        $config = $this->getRemoteConfiguration($id);
        if (!$config) {
            return new Response();
        }

        $data = $this->handleSensitiveDataInput($request->query->all());

        if (array_key_exists('github_token', $data)) {
            $config->setToken($data['github_token']);
        }
        if (array_key_exists('github_username', $data)) {
            $config->setToken($data['github_username']);
        }
        $config->setEnabled($config->getRemote()->isEnabled());

        $this->em->persist($config);

        $this->logger->info('GitHub\ApiController::update - remote updated', ['remote_id' => $id]);

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
        return $this->em->getRepository('Terramar\Packages\Plugin\GitHub\RemoteConfiguration');
    }
}
