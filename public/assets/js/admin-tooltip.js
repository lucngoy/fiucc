(function () {
    // Les info-bulles d'action (colonne Actions des tableaux admin — voir
    // $actionTooltip dans chaque vue) sont en "position: absolute" à
    // l'intérieur de conteneurs "overflow-hidden"/"overflow-x-auto" (carte
    // arrondie + défilement horizontal du tableau) : un absolute reste
    // toujours coupé par l'overflow d'un ancêtre, quel que soit son
    // z-index — la pile d'empilement ne contourne jamais le clipping.
    //
    // Amélioration progressive : la version PHP (absolute, ancrée au bouton
    // via group-hover) reste le repli si ce script ne tourne pas (JS
    // désactivé) — coupée par l'overflow, mais fonctionnelle. Ici, chaque
    // info-bulle est détachée vers la fin de <body> et repositionnée en
    // "fixed" (jamais soumis à l'overflow d'un ancêtre), avec l'affichage/
    // positionnement recalculé à la main puisque group-hover ne s'applique
    // plus une fois l'info-bulle sortie du DOM du bouton.
    var tooltips = document.querySelectorAll('[role="tooltip"]');

    tooltips.forEach(function (tooltip) {
        var trigger = tooltip.closest('.group');

        if (!trigger) {
            return;
        }

        // "position" et "opacity" en styles inline, pas en classes Tailwind
        // ("fixed"/"opacity-100") : ces classes n'apparaissant nulle part en
        // dur dans les vues PHP scannées par Tailwind (tailwind.config.js,
        // content:), un build les purgerait du CSS compilé — un style en
        // ligne, lui, n'a besoin d'aucune classe déjà générée.
        tooltip.classList.remove('absolute', 'right-0', 'top-full', 'mt-1.5', 'group-hover:opacity-100', 'z-10', 'opacity-0');
        tooltip.classList.add('z-50');
        tooltip.style.position = 'fixed';
        tooltip.style.opacity = '0';
        document.body.appendChild(tooltip);

        function position() {
            var rect = trigger.getBoundingClientRect();
            tooltip.style.top = (rect.bottom + 6) + 'px';
            tooltip.style.right = (window.innerWidth - rect.right) + 'px';
        }

        function show() {
            position();
            tooltip.style.opacity = '1';
        }

        function hide() {
            tooltip.style.opacity = '0';
        }

        trigger.addEventListener('mouseenter', show);
        trigger.addEventListener('mouseleave', hide);
        trigger.addEventListener('focusin', show);
        trigger.addEventListener('focusout', hide);

        // Le tableau défile horizontalement (overflow-x-auto) sans que la
        // fenêtre ne bouge : une info-bulle affichée doit disparaître plutôt
        // que rester figée à une position qui ne correspond plus au bouton.
        window.addEventListener('scroll', hide, true);
    });
})();
