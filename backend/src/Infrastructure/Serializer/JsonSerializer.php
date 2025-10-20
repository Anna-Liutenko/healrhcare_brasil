<?php

declare(strict_types=1);

namespace Infrastructure\Serializer;

class JsonSerializer
{
    /**
     * Recursively convert all array keys from snake_case to camelCase
     */
    public static function toCamelCase(array $data): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            // Конвертировать ключ
            $camelKey = self::snakeToCamel($key);
            
            // Рекурсивно обработать вложенные массивы
            if (is_array($value)) {
                $result[$camelKey] = self::toCamelCase($value);
            } else {
                $result[$camelKey] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert snake_case string to camelCase
     * Examples: show_in_menu → showInMenu, created_by → createdBy
     */
    private static function snakeToCamel(string $key): string
    {
        return preg_replace_callback('/_([a-z])/', function($matches) {
            return strtoupper($matches[1]);
        }, $key);
    }
}