<?php
/**
 * SEO診断クラス
 *
 * @package Kashiwazaki_SEO_Image_Viewer
 */

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO診断クラス
 */
class Kashiwazaki_SEO_Image_Viewer_SEO_Checker {

    /**
     * 設定インスタンス
     */
    private Kashiwazaki_SEO_Image_Viewer_Settings $settings;

    /**
     * 次世代フォーマット
     */
    private const NEXT_GEN_FORMATS = ['webp', 'avif'];

    /**
     * コンストラクタ
     */
    public function __construct(Kashiwazaki_SEO_Image_Viewer_Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * 投稿内の画像を分析
     */
    public function analyze_post(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) {
            return [
                'images' => [],
                'issues' => [],
                'score' => 100,
            ];
        }

        $images = $this->extract_images($post->post_content);
        $issues = [];

        foreach ($images as $index => &$image) {
            $image['issues'] = $this->check_image($image);
            foreach ($image['issues'] as $issue) {
                $issues[] = [
                    'image_index' => $index,
                    'image_src' => $image['src'],
                    'type' => $issue['type'],
                    'message' => $issue['message'],
                ];
            }
        }

        $score = $this->calculate_score($images);

        return [
            'images' => $images,
            'issues' => $issues,
            'score' => $score,
        ];
    }

    /**
     * コンテンツから画像を抽出
     */
    public function extract_images(string $content): array {
        $images = [];

        // imgタグを抽出
        if (preg_match_all('/<img[^>]+>/i', $content, $matches)) {
            foreach ($matches[0] as $img_tag) {
                $image_data = $this->parse_img_tag($img_tag);
                if ($image_data) {
                    $images[] = $image_data;
                }
            }
        }

        return $images;
    }

    /**
     * imgタグをパース
     */
    private function parse_img_tag(string $img_tag): ?array {
        // src属性を取得
        if (!preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match)) {
            return null;
        }

        $src = $src_match[1];

