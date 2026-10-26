(function () {
    // Visionneuse plein écran (FIUCC-53) — chaque vignette reste un vrai
    // <a href="..." target="_blank"> (fonctionne sans JS) ; ce script
    // intercepte le clic pour ouvrir #lightbox à la place quand il tourne.
    var dialog = document.getElementById('lightbox');
    var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox-trigger]'));

    if (!dialog || triggers.length === 0) {
        return;
    }

    var image = document.getElementById('lightbox-image');
    var caption = document.getElementById('lightbox-caption');
    var counter = document.getElementById('lightbox-counter');
    var loader = document.getElementById('lightbox-loading');
    var loaderTimer = null;
    var current = 0;

    function show(index) {
        // Modulo positif : boucle des deux côtés (dernière photo →
        // Suivant ramène à la première, et inversement) plutôt que de
        // désactiver les flèches en bout de liste — plus agréable sur une
        // galerie qu'on parcourt dans un sens ou l'autre sans réfléchir.
        current = ((index % triggers.length) + triggers.length) % triggers.length;

        var trigger = triggers[current];
        var text = trigger.dataset.caption || '';

        // image_path (data-full), jamais la miniature de la grille — seul
        // endroit où la pleine résolution a du sens (voir gallery/show.php).
        // Le navigateur garde l'ancienne image affichée tant que la
        // nouvelle charge : elle est masquée jusqu'au « load », sinon la
        // photo ouverte précédemment apparaît d'abord.
        var full = new URL(trigger.dataset.full, window.location.href).href;

        if (image.src !== full) {
            setLoading(true);
            image.src = full;
        }

        if (image.complete && image.naturalWidth > 0) {
            setLoading(false);
        }

        image.alt = text;
        caption.textContent = text;
        caption.hidden = text === '';
        counter.textContent = (current + 1) + ' / ' + triggers.length;
    }

    // Indicateur de chargement : affiché seulement si la photo tarde
    // (150 ms), pour ne pas clignoter quand elle est déjà en cache.
    function setLoading(on) {
        image.style.visibility = on ? 'hidden' : '';
        window.clearTimeout(loaderTimer);

        if (!loader) {
            return;
        }

        if (on) {
            loaderTimer = window.setTimeout(function () { loader.classList.remove('hidden'); }, 150);
        } else {
            loader.classList.add('hidden');
        }
    }

    // Photos voisines chargées d'avance, une fois la photo affichée
    // (pour ne pas lui prendre de bande passante) : Suivant/Précédent
    // s'affichent sans attente.
    function preload(index) {
        var trigger = triggers[((index % triggers.length) + triggers.length) % triggers.length];
        new Image().src = trigger.dataset.full;
    }

    // Seule l'image demandée en dernier se révèle : un « load » tardif
    // d'une photo déjà quittée ne compte pas (image.src a changé depuis).
    image.addEventListener('load', function () {
        setLoading(false);
        preload(current + 1);
        preload(current - 1);
    });

    image.addEventListener('error', function () {
        setLoading(false);
    });

    triggers.forEach(function (trigger, index) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            show(index);
            dialog.showModal();
        });
    });

    document.getElementById('lightbox-prev').addEventListener('click', function () {
        show(current - 1);
    });

    document.getElementById('lightbox-next').addEventListener('click', function () {
        show(current + 1);
    });

    document.getElementById('lightbox-close').addEventListener('click', function () {
        dialog.close();
    });

    // Clic en dehors de la boîte (sur le ::backdrop) ferme — même mécanique
    // que admin-dialogs.js : le <dialog> se dimensionne à son contenu, pas
    // à tout l'écran, donc un clic hors de son rectangle est bien un clic
    // "en dehors", pas juste un espace vide à l'intérieur.
    dialog.addEventListener('click', function (event) {
        var rect = dialog.getBoundingClientRect();
        var inside = event.clientX >= rect.left && event.clientX <= rect.right
            && event.clientY >= rect.top && event.clientY <= rect.bottom;

        if (!inside) {
            dialog.close();
        }
    });

    // Flèches clavier — Échap est géré nativement par <dialog>.showModal(),
    // rien à faire pour ça ici.
    //
    // Piège au clavier explicite pour Tab/Maj+Tab (FIUCC-76, testé au
    // clavier réel) : le confinement natif de <dialog> laisse le focus
    // atterrir brièvement sur <body> avant de reboucler dans la boîte
    // (constaté avec Playwright, pas supposé) — un lecteur d'écran y
    // annoncerait un focus sans rien à lire. Boucle donc explicitement
    // entre le premier et le dernier contrôle plutôt que de compter
    // uniquement sur le comportement natif du navigateur.
    dialog.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft') {
            show(current - 1);
            return;
        }

        if (event.key === 'ArrowRight') {
            show(current + 1);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        var focusable = Array.prototype.slice.call(
            dialog.querySelectorAll('button, a[href]')
        );

        if (focusable.length === 0) {
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    // Balayage tactile (mobile) — seuil en pixels pour ne pas confondre un
    // tap (qui ne doit rien faire) avec une intention de glisser.
    var touchStartX = null;
    var SWIPE_THRESHOLD = 40;

    dialog.addEventListener('touchstart', function (event) {
        touchStartX = event.touches[0].clientX;
    }, { passive: true });

    dialog.addEventListener('touchend', function (event) {
        if (touchStartX === null) {
            return;
        }

        var delta = event.changedTouches[0].clientX - touchStartX;
        touchStartX = null;

        if (delta > SWIPE_THRESHOLD) {
            show(current - 1);
        } else if (delta < -SWIPE_THRESHOLD) {
            show(current + 1);
        }
    }, { passive: true });
})();
