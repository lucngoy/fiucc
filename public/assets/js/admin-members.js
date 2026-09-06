(function () {
    // Raison de rejet/suspension par ligne : <dialog> plutôt qu'un panneau
    // en position absolute — un panneau absolu à l'intérieur du conteneur
    // overflow-x-auto de la table se fait couper (le CSS force overflow-y
    // à auto dès qu'overflow-x l'est), invisible ou inutilisable selon la
    // ligne. <dialog> s'affiche dans le top layer, jamais rogné par un
    // ancêtre, et ferme au clic en dehors — natif via showModal().
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

    var selectAll = document.getElementById('member-select-all');
    var rowCheckboxes = document.querySelectorAll('.member-row-checkbox');
    var countLabel = document.getElementById('bulk-selected-count');
    var bulkButtons = document.querySelectorAll('[data-bulk-action]');
    var reasonField = document.getElementById('bulk-reason');
    var bulkForm = document.getElementById('members-bulk-form');

    if (!bulkForm || rowCheckboxes.length === 0) {
        return;
    }

    function selectedCount() {
        var count = 0;

        rowCheckboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                count += 1;
            }
        });

        return count;
    }

    // Le vrai garde-fou contre une action groupée mal ciblée est côté
    // serveur (Admin\MemberController — filtre par statut source attendu).
    // Ici : juste rendre la sélection visible avant l'envoi (compteur,
    // boutons désactivés à zéro sélection, confirmation explicite avec le
    // nombre concerné) — une commodité, pas la protection elle-même.
    function refresh() {
        var count = selectedCount();

        if (countLabel && countLabel.dataset.template) {
            countLabel.textContent = countLabel.dataset.template.replace('__COUNT__', String(count));
        }

        bulkButtons.forEach(function (button) {
            button.disabled = count === 0;

            if (button.dataset.labelTemplate) {
                button.textContent = button.dataset.labelTemplate.replace('__COUNT__', String(count));
            }
        });

        if (selectAll) {
            selectAll.checked = count > 0 && count === rowCheckboxes.length;
            selectAll.indeterminate = count > 0 && count < rowCheckboxes.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            refresh();
        });
    }

    rowCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', refresh);
    });

    bulkButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            var count = selectedCount();

            if (count === 0) {
                event.preventDefault();
                return;
            }

            if (button.dataset.requiresReason === '1' && reasonField && reasonField.value.trim() === '') {
                event.preventDefault();
                window.alert(window.__bulkReasonMissingMessage || '');
                reasonField.focus();
                return;
            }

            var message = (button.dataset.confirmTemplate || '').replace('__COUNT__', String(count));

            if (message !== '' && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    refresh();
})();
