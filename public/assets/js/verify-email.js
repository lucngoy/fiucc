(function () {
    var button = document.getElementById('resend-button');

    if (!button) {
        return;
    }

    var remaining = parseInt(button.dataset.wait || '0', 10);

    if (remaining <= 0) {
        return;
    }

    var labelResend = button.dataset.labelResend || 'Resend code';
    var labelCountdown = button.dataset.labelCountdown || 'Resend code (%ds)';

    button.disabled = true;

    var render = function () {
        button.textContent = labelCountdown.replace('%d', String(remaining));
    };
    render();

    var interval = setInterval(function () {
        remaining -= 1;

        if (remaining <= 0) {
            clearInterval(interval);
            button.disabled = false;
            button.textContent = labelResend;
            return;
        }

        render();
    }, 1000);
})();
