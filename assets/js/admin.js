/**
 * Kashiwazaki SEO Image Viewer - Admin JavaScript
 */
(function() {
    'use strict';

    /**
     * スコアサークルの初期化
     */
    function initScoreCircles() {
        const scoreCircles = document.querySelectorAll('.ksiv-score-circle');

        scoreCircles.forEach(function(circle) {
            const scoreValue = circle.querySelector('.ksiv-score-value');
            if (scoreValue) {
                const score = parseInt(scoreValue.textContent, 10) || 0;
                circle.style.setProperty('--score', score);
            }
        });
    }

    /**
     * 設定ページの初期化
     */
    function initSettingsPage() {
        const settingsWrap = document.querySelector('.ksiv-settings-wrap');
        if (!settingsWrap) return;

        // 正規表現パターンのバリデーション
        const patternTextarea = document.querySelector('textarea[name="ksiv_settings[filename_warning_patterns]"]');
        if (patternTextarea) {
            patternTextarea.addEventListener('blur', function() {
                validatePatterns(this);
            });
        }
    }

    /**
     * 正規表現パターンのバリデーション
     */
    function validatePatterns(textarea) {
        const patterns = textarea.value.split('\n');
        const invalidPatterns = [];

        patterns.forEach(function(pattern, index) {
            pattern = pattern.trim();
            if (pattern === '') return;

            try {
                // PHP PCRE 形式（区切り文字 + flags）を JS RegExp に変換して検証する。
                // 区切り文字込みのまま new RegExp に渡すと実際とは別の式を検査してしまう。
                // これはクライアント側の目安チェック（PCRE 固有構文は JS で完全検証できない。
                // サーバー側は @preg_match で最終検証する）。
                var delim = pattern.charAt(0);
                var isDelimited = pattern.length >= 2 &&
                    '/#~!%|@'.indexOf(delim) !== -1 &&
                    pattern.lastIndexOf(delim) > 0;

                if (isDelimited) {
                    var close = pattern.lastIndexOf(delim);
                    var body = pattern.substring(1, close);
                    var rawFlags = pattern.substring(close + 1);
                    // JS がサポートするフラグ (i,m,s,u,y,g) のみ残し、重複は除去する
                    // （PCRE は重複修飾子を許すが JS の RegExp は SyntaxError になるため）
                    var jsFlags = (rawFlags.match(/[imsuyg]/g) || [])
                        .filter(function(f, i, a) { return a.indexOf(f) === i; })
                        .join('');
                    new RegExp(body, jsFlags);
                } else {
                    new RegExp(pattern);
                }
            } catch (e) {
                invalidPatterns.push({
                    line: index + 1,
                    pattern: pattern,
                    error: e.message
                });
            }
        });

        // エラーメッセージを表示
        let errorContainer = textarea.parentNode.querySelector('.ksiv-pattern-errors');

        if (invalidPatterns.length > 0) {
            if (!errorContainer) {
                errorContainer = document.createElement('div');
                errorContainer.className = 'ksiv-pattern-errors';
                errorContainer.style.cssText = 'color: #d63638; margin-top: 8px; font-size: 13px;';
                textarea.parentNode.appendChild(errorContainer);
            }

            let errorHtml = '<strong>無効な正規表現パターン:</strong><ul style="margin: 4px 0 0 16px;">';
            invalidPatterns.forEach(function(item) {
                errorHtml += '<li>行 ' + item.line + ': ' + escapeHtml(item.pattern) + ' (' + escapeHtml(item.error) + ')</li>';
            });
            errorHtml += '</ul>';

            errorContainer.innerHTML = errorHtml;
        } else if (errorContainer) {
            errorContainer.remove();
        }
    }

    /**
     * HTMLエスケープ
     */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * メタボックスの初期化
     */
    function initMetaBox() {
        const metaBox = document.querySelector('.ksiv-meta-box');
        if (!metaBox) return;

        // スコアサークルを初期化
        initScoreCircles();

        // 画像サムネイルのホバーエフェクト
        const issueImages = metaBox.querySelectorAll('.ksiv-issue-image img');
        issueImages.forEach(function(img) {
            img.addEventListener('click', function() {
                // 画像をプレビュー表示（簡易的な実装）
                const overlay = document.createElement('div');
                overlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 999999; display: flex; align-items: center; justify-content: center; cursor: pointer;';

                const previewImg = document.createElement('img');
                previewImg.src = img.src.replace(/-\d+x\d+\./, '.');
                previewImg.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain;';

                overlay.appendChild(previewImg);
                document.body.appendChild(overlay);

                overlay.addEventListener('click', function() {
                    overlay.remove();
                });
            });

            img.style.cursor = 'pointer';
            img.title = 'クリックして拡大';
        });
    }

    /**
     * 投稿一覧のスコア表示初期化
     */
    function initPostsList() {
        const scoreColumns = document.querySelectorAll('.ksiv-list-score:not(.ksiv-no-images)');

        scoreColumns.forEach(function(column) {
            const score = parseInt(column.textContent, 10);
            if (isNaN(score)) return;

            // ホバー時にツールチップを表示
            column.title = '画像SEOスコア: ' + score + '点';
        });
    }

    /**
     * 初期化
     */
    function init() {
        initSettingsPage();
        initMetaBox();
        initPostsList();
    }

    // DOMContentLoaded時に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
