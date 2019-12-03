<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\Bitbucket;

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
            'bitbucket_token'
        ];
    }

    public function getAction(Application $app, Request $request, $id)
    {
        $config = $this->getRemoteConfiguration($id);
        if (!$config) {
            return new JsonResponse();
        }

        return new JsonResponse($this->handleSensitiveDataOutput([
            'bitbucket_token' => $config->getToken(),
            'bitbucket_username' => $config->getUsername(),
            'bitbucket_account' => $config->getAccount()
        ]));
    }

    public function updateAction(Application $app, Request $request, $id)
    {
        $this->logger->debug('Bitbucket\ApiController::update', ['remote_id' => $id]);
        $config = $this->getRemoteConfiguration($id);
        if (!$config) {
            return new Response();
        }

        $data = $this->handleSensitiveDataInput($request->query->all());

        if (array_key_exists('bitbucket_token', $data)) {
            $config->setToken($data['bitbucket_token']);
        }

        if (array_key_exists('bitbucket_username', $data)) {
            $config->setUsername($data['bitbucket_username']);
        }

        if (array_key_exists('bitbucket_account', $data)) {
            $config->setUsername($data['bitbucket_account']);
        }

        $config->setEnabled($config->getRemote()->isEnabled());

        $this->em->persist($config);

        $this->logger->info('Bitbucket\ApiController::update - remote updated', ['remote_id' => $id]);

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
        return $this->em->getRepository('Terramar\Packages\Plugin\Bitbucket\RemoteConfiguration');
    }
}
