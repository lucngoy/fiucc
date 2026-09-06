(function () {
    var button = document.getElementById('journey-toggle');

    if (!button) {
        return;
    }

    var entries = document.querySelectorAll('[data-journey-more]');
    var expanded = false;

    button.addEventListener('click', function () {
        expanded = !expanded;

        entries.forEach(function (entry) {
            // Les entrées repliées portent la classe Tailwind "hidden", pas
            // l'attribut natif hidden — ne jamais mélanger les deux sur le
            // même élément (déjà rencontré et corrigé une fois, FIUCC-22).
            entry.classList.toggle('hidden', !expanded);
        });

        button.textContent = expanded
            ? (button.dataset.labelLess || 'Show less')
            : (button.dataset.labelMore || 'Show more');
    });
})();
