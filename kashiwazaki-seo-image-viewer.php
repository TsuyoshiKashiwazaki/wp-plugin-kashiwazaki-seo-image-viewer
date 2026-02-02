<?php
/**
 * Plugin Name: Kashiwazaki SEO Image Viewer
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: A lightweight WordPress plugin that combines an elegant Lightbox image viewer with comprehensive image SEO diagnostics. Analyzes alt attributes, filenames, dimensions, file sizes, and next-gen format (WebP/AVIF) support to calculate an overall image SEO score for each post.
 * Version: 1.0.3
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-image-viewer
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数
define('KSIV_VERSION', '1.0.3');
define('KSIV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSIV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSIV_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * メインプラグインクラス
 */
final class Kashiwazaki_SEO_Image_Viewer {

    /**
     * シングルトンインスタンス
     */
    private static ?self $instance = null;

    /**
     * 設定インスタンス
     */
    public Kashiwazaki_SEO_Image_Viewer_Settings $settings;

    /**
     * 管理画面インスタンス
     */
    public ?Kashiwazaki_SEO_Image_Viewer_Admin $admin = null;

    /**
     * フロントエンドインスタンス
     */
    public ?Kashiwazaki_SEO_Image_Viewer_Frontend $frontend = null;

    /**
     * SEOチェッカーインスタンス
     */
    public Kashiwazaki_SEO_Image_Viewer_SEO_Checker $seo_checker;

    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * 依存ファイルを読み込み
     */
    private function load_dependencies(): void {
        require_once KSIV_PLUGIN_DIR . 'includes/class-settings.php';
        require_once KSIV_PLUGIN_DIR . 'includes/class-seo-checker.php';
        require_once KSIV_PLUGIN_DIR . 'includes/class-admin.php';
        require_once KSIV_PLUGIN_DIR . 'includes/class-frontend.php';
    }

    /**
     * コンポーネントを初期化
     */
    private function init_components(): void {
        $this->settings = new Kashiwazaki_SEO_Image_Viewer_Settings();
        $this->seo_checker = new Kashiwazaki_SEO_Image_Viewer_SEO_Checker($this->settings);

        if (is_admin()) {
            $this->admin = new Kashiwazaki_SEO_Image_Viewer_Admin($this->settings, $this->seo_checker);
        }

        $this->frontend = new Kashiwazaki_SEO_Image_Viewer_Frontend($this->settings);
    }

    /**
     * フックを登録
     */
    private function register_hooks(): void {
        // プラグイン有効化時
        register_activation_hook(__FILE__, [$this, 'activate']);

        // プラグイン無効化時
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // プラグインリストにアクションリンクを追加
        add_filter('plugin_action_links_' . KSIV_PLUGIN_BASENAME, [$this, 'add_action_links']);

        // 翻訳ファイルの読み込み
        add_action('init', [$this, 'load_textdomain']);
    }

    /**
     * プラグイン有効化
     */
    public function activate(): void {
        // デフォルト設定を保存
        if (get_option('ksiv_settings') === false) {
            update_option('ksiv_settings', $this->settings->get_defaults());
        }

        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }

    /**
     * プラグイン無効化
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * プラグインリストにアクションリンクを追加
     */
    public function add_action_links(array $links): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=kashiwazaki-seo-image-viewer')),
            esc_html__('設定', 'kashiwazaki-seo-image-viewer')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * 翻訳ファイルを読み込み
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'kashiwazaki-seo-image-viewer',
            false,
            dirname(KSIV_PLUGIN_BASENAME) . '/languages'
        );
    }
}

/**
 * プラグインインスタンスを取得
 */
function kashiwazaki_seo_image_viewer(): Kashiwazaki_SEO_Image_Viewer {
    return Kashiwazaki_SEO_Image_Viewer::get_instance();
}

// プラグインを初期化
kashiwazaki_seo_image_viewer();
