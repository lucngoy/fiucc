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

    // Modaux <dialog> à la place de window.confirm()/window.alert() : un
    // dialogue natif du navigateur ne suit pas la charte graphique et coupe
    // le flux visuel de la page (cf. reason-dialog-* ci-dessus, même
    // mécanique). Le bouton d'origine n'est jamais physiquement dans
    // bulkForm (attribut form="..."), donc le confirmer relance sa
    // soumission via requestSubmit(submitter) — préserve son formaction.
    var confirmDialog = document.getElementById('bulk-confirm-dialog');
    var confirmMessage = document.getElementById('bulk-confirm-message');
    var confirmSubmit = document.getElementById('bulk-confirm-submit');
    var pendingButton = null;

    if (confirmSubmit) {
        confirmSubmit.addEventListener('click', function () {
            if (confirmDialog) {
                confirmDialog.close();
            }

            if (pendingButton) {
                bulkForm.requestSubmit(pendingButton);
                pendingButton = null;
            }
        });
    }

    // Rejeter/suspendre en masse : un clic ouvre directement ce dialogue,
    // titre/action/couleur réécrits pour l'action cliquée — pas de champ à
    // remplir au préalable dans la barre, pas de second dialogue d'erreur
    // si on avait oublié (demande explicite : saisir la raison directement
    // dans la fenêtre qui s'ouvre). "required" n'est posé qu'à l'ouverture
    // et retiré à la fermeture : le textarea vit hors du <form> $bulkFormId
    // (lié via form="...") et resterait "required" même dialogue fermé/
    // invisible sinon, ce qui bloquerait aussi la validation en masse (elle
    // n'a pas besoin de raison) en la laissant associée au même form.
    var reasonDialog = document.getElementById('bulk-reason-dialog');
    var reasonDialogTitle = document.getElementById('bulk-reason-dialog-title');
    var reasonDialogTextarea = document.getElementById('bulk-reason-dialog-textarea');
    var reasonDialogConfirm = document.getElementById('bulk-reason-dialog-confirm');

    if (reasonDialog && reasonDialogTextarea) {
        reasonDialog.addEventListener('close', function () {
            reasonDialogTextarea.required = false;
        });
    }

    bulkButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            var count = selectedCount();

            if (count === 0) {
                event.preventDefault();
                return;
            }

            if (button.dataset.openReasonDialog === '1') {
                event.preventDefault();

                if (!reasonDialog || !reasonDialogTextarea || !reasonDialogConfirm || !reasonDialogTitle) {
                    return;
                }

                reasonDialogTitle.textContent = (button.dataset.reasonDialogTitleTemplate || '').replace('__COUNT__', String(count));
                reasonDialogConfirm.setAttribute('formaction', button.dataset.reasonDialogAction || '');
                reasonDialogConfirm.textContent = button.dataset.reasonDialogConfirmLabel || '';
                reasonDialogConfirm.className = 'h-9 px-4 rounded-control text-white text-label-md font-label-md transition-colors '
                    + (button.dataset.reasonDialogConfirmClass || '');
                reasonDialogTextarea.value = '';
                reasonDialogTextarea.required = true;

                reasonDialog.showModal();
                reasonDialogTextarea.focus();

                return;
            }

            var message = (button.dataset.confirmTemplate || '').replace('__COUNT__', String(count));

            if (message === '') {
                return;
            }

            event.preventDefault();

            if (confirmDialog && confirmMessage && typeof confirmDialog.showModal === 'function') {
                confirmMessage.textContent = message;
                pendingButton = button;
                confirmDialog.showModal();
            }
        });
    });

    refresh();
})();
