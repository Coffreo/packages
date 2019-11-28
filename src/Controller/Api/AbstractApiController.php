<?php
/**
 * @date 2019-11-25 11:11
 */

namespace Terramar\Packages\Controller\Api;

use Doctrine\ORM\EntityManager;
use Nice\Router\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Terramar\Packages\Application;
use Terramar\Packages\Helper\PluginHelper;

/**
 * Class AbstractApiController
 *
 * @package Terramar\Packages\Controller\Api
 * @author  Cyril MERY <cmery@coffreo.com>
 */
abstract class AbstractApiController
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var PluginHelper
     */
    protected $pluginHelper;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * AbstractApiController constructor.
     *
     * @param EntityManager         $em
     * @param LoggerInterface       $logger
     * @param SerializerInterface   $serializer
     * @param PluginHelper          $pluginHelper
     * @param UrlGeneratorInterface $router
     */
    public function __construct(
        EntityManager $em,
        LoggerInterface $logger,
        SerializerInterface $serializer = null,
        PluginHelper $pluginHelper = null,
        UrlGeneratorInterface $router = null
    )
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->pluginHelper = $pluginHelper;
        $this->router = $router;
    }

    /**
     * @param Request $request
     *
     * @return array
     *
     * @throws BadRequestHttpException
     */
    protected function getPaginationParameters(Request $request)
    {
        $perPage = $request->get('perPage');
        $page = (int) $request->get('page', 1) ?: 1;

        $perPage = is_numeric($perPage) ? (int) $perPage : null;
        $offset = ($page - 1) * ($perPage ?: 0);

        if ($offset < 0) {
            throw new BadRequestHttpException('Invalid page parameter');
        }

        return [$page, $perPage, $offset];
    }

    protected function serialize($data)
    {
        return $this->serializer->serialize($data, 'json', ['groups' => ['rest']]);
    }

    protected function paginatorlinksSection($routeName, Request $request, $total)
    {
        list($page, $perPage) = $this->getPaginationParameters($request);

        $maxPage = ($perPage ? ceil($total / $perPage) : 1) ?: 1;

        $queryParameters = array_merge($request->query->all(), compact('page', 'perPage'));
        $current = $this->generateRoute($routeName, $queryParameters);

        $first = $this->generateRoute($routeName, array_merge($queryParameters, ['page' => 1]));

        $hasPreviousPage = $page > 1;
        $previous = $hasPreviousPage
            ? $this->generateRoute($routeName, array_merge($queryParameters, ['page' => min($page - 1, $maxPage)]))
            : null;

        $hasNextPage = $page < $maxPage;
        $next = $hasNextPage
            ? $this->generateRoute($routeName, array_merge($queryParameters, ['page' => $page + 1]))
            : null;

        $last = $this->generateRoute($routeName, array_merge($queryParameters, ['page' => $maxPage]));

        return array_filter(compact('current', 'first', 'previous', 'next', 'last'));
    }

    private function generateRoute($routeName, $query, $absolute = true)
    {
        $url = $this->router->generate($routeName, [], $absolute);
        if (!empty($query)) {
            $url .= strpos($url, '?') ? '&' : '?';
            $url .= http_build_query($query);
        }

        return $url;
    }
}
