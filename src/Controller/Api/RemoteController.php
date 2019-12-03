<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Controller\Api;

use Doctrine\ORM\EntityRepository;
use Nice\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Terramar\Packages\Entity\Remote;
use Terramar\Packages\Plugin\Actions;
use Terramar\Packages\Repository\RemoteRepository;
use Terramar\Packages\Utils;
use Terramar\Packages\Controller\RemoteController as BaseRemoteController;

class RemoteController extends AbstractApiController
{
    function getSensitiveDataKeys()
    {
        return [];
    }

    /**
     * Availables query parameters:
     *   * enabled      boolean [true] whether to retrieve only enabled remote,
     *                                 can be true/false or null (to get enabled and disabled remotes)
     *   * search       string  [null] term to search in Remote::name property
     *   * plugin_data  boolean [true] extends returned data with all plugins infos
     *   * perPage      int     [null] no pagination when perPage null
     *   * page         int     [1]
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return JsonResponse
     *
     * @throws BadRequestHttpException when calculated offset is lower than 0
     */
    public function indexAction(Application $app, Request $request)
    {
        $enabled = $request->get('enabled');
        $search = $request->get('search');
        $pluginData = $request->get('plugin_data', true);
        list($page, $perPage, $offset) = $this->getPaginationParameters($request);

        $remotesPaginator = $this->getRemoteRepository()
            ->addEnabledCriteria($enabled)
            ->addSearchCriteria($search)
            ->setLimit($perPage)
            ->setOffset($offset)
            ->getPaginator();

        $data = [];
        foreach($remotesPaginator->getIterator() as $i => $remote) {
            /** @var Remote $remote */
            $data[$i] =
                $pluginData
                ? $this->extendWithPluginInfos($request, $remote)
                : $remote->toArray($this->serializer);
        }

        return new JsonResponse([
            'page' => $page,
            'perPage' => $perPage,
            'total' => $remotesPaginator->count(),
            '_links' => $this->paginatorlinksSection('api_remote_list', $request, $remotesPaginator->count()),
            'data' => $data,
        ]);
    }

    public function remoteAction(Application $app, Request $request, $id)
    {
        try {
            $remote = $this->getRemote($id);
            $data = $this->extendWithPluginInfos($request, $remote);

            return new JsonResponse($data);

        } catch (NotFoundHttpException $exception) {
            $this->logger->warning('Api\RemoteController : Unable to find remote', ['nameOrId' => $id]);
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage()
            ], 404);
        }
    }

    public function updateAction(Application $app, Request $request, $id)
    {
        try {
            $remote = $this->getRemote($id);
            $remoteController = new BaseRemoteController(BaseRemoteController::CONTEXT_API);
            $remoteController->updateAction($app, $request, $remote->getId());

            return $this->remoteAction($app, $request, $remote->getId());

        } catch (NotFoundHttpException $exception) {
            $this->logger->warning('Api\RemoteController : Unable to find remote', ['nameOrId' => $id]);
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage()
            ], 404);
        } catch (\Exception $exception) {
            $this->logger->error('An error occured', ['exception' => $exception]);
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * @return RemoteRepository|EntityRepository
     */
    protected function getRemoteRepository()
    {
        return $this->em->getRepository('Terramar\Packages\Entity\Remote');
    }

    /**
     * @param int|string $idOrName
     *
     * @return Remote|null
     */
    protected function getRemote($idOrName)
    {
        $remote = $this->getRemoteRepository()->getRemote(urldecode($idOrName));
        if (!$remote) {
            throw new NotFoundHttpException(sprintf('Remote "%s" not found', $idOrName));
        }

        return $remote;
    }

    /**
     * @param Request $request
     * @param Remote $remote
     *
     * @return array|bool|mixed
     */
    protected function extendWithPluginInfos(Request $request, Remote $remote) {
        $extras = $this->pluginHelper->invokeAction(
            $request,
            Actions::REMOTE_API_GET,
            array_merge($request->request->all(), ['id' => $remote->getId()])
        );

        $data = $remote->toArray($this->serializer);
        foreach ($extras as $json) {
            $data = Utils::arrayDeepMerge($data, json_decode($json, true));
        }

        return $data;
    }
}
