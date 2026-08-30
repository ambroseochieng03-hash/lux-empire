/*
=========================================
LUX EMPIRE — HERO TYPEWRITER
=========================================
Types each phrase, pauses, erases it, then moves to the next —
loops forever. The blinking cursor is pure CSS (.home-typewriter-
cursor in home.css); this script only ever touches the text.

Height-locking: since font-size is a clamp() and phrases vary in
length, different phrases can wrap to different line counts at
the same viewport width (e.g. 2 lines vs 3), which grows/shrinks
.home-hero-title and shifts every section below it. To prevent
that, we measure the tallest phrase's rendered height using a
hidden clone (same classes, same width) and lock .home-hero-title
to that height. Re-measured on resize, since the clamp() font-size
and available width both change with viewport.
=========================================
*/

(function () {

    const PHRASES = [
        'Where Luxury Finds Home.',
        'Elite Properties. Verified Landlords. Real Trust.',
        'Moving Made Effortless — Track Every Mile.',
        'One Empire. Every Address. Every Journey.',
        'Experience the Future of Luxury Living.',
        'Your Next Home, Your Next Adventure.',
        'Where Every Move is a Masterpiece.',
        'Luxury Living, Redefined.',
        'From Dream to Doorstep — Seamlessly.',
        'Elevate Your Lifestyle, One Address at a Time.'
    ];

    const TYPE_SPEED_MS = 60;
    const ERASE_SPEED_MS = 50;
    const PAUSE_AFTER_TYPE_MS = 2400;
    const PAUSE_AFTER_ERASE_MS = 400;
    const RESIZE_DEBOUNCE_MS = 150;

    document.addEventListener('DOMContentLoaded', () => {

        const target = document.getElementById('homeTypewriter');
        const titleEl = document.querySelector('.home-hero-title');

        if (!target || !titleEl) {
            return;
        }

        /*
         * Measure the tallest possible height .home-hero-title could
         * need across every phrase, at the current viewport, and
         * lock it there. A hidden clone is used so measurement never
         * causes a visible flash/shift of the real element.
         */
        function lockTitleHeight() {

            const measurer = titleEl.cloneNode(true);

            measurer.style.position = 'absolute';
            measurer.style.visibility = 'hidden';
            measurer.style.pointerEvents = 'none';
            measurer.style.height = 'auto';
            measurer.style.minHeight = '0';
            measurer.style.width = titleEl.getBoundingClientRect().width + 'px';

            document.body.appendChild(measurer);

            const measurerTyped = measurer.querySelector('.home-hero-title-typed');

            let tallest = 0;

            PHRASES.forEach((phrase) => {
                if (measurerTyped) {
                    measurerTyped.textContent = phrase;
                }
                tallest = Math.max(tallest, measurer.getBoundingClientRect().height);
            });

            document.body.removeChild(measurer);

            if (tallest > 0) {
                titleEl.style.height = tallest + 'px';
            }
        }

        lockTitleHeight();

        let resizeTimer = null;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(lockTitleHeight, RESIZE_DEBOUNCE_MS);
        });

        /*
         * ---- Typing loop (unchanged from before) ----
         */

        let phraseIndex = 0;
        let charIndex = 0;
        let isErasing = false;

        function tick() {

            const currentPhrase = PHRASES[phraseIndex];

            if (!isErasing) {

                charIndex += 1;
                target.textContent = currentPhrase.slice(0, charIndex);

                if (charIndex === currentPhrase.length) {
                    isErasing = true;
                    setTimeout(tick, PAUSE_AFTER_TYPE_MS);
                    return;
                }

                setTimeout(tick, TYPE_SPEED_MS);
                return;
            }

            charIndex -= 1;
            target.textContent = currentPhrase.slice(0, charIndex);

            if (charIndex === 0) {
                isErasing = false;
                phraseIndex = (phraseIndex + 1) % PHRASES.length;
                setTimeout(tick, PAUSE_AFTER_ERASE_MS);
                return;
            }

            setTimeout(tick, ERASE_SPEED_MS);
        }

        tick();
    });

})();