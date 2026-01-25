<?php
/**
 * 管理画面クラス
 *
 * @package Kashiwazaki_SEO_Image_Viewer
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理画面クラス
 */
class Kashiwazaki_SEO_Image_Viewer_Admin {

    /**
     * 設定インスタンス
     */
    private Kashiwazaki_SEO_Image_Viewer_Settings $settings;

    /**
     * SEOチェッカーインスタンス
     */
    private Kashiwazaki_SEO_Image_Viewer_SEO_Checker $seo_checker;

    /**
     * コンストラクタ
     */
    public function __construct(
        Kashiwazaki_SEO_Image_Viewer_Settings $settings,
        Kashiwazaki_SEO_Image_Viewer_SEO_Checker $seo_checker
    ) {
        $this->settings = $settings;
        $this->seo_checker = $seo_checker;

        $this->register_hooks();
    }

    /**
     * フックを登録
     */
    private function register_hooks(): void {
        // 管理メニュー追加
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // 設定保存処理
        add_action('admin_init', [$this, 'handle_settings_save']);

        // 管理画面スタイル・スクリプト
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // メタボックス追加
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);

        // 投稿一覧にカラム追加
        add_filter('manage_posts_columns', [$this, 'add_posts_columns']);
        add_action('manage_posts_custom_column', [$this, 'render_posts_column'], 10, 2);
        add_filter('manage_edit-post_sortable_columns', [$this, 'make_column_sortable']);

        // ページ一覧にもカラム追加
        add_filter('manage_pages_columns', [$this, 'add_posts_columns']);
        add_action('manage_pages_custom_column', [$this, 'render_posts_column'], 10, 2);
        add_filter('manage_edit-page_sortable_columns', [$this, 'make_column_sortable']);

        // ソート処理
        add_action('pre_get_posts', [$this, 'handle_column_sort']);

        // 投稿保存時にスコアをキャッシュ
        add_action('save_post', [$this, 'update_seo_score_cache'], 10, 2);

