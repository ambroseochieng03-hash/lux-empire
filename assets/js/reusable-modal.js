/*
=========================================
LUX EMPIRE — REUSABLE MODAL
=========================================
Minimal generic modal shell: pass HTML, get it shown in an
overlay/box, with close-on-overlay-click and a close() you can call
programmatically. Not tied to any one feature — the truck edit
modal (and anything else later) builds its own inner HTML and hands
it to this.
=========================================
*/

(function () {

    let modalEl = null;

    function ensureModalEl() {

        if (modalEl) return modalEl;

        modalEl = document.createElement('div');
        modalEl.id = 'luxReusableModal';
        modalEl.style.cssText = `
            display:none; position:fixed; inset:0; z-index:2100;
            align-items:center; justify-content:center; padding:20px;
        `;

        modalEl.innerHTML = `
            <div id="luxReusableModalOverlay" style="position:absolute; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px);"></div>
            <div id="luxReusableModalBox" style="position:relative; max-width:520px; width:100%; max-height:85vh; overflow-y:auto; background:rgba(15,15,20,0.97); border:1px solid rgba(212,175,55,0.3); border-radius:22px; padding:28px;"></div>
        `;

        document.body.appendChild(modalEl);

        modalEl.querySelector('#luxReusableModalOverlay').addEventListener('click', close);

        return modalEl;
    }

    function open(innerHtml) {
        const el = ensureModalEl();
        el.querySelector('#luxReusableModalBox').innerHTML = innerHtml;
        el.style.display = 'flex';
    }

    function close() {
        if (modalEl) modalEl.style.display = 'none';
    }

    function box() {
        return ensureModalEl().querySelector('#luxReusableModalBox');
    }

    window.LuxModal = { open, close, box };

})();
