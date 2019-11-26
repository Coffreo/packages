<?php
/**
 * @date   2019-11-26 02:48
 */

namespace Terramar\Packages;

use Symfony\Component\Serializer\SerializerInterface;

trait ApiSerializableEntityTrait
{
    /**
     * @param SerializerInterface $serializer
     * @param array               $groups
     *
     * @return array|null
     */
    public function toArray(SerializerInterface $serializer, $groups = ['rest'])
    {
        $json = $serializer->serialize($this, 'json', ['groups' => $groups]);

        return json_decode($json, true);
    }
}