        // AJAXハンドラー
        add_action('wp_ajax_ksiv_get_image_info', [$this, 'ajax_get_image_info']);
    }

    /**
     * 管理メニューを追加
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('Kashiwazaki SEO Image Viewer', 'kashiwazaki-seo-image-viewer'),
            __('Kashiwazaki SEO Image Viewer', 'kashiwazaki-seo-image-viewer'),
            'manage_options',
            'kashiwazaki-seo-image-viewer',
            [$this, 'render_settings_page'],
            'dashicons-format-gallery',
            81
        );
    }

    /**
     * 管理画面アセットを読み込み
     */
    public function enqueue_admin_assets(string $hook): void {
        // 設定ページ
        if ($hook === 'toplevel_page_kashiwazaki-seo-image-viewer') {
            wp_enqueue_style(
                'ksiv-admin',
                KSIV_PLUGIN_URL . 'assets/css/admin.css',
                [],
                KSIV_VERSION
            );

            wp_enqueue_script(
                'ksiv-admin',
                KSIV_PLUGIN_URL . 'assets/js/admin.js',
                [],
                KSIV_VERSION,
                true
            );
        }

        // 投稿編集画面
        global $post;
        if (($hook === 'post.php' || $hook === 'post-new.php') && $post) {
            wp_enqueue_style(
                'ksiv-admin',
                KSIV_PLUGIN_URL . 'assets/css/admin.css',
                [],
                KSIV_VERSION
            );

            wp_enqueue_script(
                'ksiv-admin',
                KSIV_PLUGIN_URL . 'assets/js/admin.js',
                [],
                KSIV_VERSION,
                true
            );

            wp_localize_script('ksiv-admin', 'ksivAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ksiv_admin_nonce'),
            ]);
        }
    }

    /**
     * 設定保存処理
     */
    public function handle_settings_save(): void {
        if (!isset($_POST['ksiv_settings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['ksiv_settings_nonce'], 'ksiv_save_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $this->settings->save($_POST['ksiv_settings'] ?? []);

        add_settings_error(
            'ksiv_settings',
            'settings_updated',
            __('設定を保存しました。', 'kashiwazaki-seo-image-viewer'),
            'updated'
        );
    }

    /**
     * 設定ページを表示
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->get_all();
        ?>
        <div class="wrap ksiv-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('ksiv_settings'); ?>

            <form method="post" action="">
                <?php wp_nonce_field('ksiv_save_settings', 'ksiv_settings_nonce'); ?>

                <div class="ksiv-settings-container">
                    <!-- Lightbox設定 -->
                    <div class="ksiv-settings-section">
                        <h2><?php esc_html_e('Lightbox設定', 'kashiwazaki-seo-image-viewer'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Lightboxの有効化', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ksiv_settings[lightbox_enabled]" value="1" <?php checked($settings['lightbox_enabled']); ?>>
                                        <?php esc_html_e('記事内画像のLightbox表示を有効にする', 'kashiwazaki-seo-image-viewer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('拡大アイコンの表示', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ksiv_settings[show_zoom_icon]" value="1" <?php checked($settings['show_zoom_icon']); ?>>
                                        <?php esc_html_e('拡大可能な画像に虫眼鏡アイコンを表示する', 'kashiwazaki-seo-image-viewer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('アイコンの位置', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <select name="ksiv_settings[zoom_icon_position]">
                                        <option value="bottom-right" <?php selected($settings['zoom_icon_position'], 'bottom-right'); ?>><?php esc_html_e('右下', 'kashiwazaki-seo-image-viewer'); ?></option>
                                        <option value="bottom-left" <?php selected($settings['zoom_icon_position'], 'bottom-left'); ?>><?php esc_html_e('左下', 'kashiwazaki-seo-image-viewer'); ?></option>
                                        <option value="top-right" <?php selected($settings['zoom_icon_position'], 'top-right'); ?>><?php esc_html_e('右上', 'kashiwazaki-seo-image-viewer'); ?></option>
                                        <option value="top-left" <?php selected($settings['zoom_icon_position'], 'top-left'); ?>><?php esc_html_e('左上', 'kashiwazaki-seo-image-viewer'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('最大ズーム倍率', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[max_zoom_ratio]" value="<?php echo esc_attr($settings['max_zoom_ratio']); ?>" min="100" max="1000" step="50" class="small-text"> %
                                    <p class="description"><?php esc_html_e('100%〜1000%の範囲で設定できます（デフォルト：300%）', 'kashiwazaki-seo-image-viewer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('アニメーション速度', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[animation_speed]" value="<?php echo esc_attr($settings['animation_speed']); ?>" min="0" max="2000" step="50" class="small-text"> ms
                                    <p class="description"><?php esc_html_e('0〜2000msの範囲で設定できます（デフォルト：300ms）', 'kashiwazaki-seo-image-viewer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('画像情報パネル', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ksiv_settings[info_panel_default_visible]" value="1" <?php checked($settings['info_panel_default_visible']); ?>>
                                        <?php esc_html_e('Lightbox表示時にデフォルトで画像情報パネルを表示する', 'kashiwazaki-seo-image-viewer'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- SEO診断設定 -->
                    <div class="ksiv-settings-section">
                        <h2><?php esc_html_e('SEO診断設定', 'kashiwazaki-seo-image-viewer'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('alt属性警告', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ksiv_settings[alt_warning_enabled]" value="1" <?php checked($settings['alt_warning_enabled']); ?>>
                                        <?php esc_html_e('alt属性が未設定の画像を警告する', 'kashiwazaki-seo-image-viewer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('ファイル名警告パターン', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <textarea name="ksiv_settings[filename_warning_patterns]" rows="8" class="large-text code"><?php echo esc_textarea(implode("\n", $settings['filename_warning_patterns'])); ?></textarea>
                                    <p class="description"><?php esc_html_e('意味のないファイル名を検出する正規表現パターンを1行1つずつ入力してください。', 'kashiwazaki-seo-image-viewer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('サイズ警告閾値', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[size_warning_threshold]" value="<?php echo esc_attr($settings['size_warning_threshold']); ?>" min="1.0" max="10.0" step="0.1" class="small-text"> 倍
                                    <p class="description"><?php esc_html_e('表示サイズに対して元画像がこの倍率以上の場合に警告します（デフォルト：2.0倍）', 'kashiwazaki-seo-image-viewer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('容量警告閾値', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[filesize_warning_threshold]" value="<?php echo esc_attr($settings['filesize_warning_threshold']); ?>" min="50" max="10000" step="50" class="small-text"> KB
                                    <p class="description"><?php esc_html_e('画像ファイルがこの容量以上の場合に警告します（デフォルト：500KB）', 'kashiwazaki-seo-image-viewer'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('次世代フォーマット警告', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ksiv_settings[next_gen_warning_enabled]" value="1" <?php checked($settings['next_gen_warning_enabled']); ?>>
                                        <?php esc_html_e('WebP/AVIF以外の形式を使用している場合に警告する', 'kashiwazaki-seo-image-viewer'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- スコア重み付け設定 -->
                    <div class="ksiv-settings-section">
                        <h2><?php esc_html_e('スコア重み付け設定', 'kashiwazaki-seo-image-viewer'); ?></h2>
                        <p class="description"><?php esc_html_e('各問題が検出された場合に減点される点数を設定します。基準点は100点です。', 'kashiwazaki-seo-image-viewer'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('alt未設定', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[score_weight_alt]" value="<?php echo esc_attr($settings['score_weight_alt']); ?>" min="0" max="100" class="small-text"> <?php esc_html_e('点/枚', 'kashiwazaki-seo-image-viewer'); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('ファイル名不適切', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[score_weight_filename]" value="<?php echo esc_attr($settings['score_weight_filename']); ?>" min="0" max="100" class="small-text"> <?php esc_html_e('点/枚', 'kashiwazaki-seo-image-viewer'); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('サイズ過大', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[score_weight_size]" value="<?php echo esc_attr($settings['score_weight_size']); ?>" min="0" max="100" class="small-text"> <?php esc_html_e('点/枚', 'kashiwazaki-seo-image-viewer'); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('容量過大', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[score_weight_filesize]" value="<?php echo esc_attr($settings['score_weight_filesize']); ?>" min="0" max="100" class="small-text"> <?php esc_html_e('点/枚', 'kashiwazaki-seo-image-viewer'); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('次世代フォーマット未対応', 'kashiwazaki-seo-image-viewer'); ?></th>
                                <td>
                                    <input type="number" name="ksiv_settings[score_weight_next_gen]" value="<?php echo esc_attr($settings['score_weight_next_gen']); ?>" min="0" max="100" class="small-text"> <?php esc_html_e('点/枚', 'kashiwazaki-seo-image-viewer'); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php submit_button(__('設定を保存', 'kashiwazaki-seo-image-viewer')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * メタボックスを追加
     */
    public function add_meta_boxes(): void {
        $post_types = get_post_types(['public' => true], 'names');

        foreach ($post_types as $post_type) {
            if ($post_type === 'attachment') {
                continue;
            }

            add_meta_box(
                'ksiv_seo_checker',
                __('Kashiwazaki SEO Image Viewer - 画像SEO診断', 'kashiwazaki-seo-image-viewer'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * メタボックスを表示
     */
    public function render_meta_box(\WP_Post $post): void {
        $analysis = $this->seo_checker->analyze_post($post->ID);
        $score = $analysis['score'];
        $grade = $this->seo_checker->get_score_grade($score);
        $images = $analysis['images'];
        ?>
        <div class="ksiv-meta-box">
            <?php if (empty($images)): ?>
                <p class="ksiv-no-images"><?php esc_html_e('この投稿には画像がありません。', 'kashiwazaki-seo-image-viewer'); ?></p>
            <?php else: ?>
                <div class="ksiv-score-container">
                    <div class="ksiv-score-circle" style="--score-color: <?php echo esc_attr($grade['color']); ?>">
                        <span class="ksiv-score-value"><?php echo esc_html($score); ?></span>
                        <span class="ksiv-score-label"><?php esc_html_e('点', 'kashiwazaki-seo-image-viewer'); ?></span>
                    </div>
                    <div class="ksiv-score-info">
                        <div class="ksiv-score-grade" style="color: <?php echo esc_attr($grade['color']); ?>">
                            <?php echo esc_html($grade['grade']); ?>：<?php echo esc_html($grade['label']); ?>
                        </div>
                        <div class="ksiv-image-count">
                            <?php printf(
                                esc_html__('画像数：%d枚', 'kashiwazaki-seo-image-viewer'),
                                count($images)
                            ); ?>
                        </div>
                    </div>
                </div>

                <?php
                $issues_by_type = [
                    'alt_missing' => [],
                    'filename_inappropriate' => [],
                    'size_oversized' => [],
                    'filesize_oversized' => [],
                    'format_not_next_gen' => [],
                ];

                foreach ($images as $index => $image) {
                    foreach ($image['issues'] ?? [] as $issue) {
                        $issues_by_type[$issue['type']][] = [
                            'image' => $image,
                            'issue' => $issue,
                            'index' => $index,
                        ];
                    }
                }
                ?>

                <?php if (!empty($issues_by_type['alt_missing'])): ?>
                    <div class="ksiv-issue-section ksiv-issue-error">
                        <h4><?php esc_html_e('alt属性未設定', 'kashiwazaki-seo-image-viewer'); ?> (<?php echo count($issues_by_type['alt_missing']); ?>)</h4>
                        <div class="ksiv-issue-images">
                            <?php foreach ($issues_by_type['alt_missing'] as $item): ?>
                                <div class="ksiv-issue-image">
                                    <?php if (!empty($item['image']['thumbnail_url'])): ?>
                                        <img src="<?php echo esc_url($item['image']['thumbnail_url']); ?>" alt="">
                                    <?php else: ?>
                                        <div class="ksiv-no-thumbnail"><?php esc_html_e('サムネイルなし', 'kashiwazaki-seo-image-viewer'); ?></div>
                                    <?php endif; ?>
                                    <span class="ksiv-filename"><?php echo esc_html($item['image']['filename']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($issues_by_type['filename_inappropriate'])): ?>
                    <div class="ksiv-issue-section ksiv-issue-warning">
                        <h4><?php esc_html_e('ファイル名が不適切', 'kashiwazaki-seo-image-viewer'); ?> (<?php echo count($issues_by_type['filename_inappropriate']); ?>)</h4>
                        <div class="ksiv-issue-images">
                            <?php foreach ($issues_by_type['filename_inappropriate'] as $item): ?>
                                <div class="ksiv-issue-image">
                                    <?php if (!empty($item['image']['thumbnail_url'])): ?>
                                        <img src="<?php echo esc_url($item['image']['thumbnail_url']); ?>" alt="">
                                    <?php else: ?>
                                        <div class="ksiv-no-thumbnail"><?php esc_html_e('サムネイルなし', 'kashiwazaki-seo-image-viewer'); ?></div>
                                    <?php endif; ?>
                                    <span class="ksiv-filename"><?php echo esc_html($item['image']['filename']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($issues_by_type['size_oversized'])): ?>
                    <div class="ksiv-issue-section ksiv-issue-warning">
                        <h4><?php esc_html_e('画像サイズが過大', 'kashiwazaki-seo-image-viewer'); ?> (<?php echo count($issues_by_type['size_oversized']); ?>)</h4>
                        <div class="ksiv-issue-images">
                            <?php foreach ($issues_by_type['size_oversized'] as $item): ?>
                                <div class="ksiv-issue-image">
                                    <?php if (!empty($item['image']['thumbnail_url'])): ?>
                                        <img src="<?php echo esc_url($item['image']['thumbnail_url']); ?>" alt="">
                                    <?php else: ?>
                                        <div class="ksiv-no-thumbnail"><?php esc_html_e('サムネイルなし', 'kashiwazaki-seo-image-viewer'); ?></div>
                                    <?php endif; ?>
                                    <span class="ksiv-filename"><?php echo esc_html($item['image']['filename']); ?></span>
                                    <span class="ksiv-issue-detail"><?php echo esc_html($item['issue']['message']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($issues_by_type['filesize_oversized'])): ?>
                    <div class="ksiv-issue-section ksiv-issue-warning">
                        <h4><?php esc_html_e('ファイル容量が過大', 'kashiwazaki-seo-image-viewer'); ?> (<?php echo count($issues_by_type['filesize_oversized']); ?>)</h4>
                        <div class="ksiv-issue-images">
                            <?php foreach ($issues_by_type['filesize_oversized'] as $item): ?>
                                <div class="ksiv-issue-image">
                                    <?php if (!empty($item['image']['thumbnail_url'])): ?>
                                        <img src="<?php echo esc_url($item['image']['thumbnail_url']); ?>" alt="">
                                    <?php else: ?>
                                        <div class="ksiv-no-thumbnail"><?php esc_html_e('サムネイルなし', 'kashiwazaki-seo-image-viewer'); ?></div>
                                    <?php endif; ?>
                                    <span class="ksiv-filename"><?php echo esc_html($item['image']['filename']); ?></span>
                                    <span class="ksiv-issue-detail"><?php echo esc_html($item['issue']['message']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($issues_by_type['format_not_next_gen'])): ?>
                    <div class="ksiv-issue-section ksiv-issue-info">
                        <h4><?php esc_html_e('次世代フォーマット未対応', 'kashiwazaki-seo-image-viewer'); ?> (<?php echo count($issues_by_type['format_not_next_gen']); ?>)</h4>
                        <div class="ksiv-issue-images">
                            <?php foreach ($issues_by_type['format_not_next_gen'] as $item): ?>
                                <div class="ksiv-issue-image">
                                    <?php if (!empty($item['image']['thumbnail_url'])): ?>
                                        <img src="<?php echo esc_url($item['image']['thumbnail_url']); ?>" alt="">
                                    <?php else: ?>
                                        <div class="ksiv-no-thumbnail"><?php esc_html_e('サムネイルなし', 'kashiwazaki-seo-image-viewer'); ?></div>
                                    <?php endif; ?>
                                    <span class="ksiv-filename"><?php echo esc_html($item['image']['filename']); ?></span>
                                    <span class="ksiv-issue-detail"><?php echo esc_html($item['issue']['message']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($score === 100): ?>
                    <p class="ksiv-all-good"><?php esc_html_e('すべての画像がSEO基準を満たしています。', 'kashiwazaki-seo-image-viewer'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * 投稿一覧にカラムを追加
     */
    public function add_posts_columns(array $columns): array {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['ksiv_score'] = __('画像SEO', 'kashiwazaki-seo-image-viewer');
            }
        }

        return $new_columns;
    }

    /**
     * 投稿一覧のカラムを表示
     */
    public function render_posts_column(string $column, int $post_id): void {
        if ($column !== 'ksiv_score') {
            return;
        }

        $score = get_post_meta($post_id, '_ksiv_seo_score', true);

        if ($score === '') {
            $analysis = $this->seo_checker->analyze_post($post_id);
            $score = $analysis['score'];
            update_post_meta($post_id, '_ksiv_seo_score', $score);
            update_post_meta($post_id, '_ksiv_image_count', count($analysis['images']));
        }

        $grade = $this->seo_checker->get_score_grade(intval($score));
        $image_count = get_post_meta($post_id, '_ksiv_image_count', true);

        if ($image_count === '' || intval($image_count) === 0) {
            echo '<span class="ksiv-list-score ksiv-no-images">—</span>';
        } else {
            printf(
                '<span class="ksiv-list-score" style="background-color: %s">%s</span>',
                esc_attr($grade['color']),
                esc_html($score)
            );
        }
    }

    /**
     * カラムをソート可能にする
     */
    public function make_column_sortable(array $columns): array {
        $columns['ksiv_score'] = 'ksiv_score';
        return $columns;
    }

    /**
     * カラムのソート処理
     */
    public function handle_column_sort(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('orderby') !== 'ksiv_score') {
            return;
        }

        $query->set('meta_key', '_ksiv_seo_score');
        $query->set('orderby', 'meta_value_num');
    }

    /**
     * 投稿保存時にスコアをキャッシュ
     */
    public function update_seo_score_cache(int $post_id, \WP_Post $post): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!in_array($post->post_type, get_post_types(['public' => true]), true)) {
            return;
        }

        if ($post->post_type === 'attachment') {
            return;
        }

        $analysis = $this->seo_checker->analyze_post($post_id);
        update_post_meta($post_id, '_ksiv_seo_score', $analysis['score']);
        update_post_meta($post_id, '_ksiv_image_count', count($analysis['images']));
    }

    /**
     * AJAX: 画像情報を取得
     */
    public function ajax_get_image_info(): void {
        check_ajax_referer('ksiv_admin_nonce', 'nonce');

        $src = isset($_POST['src']) ? esc_url_raw($_POST['src']) : '';

        if (empty($src)) {
            wp_send_json_error(['message' => __('画像URLが指定されていません。', 'kashiwazaki-seo-image-viewer')]);
        }

        // 画像情報を取得
        $images = $this->seo_checker->extract_images('<img src="' . esc_attr($src) . '" alt="">');

        if (empty($images)) {
            wp_send_json_error(['message' => __('画像情報を取得できませんでした。', 'kashiwazaki-seo-image-viewer')]);
        }

        $image = $images[0];

        wp_send_json_success([
            'filename' => $image['filename'],
            'extension' => $image['extension'],
            'filesize' => $image['filesize'],
            'filesize_formatted' => $this->seo_checker->format_filesize($image['filesize']),
            'width' => $image['actual_width'],
            'height' => $image['actual_height'],
            'mime_type' => $image['mime_type'],
            'format_name' => $this->seo_checker->get_format_name($image['mime_type']),
        ]);
    }
}
