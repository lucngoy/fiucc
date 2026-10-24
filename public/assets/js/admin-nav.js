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

    // Menu hamburger (mobile, < md) : déplie/replie la nav de la barre
    // latérale. À partir de md, la nav est toujours visible (md:flex) et
    // le bouton masqué — les classes posées ici n'y changent rien.
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('admin-nav-toggle');
        var nav = document.getElementById('admin-nav');

        if (!toggle || !nav) {
            return;
        }

        function setOpen(open) {
            nav.classList.toggle('flex', open);
            nav.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.querySelector('[data-icon-open]').classList.toggle('hidden', open);
            toggle.querySelector('[data-icon-close]').classList.toggle('hidden', !open);
        }

        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                toggle.focus();
            }
        });
    });
})();
