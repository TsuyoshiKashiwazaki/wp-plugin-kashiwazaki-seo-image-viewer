<?php
/**
 * プラグインアンインストール時の処理
 *
 * @package Kashiwazaki_SEO_Image_Viewer
 */

// アンインストール以外からのアクセスを禁止
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// オプションを削除
delete_option('ksiv_settings');

// 投稿メタを削除
global $wpdb;
$wpdb->delete(
    $wpdb->postmeta,
    ['meta_key' => '_ksiv_seo_score'],
    ['%s']
);
$wpdb->delete(
    $wpdb->postmeta,
    ['meta_key' => '_ksiv_image_count'],
    ['%s']
);

// キャッシュをクリア
wp_cache_flush();
