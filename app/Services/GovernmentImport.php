<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\EncodedUploads;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;
use finfo;

/**
 * Import d'un comité (ministres et autres postes nommés) dans un mandat,
 * depuis un fichier Excel/CSV ou une liste collée (ex. l'annonce WhatsApp
 * du nouveau président) — décision du bureau 2026-10-21.
 *
 * Ce service ne fait que lire et proposer : chaque ligne reçoit les
 * profils membres qui lui correspondent, et l'admin valide dans un aperçu
 * avant tout enregistrement (Admin\GovernmentController::confirmImport()).
 * Un rapprochement de noms n'est jamais certain : il suggère, il ne décide
 * pas. Aucune dépendance Composer : un .xlsx est un zip de XML, lu avec
 * ZipArchive et SimpleXML.
 */
final class GovernmentImport
{
    public const MAX_ROWS = 100;
    public const MAX_FILE_BYTES = 2 * 1024 * 1024;

    /** En-têtes de colonnes reconnus (normalisés, voir normalize()). */
    private const HEADERS = [
        'name'       => ['nom complet', 'nom et prenom', 'nom prenom', 'prenom nom', 'nom', 'name', 'full name', 'membre', 'personne'],
        'first_name' => ['prenom', 'prenoms', 'first name'],
        'last_name'  => ['nom de famille', 'last name', 'surname'],
        'role'       => ['poste', 'role', 'fonction', 'position', 'titre', 'ministere', 'portefeuille'],
        'email'      => ['email', 'e mail', 'mail', 'adresse email', 'adresse mail', 'courriel'],
    ];

    /** Mots qui désignent un poste plutôt qu'une personne. */
    private const ROLE_WORDS = [
        'ministre', 'ministere', 'president', 'presidente', 'vice', 'secretaire', 'tresorier', 'tresoriere',
        'conseiller', 'conseillere', 'charge', 'chargee', 'responsable', 'directeur', 'directrice', 'delegue',
        'deleguee', 'porte', 'parole', 'commissaire', 'coordinateur', 'coordinatrice', 'adjoint', 'adjointe',
        'gouverneur', 'chef', 'premier', 'premiere', 'communication', 'finances', 'affaires', 'culture', 'sport',
        'sports', 'minister', 'secretary', 'treasurer', 'advisor', 'head', 'officer',
    ];

