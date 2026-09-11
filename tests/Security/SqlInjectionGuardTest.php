<?php
declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Injection SQL (§52 du cahier). CLAUDE.md règle 4 : "Toutes les requêtes
 * SQL passent par des requêtes préparées PDO." La vraie garantie est
 * structurelle (App\Core\Database : PDO::ATTR_EMULATE_PREPARES => false,
 * appliqué à la connexion unique partagée) — ce test n'attaque rien (il n'y
 * a rien à attaquer : aucune requête ne concatène d'entrée utilisateur dans
 * ce projet à ce jour), il vérifie que cette discipline reste respectée
 * dans le code source lui-même, comme .github/scripts/
 * check_vote_anonymity.py le fait pour l'anonymat du vote. Dérivé du code
 * réel (glob sur app/), pas d'une liste de fichiers codée en dur — reste
 * vrai pour tout futur fichier ajouté sous app/.
 *
 * Portée volontairement étroite : détecte un ->query()/->exec() dont
 * l'argument référence directement un superglobal sur la MÊME ligne — le
 * motif d'injection le plus direct et le plus fréquent. Un appel étalé sur
 * plusieurs lignes avec le superglobal plus loin échapperait à cette
 * détection ; ce garde-fou protège contre un type d'erreur précis, pas
 * contre toute forme possible de concaténation dangereuse (même limite que
 * documentée pour check_vote_anonymity.py, docs/SECURITE_ELECTIONS.md §2.7).
 */
final class SqlInjectionGuardTest extends TestCase
{
    private const SUPERGLOBALS = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE'];

    public function test_no_query_or_exec_call_directly_interpolates_a_superglobal_on_the_same_line(): void
    {
        $offenders = $this->findOffenders($this->appFiles());

        self::assertSame(
            [],
            $offenders,
            'Appel(s) SQL construits avec une entrée utilisateur directe : ' . implode('; ', $offenders)
        );
    }

    /**
     * Contrôle positif : sans ça, le test précédent pourrait passer parce
     * que le motif recherché ne matche jamais rien, jamais parce qu'il
     * fonctionne réellement.
     */
    public function test_the_guard_actually_detects_a_planted_violation(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'fiucc_sqli_canary_') . '.php';
        file_put_contents($tmpFile, "<?php\n\$pdo->query(\"SELECT * FROM users WHERE id = \" . \$_GET['id']);\n");

        $offenders = $this->findOffenders([$tmpFile]);

        unlink($tmpFile);

        self::assertNotSame([], $offenders);
    }

    /**
     * @param array<int, string> $files
     * @return array<int, string>
     */
    private function findOffenders(array $files): array
    {
        $offenders = [];
        $superglobalPattern = implode('|', array_map(
            static fn (string $s): string => preg_quote($s, '/'),
            self::SUPERGLOBALS
        ));
        $pattern = '/->(query|exec)\s*\([^;\n]*(' . $superglobalPattern . ')/';

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($lines as $number => $line) {
                if (preg_match($pattern, $line)) {
                    $offenders[] = $file . ':' . ($number + 1);
                }
            }
        }

        return $offenders;
    }

    /**
     * @return array<int, string>
     */
    private function appFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/app';
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }
}
