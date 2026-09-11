<?php
declare(strict_types=1);

namespace Tests\Bootstrap;

use PHPUnit\Framework\TestCase;

/**
 * Prouve que la garde d'isolation de tests/bootstrap.php refuse réellement
 * de démarrer sur un mauvais environnement — un garde-fou non testé n'est
 * qu'une supposition (conversation FIUCC-79).
 *
 * Chaque cas relance tests/bootstrap.php dans un VRAI sous-processus PHP
 * (proc_open), jamais un appel direct dans le process de test : la garde
 * elle-même fait un `exit(1)`, qui tuerait la suite entière si on
 * l'exécutait dans le même process. C'est aussi la seule façon de vérifier
 * ce que le script écrit réellement sur STDERR et son vrai code de sortie —
 * pas une reconstruction de sa logique en PHPUnit.
 */
final class DatabaseIsolationGuardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_it_starts_on_the_real_configured_testing_environment(): void
    {
        [$exitCode, , $stderr] = $this->runBootstrap(null);

        self::assertSame(
            0,
            $exitCode,
            "Le bootstrap réel (.env.testing tel que configuré sur cette machine) doit démarrer sans erreur. Stderr : {$stderr}"
        );
        self::assertSame('', $stderr);
    }

    public function test_it_refuses_app_env_local_even_with_a_test_suffixed_database(): void
    {
        $envFile = $this->writeTempEnv(['APP_ENV' => 'local', 'DB_DATABASE' => 'congolese_community_test']);

        [$exitCode, , $stderr] = $this->runBootstrapWithEnvFile($envFile);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Refus de démarrer', $stderr);
        self::assertStringContainsString("'local'", $stderr);

        unlink($envFile);
    }

    public function test_it_refuses_a_database_without_the_test_suffix_even_with_app_env_testing(): void
    {
        $envFile = $this->writeTempEnv(['APP_ENV' => 'testing', 'DB_DATABASE' => 'congolese_community']);

        [$exitCode, , $stderr] = $this->runBootstrapWithEnvFile($envFile);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Refus de démarrer', $stderr);
        self::assertStringContainsString("'congolese_community'", $stderr);

        unlink($envFile);
    }

    public function test_it_refuses_production_regardless_of_database_name(): void
    {
        $envFile = $this->writeTempEnv(['APP_ENV' => 'production', 'DB_DATABASE' => 'congolese_community_test']);

        [$exitCode, , $stderr] = $this->runBootstrapWithEnvFile($envFile);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Refus de démarrer', $stderr);

        unlink($envFile);
    }

    private function writeTempEnv(array $overrides): string
    {
        $values = array_merge([
            'APP_ENV'     => 'testing',
            'DB_HOST'     => '127.0.0.1',
            'DB_PORT'     => '3307',
            'DB_DATABASE' => 'congolese_community_test',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
        ], $overrides);

        $path = tempnam(sys_get_temp_dir(), 'fiucc_env_');
        self::assertIsString($path);

        $content = '';
        foreach ($values as $key => $value) {
            $content .= "{$key}={$value}\n";
        }
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @return array{0: int, 1: string, 2: string} [exitCode, stdout, stderr]
     */
    private function runBootstrapWithEnvFile(string $envFile): array
    {
        return $this->runBootstrap(array_merge(getenv(), ['TEST_ENV_FILE' => $envFile]));
    }

    /**
     * @return array{0: int, 1: string, 2: string} [exitCode, stdout, stderr]
     */
    private function runBootstrap(?array $env): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $process = proc_open(
            [PHP_BINARY, 'tests/bootstrap.php'],
            $descriptors,
            $pipes,
            $this->root,
            $env
        );

        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, $stdout, $stderr];
    }
}