        // alt属性を取得
        $alt = '';
        if (preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match)) {
            $alt = $alt_match[1];
        }

        // width属性を取得
        $display_width = 0;
        if (preg_match('/width=["\']?(\d+)/', $img_tag, $width_match)) {
            $display_width = intval($width_match[1]);
        }

        // height属性を取得
        $display_height = 0;
        if (preg_match('/height=["\']?(\d+)/', $img_tag, $height_match)) {
            $display_height = intval($height_match[1]);
        }

        // class属性を取得
        $class = '';
        if (preg_match('/class=["\']([^"\']*)["\']/', $img_tag, $class_match)) {
            $class = $class_match[1];
        }

        // srcset属性を取得
        $srcset = '';
        if (preg_match('/srcset=["\']([^"\']*)["\']/', $img_tag, $srcset_match)) {
            $srcset = $srcset_match[1];
        }

        // data-src-webp属性を取得（EWWW等のプラグイン用）
        $data_src_webp = '';
        if (preg_match('/data-src-webp=["\']([^"\']*)["\']/', $img_tag, $webp_match)) {
            $data_src_webp = $webp_match[1];
        }

        // data-srcset-webp属性を取得（EWWW等のプラグイン用）
        $data_srcset_webp = '';
        if (preg_match('/data-srcset-webp=["\']([^"\']*)["\']/', $img_tag, $srcset_webp_match)) {
            $data_srcset_webp = $srcset_webp_match[1];
        }

        // 画像ファイル情報を取得
        $file_info = $this->get_image_file_info($src);

        // フロントエンドで実際に配信される形式を判定
        $delivered_format = $this->detect_delivered_format($src, $srcset, $data_src_webp, $data_srcset_webp, $class);

        return [
            'src' => $src,
            'alt' => $alt,
            'display_width' => $display_width,
            'display_height' => $display_height,
            'class' => $class,
            'srcset' => $srcset,
            'data_src_webp' => $data_src_webp,
            'data_srcset_webp' => $data_srcset_webp,
            'original_tag' => $img_tag,
            'filename' => $file_info['filename'],
            'extension' => $file_info['extension'],
            'delivered_format' => $delivered_format,
            'filesize' => $file_info['filesize'],
            'actual_width' => $file_info['width'],
            'actual_height' => $file_info['height'],
            'mime_type' => $file_info['mime_type'],
            'attachment_id' => $file_info['attachment_id'],
            'thumbnail_url' => $file_info['thumbnail_url'],
        ];
    }

    /**
     * フロントエンドで実際に配信される形式を検出
     */
    private function detect_delivered_format(
        string $src,
        string $srcset,
        string $data_src_webp,
        string $data_srcset_webp,
        string $class
    ): string {
        // 1. srcが直接WebP/AVIFの場合（例: image.png.webp, image.webp）
        if (preg_match('/\.(webp|avif)(\?|$)/i', $src, $matches)) {
            return strtolower($matches[1]);
        }

        // 2. EWWWなどのプラグインがWebP変換を行っている場合
        if (!empty($data_src_webp) || !empty($data_srcset_webp)) {
            return 'webp';
        }

        // 3. srcsetにWebP/AVIFが含まれている場合
        if (!empty($srcset) && preg_match('/\.(webp|avif)\s/i', $srcset, $matches)) {
            return strtolower($matches[1]);
        }

        // 4. クラスにewww_webp関連が含まれている場合
        if (strpos($class, 'ewww_webp') !== false) {
            return 'webp';
        }

        // 5. サーバー上にWebP/AVIF版のファイルが存在するか確認
        // （EWWWなどのプラグインが変換済みの場合）
        $webp_format = $this->check_next_gen_file_exists($src);
        if ($webp_format) {
            return $webp_format;
        }

        // 6. 元の拡張子を返す
        $extension = strtolower(pathinfo(wp_parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        // .png.webp などの二重拡張子に対応
        if (preg_match('/\.(webp|avif)$/i', $src)) {
            return preg_match('/\.(webp)$/i', $src) ? 'webp' : 'avif';
        }

        return $extension;
    }

    /**
     * サーバー上に次世代フォーマット版のファイルが存在するかチェック
     */
    private function check_next_gen_file_exists(string $src): ?string {
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];

        // アップロードディレクトリ内の画像かチェック
        if (strpos($src, $base_url) !== 0) {
            return null;
        }

        // ファイルパスに変換
        $file_path = str_replace($base_url, $base_dir, $src);

        // WebP版のファイルが存在するかチェック（.png.webp, .jpg.webp など）
        $webp_path = $file_path . '.webp';
        if (file_exists($webp_path)) {
            return 'webp';
        }

        // AVIF版のファイルが存在するかチェック
        $avif_path = $file_path . '.avif';
        if (file_exists($avif_path)) {
            return 'avif';
        }

        // 拡張子を置換したWebP版が存在するかチェック（image.webp）
        $pathinfo = pathinfo($file_path);
        if (isset($pathinfo['dirname']) && isset($pathinfo['filename'])) {
            $webp_alt_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.webp';
            if (file_exists($webp_alt_path)) {
                return 'webp';
            }

            $avif_alt_path = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.avif';
            if (file_exists($avif_alt_path)) {
                return 'avif';
            }
        }

        return null;
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
            'attachment_id' => 0,
            'thumbnail_url' => '',
        ];

        // URLからファイル名を取得
        $parsed_url = wp_parse_url($src);
        $path = $parsed_url['path'] ?? '';
        $info['filename'] = basename($path);
        $info['extension'] = strtolower(pathinfo($info['filename'], PATHINFO_EXTENSION));

        // 添付ファイルIDを取得
        $attachment_id = attachment_url_to_postid($src);

        // サイズ付きURLからオリジナルを探す
        if (!$attachment_id) {
            $original_src = preg_replace('/-\d+x\d+\./', '.', $src);
            if ($original_src !== $src) {
                $attachment_id = attachment_url_to_postid($original_src);
            }
        }

        if ($attachment_id) {
            $info['attachment_id'] = $attachment_id;
            $file_path = get_attached_file($attachment_id);

            if ($file_path && file_exists($file_path)) {
                $info['filesize'] = filesize($file_path);
                $info['mime_type'] = get_post_mime_type($attachment_id);

                $image_size = wp_getimagesize($file_path);
                if ($image_size) {
                    $info['width'] = $image_size[0];
                    $info['height'] = $image_size[1];
                }
            }

            // サムネイルURLを取得
            $thumbnail = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            if ($thumbnail) {
                $info['thumbnail_url'] = $thumbnail[0];
            }
        } else {
            // 外部画像の場合
            $info['mime_type'] = $this->get_mime_type_from_extension($info['extension']);

            // ローカルファイルの場合はファイル情報を取得
            $upload_dir = wp_upload_dir();
            if (strpos($src, $upload_dir['baseurl']) === 0) {
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $src);
                if (file_exists($file_path)) {
                    $info['filesize'] = filesize($file_path);
                    $image_size = wp_getimagesize($file_path);
                    if ($image_size) {
                        $info['width'] = $image_size[0];
                        $info['height'] = $image_size[1];
                    }
                }
            }
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
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
        ];

        return $mime_types[$extension] ?? 'image/unknown';
    }

    /**
     * 画像をチェック
     */
    public function check_image(array $image): array {
        $issues = [];

        // alt属性チェック
        if ($this->settings->get('alt_warning_enabled', true)) {
            $alt_issue = $this->check_alt($image);
            if ($alt_issue) {
                $issues[] = $alt_issue;
            }
        }

        // ファイル名チェック
        $filename_issue = $this->check_filename($image);
        if ($filename_issue) {
            $issues[] = $filename_issue;
        }

        // サイズチェック
        $size_issue = $this->check_size($image);
        if ($size_issue) {
            $issues[] = $size_issue;
        }

        // 容量チェック
        $filesize_issue = $this->check_filesize($image);
        if ($filesize_issue) {
            $issues[] = $filesize_issue;
        }

        // 次世代フォーマットチェック
        if ($this->settings->get('next_gen_warning_enabled', true)) {
            $format_issue = $this->check_next_gen_format($image);
            if ($format_issue) {
                $issues[] = $format_issue;
            }
        }

        return $issues;
    }

    /**
     * alt属性をチェック
     */
    private function check_alt(array $image): ?array {
        if (empty($image['alt']) || trim($image['alt']) === '') {
            return [
                'type' => 'alt_missing',
                'message' => __('alt属性が未設定です', 'kashiwazaki-seo-image-viewer'),
                'severity' => 'error',
            ];
        }
        return null;
    }

    /**
     * ファイル名をチェック
     */
    private function check_filename(array $image): ?array {
        $filename = pathinfo($image['filename'], PATHINFO_FILENAME);
        $patterns = $this->settings->get('filename_warning_patterns', []);

        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $filename)) {
                return [
                    'type' => 'filename_inappropriate',
                    'message' => __('ファイル名が意味のない文字列です', 'kashiwazaki-seo-image-viewer'),
                    'severity' => 'warning',
                ];
            }
        }

        return null;
    }

    /**
     * 画像サイズをチェック
     */
    private function check_size(array $image): ?array {
        if ($image['actual_width'] <= 0 || $image['display_width'] <= 0) {
            return null;
        }

        $threshold = $this->settings->get('size_warning_threshold', 2.0);
        $ratio = $image['actual_width'] / $image['display_width'];

        if ($ratio >= $threshold) {
            return [
                'type' => 'size_oversized',
                'message' => sprintf(
                    __('元画像が表示サイズの%.1f倍（%d×%d → %d×%d）', 'kashiwazaki-seo-image-viewer'),
                    $ratio,
                    $image['actual_width'],
                    $image['actual_height'],
                    $image['display_width'],
                    $image['display_height']
                ),
                'severity' => 'warning',
            ];
        }

        return null;
    }

    /**
     * ファイル容量をチェック
     */
    private function check_filesize(array $image): ?array {
        if ($image['filesize'] <= 0) {
            return null;
        }

        $threshold = $this->settings->get('filesize_warning_threshold', 500) * 1024;

        if ($image['filesize'] >= $threshold) {
            return [
                'type' => 'filesize_oversized',
                'message' => sprintf(
                    __('ファイルサイズが大きすぎます（%s）', 'kashiwazaki-seo-image-viewer'),
                    $this->format_filesize($image['filesize'])
                ),
                'severity' => 'warning',
            ];
        }

        return null;
    }

    /**
     * 次世代フォーマットをチェック
     *
     * フロントエンドで実際に配信される形式を確認します。
     * EWWWなどのプラグインでWebP変換されている場合は警告しません。
     */
    private function check_next_gen_format(array $image): ?array {
        // delivered_formatがある場合はそれを使用（実際に配信される形式）
        $format = $image['delivered_format'] ?? $image['extension'];
        $format = strtolower($format);

        if (!in_array($format, self::NEXT_GEN_FORMATS, true)) {
            // 元のファイル形式を表示用に取得
            $original_extension = strtoupper($image['extension']);

            return [
                'type' => 'format_not_next_gen',
                'message' => sprintf(
                    __('次世代フォーマット未対応（%s）', 'kashiwazaki-seo-image-viewer'),
                    $original_extension
                ),
                'severity' => 'info',
            ];
        }

        return null;
    }

    /**
     * SEOスコアを計算
     */
    public function calculate_score(array $images): int {
        if (empty($images)) {
            return 100;
        }

        $score = 100;

        foreach ($images as $image) {
            foreach ($image['issues'] ?? [] as $issue) {
                switch ($issue['type']) {
                    case 'alt_missing':
                        $score -= $this->settings->get('score_weight_alt', 20);
                        break;
                    case 'filename_inappropriate':
                        $score -= $this->settings->get('score_weight_filename', 10);
                        break;
                    case 'size_oversized':
                        $score -= $this->settings->get('score_weight_size', 10);
                        break;
                    case 'filesize_oversized':
                        $score -= $this->settings->get('score_weight_filesize', 15);
                        break;
                    case 'format_not_next_gen':
                        $score -= $this->settings->get('score_weight_next_gen', 5);
                        break;
                }
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * ファイルサイズをフォーマット
     */
    public function format_filesize(int $bytes): string {
        if ($bytes >= 1048576) {
            return sprintf('%.2f MB', $bytes / 1048576);
        } elseif ($bytes >= 1024) {
            return sprintf('%.1f KB', $bytes / 1024);
        }
        return sprintf('%d B', $bytes);
    }

    /**
     * スコアの評価を取得
     */
    public function get_score_grade(int $score): array {
        if ($score >= 90) {
            return [
                'grade' => 'A',
                'label' => __('優秀', 'kashiwazaki-seo-image-viewer'),
                'color' => '#00c853',
            ];
        } elseif ($score >= 70) {
            return [
                'grade' => 'B',
                'label' => __('良好', 'kashiwazaki-seo-image-viewer'),
                'color' => '#64dd17',
            ];
        } elseif ($score >= 50) {
            return [
                'grade' => 'C',
                'label' => __('要改善', 'kashiwazaki-seo-image-viewer'),
                'color' => '#ffc107',
            ];
        } elseif ($score >= 30) {
            return [
                'grade' => 'D',
                'label' => __('問題あり', 'kashiwazaki-seo-image-viewer'),
                'color' => '#ff9800',
            ];
        }
        return [
            'grade' => 'E',
            'label' => __('要対応', 'kashiwazaki-seo-image-viewer'),
            'color' => '#f44336',
        ];
    }

    /**
     * フォーマット名を取得
     */
    public function get_format_name(string $mime_type): string {
        $formats = [
            'image/jpeg' => 'JPEG',
            'image/png' => 'PNG',
            'image/gif' => 'GIF',
            'image/webp' => 'WebP',
            'image/avif' => 'AVIF',
            'image/svg+xml' => 'SVG',
            'image/bmp' => 'BMP',
            'image/x-icon' => 'ICO',
            'image/tiff' => 'TIFF',
        ];

        return $formats[$mime_type] ?? strtoupper(str_replace('image/', '', $mime_type));
    }
}
