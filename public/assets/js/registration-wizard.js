document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('registration-form');

    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    var indicators = Array.prototype.slice.call(document.querySelectorAll('[data-step-indicator]'));
    var stepIndicator = document.getElementById('step-indicator');
    var progressFill = document.getElementById('step-progress');
    var current = 0;

    function show(index) {
        steps.forEach(function (step, i) {
            step.hidden = i !== index;
        });

        indicators.forEach(function (indicator, i) {
            var reached = i <= index;
            indicator.classList.toggle('bg-primary', reached);
            indicator.classList.toggle('text-white', reached);
            indicator.classList.toggle('border-primary', reached);
            indicator.classList.toggle('bg-surface', !reached);
            indicator.classList.toggle('text-muted', !reached);
        });

        // Ligne reliant les cercles : le remplissage suit la même
        // progression que les cercles ci-dessus (0% à l'étape 1, 100% à la
        // dernière), pas un calcul séparé à resynchroniser.
        if (progressFill && steps.length > 1) {
            progressFill.style.width = ((index / (steps.length - 1)) * 100) + '%';
        }

        current = index;

        // Pas de scroll vers l'étape 1 : au premier chargement (le seul
        // moyen d'y être, "Précédent" n'existe pas avant l'étape 2),
        // l'utilisateur est déjà en haut du formulaire — un scroll y serait
        // un mouvement parasite, pas un repère utile.
        //
        // Pas de scroll non plus en lg+ (>= 1024px, seuil Tailwind "lg" —
        // même utilisé pour la carte d'information à gauche du formulaire) :
        // à cette largeur le formulaire tient dans une colonne à côté de la
        // carte, sans dépasser assez pour justifier un repositionnement.
        if (index === 0 || window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }

        // On vise l'indicateur d'étapes (au-dessus du formulaire), pas le
        // contenu de l'étape elle-même : sinon les numéros 1/2/3/4 sortent
        // de l'écran par le haut et on perd le repère visuel de progression.
        // Décalage calculé à la main, pas scroll-margin-top + scrollIntoView :
        // ce dernier ignore scroll-margin-top en smooth scroll sur certains
        // navigateurs (Safari). getBoundingClientRect() force un reflow,
        // donc la position est à jour même juste après avoir démasqué l'étape.
        // Hauteur du header lue en direct (pas une valeur fixe) : opérationnel
        // sur petits écrans aussi, où le menu mobile déplié change sa hauteur
        // réelle.
        var target = stepIndicator || steps[index];
        var header = document.querySelector('header');
        var headerOffset = (header ? header.offsetHeight : 72) + 16;
        var top = target.getBoundingClientRect().top + window.pageYOffset - headerOffset;
        window.scrollTo({ top: top, behavior: 'smooth' });
    }

    // Ne valide que les champs requis de l'étape courante — pas tout le
    // formulaire, sinon un champ requis d'une étape cachée bloquerait la
    // navigation vers une étape qui n'en a pas besoin.
    function validateStep(index) {
        var fields = steps[index].querySelectorAll('[required]');

        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].reportValidity()) {
                return false;
            }
        }

        return true;
    }

    form.querySelectorAll('[data-next]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (validateStep(current) && current < steps.length - 1) {
                show(current + 1);
            }
        });
    });

    form.querySelectorAll('[data-prev]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (current > 0) {
                show(current - 1);
            }
        });
    });

    // Après un échec de validation serveur (FIUCC-32), le formulaire est
    // réaffiché rempli avec les erreurs — on saute directement à la première
    // étape qui en contient une, plutôt que de repartir de l'étape 1 à
    // chaque fois (l'utilisateur devrait sinon re-cliquer "Next" plusieurs
    // fois pour voir ce qui a été refusé).
    function firstErrorStepIndex() {
        var errorField = form.querySelector('[role="alert"]');

        if (!errorField) {
            return 0;
        }

        var stepEl = errorField.closest('[data-step]');
        var index = steps.indexOf(stepEl);

        return index === -1 ? 0 : index;
    }

    show(firstErrorStepIndex());
});
