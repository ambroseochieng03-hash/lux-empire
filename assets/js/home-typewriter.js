/*
=========================================
LUX EMPIRE — HERO TYPEWRITER
=========================================
Types each phrase, pauses, erases it, then moves to the next —
loops forever. The blinking cursor is pure CSS (.home-typewriter-
cursor in home.css); this script only ever touches the text.

Height handling: font-size is a clamp(), and phrases vary a lot in
length, so different phrases can wrap to different line counts at
the same viewport width. Rather than locking .home-hero-title to
the tallest phrase across the WHOLE list forever (which leaves a
permanent gap under every shorter phrase), we measure and set the
height for just the phrase about to be typed, right as it's about
to start. Paired with a CSS transition on `height` (see home.css),
this turns each phrase change into a smooth resize instead of a
sudden layout jump — no gap, no shake.
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
         * Measures a single phrase's fully-rendered height (via a
         * hidden clone, so there's never a visible flash) and sets
         * .home-hero-title to exactly that height. Called once per
         * phrase, not once for the whole list.
         */
        function setHeightForPhrase(phrase) {

            const measurer = titleEl.cloneNode(true);

            measurer.style.position = 'absolute';
            measurer.style.visibility = 'hidden';
            measurer.style.pointerEvents = 'none';
            measurer.style.height = 'auto';
            measurer.style.minHeight = '0';
            measurer.style.transition = 'none';
            measurer.style.width = titleEl.getBoundingClientRect().width + 'px';

            document.body.appendChild(measurer);

            const measurerTyped = measurer.querySelector('.home-hero-title-typed');
            if (measurerTyped) {
                measurerTyped.textContent = phrase;
            }

            const height = measurer.getBoundingClientRect().height;

            document.body.removeChild(measurer);

            if (height > 0) {
                titleEl.style.height = height + 'px';
            }
        }

        let phraseIndex = 0;
        let charIndex = 0;
        let isErasing = false;

        // Set the correct height for the very first phrase before typing starts.
        setHeightForPhrase(PHRASES[phraseIndex]);

        // Re-measure the CURRENT phrase on resize, since clamp() font-size
        // and available width both change across viewport sizes.
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                setHeightForPhrase(PHRASES[phraseIndex]);
            }, RESIZE_DEBOUNCE_MS);
        });

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

                // Resize toward the NEXT phrase now, during the pause —
                // so the box has already smoothly settled to the right
                // height before a single character of it is typed.
                setHeightForPhrase(PHRASES[phraseIndex]);

                setTimeout(tick, PAUSE_AFTER_ERASE_MS);
                return;
            }

            setTimeout(tick, ERASE_SPEED_MS);
        }

        tick();
    });

})();