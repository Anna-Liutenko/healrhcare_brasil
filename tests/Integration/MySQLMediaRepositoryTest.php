<?php
namespace Integration;

use Domain\Entity\MediaFile;
use Infrastructure\Repository\MySQLMediaRepository;
use Ramsey\Uuid\Uuid;
use DateTime;

class MySQLMediaRepositoryTest
{
    public function run(): void
    {
        $id = Uuid::uuid4()->toString();
        $mf = new MediaFile(
            $id,
            'test-repo.png',
            '/uploads/test-repo.png',
            'image',
            3000,
            // Use known admin user that exists in test DB to satisfy FK
            '550e8400-e29b-41d4-a716-446655440001',
            new DateTime(),
            'orig-repo.png',
            'image/png',
            30,
            30,
            'Alt repo'
        );

        $repo = new MySQLMediaRepository();
        // Save
        $repo->save($mf);

        // Retrieve
        $retrieved = $repo->findById($id);
        if ($retrieved === null) {
            throw new \Exception('Record not found after save');
        }
        if ($retrieved->getFilename() !== 'test-repo.png') {
            throw new \Exception('Filename mismatch after retrieve');
        }

        // Cleanup
        $repo->delete($id);

        $shouldBeNull = $repo->findById($id);
        if ($shouldBeNull !== null) {
            throw new \Exception('Record still exists after delete');
        }
    }
}
