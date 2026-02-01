=== Kashiwazaki SEO Image Viewer ===
Contributors: tsuyoshikashiwazaki
Tags: lightbox, image viewer, seo, image optimization, webp
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

記事内画像の拡大表示（Lightbox）機能とSEO診断機能を兼ね備えた軽量WordPressプラグイン。

== Description ==

Kashiwazaki SEO Image Viewerは、画像管理に必須の2つの機能を組み合わせたプラグインです：

1. **Lightbox画像ビューア** - リンクのない画像をクリックすると、ズームやナビゲーション機能を備えたエレガントなフルスクリーンLightboxで表示されます。

2. **画像SEO診断** - 投稿内の画像をSEOベストプラクティスに基づいて自動分析し、スコアを表示します。

= Lightbox機能 =

* リンクのない画像をクリックで拡大表示
* ズーム操作（マウスホイール、ピンチジェスチャー、ボタン）
* ズーム時のドラッグ/スワイプ移動
* キーボード対応の前後ナビゲーション
* 閉じる操作（オーバーレイクリック、×ボタン、Escapeキー）
* 画像情報パネル（フォーマット、ファイルサイズ、サイズ、alt属性）
* URLコピー機能
* カスタマイズ可能なズームアイコン位置

= SEO診断機能 =

* alt属性の検証
* ファイル名パターン検出（IMG_*、DSC_*などの汎用的な名前を警告）
* 画像サイズ分析（過大な場合に警告）
* ファイル容量チェック（閾値設定可能）
* 次世代フォーマット（WebP/AVIF）検出
* EWWW Image Optimizer互換

= 管理機能 =

* 包括的な設定ページ
* 詳細な診断結果を表示する投稿編集画面メタボックス
* ソート可能な投稿/固定ページ一覧のSEOスコアカラム

== Installation ==

1. プラグインフォルダを`/wp-content/plugins/`ディレクトリにアップロード
2. WordPressの「プラグイン」メニューからプラグインを有効化
3. 管理メニューの「Kashiwazaki SEO Image Viewer」から設定

== Frequently Asked Questions ==

= 遅延読み込みに対応していますか？ =

はい、WordPress標準の遅延読み込みおよび多くの遅延読み込みプラグインと互換性があります。

= 他のプラグインで変換されたWebP画像を検出できますか？ =

はい、EWWW Image Optimizerなどのプラグインで作成されたWebP/AVIFバージョンを、サーバー上の変換済みファイルをチェックして検出します。

= SEOスコアの重み付けをカスタマイズできますか？ =

はい、すべてのスコア重み付けは設定ページで設定可能です。

== Screenshots ==

1. ズームコントロール付きLightboxビューア
2. 画像情報パネル
3. 投稿編集画面のSEO診断メタボックス
4. 設定ページ

== Changelog ==

= 1.0.1 =
* Lightbox適用ページタイプ設定機能を追加
* 対応ページ: 投稿、固定ページ、アーカイブ、ホーム、フロントページ、検索結果、添付ファイル、404

= 1.0.0 =
* 初回リリース
* ズームとナビゲーション機能付きLightbox画像ビューア
* 画像SEO診断（alt、ファイル名、サイズ、フォーマット）
* SEOスコア算出
* 管理設定ページ
* 投稿編集画面メタボックス
* 投稿一覧SEOスコアカラム

== Upgrade Notice ==

= 1.0.0 =
初回リリース。
