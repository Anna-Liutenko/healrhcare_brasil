<?php
namespace Unit;

use Domain\Entity\MediaFile;
use Ramsey\Uuid\Uuid;
use DateTime;

class MediaFileTest
{
    public function run(): void
    {
        $id = Uuid::uuid4()->toString();
        $mf = new MediaFile(
            $id,
            'test.png',
            '/uploads/test.png',
            'image',
            1234,
            'tester',
            new DateTime('2025-01-01 00:00:00'),
            'original-test.png',
            'image/png',
            10,
            10,
            'Alt text'
        );

        if ($mf->getId() !== $id) {
            throw new \Exception('ID mismatch');
        }
        if ($mf->getFilename() !== 'test.png') {
            throw new \Exception('Filename mismatch');
        }
        if ($mf->getOriginalFilename() !== 'original-test.png') {
            throw new \Exception('Original filename mismatch');
        }
        if ($mf->getMimeType() !== 'image/png') {
            throw new \Exception('MIME type mismatch');
        }
        if ($mf->getWidth() !== 10 || $mf->getHeight() !== 10) {
            throw new \Exception('Width/Height mismatch');
        }
    }
}
