(function () {
    var button = document.getElementById('resend-button');

    if (!button) {
        return;
    }

    var remaining = parseInt(button.dataset.wait || '0', 10);

    if (remaining <= 0) {
        return;
    }

    button.disabled = true;

    var render = function () {
        button.textContent = 'Resend code (' + remaining + 's)';
    };
    render();

    var interval = setInterval(function () {
        remaining -= 1;

        if (remaining <= 0) {
            clearInterval(interval);
            button.disabled = false;
            button.textContent = 'Resend code';
            return;
        }

        render();
    }, 1000);
})();
