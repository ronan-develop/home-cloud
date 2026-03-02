<?php

namespace App\Service;

use App\Entity\Folder;

class FolderTreeService
{
    /**
     * Construit récursivement l'arborescence d'un dossier
     * @return array
     */
    public function buildTree(Folder $folder): array
    {
        $children = [];
        foreach ($folder->getChildren() as $child) {
            $children[] = $this->buildTree($child);
        }
        return [
            'id' => $folder->getId()->toRfc4122(),
            'name' => $folder->getName(),
            'children' => $children,
        ];
    }
}
