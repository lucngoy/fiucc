(function () {
    var container = document.getElementById('government-filters');

    if (!container) {
        return;
    }

    var chips = container.querySelectorAll('[data-filter-chip]');
    var cards = document.querySelectorAll('[data-faculty]');

    var activeClasses = ['bg-primary', 'text-white'];
    var inactiveClasses = ['bg-surface', 'text-muted'];

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) {
                c.classList.remove.apply(c.classList, activeClasses);
                c.classList.add.apply(c.classList, inactiveClasses);
            });
            chip.classList.remove.apply(chip.classList, inactiveClasses);
            chip.classList.add.apply(chip.classList, activeClasses);

            var filter = chip.dataset.filterChip;

            cards.forEach(function (card) {
                var match = filter === 'all' || card.dataset.faculty === filter;
                card.classList.toggle('hidden', !match);
            });
        });
    });
})();
