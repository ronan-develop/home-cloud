<?php

namespace App\Services;

use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

class ImageDirectoryNamer implements DirectoryNamerInterface
{
    /**
     * @param \App\Entity\Upload $object
     * @param Vich\UploaderBundle\Mapping\PropertyMapping $mapping
     * 
     *  @return string
     */
    public function directoryName($object, PropertyMapping $maping): string
    {
// TODO User $user->getUser()
        $user = $object->getOwner();

        return  $this->security->getUser();
    }
}