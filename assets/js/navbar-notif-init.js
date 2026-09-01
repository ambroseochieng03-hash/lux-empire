(function () {

    document.addEventListener('DOMContentLoaded', () => {

        var bell = document.getElementById('luxNotifBell');

        if (!bell) {
            return;
        }

        bell.addEventListener('click', function () {
            window.location.href = this.dataset.notifLink;
        });
    });

})();
