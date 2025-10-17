<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\Setting;
use Domain\Repository\SettingsRepositoryInterface;
use InvalidArgumentException;

class UpdateGlobalSettings
{
    private const KEY_MAP = GetGlobalSettings::KEY_MAP;

    public function __construct(private SettingsRepositoryInterface $settingsRepository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(array $payload): void
    {
        $existing = $this->indexSettingsByKey($this->settingsRepository->getAll());
        $updates = [];

        foreach ($payload as $group => $groupData) {
            if (!is_array($groupData)) {
                throw new InvalidArgumentException(sprintf('Group "%s" must be an object', $group));
            }

            $this->assertKnownGroup($group);

            foreach ($groupData as $field => $value) {
                $key = $this->resolveSettingKey($group, $field);
                if ($key === null) {
                    throw new InvalidArgumentException(sprintf('Unknown setting field "%s.%s"', $group, $field));
                }

                if (!isset($existing[$key])) {
                    throw new InvalidArgumentException(sprintf('Setting "%s" not found', $key));
                }

                $setting = $existing[$key];
                $normalizedValue = $this->normalizeValue($setting, $value);
                $updates[] = $setting->withValue($normalizedValue);
            }
        }

        if ($updates !== []) {
            $this->settingsRepository->updateMany($updates);
        }
    }

    /**
     * @param Setting[] $settings
     * @return array<string, Setting>
     */
    private function indexSettingsByKey(array $settings): array
    {
        $indexed = [];
        foreach ($settings as $setting) {
            $indexed[$setting->getKey()] = $setting;
        }

        return $indexed;
    }

    private function resolveSettingKey(string $group, string $field): ?string
    {
        foreach (self::KEY_MAP as $key => $mapping) {
            if ($mapping['group'] === $group && $mapping['field'] === $field) {
                return $key;
            }
        }

        return null;
    }

    private function assertKnownGroup(string $group): void
    {
        foreach (self::KEY_MAP as $mapping) {
            if ($mapping['group'] === $group) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown settings group "%s"', $group));
    }

    private function normalizeValue(Setting $setting, mixed $value): mixed
    {
        $type = $setting->getType();

        return match ($type) {
            'boolean' => $this->toBool($value),
            'number' => $this->toNumber($value, $setting->getKey()),
            'json' => $this->toJsonValue($value, $setting->getKey()),
            default => $this->toStringValue($value, $setting->getKey()),
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private function toNumber(mixed $value, string $key): float
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Setting "%s" expects numeric value', $key));
        }

        return (float) $value;
    }

    private function toJsonValue(mixed $value, string $key): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(sprintf('Setting "%s" expects valid JSON string', $key));
            }

            return $decoded;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Setting "%s" expects JSON compatible value', $key));
        }

        return $value;
    }

    private function toStringValue(mixed $value, string $key): string
    {
        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException(sprintf('Setting "%s" expects scalar value', $key));
        }

        return (string) $value;
    }
}
