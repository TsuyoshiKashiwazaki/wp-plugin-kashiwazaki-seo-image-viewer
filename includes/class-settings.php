<?php
/**
 * 設定管理クラス
 *
 * @package Kashiwazaki_SEO_Image_Viewer
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 設定管理クラス
 */
class Kashiwazaki_SEO_Image_Viewer_Settings {

    /**
     * オプション名
     */
    private const OPTION_NAME = 'ksiv_settings';

    /**
     * 設定値のキャッシュ
     */
    private ?array $settings = null;

    /**
     * デフォルト設定を取得
     */
    public function get_defaults(): array {
        return [
            // Lightbox設定
            'lightbox_enabled' => true,
            'show_zoom_icon' => true,
            'zoom_icon_position' => 'bottom-right',
            'max_zoom_ratio' => 300,
            'animation_speed' => 300,
            'info_panel_default_visible' => false,

            // SEO警告設定
            'alt_warning_enabled' => true,
            'filename_warning_patterns' => [
                '/^IMG_\d+/i',
                '/^DSC_?\d+/i',
                '/^DSCN?\d+/i',
                '/^P\d{7,}/i',
                '/^screenshot/i',
                '/^スクリーンショット/i',
                '/^画像\d*/i',
                '/^image\d*/i',
                '/^photo\d*/i',
                '/^picture\d*/i',
                '/^\d+$/i',
            ],
            'size_warning_threshold' => 2.0,
            'filesize_warning_threshold' => 500,
            'next_gen_warning_enabled' => true,

            // スコア重み付け
            'score_weight_alt' => 20,
            'score_weight_filename' => 10,
            'score_weight_size' => 10,
            'score_weight_filesize' => 15,
            'score_weight_next_gen' => 5,
        ];
    }

    /**
     * 全設定を取得
     */
    public function get_all(): array {
        if ($this->settings === null) {
            $saved = get_option(self::OPTION_NAME, []);
            $this->settings = wp_parse_args($saved, $this->get_defaults());
        }
        return $this->settings;
    }

    /**
     * 設定値を取得
     */
    public function get(string $key, $default = null) {
        $settings = $this->get_all();
        return $settings[$key] ?? $default;
    }

    /**
     * 設定を保存
     */
    public function save(array $settings): bool {
        $defaults = $this->get_defaults();
        $sanitized = [];

        // Lightbox設定
        $sanitized['lightbox_enabled'] = !empty($settings['lightbox_enabled']);
        $sanitized['show_zoom_icon'] = !empty($settings['show_zoom_icon']);
        $sanitized['zoom_icon_position'] = in_array(
            $settings['zoom_icon_position'] ?? '',
            ['bottom-right', 'bottom-left', 'top-right', 'top-left'],
            true
        ) ? $settings['zoom_icon_position'] : $defaults['zoom_icon_position'];
        $sanitized['max_zoom_ratio'] = max(100, min(1000, intval($settings['max_zoom_ratio'] ?? $defaults['max_zoom_ratio'])));
        $sanitized['animation_speed'] = max(0, min(2000, intval($settings['animation_speed'] ?? $defaults['animation_speed'])));
        $sanitized['info_panel_default_visible'] = !empty($settings['info_panel_default_visible']);

        // SEO警告設定
        $sanitized['alt_warning_enabled'] = !empty($settings['alt_warning_enabled']);

        // ファイル名警告パターン
        if (isset($settings['filename_warning_patterns'])) {
            if (is_array($settings['filename_warning_patterns'])) {
                $patterns = $settings['filename_warning_patterns'];
            } else {
                $patterns = array_filter(array_map('trim', explode("\n", $settings['filename_warning_patterns'])));
            }
            $valid_patterns = [];
            foreach ($patterns as $pattern) {
                $pattern = trim($pattern);
                if (!empty($pattern) && @preg_match($pattern, '') !== false) {
                    $valid_patterns[] = $pattern;
                }
            }
            $sanitized['filename_warning_patterns'] = $valid_patterns;
        } else {
            $sanitized['filename_warning_patterns'] = $defaults['filename_warning_patterns'];
        }

        $sanitized['size_warning_threshold'] = max(1.0, min(10.0, floatval($settings['size_warning_threshold'] ?? $defaults['size_warning_threshold'])));
        $sanitized['filesize_warning_threshold'] = max(50, min(10000, intval($settings['filesize_warning_threshold'] ?? $defaults['filesize_warning_threshold'])));
        $sanitized['next_gen_warning_enabled'] = !empty($settings['next_gen_warning_enabled']);

        // スコア重み付け
        $sanitized['score_weight_alt'] = max(0, min(100, intval($settings['score_weight_alt'] ?? $defaults['score_weight_alt'])));
        $sanitized['score_weight_filename'] = max(0, min(100, intval($settings['score_weight_filename'] ?? $defaults['score_weight_filename'])));
        $sanitized['score_weight_size'] = max(0, min(100, intval($settings['score_weight_size'] ?? $defaults['score_weight_size'])));
        $sanitized['score_weight_filesize'] = max(0, min(100, intval($settings['score_weight_filesize'] ?? $defaults['score_weight_filesize'])));
        $sanitized['score_weight_next_gen'] = max(0, min(100, intval($settings['score_weight_next_gen'] ?? $defaults['score_weight_next_gen'])));

        $this->settings = $sanitized;
        return update_option(self::OPTION_NAME, $sanitized);
    }

    /**
     * 設定をリセット
     */
    public function reset(): bool {
        $this->settings = null;
        return delete_option(self::OPTION_NAME);
    }
}
