document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('nav-toggle');
    var menu = document.getElementById('mobile-nav');

    if (!toggle || !menu) {
        return;
    }

    toggle.addEventListener('click', function () {
        var isOpen = menu.classList.contains('flex');

        menu.classList.toggle('flex', !isOpen);
        menu.classList.toggle('hidden', isOpen);
        toggle.setAttribute('aria-expanded', String(!isOpen));
    });
});
