/*
=========================================
LUX EMPIRE — ROLE SELECTION MODAL
=========================================
Opens/closes #roleSelectModal. Any element with
[data-open-role-select] opens it; any element inside the modal
with [data-role-select-close] closes it.
=========================================
*/

(function () {

    document.addEventListener('DOMContentLoaded', () => {

        const modal = document.getElementById('roleSelectModal');

        if (!modal) {
            return;
        }

        function openModal(event) {
            if (event) {
                event.preventDefault();
            }
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.addEventListener('click', (event) => {

            if (event.target.closest('[data-open-role-select]')) {
                openModal(event);
                return;
            }

            if (event.target.closest('[data-role-select-close]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    });

})();
