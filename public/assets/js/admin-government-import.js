(function () {
    // Aperçu d'import du comité : choisir un autre profil reprend son nom
    // dans « Nom affiché », « aucun profil » revient au nom du fichier —
    // seulement tant que l'admin n'a pas retouché le nom lui-même.
    document.querySelectorAll('[data-import-member]').forEach(function (select) {
        var row = select.closest('[data-import-row]');
        var nameInput = row ? row.querySelector('[data-import-name]') : null;

        if (!nameInput) {
            return;
        }

        var lastSuggested = nameInput.value;

        select.addEventListener('change', function () {
            if (nameInput.value !== lastSuggested) {
                return;
            }

            var option = select.options[select.selectedIndex];
            lastSuggested = option && option.dataset.name ? option.dataset.name : nameInput.dataset.sourceName;
            nameInput.value = lastSuggested;
        });
    });
})();
