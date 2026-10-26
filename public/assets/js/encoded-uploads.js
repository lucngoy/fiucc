(function () {
    // Envoi des fichiers encodés en base64 dans un formulaire classique
    // (application/x-www-form-urlencoded) — voir App\Core\EncodedUploads.
    // Le pare-feu d'InfinityFree (403 « openresty », 2026-10-21) bloque
    // tout envoi multipart/form-data contenant un fichier, mais laisse
    // passer ce format. Sans JavaScript, le formulaire part comme avant.
    //
    // Les grandes photos JPEG/WebP sont réduites ici avant l'envoi : le
    // base64 pèse un tiers de plus que le fichier, et le serveur les
    // redimensionne de toute façon (App\Services\Upload). Les PNG (logos,
    // transparence) et les PDF partent tels quels.
    var FIELD = '__encoded_files[]';
    var MAX_DIMENSION = 2560;
    var RESIZE_ABOVE_BYTES = 1.5 * 1024 * 1024;

    function readAsBase64(blob) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(String(reader.result).split(',')[1] || ''); };
            reader.onerror = function () { reject(reader.error); };
            reader.readAsDataURL(blob);
        });
    }

    function shrinkImage(file) {
        if ((file.type !== 'image/jpeg' && file.type !== 'image/webp') || typeof createImageBitmap !== 'function') {
            return Promise.resolve(file);
        }

        return createImageBitmap(file).then(function (bitmap) {
            var scale = Math.min(1, MAX_DIMENSION / Math.max(bitmap.width, bitmap.height));

            if (scale === 1 && file.size <= RESIZE_ABOVE_BYTES) {
                return file;
            }

            var canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

            return new Promise(function (resolve) {
                canvas.toBlob(function (blob) {
                    // Réduction plus lourde que l'original : on garde l'original.
                    resolve(blob && blob.size < file.size ? blob : file);
                }, file.type, 0.9);
            });
        }).catch(function () {
            return file;
        });
    }

    function encodeFile(input, file) {
        return shrinkImage(file).then(readAsBase64).then(function (data) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = FIELD;
            hidden.value = JSON.stringify({ field: input.name, name: file.name, data: data });
            hidden.dataset.encodedUpload = '1';
            return hidden;
        });
    }

    // Pour les envois faits par script (admin-gallery-upload.js) : la
    // valeur d'un champ FIELD, à poster telle quelle.
    window.EncodedUploads = {
        field: FIELD,
        encode: function (fieldName, file) {
            return shrinkImage(file).then(readAsBase64).then(function (data) {
                return JSON.stringify({ field: fieldName, name: file.name, data: data });
            });
        }
    };

    function restore(form) {
        form.querySelectorAll('input[data-encoded-upload]').forEach(function (hidden) { hidden.remove(); });
        form.querySelectorAll('input[data-encoded-disabled]').forEach(function (input) {
            input.disabled = false;
            delete input.dataset.encodedDisabled;
        });
        form.enctype = 'multipart/form-data';
        delete form.dataset.encodedReady;
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (event.defaultPrevented || !(form instanceof HTMLFormElement)) {
            return;
        }

        // Second passage : fichiers déjà encodés, l'envoi suit son cours.
        if (form.dataset.encodedReady === '1') {
            return;
        }

        if (form.enctype !== 'multipart/form-data') {
            return;
        }

        var inputs = Array.prototype.filter.call(form.querySelectorAll('input[type="file"]'), function (input) {
            return !input.disabled && input.name;
        });
        var jobs = [];

        inputs.forEach(function (input) {
            Array.prototype.forEach.call(input.files || [], function (file) {
                jobs.push(encodeFile(input, file));
            });
        });

        // Le champ fichier lui-même n'est jamais envoyé : ni vide, ni plein.
        inputs.forEach(function (input) {
            input.disabled = true;
            input.dataset.encodedDisabled = '1';
        });
        form.enctype = 'application/x-www-form-urlencoded';
        form.dataset.encodedReady = '1';

        if (jobs.length === 0) {
            return;
        }

        event.preventDefault();
        var submitter = event.submitter || null;
        var buttons = form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]');
        buttons.forEach(function (button) { button.setAttribute('aria-busy', 'true'); });

        Promise.all(jobs).then(function (hiddenInputs) {
            hiddenInputs.forEach(function (hidden) { form.appendChild(hidden); });
            buttons.forEach(function (button) { button.removeAttribute('aria-busy'); });

            if (submitter && typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter);
            } else if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }).catch(function () {
            // Lecture impossible : envoi classique, comme sans ce script.
            buttons.forEach(function (button) { button.removeAttribute('aria-busy'); });
            restore(form);
            form.dataset.encodedReady = '1';
            form.submit();
        });
    });

    // Retour arrière (cache du navigateur) : formulaire remis dans son état d'origine.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-encoded-ready]').forEach(restore);
    });
})();
