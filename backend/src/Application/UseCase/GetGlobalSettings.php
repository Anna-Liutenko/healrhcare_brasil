<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\SettingsRepositoryInterface;

class GetGlobalSettings
{
    public const KEY_MAP = [
        'site_name' => ['group' => 'general', 'field' => 'site_name'],
        'site_description' => ['group' => 'general', 'field' => 'site_description'],
        'site_domain' => ['group' => 'general', 'field' => 'site_domain'],
        'header_logo_text' => ['group' => 'header', 'field' => 'logo_text'],
        'header_logo_url' => ['group' => 'header', 'field' => 'logo_url'],
        'footer_logo_text' => ['group' => 'footer', 'field' => 'logo_text'],
        'footer_copyright' => ['group' => 'footer', 'field' => 'copyright'],
        'footer_privacy_link' => ['group' => 'footer', 'field' => 'privacy_link'],
        'footer_privacy_text' => ['group' => 'footer', 'field' => 'privacy_text'],
        'cookie_banner_enabled' => ['group' => 'cookie_banner', 'field' => 'enabled'],
        'cookie_banner_message' => ['group' => 'cookie_banner', 'field' => 'message'],
        'cookie_banner_accept_text' => ['group' => 'cookie_banner', 'field' => 'accept_text'],
        'cookie_banner_details_text' => ['group' => 'cookie_banner', 'field' => 'details_text'],
        'global_tracking_code' => ['group' => 'tracking', 'field' => 'global_tracking_code'],
        'global_widgets_code' => ['group' => 'widgets', 'field' => 'global_widgets_code'],
    ];

    private const DEFAULT_STRUCTURE = [
        'general' => [
            'site_name' => null,
            'site_description' => null,
            'site_domain' => null,
        ],
        'header' => [
            'logo_text' => null,
            'logo_url' => null,
        ],
        'footer' => [
            'logo_text' => null,
            'copyright' => null,
            'privacy_link' => null,
            'privacy_text' => null,
        ],
        'cookie_banner' => [
            'enabled' => false,
            'message' => null,
            'accept_text' => null,
            'details_text' => null,
        ],
        'tracking' => [
            'global_tracking_code' => null,
        ],
        'widgets' => [
            'global_widgets_code' => null,
        ],
    ];

    public function __construct(private SettingsRepositoryInterface $settingsRepository)
    {
    }

    public function execute(): array
    {
        $settings = $this->settingsRepository->getAll();
        $result = self::DEFAULT_STRUCTURE;

        foreach ($settings as $setting) {
            $key = $setting->getKey();
            if (!isset(self::KEY_MAP[$key])) {
                continue;
            }

            $group = self::KEY_MAP[$key]['group'];
            $field = self::KEY_MAP[$key]['field'];
            $result[$group][$field] = $setting->getValue();
        }

        return $result;
    }
}
