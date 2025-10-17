<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use DateTimeImmutable;
use Domain\Entity\Setting;
use Domain\Repository\SettingsRepositoryInterface;
use Infrastructure\Database\Connection;
use InvalidArgumentException;
use PDO;
use PDOException;

class MySQLSettingsRepository implements SettingsRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    /**
     * @return Setting[]
     */
    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT id, setting_key, setting_value, setting_type, setting_group, description, updated_at FROM settings ORDER BY setting_group, setting_key');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row): Setting {
            return new Setting(
                id: isset($row['id']) ? (int) $row['id'] : null,
                key: $row['setting_key'],
                value: $this->castFromDatabase($row['setting_type'], $row['setting_value']),
                type: $row['setting_type'],
                group: $row['setting_group'],
                description: $row['description'] ?? null,
                updatedAt: isset($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
            );
        }, $rows);
    }

    /**
     * @param Setting[] $settings
     */
    public function updateMany(array $settings): void
    {
        if ($settings === []) {
            return;
        }

        Connection::beginTransaction();

        try {
            $stmt = $this->db->prepare('UPDATE settings SET setting_value = :value, updated_at = NOW() WHERE setting_key = :key');

            foreach ($settings as $setting) {
                if (!$setting instanceof Setting) {
                    throw new InvalidArgumentException('Invalid setting provided');
                }

                $stmt->execute([
                    'key' => $setting->getKey(),
                    'value' => $this->castToDatabase($setting->getType(), $setting->getValue())
                ]);
            }

            Connection::commit();
        } catch (PDOException | InvalidArgumentException $exception) {
            Connection::rollBack();
            throw $exception;
        }
    }

    private function castFromDatabase(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => in_array($value, ['1', 1, true, 'true'], true),
            'number' => is_numeric($value) ? 0 + $value : null,
            'json' => $this->decodeJson((string) $value),
            default => (string) $value,
        };
    }

    private function castToDatabase(string $type, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'number' => is_numeric($value) ? (string) $value : (string) (float) $value,
            'json' => $this->encodeJson($value),
            default => (string) $value,
        };
    }

    private function decodeJson(string $value): mixed
    {
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function encodeJson(mixed $value): string
    {
        if (is_string($value)) {
            // Assume already a JSON string
            json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new InvalidArgumentException('Invalid JSON value for settings update');
        }

        return $encoded;
    }
}
