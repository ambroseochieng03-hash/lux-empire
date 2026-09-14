/**
 * LUX EMPIRE — PRODUCT TOUR ENGINE (generic, reusable)
 * SAVE AT: assets/js/product-tour.js
 *
 * A small, dependency-free "spotlight + tooltip" tour engine.
 * Used by home-tour.js (public navbar) and dashboard-tour.js
 * (sidebar), but knows nothing about either — it just walks an
 * array of steps you give it.
 *
 * A step looks like:
 * {
 *   element: function () { return document.querySelector('...'); }, // or null for a centered step
 *   title: 'Short title',
 *   text: 'One or two simple sentences.',
 *   placement: 'bottom' | 'top' | 'left' | 'right' (default 'bottom'),
 *   before: function () {}            // optional, may return a Promise. Runs before the step shows.
 *   after: function () {}             // optional. Runs right before leaving the step.
 * }
 *
 * Usage:
 *   var tour = new LuxTour(steps, {
 *       storageKey: 'lux_home_tour_seen',
 *       onFinish: function(){}, onSkip: function(){}
 *   });
 *   tour.start();
 *
 * LuxTour.prompt({ title, text, yesLabel, noLabel, onAccept, onDecline })
 * shows the "Would you like a guide?" card.
 */
(function (window, document) {
    'use strict';

    function LuxTour(steps, opts) {
        this.steps = steps || [];
        this.opts = Object.assign({
            onFinish: function () {},
            onSkip: function () {},
            storageKey: null
        }, opts || {});
        this.index = 0;
        this._onResize = this._reposition.bind(this);
        this._onKey = this._onKeyDown.bind(this);
    }

    LuxTour.prototype.start = function () {
        if (!this.steps.length) { return; }
        this.index = 0;
        this._buildDom();
        document.addEventListener('keydown', this._onKey);
        window.addEventListener('resize', this._onResize);
        window.addEventListener('scroll', this._onResize, true);
        this._renderStep();
    };

    LuxTour.prototype._buildDom = function () {
        this.overlayEl = document.createElement('div');
        this.overlayEl.className = 'lux-tour-overlay';

        this.spotlightEl = document.createElement('div');
        this.spotlightEl.className = 'lux-tour-spotlight';

        this.tooltipEl = document.createElement('div');
        this.tooltipEl.className = 'lux-tour-tooltip';
        this.tooltipEl.setAttribute('role', 'dialog');
        this.tooltipEl.setAttribute('aria-live', 'polite');

        document.body.appendChild(this.overlayEl);
        document.body.appendChild(this.spotlightEl);
        document.body.appendChild(this.tooltipEl);
    };

    LuxTour.prototype._renderStep = function () {
        var self = this;
        var step = this.steps[this.index];
        if (!step) { return this.finish(); }

        Promise.resolve(typeof step.before === 'function' ? step.before() : null)
            .then(function () {
                // small pause so any menu-open animation can settle
                setTimeout(function () {
                    var target = typeof step.element === 'function' ? step.element() : null;
                    self._positionSpotlight(target);
                    self._renderTooltip(step, target);
                }, step.delay || 90);
            });
    };

    LuxTour.prototype._positionSpotlight = function (target) {
        if (!target) {
            this.spotlightEl.style.opacity = '0';
            return;
        }
        target.scrollIntoView({ block: 'center', behavior: 'smooth' });

        var r = target.getBoundingClientRect();
        var pad = 8;
        this.spotlightEl.style.opacity = '1';
        this.spotlightEl.style.top = Math.max(0, r.top - pad) + 'px';
        this.spotlightEl.style.left = Math.max(0, r.left - pad) + 'px';
        this.spotlightEl.style.width = (r.width + pad * 2) + 'px';
        this.spotlightEl.style.height = (r.height + pad * 2) + 'px';
    };

    LuxTour.prototype._renderTooltip = function (step, target) {
        var self = this;
        var total = this.steps.length;
        var isLast = this.index === total - 1;

        var dots = '';
        for (var i = 0; i < total; i++) {
            dots += '<span class="lux-tour-dot' + (i === this.index ? ' is-active' : '') + '"></span>';
        }

        this.tooltipEl.innerHTML =
            '<button type="button" class="lux-tour-x" aria-label="Close tour">×</button>' +
            '<h4 class="lux-tour-title"></h4>' +
            '<p class="lux-tour-text"></p>' +
            '<div class="lux-tour-dots">' + dots + '</div>' +
            '<div class="lux-tour-actions">' +
                '<button type="button" class="lux-tour-link lux-tour-skip">Skip tour</button>' +
                '<div class="lux-tour-actions-right">' +
                    (this.index > 0 ? '<button type="button" class="lux-tour-btn lux-tour-btn-ghost lux-tour-back">Back</button>' : '') +
                    '<button type="button" class="lux-tour-btn lux-tour-next">' + (isLast ? 'Got it' : 'Next') + '</button>' +
                '</div>' +
            '</div>';

        // set via textContent, not innerHTML, so step copy can never break out of the markup
        this.tooltipEl.querySelector('.lux-tour-title').textContent = step.title || '';
        this.tooltipEl.querySelector('.lux-tour-text').textContent = step.text || '';

        this.tooltipEl.querySelector('.lux-tour-x').onclick = function () { self.skip(); };
        this.tooltipEl.querySelector('.lux-tour-skip').onclick = function () { self.skip(); };
        this.tooltipEl.querySelector('.lux-tour-next').onclick = function () { self.next(); };
        var backBtn = this.tooltipEl.querySelector('.lux-tour-back');
        if (backBtn) { backBtn.onclick = function () { self.prev(); }; }

        this._positionTooltip(step, target);
    };

    LuxTour.prototype._positionTooltip = function (step, target) {
        var tt = this.tooltipEl;
        tt.style.visibility = 'hidden';
        tt.style.display = 'block';

        var ttRect = tt.getBoundingClientRect();
        var vw = window.innerWidth, vh = window.innerHeight;
        var top, left;

        if (!target) {
            top = (vh - ttRect.height) / 2;
            left = (vw - ttRect.width) / 2;
        } else {
            var r = target.getBoundingClientRect();
            var placement = step.placement || 'bottom';

            if (placement === 'top') {
                top = r.top - ttRect.height - 16;
                left = r.left + (r.width / 2) - (ttRect.width / 2);
            } else if (placement === 'left') {
                top = r.top + (r.height / 2) - (ttRect.height / 2);
                left = r.left - ttRect.width - 16;
            } else if (placement === 'right') {
                top = r.top + (r.height / 2) - (ttRect.height / 2);
                left = r.right + 16;
            } else {
                top = r.bottom + 16;
                left = r.left + (r.width / 2) - (ttRect.width / 2);
            }

            // if it would run off the bottom, flip above instead
            if (top + ttRect.height > vh - 12 && placement === 'bottom') {
                top = r.top - ttRect.height - 16;
            }
        }

        var margin = 12;
        if (left < margin) { left = margin; }
        if (left + ttRect.width > vw - margin) { left = vw - ttRect.width - margin; }
        if (top < margin) { top = margin; }
        if (top + ttRect.height > vh - margin) { top = vh - ttRect.height - margin; }

        tt.style.top = top + 'px';
        tt.style.left = left + 'px';
        tt.style.visibility = 'visible';
    };

    LuxTour.prototype._reposition = function () {
        var step = this.steps[this.index];
        if (!step) { return; }
        var target = typeof step.element === 'function' ? step.element() : null;
        this._positionSpotlight(target);
        this._positionTooltip(step, target);
    };

    LuxTour.prototype._onKeyDown = function (e) {
        if (e.key === 'Escape') { this.skip(); }
    };

    LuxTour.prototype.next = function () {
        var step = this.steps[this.index];
        if (step && typeof step.after === 'function') { step.after(); }
        this.index++;
        if (this.index >= this.steps.length) { return this.finish(); }
        this._renderStep();
    };

    LuxTour.prototype.prev = function () {
        var step = this.steps[this.index];
        if (step && typeof step.after === 'function') { step.after(); }
        this.index = Math.max(0, this.index - 1);
        this._renderStep();
    };

    LuxTour.prototype.skip = function () {
        this._cleanup();
        this._markSeen();
        this.opts.onSkip();
    };

    LuxTour.prototype.finish = function () {
        this._cleanup();
        this._markSeen();
        this.opts.onFinish();
    };

    LuxTour.prototype._markSeen = function () {
        if (this.opts.storageKey) {
            try { window.localStorage.setItem(this.opts.storageKey, '1'); } catch (e) { /* ignore */ }
        }
    };

    LuxTour.prototype._cleanup = function () {
        document.removeEventListener('keydown', this._onKey);
        window.removeEventListener('resize', this._onResize);
        window.removeEventListener('scroll', this._onResize, true);
        [this.overlayEl, this.spotlightEl, this.tooltipEl].forEach(function (el) {
            if (el && el.parentNode) { el.parentNode.removeChild(el); }
        });
    };

    // ---- "Would you like a guide?" prompt ----
    LuxTour.prompt = function (opts) {
        var wrap = document.createElement('div');
        wrap.className = 'lux-tour-prompt-overlay';

        var box = document.createElement('div');
        box.className = 'lux-tour-prompt-box';
        box.innerHTML =
            '<div class="lux-tour-prompt-icon"><i class="fa-solid fa-compass"></i></div>' +
            '<h3 class="lux-tour-prompt-title"></h3>' +
            '<p class="lux-tour-prompt-text"></p>' +
            '<div class="lux-tour-prompt-actions">' +
                '<button type="button" class="lux-tour-btn lux-tour-prompt-yes"></button>' +
                '<button type="button" class="lux-tour-btn lux-tour-btn-ghost lux-tour-prompt-no"></button>' +
            '</div>';

        box.querySelector('.lux-tour-prompt-title').textContent = opts.title || '';
        box.querySelector('.lux-tour-prompt-text').textContent = opts.text || '';
        box.querySelector('.lux-tour-prompt-yes').textContent = opts.yesLabel || 'Yes, show me';
        box.querySelector('.lux-tour-prompt-no').textContent = opts.noLabel || 'No thanks';

        wrap.appendChild(box);
        document.body.appendChild(wrap);

        box.querySelector('.lux-tour-prompt-yes').onclick = function () {
            wrap.remove();
            if (typeof opts.onAccept === 'function') { opts.onAccept(); }
        };
        box.querySelector('.lux-tour-prompt-no').onclick = function () {
            wrap.remove();
            if (typeof opts.onDecline === 'function') { opts.onDecline(); }
        };

        return wrap;
    };

    // ---- small floating "?" button to relaunch a tour anytime ----
    LuxTour.addHelpButton = function (onClick, label) {
        if (document.querySelector('.lux-tour-help-btn')) { return; } // don't duplicate
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lux-tour-help-btn';
        btn.setAttribute('aria-label', label || 'Show me around');
        btn.innerHTML = '<i class="fa-solid fa-question"></i>';
        btn.onclick = onClick;
        document.body.appendChild(btn);
        return btn;
    };

    window.LuxTour = LuxTour;

})(window, document);
