(function () {
    var select = document.getElementById('government-filter');

    if (!select) {
        return;
    }

    var cards = document.querySelectorAll('[data-faculty]');

    select.addEventListener('change', function () {
        var filter = select.value;

        cards.forEach(function (card) {
            var match = filter === 'all' || card.dataset.faculty === filter;
            card.classList.toggle('hidden', !match);
        });
    });
})();
