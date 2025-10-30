<?php
namespace Unit;

use Domain\Entity\MediaFile;
use Presentation\Transformer\EntityToArrayTransformer;
use Ramsey\Uuid\Uuid;
use DateTime;

class TransformerTest
{
    public function run(): void
    {
        $mf = new MediaFile(
            Uuid::uuid4()->toString(),
            'test.png',
            '/uploads/test.png',
            'image',
            2048,
            'tester',
            new DateTime('2025-01-01 00:00:00'),
            'original-test.png',
            'image/png',
            20,
            20,
            'Alt text'
        );

        $arr = EntityToArrayTransformer::mediaFileToArray($mf);

        $expectedKeys = [
            'file_id', 'filename', 'original_filename', 'file_url', 'type', 'mime_type',
            'size', 'human_size', 'width', 'height', 'alt_text', 'uploaded_by', 'uploaded_at'
        ];

        foreach ($expectedKeys as $k) {
            if (!array_key_exists($k, $arr)) {
                throw new \Exception("Missing key in transformer output: $k");
            }
        }
    }
}
