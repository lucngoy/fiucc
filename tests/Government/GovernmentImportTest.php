<?php
declare(strict_types=1);

namespace Tests\Government;

use App\Models\Government;
use App\Models\GovernmentMember;
use App\Services\GovernmentImport;
use Tests\TestCase;
use ZipArchive;

/**
 * Import d'un comité (décision du bureau 2026-10-21) : lecture des
 * formats, rapprochement des noms avec les profils, écriture groupée.
 */
final class GovernmentImportTest extends TestCase
{
    private function entriesFromText(string $text, array $knownRoles = []): array
    {
        return GovernmentImport::entries(GovernmentImport::rowsFromText($text), $knownRoles);
    }

    /** @param list<array{id: int, full_name: string, email?: string}> $members */
    private function matchOne(string $name, array $members, string $email = ''): array
    {
        return GovernmentImport::match([['name' => $name, 'role' => 'Ministre', 'email' => $email]], $members, [], [], [])[0];
    }

    // ----- Lecture -----

    public function test_a_pasted_list_is_read_in_either_order_with_bullets_and_numbers(): void
    {
        $entries = $this->entriesFromText(
            "1. Luc Banze - Ministre des Finances\n"
            . "• Ministre de la Culture : Grace Mbuyi\n"
            . "*Trésorier* — Jean-Paul Ilunga\n"
            . "\n"
            . "Aline Kabeya\tSecrétaire générale\n"
            . "Ministre des Affaires sociales, santé : Paul Tshala"
        );

        self::assertSame([
            ['name' => 'Luc Banze', 'role' => 'Ministre des Finances', 'email' => ''],
            ['name' => 'Grace Mbuyi', 'role' => 'Ministre de la Culture', 'email' => ''],
            ['name' => 'Jean-Paul Ilunga', 'role' => 'Trésorier', 'email' => ''],
            ['name' => 'Aline Kabeya', 'role' => 'Secrétaire générale', 'email' => ''],
            ['name' => 'Paul Tshala', 'role' => 'Ministre des Affaires sociales, santé', 'email' => ''],
        ], $entries);
    }

    public function test_a_known_role_is_recognised_even_without_a_role_word(): void
    {
        $entries = $this->entriesFromText('Mascotte - Luc Banze');
        self::assertSame('Mascotte', $entries[0]['name'], 'Sans indice, l\'ordre « Nom - Poste » est gardé.');

        $entries = $this->entriesFromText('Mascotte - Luc Banze', ['Mascotte']);
        self::assertSame(['name' => 'Luc Banze', 'role' => 'Mascotte', 'email' => ''], $entries[0]);
    }

    public function test_a_csv_with_headers_is_read_by_column_whatever_its_order_and_encoding(): void
    {
        $csv = mb_convert_encoding("Poste;Prénom;Nom;Email\nMinistre des Sports;Grâce;Mbuyi;grace@final.edu.tr\n", 'Windows-1252', 'UTF-8');

        $entries = GovernmentImport::entries(GovernmentImport::rowsFromCsv($csv), []);

        self::assertSame([['name' => 'Grâce Mbuyi', 'role' => 'Ministre des Sports', 'email' => 'grace@final.edu.tr']], $entries);
    }

    public function test_an_xlsx_file_is_read_with_shared_and_inline_strings(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Comité" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/comite.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Nom</t></si><si><t>Poste</t></si><si><r><t>Luc </t></r><r><t>Banze</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/comite.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="s"><v>1</v></c></row>'
            . '<row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2" t="inlineStr"><is><t>Ministre des Finances</t></is></c></row>'
            . '</sheetData></worksheet>');
        $zip->close();

        try {
            $entries = GovernmentImport::entries(GovernmentImport::rowsFromFile($path), []);
        } finally {
            unlink($path);
        }

