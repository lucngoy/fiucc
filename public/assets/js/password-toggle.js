(function () {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var wrapper = button.closest('.relative');
            var input = wrapper ? wrapper.querySelector('input') : null;
            var showIcon = button.querySelector('[data-icon-show]');
            var hideIcon = button.querySelector('[data-icon-hide]');

            if (!input || !showIcon || !hideIcon) {
                return;
            }

            var labelShow = button.dataset.labelShow || 'Show password';
            var labelHide = button.dataset.labelHide || 'Hide password';

            var willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            showIcon.classList.toggle('hidden', willShow);
            hideIcon.classList.toggle('hidden', !willShow);
            button.setAttribute('aria-label', willShow ? labelHide : labelShow);
            button.setAttribute('aria-pressed', willShow ? 'true' : 'false');
        });
    });
})();
