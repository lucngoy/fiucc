(function () {
    // Fermeture du bandeau cookies (partials/cookie_notice.php) sans
    // recharger la page : même cookie que CookieController::dismiss().
    var NAME = 'fiucc_cookie_notice';
    var MAX_AGE = 365 * 24 * 60 * 60;

    var notice = document.querySelector('[data-cookie-notice]');

    if (!notice) {
        return;
    }

    notice.querySelector('form').addEventListener('submit', function (event) {
        event.preventDefault();
        document.cookie = NAME + '=1; Max-Age=' + MAX_AGE + '; Path=/; SameSite=Lax'
            + (window.location.protocol === 'https:' ? '; Secure' : '');
        notice.remove();
    });
})();
