/**
 * Ordre des photos d'un album par glisser-déposer (admin/gallery/form.php).
 *
 * Aucune requête ici : chaque déplacement réécrit les champs cachés
 * orders[id] = position (0, 1, 2...) et le numéro affiché sur la vignette ;
 * l'ordre est enregistré avec le bouton "Enregistrer les modifications",
 * comme les légendes et suppressions (Admin\GalleryController::managePhotos).
 *
 * Glisser-déposer HTML5 à la souris ; les flèches de chaque carte servent
 * au clavier et au tactile, où le drag HTML5 n'existe pas.
 */
(function () {
    'use strict';

    var list = document.querySelector('[data-gallery-sort]');
    if (!list) {
        return;
    }

    var dragged = null;

    function items() {
        return Array.prototype.slice.call(list.querySelectorAll('[data-photo]'));
    }

    function renumber() {
        items().forEach(function (item, index) {
            item.querySelector('[data-order]').value = String(index);
            item.querySelector('[data-position]').textContent = String(index + 1);
        });
    }

    list.addEventListener('dragstart', function (event) {
        var item = event.target.closest('[data-photo]');
        // Ne pas démarrer un drag depuis la légende : on doit pouvoir y
        // sélectionner du texte à la souris.
        if (!item || event.target.closest('input, button, label')) {
            event.preventDefault();
            return;
        }
        dragged = item;
        event.dataTransfer.effectAllowed = 'move';
        // Firefox exige des données pour lancer le drag.
        event.dataTransfer.setData('text/plain', '');
        window.requestAnimationFrame(function () {
            item.classList.add('opacity-40');
        });
    });

    list.addEventListener('dragover', function (event) {
        if (!dragged) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        var target = event.target.closest('[data-photo]');
        if (!target || target === dragged) {
            return;
        }

        // Colonnes masonry : l'ordre se lit de haut en bas dans chaque
        // colonne, donc avant/après selon la moitié verticale de la carte
        // survolée.
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
        if (dragged) {
            dragged.classList.remove('opacity-40');
            dragged = null;
            renumber();
        }
    });

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-move]');
        if (!button) {
            return;
        }
        var item = button.closest('[data-photo]');
        var all = items();
        var index = all.indexOf(item);
        var targetIndex = index + parseInt(button.getAttribute('data-move'), 10);

        if (targetIndex < 0 || targetIndex >= all.length) {
            return;
        }

        if (targetIndex < index) {
            list.insertBefore(item, all[targetIndex]);
        } else {
            list.insertBefore(item, all[targetIndex].nextSibling);
        }
        renumber();
        button.focus();
    });
})();
