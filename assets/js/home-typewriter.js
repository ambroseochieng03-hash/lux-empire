/*
=========================================
LUX EMPIRE — HERO TYPEWRITER
=========================================
Types each phrase, pauses, erases it, then moves to the next —
loops forever. The blinking cursor is pure CSS (.home-typewriter-
cursor in home.css); this script only ever touches the text.
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

    document.addEventListener('DOMContentLoaded', () => {

        const target = document.getElementById('homeTypewriter');

        if (!target) {
            return;
        }

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
