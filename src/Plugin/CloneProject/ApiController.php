<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\CloneProject;

use Nice\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Terramar\Packages\Controller\Api\AbstractApiController;

class ApiController extends AbstractApiController
{
    function getSensitiveDataKeys()
    {
        return [];
    }

    public function getAction(Application $app, Request $request, $id)
    {
        $config = $this->getConfiguration($id);
        return new JsonResponse([
            'cloneproject_enabled' => $config->isEnabled()
        ]);
    }

    public function updateAction(Application $app, Request $request, $id)
    {
        $config = $this->getConfiguration($id);
        $postData = $this->handleSensitiveDataInput($request->request->all());
        if (array_key_exists('cloneproject_enabled', $postData)) {
            $config->setEnabled($postData['cloneproject_enabled'] ? true : false);
            $this->em->persist($config);
        }

        return new Response();
    }

    /**
     * @param integer $id
     * @return PackageConfiguration
     */
    public function getConfiguration($id)
    {
        $config = $this->getRepository()->findOneBy(['package' => $id]);
        if (!$config) {
            throw new NotFoundHttpException();
        }

        return $config;
    }

    public function getRepository()
    {
        return $this->em->getRepository('Terramar\Packages\Plugin\CloneProject\PackageConfiguration');
    }
}
