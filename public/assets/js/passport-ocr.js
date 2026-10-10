/**
 * Pré-remplissage du recensement depuis le scan du passeport (migration 018).
 *
 * Tout se passe dans le navigateur : Tesseract.js et son modèle MRZ
 * (auto-hébergés sous /assets/vendor/tesseract/, chargés seulement quand un
 * fichier est choisi)
 * lit la zone MRZ — les deux lignes de 44 caractères en bas de la page
 * d'identité (format ICAO 9303, TD3). L'image n'est envoyée à aucun service
 * tiers ; elle ne part au serveur qu'avec le formulaire, comme pièce jointe.
 *
 * La MRZ porte des chiffres de contrôle : numéro, date de naissance et date
 * d'expiration ne sont remplis que si leur chiffre de contrôle est juste —
 * une lecture OCR douteuse laisse le champ vide plutôt que de le remplir
 * faux. Les noms n'ont pas de contrôle : remplis, mais à vérifier.
 * Seuls les champs encore vides sont remplis, jamais une saisie existante.
 *
 * Libellés lus depuis les attributs data-* du conteneur (CLAUDE.md : toute
 * chaîne visible passe par t(), y compris celles utilisées par du JS).
 */
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-passport-ocr]');

    if (!root) {
        return;
    }

    var form = root.closest('form');
    var input = root.querySelector('input[type="file"]');
    var dropzone = root.querySelector('[data-dropzone]');
    var status = root.querySelector('[data-ocr-status]');
    var preview = root.querySelector('[data-ocr-preview]');
    var fileName = root.querySelector('[data-ocr-filename]');
    var spinner = root.querySelector('[data-ocr-spinner]');
    var spinnerLabel = root.querySelector('[data-ocr-spinner-label]');
    var maxBytes = parseInt(root.getAttribute('data-max-bytes'), 10) || 8 * 1024 * 1024;
    var vendor = '/assets/vendor/tesseract';
    var workerPromise = null;
    var runId = 0;

    function label(name, count) {
        var text = root.getAttribute('data-label-' + name) || '';

        return count === undefined ? text : text.replace(':count', String(count));
    }

    function setStatus(text, tone) {
        status.textContent = text;
        status.classList.remove('text-muted', 'text-danger', 'text-success');
        status.classList.add(tone === 'error' ? 'text-danger' : (tone === 'success' ? 'text-success' : 'text-muted'));
    }

    // Spinner par-dessus la zone de dépôt pendant l'analyse (2 à 7 s) :
    // sans lui, rien ne bouge à l'écran et le membre croit que rien ne se
    // passe. aria-busy pour les lecteurs d'écran (le texte du statut, en
    // aria-live, annonce déjà l'étape en cours).
    function setBusy(text) {
        var busy = Boolean(text);

        // Classes, pas l'attribut hidden : la classe "flex" du conteneur
        // l'emporterait sur [hidden] et le spinner ne disparaîtrait jamais.
        if (spinner) {
            spinner.classList.toggle('hidden', !busy);
            spinner.classList.toggle('flex', busy);
            spinnerLabel.textContent = busy ? text : '';
        }

        dropzone.setAttribute('aria-busy', busy ? 'true' : 'false');

        if (busy) {
            setStatus(text);
        }
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            if (window.Tesseract) {
                resolve();
                return;
            }

            var script = document.createElement('script');
            script.src = src;
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('load')); };
            document.head.appendChild(script);
        });
    }

    // Un worker par modèle pour toute la page : chaque modèle n'est
    // téléchargé qu'une fois, puis mis en cache (IndexedDB) par Tesseract.js.
    //
    // - "mrz" (DoubangoTelecom/tesseractMRZ, BSD-3, 1,4 Mo) : réseau LSTM
    //   entraîné uniquement sur des zones MRZ (police OCR-B, alphabet
    //   A-Z 0-9 <). Mesuré sur 9 photos de test : 8 lues sans erreur, contre
    //   2 avec le modèle anglais générique, qui confond "<" avec C/K/L.
    // - "eng" (tessdata, Apache-2.0, 5 Mo) : texte imprimé ordinaire, pour
    //   la seule information absente de la MRZ — le lieu de naissance.
    //   Chargé seulement si la MRZ a été lue et que le champ est vide.
    var workers = {};
    var WORKER_PARAMETERS = {
        mrz: { tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<', tessedit_pageseg_mode: '6' },
        eng: { tessedit_pageseg_mode: '11' }
    };

    function getWorker(lang) {
        if (!workers[lang]) {
            workers[lang] = loadScript(vendor + '/tesseract.min.js').then(function () {
                return window.Tesseract.createWorker(lang, 1, {
                    workerPath: vendor + '/worker.min.js',
                    corePath: vendor + '/core',
                    langPath: vendor + '/lang',
                    gzip: false,
                    // URL directe plutôt qu'un blob: — la CSP du site
                    // n'autorise pas blob: pour les workers.
                    workerBlobURL: false
                });
            }).then(function (worker) {
                return worker.setParameters(WORKER_PARAMETERS[lang]).then(function () { return worker; });
            });

            workers[lang].catch(function () { delete workers[lang]; });
        }

        return workers[lang];
    }

    function readAsDataUrl(file) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.onerror = function () { reject(reader.error); };
            reader.readAsDataURL(file);
        });
    }

    function loadImage(src) {
        return new Promise(function (resolve, reject) {
            var image = new Image();
            image.onload = function () { resolve(image); };
            image.onerror = function () { reject(new Error('image')); };
            image.src = src;
        });
    }

    // Image tournée (photo prise de travers) et mise à l'échelle, sans
    // autre traitement. crop (facultatif) : rectangle en pixels du repère
    // tourné à l'échelle 1, pour relire la seule zone MRZ en grand.
    function rotated(image, rotation, scale, crop) {
        var drawWidth = image.naturalWidth * scale;
        var drawHeight = image.naturalHeight * scale;
        var sideways = rotation === 90 || rotation === 270;
        var fullWidth = sideways ? drawHeight : drawWidth;
        var fullHeight = sideways ? drawWidth : drawHeight;
        var area = crop
            ? { x: crop.x * scale, y: crop.y * scale, width: crop.width * scale, height: crop.height * scale }
            : { x: 0, y: 0, width: fullWidth, height: fullHeight };

        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(area.width));
        canvas.height = Math.max(1, Math.round(area.height));

        var context = canvas.getContext('2d', { willReadFrequently: true });
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.translate(fullWidth / 2 - area.x, fullHeight / 2 - area.y);
        context.rotate(rotation * Math.PI / 180);
        context.drawImage(image, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);

        return canvas;
    }

    // Binarisation par seuil ADAPTATIF (Bradley) : chaque pixel est comparé
    // à la moyenne de son voisinage, pas à un seuil global. Une photo de
    // téléphone a des ombres, des reflets et un fond guilloché — un seuil
    // global noircit la moitié ombrée ou efface la moitié claire.
    // Valeur de gris d'un pixel. channel "red" : canal rouge seul — sur les
    // passeports congolais, libellés bleus et texte noir y sont foncés,
    // alors que le fond rose/crème guilloché y devient clair (constaté sur
    // un ancien modèle où la luminance classique noyait les libellés).
    function pixelValue(pixels, offset, channel) {
        return channel === 'red'
            ? pixels[offset]
            : (pixels[offset] * 299 + pixels[offset + 1] * 587 + pixels[offset + 2] * 114) / 1000 | 0;
    }

    function binarize(canvas, channel) {
        var width = canvas.width;
        var height = canvas.height;
        var context = canvas.getContext('2d', { willReadFrequently: true });
        var imageData = context.getImageData(0, 0, width, height);
        var pixels = imageData.data;
        var gray = new Uint8Array(width * height);
        var integral = new Uint32Array((width + 1) * (height + 1));
        var x;
        var y;

        for (y = 0; y < height; y++) {
            var rowSum = 0;

            for (x = 0; x < width; x++) {
                var offset = (y * width + x) * 4;
                var value = pixelValue(pixels, offset, channel);
                gray[y * width + x] = value;
                rowSum += value;
                integral[(y + 1) * (width + 1) + x + 1] = integral[y * (width + 1) + x + 1] + rowSum;
            }
        }

        var half = Math.max(8, Math.round(Math.max(width, height) / 32));

        for (y = 0; y < height; y++) {
            var y1 = Math.max(0, y - half);
            var y2 = Math.min(height - 1, y + half);

            for (x = 0; x < width; x++) {
                var x1 = Math.max(0, x - half);
                var x2 = Math.min(width - 1, x + half);
                var count = (x2 - x1 + 1) * (y2 - y1 + 1);
                var sum = integral[(y2 + 1) * (width + 1) + x2 + 1] - integral[y1 * (width + 1) + x2 + 1]
                    - integral[(y2 + 1) * (width + 1) + x1] + integral[y1 * (width + 1) + x1];
                var out = gray[y * width + x] * count <= sum * 0.85 ? 0 : 255;
                var index = (y * width + x) * 4;
                pixels[index] = pixels[index + 1] = pixels[index + 2] = out;
            }
        }

        context.putImageData(imageData, 0, 0);

        return canvas;
    }

    // Variante en simples niveaux de gris : sur une image floue agrandie, le
    // seuillage interne de Tesseract fait parfois mieux que le nôtre — les
    // deux sont lues, la fusion garde ce que chacune a validé.
    function grayscale(canvas, channel) {
        var context = canvas.getContext('2d', { willReadFrequently: true });
        var imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        var pixels = imageData.data;

        for (var i = 0; i < pixels.length; i += 4) {
            pixels[i] = pixels[i + 1] = pixels[i + 2] = pixelValue(pixels, i, channel);
        }

        context.putImageData(imageData, 0, 0);

        return canvas;
    }

    // Échelle de la première passe : côté le plus long ramené à 1600 px.
    // Elle ne sert qu'à LOCALISER la MRZ (la relecture se fait ensuite sur
    // la zone recadrée depuis la photo d'origine) — sur une photo de
    // téléphone réelle (12 Mpx, livret entier), 2400 px coûtaient 7 à 10 s.
    function overviewScale(image) {
        return 1600 / Math.max(image.naturalWidth, image.naturalHeight);
    }

    // Zone MRZ repérée dans la première passe : les lignes longues pleines
    // de "<" (même mal lues, elles en gardent quelques-uns), élargie d'une
    // marge, en pixels du repère tourné à l'échelle 1.
    function mrzRegion(lines, scale) {
        var chevrons = function (line) { return (cleanLine(line.text).match(/</g) || []).length; };
        var candidates = (lines || []).filter(function (line) {
            return cleanLine(line.text).length >= 25 && chevrons(line) >= 3;
        });

        if (!candidates.length) {
            return null;
        }

        // Le fond guilloché d'une vraie page produit aussi des lignes avec
        // quelques "<" : on part de la ligne qui en a le plus (la ligne 1 de
        // la MRZ, pleine de remplissage), et on n'y ajoute que sa voisine
        // immédiate — jamais une ligne parasite plus loin sur la page.
        var strongest = candidates.reduce(function (best, line) {
            return chevrons(line) > chevrons(best) ? line : best;
        });
        var height = strongest.bbox.y1 - strongest.bbox.y0;
        var centerOf = function (line) { return (line.bbox.y0 + line.bbox.y1) / 2; };
        var neighbour = candidates.filter(function (line) {
            return line !== strongest && Math.abs(centerOf(line) - centerOf(strongest)) < height * 2.5;
        }).sort(function (a, b) {
            return Math.abs(centerOf(a) - centerOf(strongest)) - Math.abs(centerOf(b) - centerOf(strongest));
        })[0];
        var hits = neighbour ? [strongest, neighbour] : [strongest];

        var x0 = Math.min.apply(null, hits.map(function (line) { return line.bbox.x0; }));
        var x1 = Math.max.apply(null, hits.map(function (line) { return line.bbox.x1; }));
        var y0 = Math.min.apply(null, hits.map(function (line) { return line.bbox.y0; }));
        var y1 = Math.max.apply(null, hits.map(function (line) { return line.bbox.y1; }));
        var lineHeight = (y1 - y0) / hits.length;
        var marginX = (x1 - x0) * 0.04;

        // Deux lignes attendues : si une seule a été repérée, on élargit
        // vers le haut ET le bas pour être sûr d'inclure l'autre.
        var extra = hits.length === 1 ? lineHeight * 1.6 : lineHeight * 0.6;

        // Pente des lignes (ligne de base fournie par Tesseract) : sur une
        // photo inclinée de quelques degrés, la zone recadrée est redressée
        // avant relecture — 44 caractères penchés débordent sur la ligne voisine.
        var angles = hits.filter(function (line) {
            return line.baseline && line.baseline.x1 > line.baseline.x0;
        }).map(function (line) {
            return Math.atan2(line.baseline.y1 - line.baseline.y0, line.baseline.x1 - line.baseline.x0);
        });
        var skew = angles.length ? angles.reduce(function (a, b) { return a + b; }, 0) / angles.length : 0;

        // Une zone penchée est plus haute : marge verticale élargie d'autant.
        extra += Math.abs(Math.sin(skew)) * (x1 - x0) / 2;

        return {
            x: Math.max(0, (x0 - marginX) / scale),
            y: Math.max(0, (y0 - extra) / scale),
            width: (x1 - x0 + 2 * marginX) / scale,
            height: (y1 - y0 + 2 * extra) / scale,
            skew: skew,
            // Ligne 1 de la MRZ (la plus riche en "<") : haut et largeur
            // exacts, sans marge — sert à situer la page d'identité au-dessus.
            chevrons: chevrons(strongest),
            lineTop: Math.min(y0, strongest.bbox.y0) / scale,
            lineLeft: strongest.bbox.x0 / scale,
            lineWidth: (strongest.bbox.x1 - strongest.bbox.x0) / scale
        };
    }

    function deskew(canvas, angle) {
        if (Math.abs(angle) < 0.005) {
            return canvas;
        }

        var straight = document.createElement('canvas');
        straight.width = canvas.width;
        straight.height = canvas.height;

        var context = straight.getContext('2d', { willReadFrequently: true });
        context.fillStyle = '#fff';
        context.fillRect(0, 0, straight.width, straight.height);
        context.translate(straight.width / 2, straight.height / 2);
        context.rotate(-angle);
        context.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);

        return straight;
    }

    // --- Lecture de la MRZ (ICAO 9303, TD3 : 2 × 44 caractères) ----------

    var DIGIT_FIXES = { O: '0', Q: '0', D: '0', U: '0', I: '1', L: '1', T: '1', Z: '2', S: '5', B: '8', G: '6' };

    function asDigits(value) {
        return value.replace(/[A-Z]/g, function (char) { return DIGIT_FIXES[char] || char; });
    }

    // Inverse, pour les zones qui ne contiennent que des lettres (codes pays).
    var LETTER_FIXES = { 0: 'O', 1: 'I', 2: 'Z', 5: 'S', 6: 'G', 8: 'B' };

    function asLetters(value) {
        return value.replace(/[0-9]/g, function (char) { return LETTER_FIXES[char] || char; });
    }

    // Numéro de passeport : lettres et chiffres mêlés, donc aucune correction
    // automatique sûre a priori ("0P0123456" peut être lu pour "OP0123456").
    // On essaie toutes les variantes lettre/chiffre des caractères ambigus,
    // et on ne retient une correction que si elle passe le chiffre de
    // contrôle ET qu'elle est la seule dans ce cas au même nombre de
    // corrections — préférant la forme habituelle (lettres puis chiffres).
    // Plusieurs corrections possibles à égalité : rien, plutôt qu'un numéro faux.
    var SWAPS = { 0: 'O', O: '0', 1: 'I', I: '1', 2: 'Z', Z: '2', 5: 'S', S: '5', 6: 'G', G: '6', 8: 'B', B: '8', D: '0', Q: '0' };

    function passportNumber(raw, check) {
        var ambiguous = [];

        for (var i = 0; i < raw.length; i++) {
            if (SWAPS[raw.charAt(i)]) {
                ambiguous.push(i);
            }
        }

        ambiguous = ambiguous.slice(0, 8);

        var best = null;
        var tie = false;

        for (var mask = 0; mask < (1 << ambiguous.length); mask++) {
            var chars = raw.split('');
            var changes = 0;

            ambiguous.forEach(function (position, bit) {
                if (mask & (1 << bit)) {
                    chars[position] = SWAPS[chars[position]];
                    changes++;
                }
            });

            var candidate = chars.join('');

            if (checkDigit(candidate) !== check) {
                continue;
            }

            // Coût : nombre de corrections, plus une pénalité si la forme
            // n'est pas "lettres puis chiffres" (OP0123456, U12345678...).
            var cost = changes * 2 + (/^[A-Z]{0,3}[0-9]+<*$/.test(candidate) ? 0 : 1);

            if (!best || cost < best.cost) {
                best = { cost: cost, value: candidate };
                tie = false;
            } else if (cost === best.cost) {
                tie = true;
            }
        }

        return best && !tie ? best.value.replace(/<+$/, '') : null;
    }

    function checkDigit(value) {
        var weights = [7, 3, 1];
        var sum = 0;

        for (var i = 0; i < value.length; i++) {
            var char = value.charAt(i);
            var number = char === '<' ? 0 : (/[0-9]/.test(char) ? parseInt(char, 10) : char.charCodeAt(0) - 55);
            sum += number * weights[i % 3];
        }

        return String(sum % 10);
    }

    function cleanLine(line) {
        return line.toUpperCase().replace(/[«‹]/g, '<').replace(/[^A-Z0-9<]/g, '');
    }

    function padLine(line) {
        return (line + '<'.repeat(44)).slice(0, 44);
    }

    // Ligne 2 candidate : date de naissance et date d'expiration valides avec
    // leur chiffre de contrôle, sexe M/F/<. C'est la seule partie de la MRZ
    // qui se vérifie elle-même — on la cherche partout dans le texte lu, à
    // n'importe quel décalage (bord du passeport ou ombre lus comme des
    // caractères parasites en tête de ligne).
    function line2Score(candidate) {
        var birthOk = checkDigit(asDigits(candidate.slice(13, 19))) === asDigits(candidate.charAt(19))
            && mrzDate(candidate.slice(13, 19), false) !== null;
        var expiryOk = checkDigit(asDigits(candidate.slice(21, 27))) === asDigits(candidate.charAt(27))
            && mrzDate(candidate.slice(21, 27), true) !== null;
        var sexOk = /[MF<]/.test(candidate.charAt(20));
        // Nationalité (positions 10-12) : 3 lettres, au plus un chiffre mal
        // lu. Un décalage (caractères perdus en tête de ligne) y place des
        // chiffres de date — c'est ce qui empêche de prendre la date
        // d'expiration pour la date de naissance.
        var nationality = candidate.slice(10, 13);
        var nationalityOk = /^[A-Z<]{3}$/.test(asLetters(nationality)) && (nationality.match(/[0-9]/g) || []).length <= 1;

        return {
            both: birthOk && expiryOk,
            one: (birthOk || expiryOk) && sexOk && nationalityOk && candidate.indexOf('<<') !== -1,
            score: (birthOk ? 2 : 0) + (expiryOk ? 2 : 0) + (sexOk ? 1 : 0) + (nationalityOk ? 1 : 0)
        };
    }

    // Noms de la ligne 1 : après le code du pays émetteur, qui est presque
    // toujours la nationalité lue en ligne 2 (positions 10-12) — un ancrage
    // plus fiable qu'un "P<" exact en début de ligne.
    function namesFromLine1(line, nationality) {
        // Code pays toujours en lettres : "C0D" lu pour "COD" est ramené en
        // lettres des deux côtés avant comparaison.
        nationality = asLetters(nationality);
        var anchor = asLetters(line.slice(0, 9)).indexOf(nationality);

        if (nationality.indexOf('<') === -1 && anchor >= 1 && anchor <= 6) {
            return line.slice(anchor + 3);
        }

        var start = line.search(/P[A-Z<]/);

        return start !== -1 && start <= 4 ? line.slice(start + 5) : '';
    }

    function findMrz(text) {
        var lines = text.split('\n').map(cleanLine);
        var best = null;

        lines.forEach(function (line, index) {
            for (var offset = 0; offset + 28 <= line.length; offset++) {
                var candidate = line.slice(offset);
                var result = line2Score(candidate);
                // Les deux dates justes, ou une seule avec sexe, nationalité
                // et remplissage "<<" plausibles : jamais une ligne de texte
                // quelconque qui passerait un contrôle par hasard.
                if ((result.both || result.one) && (!best || result.score > best.score)) {
                    best = { score: result.score, index: index, line2: padLine(candidate) };
                }
            }
        });

        if (!best) {
            return null;
        }

        var names = '';

        for (var i = best.index - 1; i >= Math.max(0, best.index - 2); i--) {
            if (lines[i].length >= 15 && lines[i].indexOf('<') !== -1) {
                names = namesFromLine1(lines[i], best.line2.slice(10, 13));
                break;
            }
        }

        return { names: names, line2: best.line2 };
    }

    function mrzDate(value, isExpiry) {
        var digits = asDigits(value);

        if (!/^\d{6}$/.test(digits)) {
            return null;
        }

        var year = parseInt(digits.slice(0, 2), 10);
        var month = digits.slice(2, 4);
        var day = digits.slice(4, 6);
        var currentYear = new Date().getFullYear() % 100;
        // Expiration : toujours dans le siècle courant. Naissance : une
        // année "dans le futur" sur deux chiffres appartient au siècle passé.
        var century = isExpiry || year <= currentYear ? 2000 : 1900;
        var iso = (century + year) + '-' + month + '-' + day;
        var parsed = new Date(iso + 'T00:00:00Z');

        return isNaN(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== iso ? null : iso;
    }

    function titleCase(value) {
        return value.toLowerCase().replace(/(^|[\s-])([a-z])/g, function (match, separator, letter) {
            return separator + letter.toUpperCase();
        });
    }

    // Un nom MRZ ne contient jamais de chiffre : un chiffre lu est du bruit,
    // comme une lettre isolée ("GRACE<DIVINE<2" lu pour "GRACE<DIVINE<<").
    function nameParts(value) {
        return value.replace(/<+$/, '').split('<').map(function (part) {
            return part.replace(/[0-9]/g, '');
        }).filter(function (part) {
            return part.length >= 2;
        }).map(titleCase);
    }

    // Le remplissage "<<<<" de fin de ligne est souvent lu "C", "L" ou "K"
    // par l'OCR ("MARIA<CLCLLLL"). On coupe au premier "<" à partir duquel
    // il ne reste plus que ces caractères — jamais une lettre finale seule
    // (un "MICHEL<<<<" garde son L : la coupe commence toujours à un "<").
    function stripFiller(value) {
        for (var i = 0; i < value.length; i++) {
            if (value.charAt(i) === '<' && /^[<CKL]+$/.test(value.slice(i))) {
                return value.slice(0, i);
            }
        }

        // Aucun "<" lu du tout ("DIVINELLKLLLLLL") : une suite de 4 de ces
        // lettres ou plus en fin de ligne n'est pas un nom, c'est le
        // remplissage mal lu.
        return value.replace(/[CKL]{4,}$/, '');
    }

    function parseMrz(mrz) {
        var line2 = mrz.line2;
        var result = {};

        var names = stripFiller(mrz.names).split('<<');
        var surnames = nameParts(names[0] || '');
        var givenNames = nameParts(names.slice(1).join('<'));

        if (surnames.length) {
            result.last_name = surnames[0];

            if (surnames.length > 1) {
                result.middle_name = surnames.slice(1).join(' ');
            }
        }

        if (givenNames.length) {
            result.first_name = givenNames.join(' ');
        }

        // Passeport congolais : la MRZ n'a que deux champs de nom, et le
        // post-nom y est rangé en tête des "prénoms" (NOM<<POSTNOM<PRENOM —
        // ordre constaté sur un passeport réel, 2026-10-09). Si le nom de
        // famille n'a qu'un mot, le premier "prénom" est donc le post-nom.
        // Autres nationalités : plusieurs mots après "<<" sont tous des prénoms.
        if (asLetters(line2.slice(10, 13)) === 'COD' && surnames.length === 1 && givenNames.length >= 2) {
            result.middle_name = givenNames[0];
            result.first_name = givenNames.slice(1).join(' ');
        }

        var number = passportNumber(line2.slice(0, 9), asDigits(line2.charAt(9)));

        if (number) {
            result.passport_number = number;
        }

        var birth = line2.slice(13, 19);

        if (checkDigit(asDigits(birth)) === asDigits(line2.charAt(19))) {
            result.date_of_birth = mrzDate(birth, false);
        }

        var expiry = line2.slice(21, 27);

        if (checkDigit(asDigits(expiry)) === asDigits(line2.charAt(27))) {
            result.passport_expiry = mrzDate(expiry, true);
        }

        var sex = line2.charAt(20);

        if (sex === 'M' || sex === 'F') {
            result.gender = sex === 'M' ? 'male' : 'female';
        }

        result._composite = compositeOk(line2, number);

        return result;
    }

    // Chiffre de contrôle GLOBAL (position 43) : couvre numéro, naissance,
    // expiration et numéro personnel d'un coup. Un chiffre de contrôle
    // isolé laisse passer 1 lecture fausse sur 10 (constaté : "4P0123456"
    // accepté pour "OP0123456") — celui-ci confirme toute la ligne.
    function compositeOk(line2, number) {
        if (!number) {
            return false;
        }

        var personal = line2.slice(28, 42);

        // Numéro personnel vide = remplissage "<" souvent lu K/L/C.
        if (/^[<KLC]+$/.test(personal)) {
            personal = '<'.repeat(14);
        }

        var data = (number + '<<<<<<<<<').slice(0, 9) + asDigits(line2.charAt(9))
            + asDigits(line2.slice(13, 20))
            + asDigits(line2.slice(21, 28))
            + personal + asDigits(line2.charAt(42));

        return checkDigit(data) === asDigits(line2.charAt(43));
    }

    // --- Remplissage du formulaire ---------------------------------------

    function fill(values) {
        var filled = 0;

        Object.keys(values).forEach(function (name) {
            var value = values[name];

            if (!value || name.charAt(0) === '_') {
                return;
            }

            if (name === 'gender') {
                if (!form.querySelector('input[name="gender"]:checked')) {
                    var radio = form.querySelector('input[name="gender"][value="' + value + '"]');

                    if (radio) {
                        radio.checked = true;
                        filled++;
                    }
                }

                return;
            }

            var field = form.elements.namedItem(name);

            if (field && field.value === '') {
                field.value = value;
                field.classList.add('ring-2', 'ring-primary-light/50');
                filled++;
            }
        });

        return filled;
    }

    // Photo droite d'abord ; si rien n'est trouvé, la même photo tournée
    // (passeport photographié en portrait ou à l'envers). Par orientation :
    // une passe sur l'image entière pour repérer la MRZ, puis une relecture
    // de la seule zone MRZ recadrée depuis la photo d'origine et agrandie —
    // c'est elle qui rend lisible une photo compressée (WhatsApp) où les
    // caractères ne font que quelques pixels.
    var ROTATIONS = [0, 90, 270, 180];
    var DEBUG = /[?&]ocr_debug=1/.test(window.location.search);

    function debug(label, text) {
        // Diagnostic local uniquement (?ocr_debug=1) : le texte lu reste
        // dans la console du navigateur de la personne, jamais envoyé.
        if (DEBUG) {
            console.log('[passport-ocr] ' + label + '\n' + text);
        }
    }

    var VERIFIED_FIELDS = ['passport_number', 'date_of_birth', 'passport_expiry'];

    function filledCount(values) {
        return Object.keys(values).filter(function (key) { return values[key]; }).length;
    }

    // Fusion champ par champ de toutes les lectures d'une même orientation :
    // numéro et dates sont validés par leur chiffre de contrôle, donc fiables
    // quelle que soit la passe qui les a lus. Les noms (sans contrôle)
    // viennent de la lecture la plus complète.
    function merge(readings) {
        var parsed = readings.filter(Boolean).map(parseMrz).sort(function (a, b) {
            return filledCount(b) - filledCount(a);
        });
        var merged = {};

        // Numéro et dates : retenus seulement si une lecture les confirme par
        // le chiffre de contrôle global, ou si au moins deux lectures donnent
        // exactement la même valeur. Une coïncidence sur un seul chiffre de
        // contrôle ne suffit plus à remplir le formulaire.
        VERIFIED_FIELDS.forEach(function (key) {
            var counts = {};

            parsed.forEach(function (values) {
                if (!values[key]) {
                    return;
                }

                counts[values[key]] = (counts[values[key]] || 0) + (values._composite ? 2 : 1);
            });

            Object.keys(counts).forEach(function (value) {
                if (counts[value] >= 2 && (!merged[key] || counts[value] > counts[merged[key]])) {
                    merged[key] = value;
                }
            });
        });

        // Sexe : la valeur la plus lue.
        var genders = parsed.map(function (values) { return values.gender; }).filter(Boolean);

        if (genders.length) {
            merged.gender = genders.sort(function (a, b) {
                return genders.filter(function (g) { return g === b; }).length - genders.filter(function (g) { return g === a; }).length;
            })[0];
        }

        // Noms : la version la plus souvent lue à l'identique l'emporte — les
        // lectures se trompent rarement au même endroit ("GRACECDIVINE" une
        // fois, "GRACE<DIVINE" deux fois). À égalité, la lecture la plus
        // complète (premier du tri ci-dessus).
        var votes = {};
        var bestKey = null;

        parsed.forEach(function (values) {
            if (!values.last_name) {
                return;
            }

            var key = JSON.stringify([values.last_name, values.middle_name || '', values.first_name || '']);
            votes[key] = (votes[key] || 0) + 1;

            if (bestKey === null || votes[key] > votes[bestKey]) {
                bestKey = key;
            }
        });

        // Noms seulement si la ligne 2 a été authentifiée : sans numéro ni
        // date confirmés, rien ne prouve que la "ligne 1" lue est la bonne.
        if (bestKey !== null && hasVerifiedField(merged)) {
            var names = JSON.parse(bestKey);
            merged.last_name = names[0];
            merged.middle_name = names[1];
            merged.first_name = names[2];
        }

        return merged;
    }

    function hasVerifiedField(values) {
        return VERIFIED_FIELDS.some(function (key) { return values[key]; });
    }

    // pageSegMode : "11" = texte épars (mots cherchés n'importe où, sans
    // supposer de mise en page) — seul mode qui trouve le texte d'une vraie
    // photo de livret (hologrammes, guilloches, page du haut, perforations) ;
    // "6" = un bloc de lignes, pour relire la zone MRZ déjà recadrée.
    function read(worker, canvas, label, pageSegMode) {
        var ready = pageSegMode ? worker.setParameters({ tessedit_pageseg_mode: pageSegMode }) : Promise.resolve();

        return ready.then(function () {
            return worker.recognize(canvas);
        }).then(function (output) {
            debug(label, output.data.text);

            return output;
        });
    }

    // Une orientation, un mode de localisation : repérage de la MRZ sur
    // l'image entière, puis relecture de la zone recadrée et agrandie.
    function readOrientation(worker, image, rotation, overviewCanvas, scale, pageSegMode) {
        return read(worker, overviewCanvas, 'rotation ' + rotation + ' (psm ' + pageSegMode + ')', pageSegMode).then(function (overview) {
            var readings = [findMrz(overview.data.text)];
            var region = mrzRegion(overview.data.lines, scale);

            if (!region) {
                return readings;
            }

            readings.region = region;

            // Zone MRZ ramenée à ~2200 px de large (×4 maximum).
            var zoom = Math.min(4, 2200 / region.width);

            return read(worker, binarize(deskew(rotated(image, rotation, zoom, region), region.skew)), 'zone MRZ binarisée', '6').then(function (detail) {
                readings.push(findMrz(detail.data.text));

                return read(worker, grayscale(deskew(rotated(image, rotation, zoom, region), region.skew)), 'zone MRZ gris');
            }).then(function (detail) {
                readings.push(findMrz(detail.data.text));

                return readings;
            });
        });
    }

    // Par orientation, deux modes de localisation : texte épars d'abord
    // (1,3-1,6 s sur de vraies photos de livret, là où le mode bloc prenait
    // 3-12 s ou ne trouvait rien), mode bloc ensuite si le premier n'a rien
    // validé (meilleur sur une page seule, sans décor autour). Puis
    // l'orientation suivante.
    var PAGE_SEG_MODES = ['11', '6'];

    function recognize(worker, image, attempt, clearlyFound) {
        attempt = attempt || 0;

        if (attempt >= ROTATIONS.length * PAGE_SEG_MODES.length) {
            return Promise.resolve(null);
        }

        var rotation = ROTATIONS[Math.floor(attempt / PAGE_SEG_MODES.length)];
        var pageSegMode = PAGE_SEG_MODES[attempt % PAGE_SEG_MODES.length];
        var scale = overviewScale(image);

        return readOrientation(worker, image, rotation, binarize(rotated(image, rotation, scale)), scale, pageSegMode).then(function (readings) {
            readings.clearlyFound = clearlyFound || Boolean(readings.region && readings.region.chevrons >= 12);

            var values = merge(readings);

            if (!hasVerifiedField(values)) {
                // MRZ nettement repérée (une ligne pleine de "<") mais rien
                // de validé : l'orientation est la bonne, la photo est
                // illisible (floue, reflet, MRZ masquée). Inutile de tester
                // les autres orientations — abandon en ~8 s au lieu de 25-37 s.
                var lastModeOfOrientation = attempt % PAGE_SEG_MODES.length === PAGE_SEG_MODES.length - 1;

                if (lastModeOfOrientation && readings.clearlyFound) {
                    return null;
                }

                return recognize(worker, image, attempt + 1, readings.clearlyFound);
            }

            values._rotation = rotation;
            values._region = readings.region || null;

            return values;
        });
    }

    // --- Lieu de naissance (zone imprimée, absente de la MRZ) -------------

    // Mots d'autres libellés : une "valeur" qui en contient un est en fait
    // le libellé de la colonne voisine, pas un lieu.
    var LABEL_WORDS = /^(DATE|DE|DU|OF|SEXE|SEX|NATIONALITE|NATIONALITY|AUTORITE|AUTHORITY|DELIVRANCE|ISSUE|EXPIRATION|EXPIRY|PROFESSION|TAILLE|HEIGHT|NOM|SURNAME|PRENOMS?|GIVEN|NAMES?|TYPE|CODE|PASSEPORT|PASSPORT|SIGNATURE|LIEU|PLACE|NAISSANCE|BIRTH)$/;
    var COUNTRY_WORDS = /^(RDC|RD|DRC|COD|CONGO|RCONGO|ZAIRE)$/;
    // Nationalité imprimée juste à côté du lieu sur le modèle récent.
    var NATIONALITY_WORDS = /^(CONGOLAISE?|CONGOLESE)$/;

    function plainWord(text) {
        return text.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Z]/g, '');
    }

    // Mots contigus (écart inférieur à 1,5 hauteur de ligne) à partir du
    // premier : s'arrête avant la colonne suivante.
    function contiguousRun(words, lineHeight) {
        var run = [];

        for (var i = 0; i < words.length; i++) {
            if (run.length && words[i].bbox.x0 - run[run.length - 1].bbox.x1 >= lineHeight * 1.5) {
                break;
            }

            run.push(words[i]);
        }

        return run.map(function (word) { return word.text; }).join(' ');
    }

    function cleanPlace(text) {
        // Traits d'union et apostrophes conservés ("Mbuji-Mayi").
        var tokens = text.split(/[,\/(;:]/)[0].split(/\s+/).map(function (token) {
            return token.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^A-Z'-]/g, '').replace(/^[-']+|[-']+$/g, '');
        }).filter(function (token) {
            return token.length >= 2;
        });

        while (tokens.length && COUNTRY_WORDS.test(plainWord(tokens[tokens.length - 1]))) {
            tokens.pop();
        }

        if (!tokens.length || tokens.some(function (token) {
            var plain = plainWord(token);

            // Libellés collés par l'OCR en un seul "mot" ("Nationalité-+
            // Nationality" lu NATIONALITENATIONALITY) : rejetés aussi.
            return LABEL_WORDS.test(plain) || NATIONALITY_WORDS.test(plain)
                || /NATIONAL|AUTORIT|AUTHORIT|EXPIR|DELIVR|PROFESS|OCCUPAT|PERSONN|SIGNAT/.test(plain);
        })) {
            return null;
        }

        var value = tokens.join(' ');

        return value.length >= 3 && value.length <= 60 ? titleCase(value) : null;
    }

    // Le libellé est repéré par sa partie anglaise, "Place of birth" : sur
    // de vraies photos, "Lieu" est presque toujours mal lu ("Jon du
    // neinsance", "Adeu de naissance", ou absent), alors que "Place of
    // birth" survit ("Pace of birth", "Place of seth"). "...ACE" avant
    // "OF" le distingue de "Date of birth" ("...ATE"/"...ALE").
    function findPlaceLabel(words) {
        for (var k = 1; k < words.length - 1; k++) {
            if (plainWord(words[k].text) !== 'OF') {
                continue;
            }

            var before = plainWord(words[k - 1].text);
            var after = plainWord(words[k + 1].text);

            if (/ACE$/.test(before) && before.length <= 6 && /(TH|RT|IRTH)$|^BIR/.test(after)) {
                return { place: k - 1, end: k + 1 };
            }
        }

        // Repli : "Lieu de naissance" seul, s'il a été bien lu.
        for (var j = 0; j < words.length - 2; j++) {
            if (plainWord(words[j].text) === 'LIEU' && /^NA[A-Z]{5,}/.test(plainWord(words[j + 2].text))) {
                return { place: j, end: j + 2 };
            }
        }

        return null;
    }

    function placeOfBirth(lines) {
        for (var i = 0; i < lines.length; i++) {
            var words = lines[i].words || [];
            var lineHeight = Math.max(1, lines[i].bbox.y1 - lines[i].bbox.y0);
            var label = findPlaceLabel(words);

            if (!label) {
                continue;
            }

            var end = label.end;

            // Début du libellé : on remonte vers la gauche tant que les mots
            // se suivent ("Lieu de naissance / Place of birth" est un bloc).
            var start = label.place;

            while (start > 0 && start > label.place - 5 && words[start].bbox.x0 - words[start - 1].bbox.x1 < lineHeight * 1.5) {
                start--;
            }

            // Valeur SOUS le libellé d'abord (disposition des deux modèles de
            // passeport congolais constatés). Comparaison par le CENTRE des lignes : sur une photo un peu penchée, la ligne
            // de valeur chevauche celle du libellé de quelques pixels.
            var labelX = words[start].bbox.x0;
            var labelCenter = (lines[i].bbox.y0 + lines[i].bbox.y1) / 2;
            var labelWidth = words[end].bbox.x1 - labelX;
            var below = null;
            // Un mot "sous le libellé" commence entre un peu avant son début
            // et ses deux tiers — au-delà, c'est la colonne voisine.
            var underLabel = function (word) {
                return plainWord(word.text) !== ''
                    && word.bbox.x0 >= labelX - lineHeight * 2
                    && word.bbox.x0 <= labelX + labelWidth * 0.66;
            };

            lines.forEach(function (line) {
                var center = (line.bbox.y0 + line.bbox.y1) / 2;

                if (line !== lines[i] && (line.words || []).some(underLabel) && center > labelCenter + lineHeight * 0.4
                    && center < labelCenter + lineHeight * 3.5 && (!below || center < (below.bbox.y0 + below.bbox.y1) / 2)) {
                    below = line;
                }
            });

            if (below) {
                var first = (below.words || []).filter(underLabel).sort(function (a, b) { return a.bbox.x0 - b.bbox.x0; })[0];
                var under = (below.words || []).filter(function (word) {
                    return plainWord(word.text) !== '' && word.bbox.x0 >= first.bbox.x0;
                }).sort(function (a, b) { return a.bbox.x0 - b.bbox.x0; });
                var value = cleanPlace(contiguousRun(under, lineHeight));

                if (value) {
                    return value;
                }
            }

            // Sinon, à DROITE du libellé, à la même hauteur ("Place of birth:
            // KINSHASA") : dans le même groupe de mots, ou dans un groupe
            // voisin — en mode texte épars, Tesseract sépare souvent une
            // valeur éloignée de son libellé. La plus proche, à moins de
            // 8 hauteurs de ligne ; une colonne voisine qui commence par un
            // libellé ("Nationalité / Nationality") est rejetée par cleanPlace().
            var labelRight = words[end].bbox.x1;
            var nearest = null;

            lines.forEach(function (line) {
                (line.words || []).forEach(function (word) {
                    var center = (word.bbox.y0 + word.bbox.y1) / 2;
                    var gap = word.bbox.x0 - labelRight;

                    if (plainWord(word.text) !== '' && gap > 0 && gap < lineHeight * 8
                        && Math.abs(center - labelCenter) < lineHeight * 0.8 && (!nearest || gap < nearest.gap)) {
                        nearest = { gap: gap, line: line, word: word };
                    }
                });
            });

            if (nearest) {
                var right = (nearest.line.words || []).filter(function (word) {
                    return word.bbox.x0 >= nearest.word.bbox.x0;
                }).sort(function (a, b) { return a.bbox.x0 - b.bbox.x0; });
                var sideValue = cleanPlace(contiguousRun(right, lineHeight));

                if (sideValue) {
                    return sideValue;
                }
            }
        }

        return null;
    }

    // Lecture limitée à la page d'identité, située depuis la ligne 1 de la
    // MRZ : la page (format ICAO TD3) s'étend au-dessus sur environ 0,66 fois
    // la largeur de la MRZ. Exclut la page du haut du livret, les doigts, la
    // table — et divise le temps de lecture par 5 à 10 sur une vraie photo.
    // Deux variantes d'image, arrêt à la première qui donne un lieu : seuil
    // adaptatif classique, puis canal rouge (libellés bleus sur fond rose de
    // l'ancien modèle de passeport).
    function readPlaceOfBirth(image, rotation, region) {
        if (!region) {
            return Promise.resolve(null);
        }

        var page = {
            x: Math.max(0, region.lineLeft - region.lineWidth * 0.08),
            y: Math.max(0, region.lineTop - region.lineWidth * 0.66),
            width: region.lineWidth * 1.16,
            height: region.lineWidth * 0.66
        };
        var zoom = Math.min(3, 2000 / page.width);
        var variants = [null, 'red'];

        return getWorker('eng').then(function (worker) {
            var attempt = function (index) {
                if (index >= variants.length) {
                    return null;
                }

                var canvas = binarize(deskew(rotated(image, rotation, zoom, page), region.skew), variants[index]);

                return read(worker, canvas, 'zone imprimée ' + (variants[index] || 'standard'), '11').then(function (output) {
                    return placeOfBirth(output.data.lines || []) || attempt(index + 1);
                });
            };

            return attempt(0);
        });
    }

    function handleFile(file) {
        var currentRun = ++runId;

        if (!file) {
            return;
        }

        fileName.textContent = file.name;

        if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) === -1) {
            input.value = '';
            setStatus(label('invalid-type'), 'error');
            return;
        }

        if (file.size > maxBytes) {
            input.value = '';
            setStatus(label('too-large'), 'error');
            return;
        }

        dropzone.classList.remove('border-danger');
        setBusy(label('reading'));

        readAsDataUrl(file).then(function (dataUrl) {
            preview.src = dataUrl;
            preview.hidden = false;

            return Promise.all([loadImage(dataUrl), getWorker('mrz')]);
        }).then(function (results) {
            var image = results[0];

            return recognize(results[1], image).then(function (values) {
                var city = form.elements.namedItem('city_of_origin');

                if (!values || !city || city.value !== '' || currentRun !== runId) {
                    return values;
                }

                // Deuxième lecture, facultative : son échec (modèle non
                // chargé, libellé introuvable) ne compromet jamais le
                // pré-remplissage déjà obtenu depuis la MRZ.
                setBusy(label('reading-birthplace'));

                return readPlaceOfBirth(image, values._rotation, values._region).then(function (place) {
                    if (place) {
                        values.city_of_origin = place;
                    }

                    return values;
                }, function () {
                    return values;
                });
            });
        }).then(function (values) {
            if (currentRun !== runId) {
                return;
            }

            setBusy(null);

            if (!values) {
                setStatus(label('not-found'), 'error');
                return;
            }

            var filled = fill(values);

            setStatus(filled > 0 ? label('filled', filled) : label('nothing-filled'), filled > 0 ? 'success' : null);
        }).catch(function () {
            if (currentRun === runId) {
                setBusy(null);
                setStatus(label('failed'), 'error');
            }
        });
    }

    input.addEventListener('change', function () {
        handleFile(input.files && input.files[0]);
    });

    // Glisser-déposer : le fichier est réinjecté dans l'<input> (DataTransfer)
    // pour partir avec le formulaire comme un fichier choisi au clic.
    ['dragenter', 'dragover'].forEach(function (type) {
        dropzone.addEventListener(type, function (event) {
            event.preventDefault();
            dropzone.classList.add('border-primary', 'bg-primary/[4%]');
        });
    });

    ['dragleave', 'drop'].forEach(function (type) {
        dropzone.addEventListener(type, function (event) {
            event.preventDefault();
            dropzone.classList.remove('border-primary', 'bg-primary/[4%]');
        });
    });

    dropzone.addEventListener('drop', function (event) {
        var files = event.dataTransfer && event.dataTransfer.files;

        if (!files || !files.length) {
            return;
        }

        try {
            var transfer = new DataTransfer();
            transfer.items.add(files[0]);
            input.files = transfer.files;
        } catch (error) {
            // Navigateur sans DataTransfer constructible : l'OCR fonctionne
            // quand même, mais le fichier ne sera pas joint à l'envoi.
        }

        handleFile(files[0]);
    });
});
