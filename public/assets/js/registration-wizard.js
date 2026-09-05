document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('registration-form');

    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    var indicators = Array.prototype.slice.call(document.querySelectorAll('[data-step-indicator]'));
    var stepIndicator = document.getElementById('step-indicator');
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

        current = index;

        // On vise l'indicateur d'étapes (au-dessus du formulaire), pas le
        // contenu de l'étape elle-même : sinon les numéros 1/2/3/4 sortent
        // de l'écran par le haut et on perd le repère visuel de progression.
        // Décalage calculé à la main, pas scroll-margin-top + scrollIntoView :
        // ce dernier ignore scroll-margin-top en smooth scroll sur certains
        // navigateurs (Safari). getBoundingClientRect() force un reflow,
        // donc la position est à jour même juste après avoir démasqué l'étape.
        var target = stepIndicator || steps[index];
        var headerOffset = 88;
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

    show(0);
});
