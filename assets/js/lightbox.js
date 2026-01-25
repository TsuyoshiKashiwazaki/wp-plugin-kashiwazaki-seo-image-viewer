/**
 * Kashiwazaki SEO Image Viewer - Lightbox JavaScript
 */
(function() {
    'use strict';

    // 設定と翻訳文字列
    const config = window.ksivLightbox || {};
    const settings = config.settings || {};
    const i18n = config.i18n || {};

    // DOM要素
    let lightbox = null;
    let overlay = null;
    let imageElement = null;
    let imageContainer = null;
    let loadingElement = null;
    let infoPanel = null;
    let counterCurrent = null;
    let counterTotal = null;
    let prevButton = null;
    let nextButton = null;
    let closeButton = null;
    let infoToggleButton = null;
    let zoomInButton = null;
    let zoomOutButton = null;
    let zoomLevelDisplay = null;
    let copyUrlButton = null;
    let copyFeedback = null;

    // 状態
    let images = [];
    let currentIndex = 0;
    let currentZoom = 1;
    let minZoom = 1;
    let maxZoom = settings.maxZoomRatio || 3;
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let translateX = 0;
    let translateY = 0;
    let lastTranslateX = 0;
    let lastTranslateY = 0;
    let animationSpeed = settings.animationSpeed || 300;

    // タッチ用
    let lastTouchDistance = 0;
    let isTouchDevice = 'ontouchstart' in window;

    /**
     * 初期化
     */
    function init() {
        // DOM要素を取得
        lightbox = document.getElementById('ksiv-lightbox');
        if (!lightbox) return;

        overlay = lightbox.querySelector('.ksiv-lightbox-overlay');
        imageElement = lightbox.querySelector('.ksiv-lightbox-image');
        imageContainer = lightbox.querySelector('.ksiv-lightbox-image-container');
        loadingElement = lightbox.querySelector('.ksiv-lightbox-loading');
        infoPanel = lightbox.querySelector('.ksiv-lightbox-info-panel');
        counterCurrent = lightbox.querySelector('.ksiv-lightbox-current');
        counterTotal = lightbox.querySelector('.ksiv-lightbox-total');
        prevButton = lightbox.querySelector('.ksiv-lightbox-prev');
        nextButton = lightbox.querySelector('.ksiv-lightbox-next');
        closeButton = lightbox.querySelector('.ksiv-lightbox-close');
        infoToggleButton = lightbox.querySelector('.ksiv-lightbox-info-toggle');
        zoomInButton = lightbox.querySelector('.ksiv-lightbox-zoom-in');
        zoomOutButton = lightbox.querySelector('.ksiv-lightbox-zoom-out');
        zoomLevelDisplay = lightbox.querySelector('.ksiv-lightbox-zoom-level');
        copyUrlButton = lightbox.querySelector('.ksiv-lightbox-copy-url');
        copyFeedback = lightbox.querySelector('.ksiv-lightbox-copy-feedback');

        // 拡大対象の画像を収集してイベントを設定
        collectImages();

        // イベントリスナーを設定
        setupEventListeners();

        // 情報パネルのデフォルト状態を設定
        if (settings.infoPanelDefaultVisible) {
            infoPanel.classList.add('is-visible');
            infoToggleButton.classList.add('is-active');
        }
    }

    /**
     * 拡大対象の画像を収集
     */
    function collectImages() {
        const targetImages = document.querySelectorAll('img[data-ksiv-image]');
        images = [];

        targetImages.forEach((img, index) => {
            images.push({
                src: img.currentSrc || img.src,
                alt: img.alt || '',
                element: img
            });

            // 画像をラッパーで囲む（アイコン表示用）
            if (settings.showZoomIcon) {
                wrapImageWithIcon(img);
            }

            // クリックイベント
            img.addEventListener('click', function(e) {
                e.preventDefault();
                openLightbox(index);
            });
        });
    }

    /**
     * 画像を虫眼鏡アイコン付きラッパーで囲む
     */
    function wrapImageWithIcon(img) {
        // すでにラッパーで囲まれている場合はスキップ
        if (img.parentElement.classList.contains('ksiv-image-wrapper')) {
            return;
        }

        const wrapper = document.createElement('span');
        wrapper.className = 'ksiv-image-wrapper';

        const icon = document.createElement('span');
        icon.className = 'ksiv-zoom-icon position-' + (settings.zoomIconPosition || 'bottom-right');
        icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>';

        img.parentNode.insertBefore(wrapper, img);
        wrapper.appendChild(img);
        wrapper.appendChild(icon);
    }

    /**
     * イベントリスナーを設定
     */
    function setupEventListeners() {
        // 閉じるボタン
        closeButton.addEventListener('click', closeLightbox);

        // オーバーレイクリック
        overlay.addEventListener('click', closeLightbox);

        // ナビゲーション
        prevButton.addEventListener('click', showPrevious);
        nextButton.addEventListener('click', showNext);

        // 情報パネルトグル
        infoToggleButton.addEventListener('click', toggleInfoPanel);

        // ズームボタン
        zoomInButton.addEventListener('click', function() { zoom(0.5); });
        zoomOutButton.addEventListener('click', function() { zoom(-0.5); });

        // URLコピー
        copyUrlButton.addEventListener('click', copyImageUrl);

        // キーボード
        document.addEventListener('keydown', handleKeydown);

        // マウスホイール（ズーム）
        imageContainer.addEventListener('wheel', handleWheel, { passive: false });

        // ドラッグ（パン）
        imageContainer.addEventListener('mousedown', handleDragStart);
        document.addEventListener('mousemove', handleDragMove);
        document.addEventListener('mouseup', handleDragEnd);

        // タッチイベント
        if (isTouchDevice) {
            imageContainer.addEventListener('touchstart', handleTouchStart, { passive: false });
            imageContainer.addEventListener('touchmove', handleTouchMove, { passive: false });
            imageContainer.addEventListener('touchend', handleTouchEnd);
        }

        // 画像読み込み完了
        imageElement.addEventListener('load', handleImageLoad);
        imageElement.addEventListener('error', handleImageError);
    }

    /**
     * Lightboxを開く
     */
    function openLightbox(index) {
        currentIndex = index;
        currentZoom = 1;
        translateX = 0;
        translateY = 0;
        lastTranslateX = 0;
        lastTranslateY = 0;

        // 単独画像かどうかを判定
        if (images.length <= 1) {
            lightbox.classList.add('is-single');
        } else {
            lightbox.classList.remove('is-single');
        }

        lightbox.classList.add('is-active');

        // スクロール禁止
        document.body.style.overflow = 'hidden';

        // 画像を表示
        showImage(index);

        // フェードイン
        requestAnimationFrame(function() {
            lightbox.classList.add('is-visible');
        });

        // フォーカスをLightboxに移動
        closeButton.focus();
    }

    /**
     * Lightboxを閉じる
     */
    function closeLightbox() {
        lightbox.classList.remove('is-visible');

        setTimeout(function() {
            lightbox.classList.remove('is-active');
            document.body.style.overflow = '';

            // ズームをリセット
            resetZoom();
        }, animationSpeed);
    }

    /**
     * 画像を表示
     */
    function showImage(index) {
        if (index < 0 || index >= images.length) return;

        const image = images[index];

        // ローディング表示
        loadingElement.classList.add('is-active');
        imageElement.classList.add('is-loading');

        // 画像を読み込み
        imageElement.src = image.src;
        imageElement.alt = image.alt;

        // カウンターを更新
        counterCurrent.textContent = index + 1;
        counterTotal.textContent = images.length;

        // ナビゲーションボタンの状態を更新
        prevButton.disabled = index === 0;
        nextButton.disabled = index === images.length - 1;

        // ズームをリセット
        resetZoom();

        // 画像情報を更新
        updateImageInfo(image);
    }

    /**
     * 画像読み込み完了
     */
    function handleImageLoad() {
        loadingElement.classList.remove('is-active');
        imageElement.classList.remove('is-loading');
    }

    /**
     * 画像読み込みエラー
     */
    function handleImageError() {
        loadingElement.classList.remove('is-active');
        imageElement.classList.remove('is-loading');
        imageElement.alt = i18n.loading || 'Loading error';
    }

    /**
     * 前の画像を表示
     */
    function showPrevious() {
        if (currentIndex > 0) {
            currentIndex--;
            showImage(currentIndex);
        }
    }

    /**
     * 次の画像を表示
     */
    function showNext() {
        if (currentIndex < images.length - 1) {
            currentIndex++;
            showImage(currentIndex);
        }
    }

    /**
     * キーボードハンドラー
     */
    function handleKeydown(e) {
        if (!lightbox.classList.contains('is-active')) return;

        switch (e.key) {
            case 'Escape':
                closeLightbox();
                break;
            case 'ArrowLeft':
                showPrevious();
                break;
            case 'ArrowRight':
                showNext();
                break;
            case '+':
            case '=':
                zoom(0.25);
                break;
            case '-':
                zoom(-0.25);
                break;
        }
    }

    /**
     * マウスホイールハンドラー
     */
    function handleWheel(e) {
        e.preventDefault();

        const delta = e.deltaY > 0 ? -0.1 : 0.1;
        zoom(delta, e.clientX, e.clientY);
    }

    /**
     * ズーム処理
     */
    function zoom(delta, centerX, centerY) {
        const prevZoom = currentZoom;
        currentZoom = Math.min(maxZoom, Math.max(minZoom, currentZoom + delta));

        if (currentZoom === prevZoom) return;

        // ズーム中心点を考慮した位置調整
        if (centerX !== undefined && centerY !== undefined) {
            const rect = imageContainer.getBoundingClientRect();
            const imgCenterX = rect.left + rect.width / 2;
            const imgCenterY = rect.top + rect.height / 2;

            const offsetX = (centerX - imgCenterX) / prevZoom;
            const offsetY = (centerY - imgCenterY) / prevZoom;

            translateX -= offsetX * (currentZoom - prevZoom);
            translateY -= offsetY * (currentZoom - prevZoom);
        }

        applyTransform();
        updateZoomDisplay();
        updateContainerState();
    }

    /**
     * ズームをリセット
     */
    function resetZoom() {
        currentZoom = 1;
        translateX = 0;
        translateY = 0;
        lastTranslateX = 0;
        lastTranslateY = 0;

        applyTransform();
        updateZoomDisplay();
        updateContainerState();
    }

    /**
     * 変換を適用
     */
    function applyTransform() {
        // パン範囲を制限
        constrainPan();

        imageElement.style.transform =
            'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + currentZoom + ')';
    }

    /**
     * パン範囲を制限
     */
    function constrainPan() {
        if (currentZoom <= 1) {
            translateX = 0;
            translateY = 0;
            return;
        }

        const rect = imageElement.getBoundingClientRect();
        const containerRect = imageContainer.getBoundingClientRect();

        const scaledWidth = rect.width;
        const scaledHeight = rect.height;

        const maxTranslateX = Math.max(0, (scaledWidth - containerRect.width) / 2 / currentZoom);
        const maxTranslateY = Math.max(0, (scaledHeight - containerRect.height) / 2 / currentZoom);

        translateX = Math.min(maxTranslateX, Math.max(-maxTranslateX, translateX));
        translateY = Math.min(maxTranslateY, Math.max(-maxTranslateY, translateY));
    }

    /**
     * ズーム表示を更新
     */
    function updateZoomDisplay() {
        zoomLevelDisplay.textContent = Math.round(currentZoom * 100) + '%';
    }

    /**
     * コンテナの状態を更新
     */
    function updateContainerState() {
        if (currentZoom > 1) {
            imageContainer.classList.add('is-zoomed');
        } else {
            imageContainer.classList.remove('is-zoomed');
        }
    }

    /**
     * ドラッグ開始
     */
    function handleDragStart(e) {
        if (currentZoom <= 1) return;

        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        lastTranslateX = translateX;
        lastTranslateY = translateY;

        imageContainer.classList.add('is-dragging');
    }

    /**
     * ドラッグ中
     */
    function handleDragMove(e) {
        if (!isDragging) return;

        e.preventDefault();

        const deltaX = (e.clientX - startX) / currentZoom;
        const deltaY = (e.clientY - startY) / currentZoom;

        translateX = lastTranslateX + deltaX;
        translateY = lastTranslateY + deltaY;

        applyTransform();
    }

    /**
     * ドラッグ終了
     */
    function handleDragEnd() {
        isDragging = false;
        imageContainer.classList.remove('is-dragging');
    }

    /**
     * タッチ開始
     */
    function handleTouchStart(e) {
        if (e.touches.length === 2) {
            // ピンチズーム開始
            lastTouchDistance = getTouchDistance(e.touches);
        } else if (e.touches.length === 1 && currentZoom > 1) {
            // パン開始
            isDragging = true;
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            lastTranslateX = translateX;
            lastTranslateY = translateY;
        }
    }

    /**
     * タッチ移動
     */
    function handleTouchMove(e) {
        if (e.touches.length === 2) {
            e.preventDefault();

            // ピンチズーム
            const distance = getTouchDistance(e.touches);
            const delta = (distance - lastTouchDistance) / 200;
            lastTouchDistance = distance;

            const centerX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
            const centerY = (e.touches[0].clientY + e.touches[1].clientY) / 2;

            zoom(delta, centerX, centerY);
        } else if (e.touches.length === 1 && isDragging) {
            e.preventDefault();

            // パン
            const deltaX = (e.touches[0].clientX - startX) / currentZoom;
            const deltaY = (e.touches[0].clientY - startY) / currentZoom;

            translateX = lastTranslateX + deltaX;
            translateY = lastTranslateY + deltaY;

            applyTransform();
        }
    }

    /**
     * タッチ終了
     */
    function handleTouchEnd() {
        isDragging = false;
        lastTouchDistance = 0;
    }

    /**
     * 2点間のタッチ距離を計算
     */
    function getTouchDistance(touches) {
        const dx = touches[0].clientX - touches[1].clientX;
        const dy = touches[0].clientY - touches[1].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    /**
     * 情報パネルをトグル
     */
    function toggleInfoPanel() {
        infoPanel.classList.toggle('is-visible');
        infoToggleButton.classList.toggle('is-active');
        infoPanel.setAttribute('aria-hidden', !infoPanel.classList.contains('is-visible'));
    }

    /**
     * 画像情報を更新
     */
    function updateImageInfo(image) {
        const infoFormat = lightbox.querySelector('.ksiv-info-format');
        const infoFilesize = lightbox.querySelector('.ksiv-info-filesize');
        const infoDimensions = lightbox.querySelector('.ksiv-info-dimensions');
        const infoAlt = lightbox.querySelector('.ksiv-info-alt');
        const infoUrl = lightbox.querySelector('.ksiv-info-url');

        // 画像のURL
        infoUrl.textContent = image.src;
        infoUrl.title = image.src;

        // alt属性
        if (image.alt && image.alt.trim() !== '') {
            infoAlt.textContent = image.alt;
            infoAlt.classList.remove('is-warning');
        } else {
            infoAlt.textContent = i18n.altNotSet || '未設定';
            infoAlt.classList.add('is-warning');
        }

        // フォーマット（拡張子から判定）
        const extension = getFileExtension(image.src).toUpperCase();
        infoFormat.textContent = extension || '—';

        // サイズ情報を取得（画像読み込み後）
        const tempImg = new Image();
        tempImg.onload = function() {
            infoDimensions.textContent = tempImg.naturalWidth + ' × ' + tempImg.naturalHeight;
        };
        tempImg.src = image.src;

        // ファイルサイズはサーバーから取得する必要があるため、
        // ここでは取得できない場合は「—」を表示
        infoFilesize.textContent = '—';

        // AJAXでファイル情報を取得（オプション）
        fetchImageInfo(image.src, function(info) {
            if (info) {
                if (info.filesize_formatted) {
                    infoFilesize.textContent = info.filesize_formatted;
                }
                if (info.format_name) {
                    infoFormat.textContent = info.format_name;
                }
                if (info.width && info.height) {
                    infoDimensions.textContent = info.width + ' × ' + info.height;
                }
            }
        });
    }

    /**
     * URLからファイル拡張子を取得
     */
    function getFileExtension(url) {
        const pathname = new URL(url, window.location.origin).pathname;
        const extension = pathname.split('.').pop();
        return extension && extension.length <= 5 ? extension : '';
    }

    /**
     * AJAXで画像情報を取得
     */
    function fetchImageInfo(src, callback) {
        if (!config.ajaxUrl) {
            callback(null);
            return;
        }

        const formData = new FormData();
        formData.append('action', 'ksiv_get_image_info');
        formData.append('nonce', config.nonce);
        formData.append('src', src);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                callback(data.data);
            } else {
                callback(null);
            }
        })
        .catch(function() {
            callback(null);
        });
    }

    /**
     * 画像URLをコピー
     */
    function copyImageUrl() {
        const infoUrl = lightbox.querySelector('.ksiv-info-url');
        const url = infoUrl.textContent;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                showCopyFeedback();
            }).catch(function() {
                fallbackCopy(url);
            });
        } else {
            fallbackCopy(url);
        }
    }

    /**
     * フォールバックのコピー処理
     */
    function fallbackCopy(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();

        try {
            document.execCommand('copy');
            showCopyFeedback();
        } catch (err) {
            console.error('Copy failed:', err);
        }

        document.body.removeChild(textArea);
    }

    /**
     * コピー完了フィードバックを表示
     */
    function showCopyFeedback() {
        copyFeedback.classList.add('is-visible');

        setTimeout(function() {
            copyFeedback.classList.remove('is-visible');
        }, 2000);
    }

    // DOMContentLoaded時に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
