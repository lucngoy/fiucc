(function () {
    // Déclenche l'impression native du navigateur (FIUCC-72) — c'est elle,
    // pas une bibliothèque PDF côté serveur, qui produit le document : voir
    // resources/views/admin/elections/report.php pour le raisonnement.
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-print-trigger]');

        if (trigger) {
            window.print();
        }
    });
})();
