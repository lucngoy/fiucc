(function () {
    // Ajout de photos à un album (admin/gallery/form.php) : aperçus
    // retirables avant l'envoi, puis une requête par photo. Une photo en
    // échec (fichier refusé, connexion coupée, session expirée) reste dans
    // la file avec sa raison, et le bouton la renvoie sans la resélectionner.
    // Une fois la file vide, la page se recharge sur l'album.
    //
    // Les fichiers partent encodés en base64 (encoded-uploads.js), comme
    // les autres formulaires : le pare-feu de l'hébergeur bloque le
    // multipart. Sans JavaScript, le formulaire part en un seul envoi.
    var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var PARALLEL_UPLOADS = 2;

    var form = document.querySelector('form[data-gallery-upload]');

    if (!form || typeof window.fetch !== 'function' || !('content' in document.createElement('template'))) {
        return;
    }

    var input = form.querySelector('input[type="file"]');
    var submit = form.querySelector('[data-upload-submit]');
    var summary = form.querySelector('[data-upload-summary]');
    var list = form.querySelector('[data-upload-queue]');
    var template = form.querySelector('template[data-upload-item]');
    var csrf = form.querySelector('input[name="_csrf_token"]');
    var labels = form.dataset;
    var queue = [];
    var sending = false;
    var attempted = false;

    function keyOf(file) {
        return [file.name, file.size].join('|');
    }

    function pending() {
        return queue.filter(function (item) { return item.status === 'waiting' || item.status === 'failed'; });
    }

    // Une photo au type refusé ne repart pas : elle échouerait pareil.
    function sendable() {
        return pending().filter(function (item) { return !item.invalid; });
    }

    function add(files) {
        Array.prototype.forEach.call(files, function (file) {
            var key = keyOf(file);

            if (queue.some(function (item) { return item.key === key; })) {
                return;
            }

            var item = { key: key, file: file, status: 'waiting', reason: '', url: '' };

            if (ALLOWED_TYPES.indexOf(file.type) === -1) {
                item.status = 'failed';
                item.invalid = true;
                item.reason = labels.labelInvalidType;
            } else {
                item.url = URL.createObjectURL(file);
            }

            item.node = build(item);
            list.appendChild(item.node);
            queue.push(item);
        });

        // Le champ est vidé : la file garde les fichiers, et resélectionner
        // la même photo plus tard redéclenche bien « change ».
        input.value = '';
        refresh();
    }

    function build(item) {
        var node = template.content.firstElementChild.cloneNode(true);
        var preview = node.querySelector('[data-item-preview]');
        var remove = node.querySelector('[data-item-remove]');

        if (item.url) {
            preview.src = item.url;
        } else {
            preview.remove();
        }

        node.querySelector('[data-item-name]').textContent = item.file.name;
        remove.setAttribute('aria-label', labels.labelRemove.replace(':name', item.file.name));
        remove.title = remove.getAttribute('aria-label');
        remove.addEventListener('click', function () {
            if (item.url) {
                URL.revokeObjectURL(item.url);
            }
            item.node.remove();
            queue.splice(queue.indexOf(item), 1);

            // Dernière photo en échec retirée : les autres sont dans
            // l'album, la page se recharge pour les montrer.
            if (pending().length === 0 && queue.some(function (other) { return other.status === 'done'; })) {
                finish();
                return;
            }

            refresh();
        });

        return node;
    }

    function paint(item) {
        var labelKey = 'label' + item.status.charAt(0).toUpperCase() + item.status.slice(1);

        item.node.querySelectorAll('[data-item-status]').forEach(function (badge) {
            var active = badge.dataset.itemStatus === item.status;
            badge.classList.toggle('hidden', !active);

            if (active) {
                (badge.querySelector('[data-item-status-text]') || badge).textContent = labels[labelKey];
            }
        });

        var reason = item.node.querySelector('[data-item-reason]');
        reason.textContent = item.reason;
        reason.classList.toggle('hidden', item.status !== 'failed' || item.reason === '');
        item.node.querySelector('[data-item-remove]').disabled = sending || item.status === 'done';
    }

    function refresh() {
        queue.forEach(paint);

        var toSend = sendable();
        // « Photos en échec gardées » seulement après un envoi : un fichier
        // refusé dès la sélection n'est pas un envoi raté.
        var keptAfterSending = attempted && !sending && pending().length > 0;

        list.classList.toggle('hidden', queue.length === 0);
        summary.classList.toggle('hidden', toSend.length === 0 && !keptAfterSending);
        summary.textContent = keptAfterSending
            ? labels.labelKept
            : labels.labelCount.replace(':count', String(toSend.length));
        submit.textContent = keptAfterSending && toSend.length > 0 ? labels.labelRetry : labels.labelSubmit;
        submit.disabled = sending || (toSend.length === 0 && input.files.length === 0);
        input.disabled = sending;
    }

    // Fichier encodé en base64 (encoded-uploads.js) : le pare-feu de
    // l'hébergeur bloque le multipart. Si ce script manque (ancienne
    // version en cache), envoi multipart classique plutôt que rien : en
    // cas de refus, la photo passe en échec et reste dans la file.
    function buildBody(file) {
        if (window.EncodedUploads && typeof window.EncodedUploads.encode === 'function') {
            return window.EncodedUploads.encode(input.name, file).then(function (encoded) {
                var body = new URLSearchParams();
                body.append(csrf.name, csrf.value);
                body.append(window.EncodedUploads.field, encoded);
                return body;
            });
        }

        var data = new FormData();
        data.append(csrf.name, csrf.value);
        data.append(input.name, file, file.name);
        return Promise.resolve(data);
    }

    function send(item) {
        item.status = 'sending';
        item.reason = '';
        paint(item);

        return buildBody(item.file).then(function (body) {
            return fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                body: body
            });
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (result) {
            if (result.succeeded > 0) {
                // L'aperçu reste affiché : l'URL n'est pas libérée.
                item.status = 'done';
            } else {
                item.status = 'failed';
                item.reason = (result.failures && result.failures[0] && result.failures[0].reason) || labels.labelNetwork;
            }
        }).catch(function () {
            // Connexion coupée, 403 du pare-feu ou de la session, réponse
            // illisible : la photo reste en file pour un nouvel essai.
            item.status = 'failed';
            item.reason = labels.labelNetwork;
        }).then(function () {
            paint(item);
        });
    }

    function sendAll() {
        var items = sendable();

        if (items.length === 0) {
            return;
        }

        sending = true;
        attempted = true;
        refresh();

        var next = 0;
        function worker() {
            if (next >= items.length) {
                return Promise.resolve();
            }
            return send(items[next++]).then(worker);
        }

        var workers = [];
        for (var i = 0; i < Math.min(PARALLEL_UPLOADS, items.length); i++) {
            workers.push(worker());
        }

        Promise.all(workers).then(function () {
            sending = false;

            if (pending().length === 0) {
                finish();
                return;
            }

            refresh();
        });
    }

    function finish() {
        window.removeEventListener('beforeunload', warnBeforeLeaving);
        window.location.href = labels.redirect;
    }

    function warnBeforeLeaving(event) {
        if (sending || pending().length > 0) {
            event.preventDefault();
            event.returnValue = '';
        }
    }

    input.addEventListener('change', function () {
        add(input.files || []);
    });

    // Écouteur sur le formulaire, déclenché avant celui d'encoded-uploads.js
    // (posé sur document) : l'envoi groupé n'a jamais lieu avec ce script.
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (input.files.length > 0) {
            add(input.files);
        }

        if (!sending) {
            sendAll();
        }
    });

    // Le champ natif annoncerait « aucun fichier » alors que la file en
    // contient : il est remplacé par le bouton qui l'ouvre.
    var picker = form.querySelector('[data-upload-picker]');
    if (picker) {
        input.classList.add('sr-only');
        picker.classList.remove('hidden');
    }

    window.addEventListener('beforeunload', warnBeforeLeaving);
    refresh();
})();
