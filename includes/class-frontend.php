<?php
/**
 * フロントエンドクラス
 *
 * @package Kashiwazaki_SEO_Image_Viewer
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * フロントエンドクラス
 */
class Kashiwazaki_SEO_Image_Viewer_Frontend {

    /**
     * 設定インスタンス
     */
    private Kashiwazaki_SEO_Image_Viewer_Settings $settings;

    /**
     * コンストラクタ
     */
    public function __construct(Kashiwazaki_SEO_Image_Viewer_Settings $settings) {
        $this->settings = $settings;

        if ($this->settings->get('lightbox_enabled', true)) {
            $this->register_hooks();
        }
    }

    /**
     * フックを登録
     */
    private function register_hooks(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('the_content', [$this, 'process_content'], 20);
        add_action('wp_footer', [$this, 'render_lightbox_template']);

        // フロントエンド用AJAXハンドラー
        add_action('wp_ajax_ksiv_get_image_info', [$this, 'ajax_get_image_info']);
        add_action('wp_ajax_nopriv_ksiv_get_image_info', [$this, 'ajax_get_image_info']);
    }

    /**
     * 現在のページでLightboxを有効にするかチェック
     */
    private function should_enable_lightbox(): bool {
        $page_types = $this->settings->get('lightbox_page_types', []);

        // 投稿ページ
        if (!empty($page_types['single']) && is_single()) {
            return true;
        }

        // 固定ページ
        if (!empty($page_types['page']) && is_page()) {
            return true;
        }

        // アーカイブページ
        if (!empty($page_types['archive']) && is_archive()) {
            return true;
        }

        // フロントページ（静的フロントページまたは投稿一覧がフロントページの場合）
        if (!empty($page_types['front_page']) && is_front_page()) {
            return true;
        }

        // ホームページ（投稿一覧ページ、ただしフロントページでない場合）
        if (!empty($page_types['home']) && is_home() && !is_front_page()) {
            return true;
        }

        // 検索結果ページ
        if (!empty($page_types['search']) && is_search()) {
            return true;
        }

        // 添付ファイルページ（画像詳細ページ）
        if (!empty($page_types['attachment']) && is_attachment()) {
            return true;
        }

        // 404エラーページ
        if (!empty($page_types['404']) && is_404()) {
            return true;
        }

        return false;
    }

    /**
     * AJAX: 画像情報を取得
     */
    public function ajax_get_image_info(): void {
        check_ajax_referer('ksiv_frontend_nonce', 'nonce');

        $src = isset($_POST['src']) ? esc_url_raw($_POST['src']) : '';

        if (empty($src)) {
            wp_send_json_error(['message' => __('画像URLが指定されていません。', 'kashiwazaki-seo-image-viewer')]);
        }

        $file_info = $this->get_image_file_info($src);

        wp_send_json_success([
            'filename' => $file_info['filename'],
            'extension' => $file_info['extension'],
            'filesize' => $file_info['filesize'],
            'filesize_formatted' => $this->format_filesize($file_info['filesize']),
            'width' => $file_info['width'],
            'height' => $file_info['height'],
            'mime_type' => $file_info['mime_type'],
            'format_name' => $this->get_format_name($file_info['mime_type']),
        ]);
    }

    /**
     * 画像ファイル情報を取得
     */
    private function get_image_file_info(string $src): array {
        $info = [
            'filename' => '',
            'extension' => '',
            'filesize' => 0,
            'width' => 0,
            'height' => 0,
            'mime_type' => '',
        ];

        // URLからファイル名を取得
        $parsed_url = wp_parse_url($src);
        $path = $parsed_url['path'] ?? '';
        $info['filename'] = basename($path);
        $info['extension'] = strtolower(pathinfo($info['filename'], PATHINFO_EXTENSION));

        // アップロードディレクトリ情報
        $upload_dir = wp_upload_dir();

        // まずURLから直接ファイルパスを解決（WebP対応）
        if (strpos($src, $upload_dir['baseurl']) === 0) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $src);

            // クエリ文字列を除去
            $file_path = preg_replace('/\?.*$/', '', $file_path);

