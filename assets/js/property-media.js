/**
 * PROPERTY MEDIA — card carousel, in-card video, + enlarge/lightbox
 * ---------------------------------------------------------------
 * Powers property media on:
 *   - dashboard/landlord/manage_houses.php
 *   - dashboard/tenant/search_houses.php
 *
 * Each card's media area is a ".media-frame":
 *   - Images: has data-images="[...]" (from House::getHouseMedia(),
 *     filtered to non-.mp4 rows) and one <img class="media-slide">
 *     per image, plus optional prev/next + dot controls when there
 *     is more than one image.
 *   - Video: has data-video="..." (the single .mp4 row, per the
 *     existing "images OR one video" backend rule) and a native
 *     <video controls> element.
 *
 * The enlarge button on each frame opens a single shared lightbox
 * that can page through the same image set, or play the same video
 * at a larger size.
 *
 * Plain JavaScript, no framework, no external dependency.
 */
(function () {
    'use strict';

    /* ================================================================
       CARD-LEVEL CAROUSEL (auto-sliding, stops permanently on touch)
       ================================================================ */

    var AUTOPLAY_INTERVAL_MS = 4500;

    function getTrack(frame) {
        return frame.querySelector('.media-carousel-track');
    }

    function setActiveSlide(frame, index) {
        var track = getTrack(frame);
        var slides = frame.querySelectorAll('.media-slide');
        var dots = frame.querySelectorAll('.media-dot');

        if (!track || !slides.length) {
            return;
        }

        index = ((index % slides.length) + slides.length) % slides.length;

        track.style.transform = 'translateX(-' + (index * 100) + '%)';

        slides.forEach(function (slide) {
            var slideIndex = parseInt(slide.getAttribute('data-index'), 10);
            slide.classList.toggle('is-active', slideIndex === index);
        });

        dots.forEach(function (dot) {
            var dotIndex = parseInt(dot.getAttribute('data-index'), 10);
            dot.classList.toggle('is-active', dotIndex === index);
        });

        frame.setAttribute('data-current-index', String(index));
    }

    function stopAutoplay(frame) {
        if (!frame) {
            return;
        }
        var intervalId = frame.getAttribute('data-autoplay-id');
        if (intervalId) {
            clearInterval(Number(intervalId));
            frame.removeAttribute('data-autoplay-id');
        }
    }

    function startAutoplay(frame) {
        var slides = frame.querySelectorAll('.media-slide');
        if (slides.length < 2) {
            return;
        }

        stopAutoplay(frame);

        var intervalId = setInterval(function () {
            var current = parseInt(frame.getAttribute('data-current-index') || '0', 10);
            setActiveSlide(frame, current + 1);
        }, AUTOPLAY_INTERVAL_MS);

        frame.setAttribute('data-autoplay-id', String(intervalId));
    }

    // Start auto-sliding for every card that has more than one image.
    document.querySelectorAll('.media-frame').forEach(function (frame) {
        if (frame.querySelector('.media-carousel')) {
            startAutoplay(frame);
        }
    });

    // A touch on a carousel stops its auto-slide permanently for that
    // card. Manual prev/next/dot controls remain fully usable — they
    // are handled separately below and are never disabled.
    document.addEventListener('touchstart', function (event) {
        var carousel = event.target.closest('.media-carousel');
        if (!carousel) {
            return;
        }
        stopAutoplay(carousel.closest('.media-frame'));
    }, { passive: true });

    document.addEventListener('click', function (event) {
        var frame = event.target.closest('.media-frame');
        if (!frame) {
            return;
        }

        var current = parseInt(frame.getAttribute('data-current-index') || '0', 10);

        if (event.target.closest('.media-carousel-prev')) {
            event.stopPropagation();
            stopAutoplay(frame);
            setActiveSlide(frame, current - 1);
            return;
        }

        if (event.target.closest('.media-carousel-next')) {
            event.stopPropagation();
            stopAutoplay(frame);
            setActiveSlide(frame, current + 1);
            return;
        }

        var dot = event.target.closest('.media-dot');
        if (dot) {
            event.stopPropagation();
            stopAutoplay(frame);
            setActiveSlide(frame, parseInt(dot.getAttribute('data-index'), 10));
            return;
        }
    });

    /* ================================================================
       LIGHTBOX
       ================================================================ */

    var lightbox = document.getElementById('mediaLightbox');
    if (!lightbox) {
        return;
    }

    var stage      = lightbox.querySelector('.media-lightbox-stage');
    var imageEl    = lightbox.querySelector('.media-lightbox-image');
    var prevBtn    = lightbox.querySelector('.media-lightbox-prev');
    var nextBtn    = lightbox.querySelector('.media-lightbox-next');
    var counterEl  = lightbox.querySelector('.media-lightbox-counter');
    var closeEls   = lightbox.querySelectorAll('[data-media-close]');

    var currentItems  = [];
    var currentIndex  = 0;
    var lastFocusedEl = null;

    function isVideoSrc(src) {
        return /\.mp4(\?.*)?$/i.test(src || '');
    }

    function renderCurrentItem() {
        var src = currentItems[currentIndex];
        if (!src) {
            return;
        }

        var existingVideo = stage.querySelector('.media-lightbox-video');
        if (existingVideo) {
            existingVideo.remove();
        }

        if (isVideoSrc(src)) {
            imageEl.style.display = 'none';

            var video = document.createElement('video');
            video.className = 'media-lightbox-video';
            video.src = src;
            video.controls = true;
            video.playsInline = true;
            video.setAttribute('preload', 'metadata');
            stage.appendChild(video);
        } else {
            imageEl.style.display = '';
            imageEl.src = src;
            imageEl.alt = lightbox.getAttribute('data-current-caption') || '';
        }

        var hasMultiple = currentItems.length > 1;
        prevBtn.hidden = !hasMultiple;
        nextBtn.hidden = !hasMultiple;

        counterEl.textContent = hasMultiple
            ? (currentIndex + 1) + ' / ' + currentItems.length
            : '';
    }

    function stopVideoIfPlaying() {
        var video = stage.querySelector('.media-lightbox-video');
        if (video) {
            video.pause();
        }
    }

    function openLightbox(items, caption, startIndex) {
        if (!items || !items.length) {
            return;
        }

        currentItems  = items;
        currentIndex  = startIndex || 0;
        lastFocusedEl = document.activeElement;

        lightbox.setAttribute('data-current-caption', caption || '');
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        renderCurrentItem();

        var closeBtn = lightbox.querySelector('.media-lightbox-close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeLightbox() {
        stopVideoIfPlaying();

        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        currentItems = [];
        currentIndex = 0;

        if (lastFocusedEl && typeof lastFocusedEl.focus === 'function') {
            lastFocusedEl.focus();
        }
    }

    function showPrev() {
        if (currentItems.length < 2) {
            return;
        }
        stopVideoIfPlaying();
        currentIndex = (currentIndex - 1 + currentItems.length) % currentItems.length;
        renderCurrentItem();
    }

    function showNext() {
        if (currentItems.length < 2) {
            return;
        }
        stopVideoIfPlaying();
        currentIndex = (currentIndex + 1) % currentItems.length;
        renderCurrentItem();
    }

    /* ---------- Enlarge button opens the lightbox ---------- */

    document.addEventListener('click', function (event) {
        var enlargeBtn = event.target.closest('.media-enlarge-btn');
        if (!enlargeBtn) {
            return;
        }

        var frame = enlargeBtn.closest('.media-frame');
        if (!frame) {
            return;
        }

        var caption = frame.getAttribute('data-caption') || '';

        var videoSrc = frame.getAttribute('data-video');
        if (videoSrc) {
            openLightbox([videoSrc], caption, 0);
            return;
        }

        var raw = frame.getAttribute('data-images');
        var items;

        try {
            items = JSON.parse(raw);
        } catch (err) {
            items = [];
        }

        if (!Array.isArray(items) || !items.length) {
            return;
        }

        var startIndex = parseInt(frame.getAttribute('data-current-index') || '0', 10);
        openLightbox(items, caption, startIndex);
    });

    /* ---------- Close controls ---------- */

    closeEls.forEach(function (el) {
        el.addEventListener('click', closeLightbox);
    });

    /* ---------- Prev / next ---------- */

    prevBtn.addEventListener('click', showPrev);
    nextBtn.addEventListener('click', showNext);

    /* ---------- Keyboard controls while open ---------- */

    document.addEventListener('keydown', function (event) {
        if (!lightbox.classList.contains('is-open')) {
            return;
        }

        switch (event.key) {
            case 'Escape':
                closeLightbox();
                break;
            case 'ArrowLeft':
                showPrev();
                break;
            case 'ArrowRight':
                showNext();
                break;
        }
    });

}());