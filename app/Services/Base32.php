<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Base32 de Crockford (FIUCC-64) — encodage lisible du jeton de vote, pas
 * une deuxième forme du secret : App\Models\VotingToken hache toujours les
 * octets bruts, jamais la chaîne produite ici. Voir CLAUDE.md, règle 3,
 * pour le raisonnement complet (pourquoi 128 bits, pourquoi cet alphabet).
 *
 * Alphabet standard (pas inventé pour ce projet — utilisé entre autres par
 * ULID) : chiffres 0-9 conservés, lettres I/L/O/U retirées (I/L confondues
 * avec 1, O avec 0 — une fois ces lettres absentes, 0 et 1 seuls dans
 * l'alphabet ne sont plus ambigus ; U retiré par la spécification d'origine,
 * conservé ici pour rester un alphabet reconnu plutôt qu'une variante).
 */
final class Base32
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Octets bruts -> chaîne Crockford, sans séparateurs (la mise en
     * groupes est un choix d'affichage, voir group()).
     */
    public static function encode(string $bytes): string
    {
        $output = '';
        $buffer = 0;
        $bitsInBuffer = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bitsInBuffer += 8;

            while ($bitsInBuffer >= 5) {
                $bitsInBuffer -= 5;
                $output .= self::ALPHABET[($buffer >> $bitsInBuffer) & 0b11111];
            }
        }

        if ($bitsInBuffer > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bitsInBuffer)) & 0b11111];
        }

        return $output;
    }

    /**
     * Chaîne Crockford (déjà normalisée, voir normalize()) -> octets bruts,
     * ou null si un caractère hors alphabet est rencontré — un jeton saisi
     * avec un caractère invalide est simplement un jeton incorrect, pas une
     * erreur à traiter différemment (App\Models\VotingToken::verify()
     * compte cette tentative comme n'importe quel échec).
     *
     * @param int $expectedByteLength Longueur attendue en octets (16 pour le
     *                                jeton de vote, 128 bits) — les bits de
     *                                bourrage au-delà sont ignorés, jamais
     *                                validés strictement : un bourrage non
     *                                nul n'indique rien d'utile, seul le
     *                                résultat du hachage tranche.
     */
    public static function decode(string $normalized, int $expectedByteLength): ?string
    {
        $buffer = 0;
        $bitsInBuffer = 0;
        $bytes = '';

        for ($i = 0, $len = strlen($normalized); $i < $len; $i++) {
            $value = strpos(self::ALPHABET, $normalized[$i]);

            if ($value === false) {
                return null;
            }

            $buffer = ($buffer << 5) | $value;
            $bitsInBuffer += 5;

            if ($bitsInBuffer >= 8) {
                $bitsInBuffer -= 8;
                $bytes .= chr(($buffer >> $bitsInBuffer) & 0xFF);

                if (strlen($bytes) === $expectedByteLength) {
                    return $bytes;
                }
            }
        }

        return strlen($bytes) === $expectedByteLength ? $bytes : null;
    }

    /**
     * Tolérance de saisie (décision explicite conversation FIUCC-64) :
     * espaces et tirets retirés, casse normalisée — AVANT tout décodage ou
     * hachage, jamais après. Le résultat n'est pas garanti être un alphabet
     * Crockford valide (decode() s'en charge et renvoie null sinon).
     */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $input));
    }

    /**
     * Mise en groupes pour l'affichage (email, écran admin) — jamais
     * appliqué avant normalize()/decode(), uniquement en sortie.
     */
    public static function group(string $code, int $groupSize = 4): string
    {
        return implode('-', str_split($code, $groupSize));
    }
}
