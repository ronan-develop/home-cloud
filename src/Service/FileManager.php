<?php

namespace App\Service;

use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;

class FileManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Crée et persiste une entité File à partir des métadonnées fournies
     * @param array $data [originalName, path, size, mimeType, hash]
     * @return File
     */
    public function createAndSave(array $data): File
    {
        $file = new File();
        $file->setName($data['originalName']);
        $file->setPath($data['path']);
        $file->setSize($data['size']);
        $file->setMimeType($data['mimeType']);
        $file->setUploadedAt(new \DateTimeImmutable());
        $file->setHash($data['hash']);
        $this->em->persist($file);
        $this->em->flush();
        return $file;
    }
}