        self::assertSame([['name' => 'Luc Banze', 'role' => 'Ministre des Finances', 'email' => '']], $entries);
    }

    public function test_a_file_of_another_type_is_refused_on_its_real_content(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg=='));

        try {
            $this->expectException(\RuntimeException::class);
            GovernmentImport::rowsFromFile($path);
        } finally {
            unlink($path);
        }
    }

    // ----- Rapprochement -----

    public function test_a_name_matches_regardless_of_order_accents_and_case(): void
    {
        $members = [['id' => 1, 'full_name' => 'Grâce MBUYI Kalala'], ['id' => 2, 'full_name' => 'Luc Banze']];

        $row = $this->matchOne('BANZE luc', $members);
        self::assertSame(['matched', 2], [$row['status'], $row['member_id']]);
        self::assertSame('Luc Banze', $row['display_name'], 'Le nom du profil remplace l\'écriture du fichier.');

        $row = $this->matchOne('Grace Mbuyi', $members);
        self::assertSame(['check', 1], [$row['status'], $row['member_id']], 'Deuxième nom absent : proposé, mais à vérifier.');

        $row = $this->matchOne('Luc Banzé', $members);
        self::assertSame(['matched', 2], [$row['status'], $row['member_id']]);
    }

    public function test_a_typo_is_proposed_for_review_never_as_certain(): void
    {
        $row = $this->matchOne('Luc Banzi', [['id' => 2, 'full_name' => 'Luc Banze']]);

        self::assertSame(['check', 2], [$row['status'], $row['member_id']]);
    }

    public function test_an_ambiguous_name_preselects_nobody(): void
    {
        $members = [['id' => 1, 'full_name' => 'Luc Banze'], ['id' => 2, 'full_name' => 'Luc Mukendi'], ['id' => 3, 'full_name' => 'Aline Kabeya']];

        $row = $this->matchOne('Luc', $members);

        self::assertSame('choose', $row['status']);
        self::assertNull($row['member_id']);
        self::assertSame([1, 2], array_column($row['candidates'], 'id'));
    }

    public function test_namesakes_are_never_resolved_by_chance_but_an_email_settles_it(): void
    {
        $members = [
            ['id' => 1, 'full_name' => 'Jean Kabila', 'email' => 'jean.kabila@final.edu.tr'],
            ['id' => 2, 'full_name' => 'Jean Kabila', 'email' => 'j.kabila2@final.edu.tr'],
        ];

        self::assertNull($this->matchOne('Jean Kabila', $members)['member_id'], 'Homonymes : l\'admin choisit.');

        $row = $this->matchOne('Jean Kabila', $members, 'J.Kabila2@final.edu.tr');
        self::assertSame(['matched', 2], [$row['status'], $row['member_id']]);
    }

    public function test_an_unknown_person_is_kept_with_the_name_only(): void
    {
        $row = $this->matchOne('Inconnu Total', [['id' => 1, 'full_name' => 'Luc Banze']]);

        self::assertSame(['none', null, 'Inconnu Total', true], [$row['status'], $row['member_id'], $row['display_name'], $row['include']]);
    }

    public function test_roles_reuse_known_spelling_and_duplicates_are_unticked(): void
    {
        $entries = [
            ['name' => 'Aline Kabeya', 'role' => 'ministre des finances', 'email' => ''],
            ['name' => 'Paul Tshala', 'role' => 'ministre des sports', 'email' => ''],
            ['name' => 'Grace Mbuyi', 'role' => 'Ministre des Sports', 'email' => ''],
            ['name' => 'Luc Banze', 'role' => 'Conseiller', 'email' => ''],
        ];
        $members = [['id' => 9, 'full_name' => 'Luc Banze']];

        $rows = GovernmentImport::match($entries, $members, ['Ministre des Finances'], ['Ministre des Finances'], [9]);

        self::assertSame(['Ministre des Finances', 'Ministre des sports', 'Ministre des Sports', 'Conseiller'], array_column($rows, 'role'));
        self::assertSame([true, false, true, false], array_column($rows, 'duplicate_role'));
        self::assertTrue($rows[3]['already_in_mandate'], 'Le président élu déjà installé n\'est pas doublé.');
        self::assertSame([false, true, false, false], array_column($rows, 'include'));
    }

    // ----- Écriture -----

    public function test_imported_posts_are_appended_after_the_existing_ones_with_the_profile_details(): void
    {
        $governmentId = Government::createMandate('2026-2027', '2026-11-16', '2027-11-15');
        GovernmentMember::create($governmentId, [
            'member_id' => null, 'role' => 'Président', 'faculty' => null, 'display_name' => 'Élu', 'instagram' => null, 'sort_order' => 1,
        ], null);

        $memberId = $this->makeMember(['full_name' => 'Aline Kabeya']);
        $this->db->prepare("UPDATE members SET faculty = 'Ingénierie', instagram = 'aline.k' WHERE id = :id")->execute(['id' => $memberId]);
        $candidates = array_values(array_filter(GovernmentMember::importCandidates(), static fn (array $m): bool => (int) $m['id'] === $memberId));
        self::assertNotSame('', $candidates[0]['email']);

        $count = GovernmentMember::createMany($governmentId, [
            ['member_id' => $memberId, 'role' => 'Ministre des Finances', 'faculty' => 'Ingénierie', 'display_name' => 'Aline Kabeya', 'instagram' => 'aline.k'],
            ['member_id' => null, 'role' => 'Conseiller', 'faculty' => null, 'display_name' => 'Nom seul', 'instagram' => null],
        ]);

        self::assertSame(2, $count);
        $posts = GovernmentMember::forGovernment($governmentId);
        self::assertSame(['Président', 'Ministre des Finances', 'Conseiller'], array_column($posts, 'role'));
        self::assertSame([1, 2, 3], array_map('intval', array_column($posts, 'sort_order')));
        self::assertContains('Ministre des Finances', GovernmentMember::knownRoles());
    }

    public function test_a_failed_import_writes_nothing(): void
    {
        $governmentId = Government::createMandate('2026-2027', '2026-11-16', '2027-11-15');

        try {
            GovernmentMember::createMany($governmentId, [
                ['member_id' => null, 'role' => 'Conseiller', 'faculty' => null, 'display_name' => 'Premier', 'instagram' => null],
                ['member_id' => 999999999, 'role' => 'Conseiller', 'faculty' => null, 'display_name' => 'Profil inexistant', 'instagram' => null],
            ]);
            self::fail('Clé étrangère attendue.');
        } catch (\PDOException) {
        }

        self::assertSame([], GovernmentMember::forGovernment($governmentId));
    }
}
