/*
=========================================
LUX EMPIRE — SHARED INFO MODAL
=========================================
One modal (#infoModal), content swapped from the matching
<template id="infoModalTemplate-{key}"> whenever an element with
[data-open-info-modal="{key}"] is clicked.
=========================================
*/

(function () {

    document.addEventListener('DOMContentLoaded', () => {

        var modal = document.getElementById('infoModal');
        var contentEl = document.getElementById('infoModalContent');

        if (!modal || !contentEl) {
            return;
        }

        function openModal(key) {

            var template = document.getElementById('infoModalTemplate-' + key);

            if (!template) {
                return;
            }

            contentEl.innerHTML = '';
            contentEl.appendChild(template.content.cloneNode(true));

            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.addEventListener('click', (event) => {

            var trigger = event.target.closest('[data-open-info-modal]');

            if (trigger) {
                event.preventDefault();
                openModal(trigger.dataset.openInfoModal);
                return;
            }

            if (event.target.closest('[data-info-modal-close]')) {
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