            if (file_exists($file_path)) {
                $info['filesize'] = filesize($file_path);
                $info['mime_type'] = $this->get_mime_type_from_extension($info['extension']);

                $image_size = @getimagesize($file_path);
                if ($image_size) {
                    $info['width'] = $image_size[0];
                    $info['height'] = $image_size[1];
                    if (!empty($image_size['mime'])) {
                        $info['mime_type'] = $image_size['mime'];
                    }
                }
                return $info;
            }
        }

        // 添付ファイルIDを取得
        $attachment_id = attachment_url_to_postid($src);

        // サイズ付きURLからオリジナルを探す
        if (!$attachment_id) {
            $original_src = preg_replace('/-\d+x\d+\./', '.', $src);
            if ($original_src !== $src) {
                $attachment_id = attachment_url_to_postid($original_src);
            }
        }

        // WebP/AVIF拡張子を除去してオリジナルを探す
        if (!$attachment_id && preg_match('/\.(webp|avif)$/i', $src)) {
            $original_src = preg_replace('/\.(webp|avif)$/i', '', $src);
            $attachment_id = attachment_url_to_postid($original_src);

            // サイズ付きも試す
            if (!$attachment_id) {
                $original_src = preg_replace('/-\d+x\d+\./', '.', $original_src);
                $attachment_id = attachment_url_to_postid($original_src);
            }
        }

        if ($attachment_id) {
            $file_path = get_attached_file($attachment_id);

            if ($file_path && file_exists($file_path)) {
                // 元ファイルのサイズではなく、実際に表示されているWebPファイルのサイズを取得
                $webp_path = $file_path . '.webp';
                if (file_exists($webp_path) && $info['extension'] === 'webp') {
                    $info['filesize'] = filesize($webp_path);
                    $info['mime_type'] = 'image/webp';
                } else {
                    $info['filesize'] = filesize($file_path);
                    $info['mime_type'] = get_post_mime_type($attachment_id);
                }

                $image_size = wp_getimagesize($file_path);
                if ($image_size) {
                    $info['width'] = $image_size[0];
                    $info['height'] = $image_size[1];
                }
            }
        } else {
            // 外部画像またはファイルが見つからない場合
            $info['mime_type'] = $this->get_mime_type_from_extension($info['extension']);
        }

        return $info;
    }

    /**
     * 拡張子からMIMEタイプを取得
     */
    private function get_mime_type_from_extension(string $extension): string {
        $mime_types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
        ];

        return $mime_types[$extension] ?? 'image/unknown';
    }

    /**
     * フォーマット名を取得
     */
    private function get_format_name(string $mime_type): string {
        $formats = [
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/gif' => 'GIF',
            'image/webp' => 'WebP',
            'image/avif' => 'AVIF',
            'image/svg+xml' => 'SVG',
        ];

        return $formats[$mime_type] ?? strtoupper(str_replace('image/', '', $mime_type));
    }

    /**
     * ファイルサイズをフォーマット
     */
    private function format_filesize(int $bytes): string {
        if ($bytes >= 1048576) {
            return sprintf('%.2f MB', $bytes / 1048576);
        } elseif ($bytes >= 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }
        return sprintf('%d B', $bytes);
    }

    /**
     * アセットを読み込み
     */
    public function enqueue_assets(): void {
        if (!$this->should_enable_lightbox()) {
            return;
        }

        wp_enqueue_style(
            'ksiv-lightbox',
            KSIV_PLUGIN_URL . 'assets/css/lightbox.css',
            [],
            KSIV_VERSION
        );

        wp_enqueue_script(
            'ksiv-lightbox',
            KSIV_PLUGIN_URL . 'assets/js/lightbox.js',
            [],
            KSIV_VERSION,
            true
        );

        wp_localize_script('ksiv-lightbox', 'ksivLightbox', [
            'settings' => [
                'maxZoomRatio' => $this->settings->get('max_zoom_ratio', 300) / 100,
                'animationSpeed' => $this->settings->get('animation_speed', 300),
                'infoPanelDefaultVisible' => $this->settings->get('info_panel_default_visible', false),
                'showZoomIcon' => $this->settings->get('show_zoom_icon', true),
                'zoomIconPosition' => $this->settings->get('zoom_icon_position', 'bottom-right'),
            ],
            'i18n' => [
                'close' => __('閉じる', 'kashiwazaki-seo-image-viewer'),
                'previous' => __('前の画像', 'kashiwazaki-seo-image-viewer'),
                'next' => __('次の画像', 'kashiwazaki-seo-image-viewer'),
                'toggleInfo' => __('画像情報', 'kashiwazaki-seo-image-viewer'),
                'format' => __('フォーマット', 'kashiwazaki-seo-image-viewer'),
                'filesize' => __('ファイルサイズ', 'kashiwazaki-seo-image-viewer'),
                'dimensions' => __('画像サイズ', 'kashiwazaki-seo-image-viewer'),
                'alt' => __('alt属性', 'kashiwazaki-seo-image-viewer'),
                'altNotSet' => __('未設定', 'kashiwazaki-seo-image-viewer'),
                'url' => __('画像URL', 'kashiwazaki-seo-image-viewer'),
                'copyUrl' => __('URLをコピー', 'kashiwazaki-seo-image-viewer'),
                'copied' => __('コピーしました', 'kashiwazaki-seo-image-viewer'),
                'loading' => __('読み込み中...', 'kashiwazaki-seo-image-viewer'),
                'zoomIn' => __('拡大', 'kashiwazaki-seo-image-viewer'),
                'zoomOut' => __('縮小', 'kashiwazaki-seo-image-viewer'),
            ],
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ksiv_frontend_nonce'),
        ]);
    }

    /**
     * コンテンツを処理
     */
    public function process_content(string $content): string {
        if (!$this->should_enable_lightbox()) {
            return $content;
        }

        // コンテンツが空または画像がない場合はスキップ
        if (empty($content) || stripos($content, '<img') === false) {
            return $content;
        }

        // DOMDocumentを使用して正確に処理
        $dom = new \DOMDocument();

        // エラーを抑制しつつHTMLをロード
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="ksiv-wrapper">' . $content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        // 全ての画像要素を取得
        $images = $dom->getElementsByTagName('img');
        $images_to_process = [];

        // NodeListは動的に変化するため、一度配列にコピー
        foreach ($images as $img) {
            $images_to_process[] = $img;
        }

        foreach ($images_to_process as $img) {
            // すでに処理済みの場合はスキップ
            if ($img->hasAttribute('data-ksiv-image') || $img->hasAttribute('data-ksiv-linked')) {
                continue;
            }

            // 祖先にaタグがあるかチェック
            $is_linked = $this->has_ancestor_link($img);

            if ($is_linked) {
                // リンクありの画像はマーク（Lightbox対象外）
                $img->setAttribute('data-ksiv-linked', '');
            } else {
                // リンクなしの画像はLightbox対象
                $img->setAttribute('data-ksiv-image', '');
            }
        }

        // 変更を取得
        $wrapper = $dom->getElementById('ksiv-wrapper');
        if ($wrapper) {
            $result = '';
            foreach ($wrapper->childNodes as $child) {
                $result .= $dom->saveHTML($child);
            }
            return $result;
        }

        return $content;
    }

    /**
     * 要素に祖先としてaタグがあるかチェック
     */
    private function has_ancestor_link(\DOMElement $element): bool {
        $parent = $element->parentNode;

        while ($parent !== null && $parent instanceof \DOMElement) {
            if (strtolower($parent->tagName) === 'a') {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    /**
     * Lightboxテンプレートを出力
     */
    public function render_lightbox_template(): void {
        if (!$this->should_enable_lightbox()) {
            return;
        }

        $icon_position = $this->settings->get('zoom_icon_position', 'bottom-right');
        ?>
        <div id="ksiv-lightbox" class="ksiv-lightbox" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('画像拡大表示', 'kashiwazaki-seo-image-viewer'); ?>">
            <div class="ksiv-lightbox-overlay"></div>

            <div class="ksiv-lightbox-container">
                <!-- ヘッダー -->
                <div class="ksiv-lightbox-header">
                    <div class="ksiv-lightbox-counter">
                        <span class="ksiv-lightbox-current">1</span>
                        <span class="ksiv-lightbox-separator">/</span>
                        <span class="ksiv-lightbox-total">1</span>
                    </div>

                    <div class="ksiv-lightbox-actions">
                        <button type="button" class="ksiv-lightbox-btn ksiv-lightbox-info-toggle" aria-label="<?php esc_attr_e('画像情報', 'kashiwazaki-seo-image-viewer'); ?>" title="<?php esc_attr_e('画像情報', 'kashiwazaki-seo-image-viewer'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                        </button>

                        <button type="button" class="ksiv-lightbox-btn ksiv-lightbox-close" aria-label="<?php esc_attr_e('閉じる', 'kashiwazaki-seo-image-viewer'); ?>" title="<?php esc_attr_e('閉じる', 'kashiwazaki-seo-image-viewer'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- メインコンテンツ -->
                <div class="ksiv-lightbox-content">
                    <!-- ナビゲーション（前へ） -->
                    <button type="button" class="ksiv-lightbox-nav ksiv-lightbox-prev" aria-label="<?php esc_attr_e('前の画像', 'kashiwazaki-seo-image-viewer'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>

                    <!-- 画像表示エリア -->
                    <div class="ksiv-lightbox-image-wrapper">
                        <div class="ksiv-lightbox-image-container">
                            <img src="" alt="" class="ksiv-lightbox-image" draggable="false">
                            <div class="ksiv-lightbox-loading">
                                <div class="ksiv-lightbox-spinner"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ナビゲーション（次へ） -->
                    <button type="button" class="ksiv-lightbox-nav ksiv-lightbox-next" aria-label="<?php esc_attr_e('次の画像', 'kashiwazaki-seo-image-viewer'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>

                <!-- 画像情報パネル -->
                <div class="ksiv-lightbox-info-panel" aria-hidden="true">
                    <div class="ksiv-lightbox-info-content">
                        <div class="ksiv-lightbox-info-row">
                            <span class="ksiv-lightbox-info-label"><?php esc_html_e('フォーマット', 'kashiwazaki-seo-image-viewer'); ?></span>
                            <span class="ksiv-lightbox-info-value ksiv-info-format">—</span>
                        </div>
                        <div class="ksiv-lightbox-info-row">
                            <span class="ksiv-lightbox-info-label"><?php esc_html_e('ファイルサイズ', 'kashiwazaki-seo-image-viewer'); ?></span>
                            <span class="ksiv-lightbox-info-value ksiv-info-filesize">—</span>
                        </div>
                        <div class="ksiv-lightbox-info-row">
                            <span class="ksiv-lightbox-info-label"><?php esc_html_e('画像サイズ', 'kashiwazaki-seo-image-viewer'); ?></span>
                            <span class="ksiv-lightbox-info-value ksiv-info-dimensions">—</span>
                        </div>
                        <div class="ksiv-lightbox-info-row">
                            <span class="ksiv-lightbox-info-label"><?php esc_html_e('alt属性', 'kashiwazaki-seo-image-viewer'); ?></span>
                            <span class="ksiv-lightbox-info-value ksiv-info-alt">—</span>
                        </div>
                        <div class="ksiv-lightbox-info-row ksiv-lightbox-info-url-row">
                            <span class="ksiv-lightbox-info-label"><?php esc_html_e('画像URL', 'kashiwazaki-seo-image-viewer'); ?></span>
                            <div class="ksiv-lightbox-info-url-container">
                                <span class="ksiv-lightbox-info-value ksiv-info-url">—</span>
                                <button type="button" class="ksiv-lightbox-copy-url" aria-label="<?php esc_attr_e('URLをコピー', 'kashiwazaki-seo-image-viewer'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                                <span class="ksiv-lightbox-copy-feedback"><?php esc_html_e('コピーしました', 'kashiwazaki-seo-image-viewer'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ズームコントロール -->
                <div class="ksiv-lightbox-zoom-controls">
                    <button type="button" class="ksiv-lightbox-btn ksiv-lightbox-zoom-out" aria-label="<?php esc_attr_e('縮小', 'kashiwazaki-seo-image-viewer'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            <line x1="8" y1="11" x2="14" y2="11"></line>
                        </svg>
                    </button>
                    <span class="ksiv-lightbox-zoom-level">100%</span>
                    <button type="button" class="ksiv-lightbox-btn ksiv-lightbox-zoom-in" aria-label="<?php esc_attr_e('拡大', 'kashiwazaki-seo-image-viewer'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            <line x1="11" y1="8" x2="11" y2="14"></line>
                            <line x1="8" y1="11" x2="14" y2="11"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <style>
            [data-ksiv-image] {
                cursor: zoom-in;
            }
            <?php if ($this->settings->get('show_zoom_icon', true)): ?>
            .ksiv-image-wrapper {
                position: relative;
                display: inline-block;
            }
            .ksiv-zoom-icon {
                position: absolute;
                <?php echo esc_html($this->get_icon_position_css($icon_position)); ?>
                width: 24px;
                height: 24px;
                background: rgba(0, 0, 0, 0.6);
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.2s ease;
            }
            .ksiv-image-wrapper:hover .ksiv-zoom-icon {
                opacity: 1;
            }
            .ksiv-zoom-icon svg {
                width: 16px;
                height: 16px;
                color: #fff;
            }
            <?php endif; ?>
        </style>
        <?php
    }

    /**
     * アイコン位置のCSSを取得
     */
    private function get_icon_position_css(string $position): string {
        switch ($position) {
            case 'top-left':
                return 'top: 8px; left: 8px;';
            case 'top-right':
                return 'top: 8px; right: 8px;';
            case 'bottom-left':
                return 'bottom: 8px; left: 8px;';
            case 'bottom-right':
            default:
                return 'bottom: 8px; right: 8px;';
        }
    }
}
