# Kashiwazaki SEO Image Viewer

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)

記事内画像の拡大表示（Lightbox）機能とSEO診断機能を兼ね備えた軽量WordPressプラグインです。alt属性、ファイル名、画像サイズ、ファイル容量、次世代フォーマット（WebP/AVIF）対応をチェックし、総合的な画像SEOスコアを算出します。

## 機能

### Lightbox画像ビューア
- リンクのない画像をクリックで拡大表示
- ズーム操作（マウスホイール、ピンチジェスチャー、ボタン）
- ズーム時のドラッグ/スワイプ移動
- 前後ナビゲーション（矢印キー対応）
- 閉じる操作（オーバーレイクリック、×ボタン、Escapeキー）
- 画像情報パネル（フォーマット、ファイルサイズ、サイズ、alt属性、URLコピー）
- カスタマイズ可能なズームアイコン位置

### SEO診断機能
- **alt属性チェック**: alt属性が未設定の画像を警告
- **ファイル名パターン検出**: 意味のないファイル名（IMG_*、DSC_*、スクリーンショットなど）を検出
- **画像サイズチェック**: 表示サイズに対して過大な画像を警告
- **ファイル容量チェック**: 閾値（デフォルト: 500KB）を超える画像を警告
- **次世代フォーマット検出**: WebP/AVIF対応をチェック（EWWW Image Optimizer互換）
- **SEOスコア**: 設定可能な重み付けで0-100点のスコアを算出

### 管理機能
- 全オプションを設定可能な設定ページ
- 投稿編集画面に詳細な診断結果を表示するメタボックス
- 投稿/固定ページ一覧にソート可能なSEOスコアカラム
- プラグインアクションリンクから設定へ直接アクセス

## 動作要件

- WordPress 6.0以上
- PHP 7.4以上

## インストール

1. `wp-plugin-kashiwazaki-seo-image-viewer`フォルダを`/wp-content/plugins/`にアップロード
2. WordPressの「プラグイン」メニューからプラグインを有効化
3. 「Kashiwazaki SEO Image Viewer」メニューから設定

## 使い方

### Lightbox
有効化後、投稿内のリンクのない画像をクリックするとLightboxビューアが開きます。
- **マウスホイール**または**ピンチジェスチャー**でズーム
- ズーム時は**ドラッグ**で移動
- **矢印キー**で前後の画像に移動
- **Escape**またはオーバーレイクリックで閉じる
- **情報ボタン**（i）で画像詳細を表示

### SEO診断
SEOスコアと診断結果は以下で確認できます：
- 投稿/固定ページ編集画面のメタボックス
- 投稿/固定ページ一覧の「画像SEO」カラム

## 作者

**柏崎剛 (Tsuyoshi Kashiwazaki)**
- ウェブサイト: [tsuyoshikashiwazaki.jp](https://www.tsuyoshikashiwazaki.jp)
- プロフィール: [tsuyoshikashiwazaki.jp/profile](https://www.tsuyoshikashiwazaki.jp/profile/)

## ライセンス

このプラグインはGPL v2以降でライセンスされています。

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```