    private const XLSX_MIMES = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'];
    private const TEXT_MIMES = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'text/tab-separated-values'];

    // ----- Lecture -----

    /**
     * Fichier envoyé -> lignes de cellules. Type réel vérifié par finfo,
     * jamais l'extension ; le fichier n'est jamais conservé.
     *
     * @return list<list<string>>
     * @throws RuntimeException message affichable
     */
    public static function rowsFromUpload(array $file): array
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $tmp = (string) ($file['tmp_name'] ?? '');

        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($error === UPLOAD_ERR_OK && filesize($tmp) > self::MAX_FILE_BYTES)) {
            throw new RuntimeException(t('admin.government.import.error.file_too_large', ['max' => (string) (self::MAX_FILE_BYTES / 1024 / 1024)]));
        }

        if ($error !== UPLOAD_ERR_OK || !EncodedUploads::isUploaded($tmp)) {
            throw new RuntimeException(t('admin.government.import.error.file_unreadable'));
        }

        return self::rowsFromFile($tmp);
    }

    /**
     * @return list<list<string>>
     * @throws RuntimeException
     */
    public static function rowsFromFile(string $path): array
    {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (in_array($mime, self::TEXT_MIMES, true)) {
            return self::rowsFromCsv((string) file_get_contents($path));
        }

        if (in_array($mime, self::XLSX_MIMES, true)) {
            return self::rowsFromXlsx($path);
        }

        throw new RuntimeException(t('admin.government.import.error.file_type'));
    }

    /**
     * CSV ou texte tabulé (Excel « Enregistrer sous… CSV ») : séparateur
     * deviné sur la première ligne, encodage Windows converti en UTF-8.
     *
     * @return list<list<string>>
     */
    public static function rowsFromCsv(string $content): array
    {
        $content = self::toUtf8($content);
        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = "\t";

        foreach (["\t", ';', ','] as $candidate) {
            if (substr_count($firstLine, $candidate) > 0) {
                $delimiter = $candidate;
                break;
            }
        }

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(static fn ($cell): string => trim((string) $cell), $cells);
        }

        fclose($handle);

        return self::withoutEmptyRows($rows);
    }

    /**
     * Première feuille d'un classeur .xlsx.
     *
     * @return list<list<string>>
     * @throws RuntimeException
     */
    public static function rowsFromXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(t('admin.government.import.error.xlsx_unsupported'));
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true || $zip->locateName('xl/workbook.xml') === false) {
            throw new RuntimeException(t('admin.government.import.error.file_type'));
        }

        try {
            $shared = [];
            $sharedXml = self::xml($zip, 'xl/sharedStrings.xml');

            if ($sharedXml !== null) {
                foreach ($sharedXml->si as $item) {
                    $shared[] = self::xlsxText($item);
                }
            }

            $sheet = self::xml($zip, self::firstSheetPath($zip));

            if ($sheet === null) {
                throw new RuntimeException(t('admin.government.import.error.file_unreadable'));
            }

            $rows = [];

            foreach ($sheet->sheetData->row as $row) {
                $cells = [];

                foreach ($row->c as $cell) {
                    $column = self::columnIndex((string) $cell['r']) ?? count($cells);
                    $type = (string) $cell['t'];
                    $value = match ($type) {
                        's'         => $shared[(int) $cell->v] ?? '',
                        'inlineStr' => self::xlsxText($cell->is),
                        default     => (string) $cell->v,
                    };
                    $cells[$column] = trim($value);
                }

                if ($cells !== []) {
                    $filled = array_fill(0, max(array_keys($cells)) + 1, '');
                    $rows[] = array_replace($filled, $cells);
                }

                if (count($rows) > self::MAX_ROWS + 1) {
                    break;
                }
            }
        } finally {
            $zip->close();
        }

        return self::withoutEmptyRows($rows);
    }

    /**
     * Liste collée : une personne par ligne, « Nom - Poste », « Poste :
     * Nom », colonnes copiées d'Excel (tabulations)... Puces et numéros en
     * début de ligne ignorés.
     *
     * @return list<list<string>>
     */
    public static function rowsFromText(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', self::toUtf8($text)) ?: [] as $line) {
            // Puces, numéros, astérisques WhatsApp (*gras*), emojis en tête.
            $line = trim(str_replace('*', '', $line));
            $line = (string) preg_replace('/^[^\p{L}\p{N}]+|^\d+\s*[.)\-:]\s*/u', '', $line);
            $line = trim((string) preg_replace('/^[^\p{L}]+/u', '', $line));

            if ($line === '') {
                continue;
            }

            if (str_contains($line, "\t")) {
                $rows[] = array_map('trim', explode("\t", $line));
                continue;
            }

            // Séparateurs par ordre de priorité : la virgule en dernier, un
            // poste peut en contenir (« Affaires sociales, santé »).
            $parts = [$line];

            foreach (['/\s*:\s+/u', '/\s+[-–—]+\s+/u', '/\s*[;|]\s*/u', '/\s*,\s+/u'] as $separator) {
                $split = preg_split($separator, $line, 2);

                if ($split !== false && count($split) === 2) {
                    $parts = $split;
                    break;
                }
            }

            $rows[] = array_map('trim', $parts);
        }

        return self::withoutEmptyRows($rows);
    }

    // ----- Interprétation -----

    /**
     * Lignes de cellules -> entrées {name, role, email}. Une ligne
     * d'en-tête (Nom / Poste / Email...) fixe les colonnes ; sans en-tête,
     * chaque ligne est lue seule : la cellule qui ressemble à un poste
     * (poste déjà connu, mot comme « Ministre ») est le poste, l'autre le
     * nom — dans n'importe quel ordre.
     *
     * @param list<list<string>> $rows
     * @param list<string> $knownRoles postes déjà utilisés dans un mandat
     * @return list<array{name: string, role: string, email: string}>
     * @throws RuntimeException trop de lignes
     */
    public static function entries(array $rows, array $knownRoles): array
    {
        $columns = $rows !== [] ? self::headerColumns($rows[0]) : null;

        if ($columns !== null) {
            array_shift($rows);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException(t('admin.government.import.error.too_many_rows', ['max' => (string) self::MAX_ROWS]));
        }

        $known = array_flip(array_map([self::class, 'normalize'], $knownRoles));
        $entries = [];

        foreach ($rows as $cells) {
            if ($columns !== null) {
                $cell = static fn (string $key): string => isset($columns[$key]) ? trim((string) ($cells[$columns[$key]] ?? '')) : '';
                $name = $cell('name') !== '' ? $cell('name') : trim($cell('first_name') . ' ' . $cell('last_name'));
                $entry = ['name' => $name, 'role' => $cell('role'), 'email' => $cell('email')];
            } else {
                $entry = self::guessEntry($cells, $known);
            }

            if ($entry['name'] !== '' || $entry['role'] !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Rapproche chaque entrée des profils membres et des postes connus.
     *
     * Statuts : 'matched' (un seul profil, nom identique à l'ordre et aux
     * accents près, ou même email), 'check' (un profil très proche — faute
     * de frappe, deuxième prénom : présélectionné mais signalé), 'choose'
     * (plusieurs profils possibles, aucun présélectionné), 'none'.
     *
     * @param list<array{name: string, role: string, email: string}> $entries
     * @param list<array{id: int|string, full_name: string, email?: ?string}> $members membres validés
     * @param list<string> $knownRoles postes déjà utilisés dans un mandat
     * @param list<string> $mandateRoles postes déjà présents dans le mandat visé
     * @param list<int> $mandateMemberIds membres déjà dans le mandat visé
     * @return list<array{source_name: string, role: string, display_name: string, status: string, member_id: ?int, candidates: list<array{id: int, full_name: string, score: int}>, duplicate_role: bool, already_in_mandate: bool, include: bool}>
     */
    public static function match(array $entries, array $members, array $knownRoles, array $mandateRoles, array $mandateMemberIds): array
    {
        $index = [];

        foreach ($members as $member) {
            $index[] = [
                'id'        => (int) $member['id'],
                'full_name' => (string) $member['full_name'],
                'email'     => strtolower(trim((string) ($member['email'] ?? ''))),
                'tokens'    => self::tokens((string) $member['full_name']),
            ];
        }

        $roleSpelling = [];

        foreach ($knownRoles as $role) {
            $roleSpelling[self::normalize($role)] ??= $role;
        }

        $takenRoles = array_flip(array_map([self::class, 'normalize'], $mandateRoles));
        $takenMembers = array_flip($mandateMemberIds);
        $result = [];

        foreach ($entries as $entry) {
            $role = self::clean($entry['role']);
            $role = $roleSpelling[self::normalize($role)] ?? self::capitalize($role);
            $roleKey = self::normalize($role);

            [$status, $candidates] = self::candidatesFor($entry, $index);
            $memberId = in_array($status, ['matched', 'check'], true) ? $candidates[0]['id'] : null;

            $duplicateRole = $roleKey !== '' && isset($takenRoles[$roleKey]);
            $alreadyIn = $memberId !== null && isset($takenMembers[$memberId]);

            $result[] = [
                'source_name'        => self::clean($entry['name']),
                'role'               => $role,
                'display_name'       => $memberId !== null ? $candidates[0]['full_name'] : self::clean($entry['name']),
                'status'             => $status,
                'member_id'          => $memberId,
                'candidates'         => $candidates,
                'duplicate_role'     => $duplicateRole,
                'already_in_mandate' => $alreadyIn,
                'include'            => !$duplicateRole && !$alreadyIn && $role !== '' && $entry['name'] !== '',
            ];

            // Deux lignes du même fichier pour un même poste : la seconde
            // est signalée comme la première l'aurait été dans le mandat.
            if ($roleKey !== '') {
                $takenRoles[$roleKey] = true;
            }

            if ($memberId !== null) {
                $takenMembers[$memberId] = true;
            }
        }

        return $result;
    }

    /**
     * Profils candidats d'une entrée, du plus probable au moins probable.
     *
     * @return array{0: string, 1: list<array{id: int, full_name: string, score: int}>}
     */
    public static function candidatesFor(array $entry, array $index): array
    {
        $email = strtolower(trim((string) ($entry['email'] ?? '')));

        if ($email !== '') {
            foreach ($index as $member) {
                if ($member['email'] !== '' && $member['email'] === $email) {
                    return ['matched', [['id' => $member['id'], 'full_name' => $member['full_name'], 'score' => 100]]];
                }
            }
        }

        $tokens = self::tokens((string) $entry['name']);

        if ($tokens === []) {
            return ['none', []];
        }

        $candidates = [];

        foreach ($index as $member) {
            $score = self::score($tokens, $member['tokens']);

            if ($score >= 50) {
                $candidates[] = ['id' => $member['id'], 'full_name' => $member['full_name'], 'score' => $score];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => [$b['score'], $a['full_name']] <=> [$a['score'], $b['full_name']]);
        $candidates = array_slice($candidates, 0, 5);

        if ($candidates === []) {
            return ['none', []];
        }

        $top = $candidates[0]['score'];
        $tiedAtTop = count(array_filter($candidates, static fn (array $c): bool => $c['score'] === $top));

        if ($tiedAtTop > 1 || $top < 70) {
            return ['choose', $candidates];
        }

        return [$top >= 100 ? 'matched' : 'check', $candidates];
    }

    /**
     * 100 : mêmes mots (ordre, accents, majuscules ignorés).
     *  90 : tous les mots saisis présents, le profil en a d'autres
     *       (« Luc Banze » / « Luc Banze Mukendi »).
     *  75 : tous présents, à une faute de frappe près par mot.
     *  50 : au moins deux mots en commun, ou un seul mot saisi présent.
     *
     * @param list<string> $input
     * @param list<string> $member
     */
    private static function score(array $input, array $member): int
    {
        if ($input === $member) {
            return 100;
        }

        $exact = 0;
        $fuzzy = 0;

        foreach ($input as $token) {
            if (in_array($token, $member, true)) {
                $exact++;
                continue;
            }

            foreach ($member as $candidate) {
                $tolerance = min(strlen($token), strlen($candidate)) >= 7 ? 2 : (min(strlen($token), strlen($candidate)) >= 4 ? 1 : 0);

                if ($tolerance > 0 && levenshtein($token, $candidate) <= $tolerance) {
                    $fuzzy++;
                    continue 2;
                }
            }
        }

        $found = $exact + $fuzzy;

        if (count($input) >= 2 && $found === count($input)) {
            return $fuzzy === 0 ? 90 : 75;
        }

        if ($exact >= 2 || (count($input) === 1 && $exact === 1)) {
            return 50;
        }

        return 0;
    }

    // ----- Outils -----

    /** Minuscules, sans accents ni ponctuation, espaces simples. */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'ş' => 's', 'ğ' => 'g',
            'ı' => 'i', 'ß' => 'ss',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /** @return list<string> mots triés, sans doublon */
    private static function tokens(string $name): array
    {
        $tokens = array_values(array_unique(array_filter(explode(' ', self::normalize($name)), static fn (string $t): bool => $t !== '')));
        sort($tokens);

        return $tokens;
    }

    /**
     * @param list<string> $cells
     * @param array<string, int> $known postes connus normalisés
     * @return array{name: string, role: string, email: string}
     */
    private static function guessEntry(array $cells, array $known): array
    {
        $email = '';
        $texts = [];

        foreach ($cells as $cell) {
            $cell = trim($cell);

            if ($cell === '') {
                continue;
            }

            if ($email === '' && filter_var($cell, FILTER_VALIDATE_EMAIL) !== false) {
                $email = $cell;
                continue;
            }

            $texts[] = $cell;
        }

        if (count($texts) < 2) {
            return ['name' => $texts[0] ?? '', 'role' => '', 'email' => $email];
        }

        [$first, $second] = $texts;

        // « Nom - Poste » par défaut ; inversé si seul le premier ressemble
        // à un poste (« Ministre des Finances : Luc Banze »).
        return self::roleScore($first, $known) > self::roleScore($second, $known)
            ? ['name' => $second, 'role' => $first, 'email' => $email]
            : ['name' => $first, 'role' => $second, 'email' => $email];
    }

    private static function roleScore(string $value, array $known): int
    {
        $normalized = self::normalize($value);

        if (isset($known[$normalized])) {
            return 2;
        }

        return array_intersect(explode(' ', $normalized), self::ROLE_WORDS) !== [] ? 1 : 0;
    }

    /** @return array<string, int>|null colonne de chaque champ, null si la ligne n'est pas un en-tête */
    private static function headerColumns(array $cells): ?array
    {
        $columns = [];

        foreach ($cells as $index => $cell) {
            $normalized = self::normalize((string) $cell);

            foreach (self::HEADERS as $field => $labels) {
                if (!isset($columns[$field]) && in_array($normalized, $labels, true)) {
                    $columns[$field] = $index;
                    continue 2;
                }
            }
        }

        $hasName = isset($columns['name']) || isset($columns['first_name']) || isset($columns['last_name']);

        // « Nom » + « Prénom » : la colonne « nom » est alors le nom de famille.
        if (isset($columns['name'], $columns['first_name']) && !isset($columns['last_name'])
            && self::normalize((string) $cells[$columns['name']]) === 'nom') {
            $columns['last_name'] = $columns['name'];
            unset($columns['name']);
        }

        return $hasName && isset($columns['role']) ? $columns : null;
    }

    private static function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private static function capitalize(string $value): string
    {
        return $value === '' ? '' : mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($value, 1, null, 'UTF-8');
    }

    private static function toUtf8(string $content): string
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return str_replace("\r\n", "\n", $content);
    }

    /** @param list<list<string>> $rows */
    private static function withoutEmptyRows(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $cells): bool => implode('', $cells) !== ''));
    }

    private static function xml(ZipArchive $zip, string $name): ?SimpleXMLElement
    {
        $content = $zip->getFromName($name);

        if ($content === false) {
            return null;
        }

        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);

        return $xml === false ? null : $xml;
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = self::xml($zip, 'xl/workbook.xml');
        $rels = self::xml($zip, 'xl/_rels/workbook.xml.rels');

        if ($workbook !== null && $rels !== null && isset($workbook->sheets->sheet[0])) {
            $relationId = (string) $workbook->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];

            foreach ($rels->Relationship as $relation) {
                if ((string) $relation['Id'] === $relationId) {
                    $target = ltrim((string) $relation['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function xlsxText(?SimpleXMLElement $item): string
    {
        if ($item === null) {
            return '';
        }

        if (isset($item->t)) {
            return (string) $item->t;
        }

        $text = '';

        foreach ($item->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private static function columnIndex(string $reference): ?int
    {
        if (preg_match('/^([A-Z]+)\d+$/', $reference, $m) !== 1) {
            return null;
        }

        $index = 0;

        foreach (str_split($m[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
