document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('nav-toggle');
    var menu = document.getElementById('mobile-nav');

    if (!toggle || !menu) {
        return;
    }

    toggle.addEventListener('click', function () {
        var isHidden = menu.hasAttribute('hidden');

        if (isHidden) {
            menu.removeAttribute('hidden');
        } else {
            menu.setAttribute('hidden', '');
        }

        toggle.setAttribute('aria-expanded', String(isHidden));
    });
});
