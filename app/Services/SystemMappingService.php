<?php

namespace App\Services;

class SystemMappingService
{
    /**
     * Map loja_id to system information
     */
    private static array $systemsMap = [
        205404185 => [
            'name' => 'Beauty',
            'short_name' => 'Coll Me! Beauty',
            'color' => 'bg-yellow-400 text-gray-900 border border-yellow-500',
            'indicator_color' => 'bg-yellow-500',
            'gradient_from' => 'from-yellow-400',
            'gradient_to' => 'to-yellow-500',
            'border_color' => 'border-yellow-400',
        ],
        205274224 => [
            'name' => 'Pro',
            'short_name' => 'Coll Me! Pro',
            'color' => 'bg-blue-600 text-white border border-blue-700',
            'indicator_color' => 'bg-blue-600',
            'gradient_from' => 'from-blue-600',
            'gradient_to' => 'to-blue-700',
            'border_color' => 'border-blue-500',
        ],
        205483126 => [
            'name' => 'TikTok',
            'short_name' => 'TikTok',
            'color' => 'bg-gray-100 text-gray-800 border border-gray-200',
            'indicator_color' => 'bg-gray-500',
            'gradient_from' => 'from-gray-400',
            'gradient_to' => 'to-gray-500',
            'border_color' => 'border-gray-400',
        ],
        205432408 => [
            'name' => 'Atacadista', // Atacadista is also Pro system
            'short_name' => 'Coll Me! Atacadista',
            'color' => 'bg-green-600 text-white border border-green-700',
            'indicator_color' => 'bg-green-600',
            'gradient_from' => 'from-green-600',
            'gradient_to' => 'to-green-700',
            'border_color' => 'border-green-500',
            'is_atacadista' => true,
        ]
    ];

    /**
     * Get system name by loja_id
     */
    public static function getSystemName(int $lojaId): string
    {
        // Handle special case for loja_id = 0 (usually old orders or imports)
        if ($lojaId === 0) {
            return '-';
        }
        
        return self::$systemsMap[$lojaId]['name'] ?? 'Unknown System';
    }

    /**
     * Get system short name by loja_id
     */
    public static function getSystemShortName(int $lojaId): string
    {
        return self::$systemsMap[$lojaId]['short_name'] ?? '-';
    }

    /**
     * Get system badge color by loja_id
     */
    public static function getSystemBadgeColor(int $lojaId): string
    {
        return self::$systemsMap[$lojaId]['color'] ?? 'bg-gray-100 text-gray-800 border border-gray-200';
    }

    /**
     * Get system indicator color by loja_id
     */
    public static function getSystemIndicatorColor(int $lojaId): string
    {
        return self::$systemsMap[$lojaId]['indicator_color'] ?? 'bg-gray-500';
    }

    /**
     * Get system gradient classes by loja_id
     */
    public static function getSystemGradientClasses(int $lojaId): array
    {
        $system = self::$systemsMap[$lojaId] ?? null;
        if (!$system) {
            return ['from-gray-600', 'to-gray-700', 'border-gray-500'];
        }

        return [
            'from' => $system['gradient_from'],
            'to' => $system['gradient_to'],
            'border' => $system['border_color']
        ];
    }

    /**
     * Check if system is Atacadista
     */
    public static function isAtacadista(int $lojaId): bool
    {
        return self::$systemsMap[$lojaId]['is_atacadista'] ?? false;
    }

    /**
     * Get all available systems
     */
    public static function getAllSystems(): array
    {
        return self::$systemsMap;
    }

    /**
     * Get system by loja_id with all information
     */
    public static function getSystem(int $lojaId): ?array
    {
        return self::$systemsMap[$lojaId] ?? null;
    }

    /**
     * Map loja_id to source name (for backward compatibility)
     */
    public static function mapLojaIdToSource(int $lojaId): string
    {
        return self::getSystemName($lojaId);
    }

    /**
     * Get system short name by source name (reverse lookup)
     */
    public static function getShortNameBySource(string $source): string
    {
        foreach (self::$systemsMap as $config) {
            if ($config['name'] === $source) {
                return $config['short_name'];
            }
        }
        return '-'; // Unknown
    }

    /**
     * Get system badge color by source name (reverse lookup)
     */
    public static function getBadgeColorBySource(string $source): string
    {
        foreach (self::$systemsMap as $config) {
            if ($config['name'] === $source) {
                return $config['color'];
            }
        }
        return 'bg-gray-100 text-gray-800 border border-gray-200';
    }

    /**
     * Get system indicator color by source name (reverse lookup)
     */
    public static function getIndicatorColorBySource(string $source): string
    {
        foreach (self::$systemsMap as $config) {
            if ($config['name'] === $source) {
                return $config['indicator_color'];
            }
        }
        return 'bg-gray-500';
    }

    /**
     * Get system gradient classes by source name (reverse lookup)
     */
    public static function getGradientClassesBySource(string $source): array
    {
        foreach (self::$systemsMap as $config) {
            if ($config['name'] === $source) {
                return [
                    'from' => $config['gradient_from'],
                    'to' => $config['gradient_to'],
                    'border' => $config['border_color']
                ];
            }
        }
        return ['from-gray-600', 'to-gray-700', 'border-gray-500'];
    }

    /**
     * Get all available source names
     */
    public static function getAllSourceNames(): array
    {
        $sources = [];
        foreach (self::$systemsMap as $config) {
            if (!in_array($config['name'], $sources)) {
                $sources[] = $config['name'];
            }
        }
        // Add legacy orders
        $sources[] = '-';
        return $sources;
    }

    /**
     * Get systems mapping for frontend dropdown (loja_id => source_name)
     */
    public static function getSystemsForDropdown(): array
    {
        $dropdown = [];
        foreach (self::$systemsMap as $lojaId => $config) {
            $dropdown[$lojaId] = $config['name'];
        }
        // Add -
        $dropdown[0] = '-';
        return $dropdown;
    }
}
