(function () {
    // Menu profil (barre du haut, layouts/admin.php) : <details> natif, pas
    // de fermeture au clic en dehors par défaut — on la reproduit ici, pour
    // toutes les pages admin (pas seulement /admin/members).
    document.addEventListener('click', function (event) {
        document.querySelectorAll('details[open]').forEach(function (details) {
            if (!details.contains(event.target)) {
                details.removeAttribute('open');
            }
        });
    });
})();
