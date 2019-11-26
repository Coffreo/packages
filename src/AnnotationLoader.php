<?php
/**
 * @date 2019-11-20 13:07
 */

namespace Terramar\Packages;

use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * Class AnnotationLoader
 *
 * @package Terramar\Packages
 * @author  Cyril MERY <cmery@coffreo.com>
 */
class AnnotationLoader
{
    static $files = [
        __DIR__.'/../vendor/symfony/serializer/Annotation/Groups.php'
    ];

    static public function loadAnnotations()
    {
        foreach (self::$files as $file) {
            AnnotationRegistry::registerFile($file);
        }
    }
}
