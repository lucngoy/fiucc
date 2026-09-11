<?php
declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * XSS (§52 du cahier). CLAUDE.md règle 5 : toute sortie passe par e()
 * (htmlspecialchars). Cible précisément le motif le plus dangereux et le
 * plus fréquent — imprimer un champ de tableau (typiquement une ligne de
 * base de données, ex. $member['full_name']) directement dans un bloc
 * <?= ?> sans jamais appeler e() nulle part sur la ligne — plutôt que de
 * tenter de modéliser tous les usages sûrs d'un <?= $x ?> dans ce projet
 * (fermetures locales qui échappent en interne comme $statusBadge/
 * $actionTooltip, ternaires de classes CSS fixes, attributs booléens...),
 * bien plus nombreux et dont l'exhaustivité rendrait ce garde-fou aussi
 * fragile que ce qu'il est censé protéger. Dérivé du code réel (glob sur
 * resources/views/), pas d'une liste de fichiers codée en dur.
 */
final class XssEscapingGuardTest extends TestCase
{
    public function test_no_view_echoes_a_raw_array_field_without_e_anywhere_on_the_line(): void
    {
        $offenders = $this->findOffenders($this->viewFiles());

        self::assertSame([], $offenders, 'Sortie non échappée détectée : ' . implode('; ', $offenders));
    }

    /**
     * Contrôle positif — même principe que SqlInjectionGuardTest : sans ça,
     * le test précédent pourrait passer par vide plutôt que par preuve.
     */
    public function test_the_guard_actually_detects_a_planted_violation(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'fiucc_xss_canary_') . '.php';
        file_put_contents($tmpFile, "<div><?= \$member['full_name'] ?></div>\n");

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

        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];

            foreach ($lines as $number => $line) {
                if (preg_match('/<\?=\s*\$[a-zA-Z_][a-zA-Z0-9_]*\[/', $line) && !str_contains($line, 'e(')) {
                    $offenders[] = $file . ':' . ($number + 1);
                }
            }
        }

        return $offenders;
    }

    /**
     * @return array<int, string>
     */
    private function viewFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/resources/views';
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
