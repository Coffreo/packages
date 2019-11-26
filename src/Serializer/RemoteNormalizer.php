<?php
/**
 * @date 2019-11-20 02:19
 */

namespace Terramar\Packages\Serializer;

use Nice\Router\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Terramar\Packages\Entity\Package;
use Terramar\Packages\Entity\Remote;

/**
 * Class PackageNormalizer
 *
 * @package Terramar\Packages\Plugin\Api
 * @author  Cyril MERY <cmery@coffreo.com>
 */
class RemoteNormalizer implements NormalizerInterface
{
    private $router;
    private $normalizer;

    public function __construct(UrlGeneratorInterface $router, ObjectNormalizer $normalizer)
    {
        $this->router = $router;
        $this->normalizer = $normalizer;
    }

    /**
     * Normalizes an object into a set of arrays/scalars.
     *
     * @param Remote $remote   Object to normalize
     * @param string $format   Format the normalization result will be encoded as
     * @param array $context   Context options for the normalizer
     *
     * @return array
     */
    public function normalize($remote, $format = null, array $context = [])
    {
        return array_merge(
            ['_links' => $this->getLinksSection($remote)],
            $this->normalizer->normalize($remote, $format, $context)
        );
    }

    protected function getLinksSection($remote)
    {
        return [
            'self' => [
                'url' => $this->router->generate('api_remote_get', ['id' => $remote->getId()], true),
                'type' => 'GET'
            ],
            'update' => [
                'url' => $this->router->generate('api_remote_update', ['id' => $remote->getId()], true),
                'type' => 'POST'
            ],
        ];
    }

    /**
     * Checks whether the given class is supported for normalization by this normalizer.
     *
     * @param mixed  $data   Data to normalize
     * @param string $format The format being (de-)serialized from or into
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof Remote;
    }
}
