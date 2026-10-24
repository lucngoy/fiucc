/**
 * Ordre des postes d'un mandat par glisser-déposer (admin/government/index.php).
 *
 * Au lâcher, si l'ordre a changé, envoie le formulaire caché avec l'ordre
 * complet (ids[]) — Admin\GovernmentController::reorderMembers, qui recharge
 * la page. Même principe qu'un clic sur les flèches : un déplacement, un
 * enregistrement.
 *
 * Glisser-déposer HTML5 à la souris ; les flèches de chaque ligne restent
 * pour le clavier et le tactile, où le drag HTML5 n'existe pas.
 */
(function () {
    'use strict';

    var list = document.querySelector('[data-government-sort]');
    var form = document.querySelector('[data-government-sort-form]');
    if (!list || !form) {
        return;
    }

    var dragged = null;
    var initialOrder = '';

    function rows() {
        return Array.prototype.slice.call(list.querySelectorAll('[data-member-id]'));
    }

    function order() {
        return rows().map(function (row) {
            return row.getAttribute('data-member-id');
        });
    }

    list.addEventListener('dragstart', function (event) {
        var row = event.target.closest('[data-member-id]');
        // Pas de drag depuis un bouton, un lien ou une boîte de dialogue.
        if (!row || event.target.closest('a, button, input, dialog')) {
            event.preventDefault();
            return;
        }
        dragged = row;
        initialOrder = order().join(',');
        event.dataTransfer.effectAllowed = 'move';
        // Firefox exige des données pour lancer le drag.
        event.dataTransfer.setData('text/plain', '');
        window.requestAnimationFrame(function () {
            row.classList.add('opacity-40');
        });
    });

    list.addEventListener('dragover', function (event) {
        if (!dragged) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        var target = event.target.closest('[data-member-id]');
        if (!target || target === dragged) {
            return;
        }

        var rect = target.getBoundingClientRect();
        var after = event.clientY > rect.top + rect.height / 2;
        list.insertBefore(dragged, after ? target.nextSibling : target);
    });

    list.addEventListener('drop', function (event) {
        if (dragged) {
            event.preventDefault();
        }
    });

    list.addEventListener('dragend', function () {
        if (!dragged) {
            return;
        }
        dragged.classList.remove('opacity-40');
        dragged = null;

        var ids = order();
        if (ids.join(',') === initialOrder) {
            return;
        }

        rows().forEach(function (row, index) {
            row.querySelector('[data-position]').textContent = String(index + 1);
        });
        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
        list.classList.add('opacity-60', 'pointer-events-none');
        form.submit();
    });
})();
