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
use Terramar\Packages\Entity\Package;
use Terramar\Packages\Plugin\Actions;
use Terramar\Packages\Repository\PackageRepository;
use Terramar\Packages\Utils;
use Terramar\Packages\Controller\PackageController as BasePackageController;

class PackageController extends AbstractApiController
{
    function getSensitiveDataKeys()
    {
        return [];
    }

    /**
     * Availables query parameters:
     *   * enabled         boolean [true] whether to retrieve only enabled package,
     *                                    can be true/false or null (to get enabled and disabled packages)
     *   * remote_enabled  boolean [true] whether to retrieve package related to enabled remote,
     *                                    can be true/false or null (to get from enabled and disabled remotes)
     *   * search          string  [null] term to search in Package::fqn property
     *   * plugin_data     boolean [true] extends returned data with all plugins infos
     *   * perPage         int     [null] no pagination when perPage null
     *   * page            int     [1]
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
        $remoteEnabled = $request->get('remote_enabled', true);
        $search = $request->get('search');
        $pluginData = $request->get('plugin_data', true);
        list($page, $perPage, $offset) = $this->getPaginationParameters($request);

        $packagesPaginator = $this->getPackageRepository()
            ->addEnabledCriteria($enabled)
            ->addEnabledRemoteCriteria($remoteEnabled)
            ->addSearchCriteria($search)
            ->setLimit($perPage)
            ->setOffset($offset)
            ->getPaginator();

        $data = [];
        foreach($packagesPaginator->getIterator() as $i => $package) {
            /** @var Package $package */
            $data[$i] = $pluginData
                ? $this->extendWithPluginInfos($request, $package)
                : $package->toArray($this->serializer);
        }

        return new JsonResponse([
            'page' => $page,
            'perPage' => $perPage,
            'total' => $packagesPaginator->count(),
            '_links' => $this->paginatorlinksSection('api_package_list', $request, $packagesPaginator->count()),
            'data' => $data,
        ]);
    }

    public function packageAction(Application $app, Request $request, $id)
    {
        try {
            $package = $this->getPackage($id);
            $data = $this->extendWithPluginInfos($request, $package);

            return new JsonResponse($data);

        } catch (NotFoundHttpException $exception) {
            $this->logger->warning('Api\PackageController : Unable to find project', ['FqnOrId' => $id]);
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage()
            ], 404);
        }
    }

    public function updateAction(Application $app, Request $request, $id)
    {
        try {
            $package = $this->getPackage($id);
            $packageController = new BasePackageController(BasePackageController::CONTEXT_API);
            $packageController->updateAction($app, $request, $package->getId());

            return $this->packageAction($app, $request, $package->getId());

        } catch (NotFoundHttpException $exception) {
            $this->logger->warning('Api\PackageController : Unable to find project', ['FqnOrId' => $id]);
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage()
            ], 404);
        } catch (\Exception $exception) {
            $this->logger->error('An error occured', ['exception' => $exception]);
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage()
            ], $exception->getCode() ?: 500);
        }
    }

    /**
     * @return PackageRepository|EntityRepository
     */
    protected function getPackageRepository()
    {
        return $this->em->getRepository('Terramar\Packages\Entity\Package');
    }

    /**
     * @param int|string $idOrName
     *
     * @return Package|null
     */
    protected function getPackage($idOrName)
    {
        $package = $this->getPackageRepository()->getPackage(urldecode($idOrName));
        if (!$package) {
            throw new NotFoundHttpException(sprintf('Package "%s" not found', $idOrName));
        }

        return $package;
    }

    /**
     * @param Request $request
     * @param Package $package
     *
     * @return array|bool|mixed
     */
    protected function extendWithPluginInfos(Request $request, Package $package) {
        $extras = $this->pluginHelper->invokeAction(
            $request,
            Actions::PACKAGE_API_GET,
            array_merge($request->request->all(), ['id' => $package->getId()])
        );

        $data = $package->toArray($this->serializer);
        foreach ($extras as $json) {
            $data = Utils::arrayDeepMerge($data, json_decode($json, true));
        }

        return $data;
    }
}
