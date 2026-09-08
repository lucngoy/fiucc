(function () {
    // Compte à rebours d'une élection (FIUCC-62) — amélioration progressive :
    // le serveur rend déjà "00" partout (voir resources/views/elections/
    // show.php), ce script calcule la vraie valeur au premier tick puis
    // toutes les secondes. data-countdown-target est un timestamp Unix
    // (secondes) déjà calculé côté serveur dans le fuseau app.timezone —
    // comparé ici à Date.now() (millisecondes UTC), aucune conversion de
    // fuseau nécessaire côté client puisque les deux sont des instants
    // absolus.
    var containers = document.querySelectorAll('[data-countdown-target]');

    containers.forEach(function (container) {
        var targetMs = parseInt(container.dataset.countdownTarget, 10) * 1000;
        var daysEl = container.querySelector('[data-countdown-days]');
        var hoursEl = container.querySelector('[data-countdown-hours]');
        var minutesEl = container.querySelector('[data-countdown-minutes]');
        var secondsEl = container.querySelector('[data-countdown-seconds]');

        function pad(value) {
            return String(value).padStart(2, '0');
        }

        function tick() {
            var remainingMs = Math.max(0, targetMs - Date.now());
            var totalSeconds = Math.floor(remainingMs / 1000);

            if (daysEl) daysEl.textContent = pad(Math.floor(totalSeconds / 86400));
            if (hoursEl) hoursEl.textContent = pad(Math.floor((totalSeconds % 86400) / 3600));
            if (minutesEl) minutesEl.textContent = pad(Math.floor((totalSeconds % 3600) / 60));
            if (secondsEl) secondsEl.textContent = pad(totalSeconds % 60);
        }

        tick();
        setInterval(tick, 1000);
    });
})();
