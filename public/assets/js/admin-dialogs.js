(function () {
    // Ouverture/fermeture générique des <dialog> admin (data-open-dialog/
    // data-close-dialog) — même mécanique que admin-members.js, extraite ici
    // pour les pages qui n'ont pas besoin de sa logique de sélection en
    // masse (admin/events).
    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-open-dialog]');

        if (opener) {
            var dialog = document.getElementById(opener.dataset.openDialog);

            if (dialog && typeof dialog.showModal === 'function') {
                dialog.showModal();
            }

            return;
        }

        var closer = event.target.closest('[data-close-dialog]');

        if (closer) {
            var openDialog = closer.closest('dialog');

            if (openDialog) {
                openDialog.close();
            }
        }
    });

    document.querySelectorAll('dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            var rect = dialog.getBoundingClientRect();
            var inside = event.clientX >= rect.left && event.clientX <= rect.right
                && event.clientY >= rect.top && event.clientY <= rect.bottom;

            if (!inside) {
                dialog.close();
            }
        });
    });
})();
