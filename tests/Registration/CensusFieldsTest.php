<?php
declare(strict_types=1);

namespace Tests\Registration;

use App\Services\CensusFields;
use Tests\TestCase;

/**
 * Fiche de recensement étendue (migration 018) : ce qui est requis, ce qui
 * est refusé, et la conversion vers les colonnes de members.
 */
final class CensusFieldsTest extends TestCase
{
    private function validPost(array $overrides = []): array
    {
        return $overrides + [
            'last_name'         => 'Kasongo',
            'middle_name'       => 'Mbuyi',
            'first_name'        => 'Jean',
            'community_role'    => 'student',
            'address_street'    => 'Atatürk Caddesi 12',
            'address_building'  => 'Résidence Lale',
            'address_apartment' => '12B',
            'district'          => 'girne',
            'passport_number'   => 'op 1234567',
            'passport_expiry'   => '2030-05-01',
            'religion'          => 'prefer_not_to_say',
        ];
    }

    public function test_a_complete_sheet_is_valid(): void
    {
        self::assertSame([], CensusFields::validate(CensusFields::collect($this->validPost())));
    }

    public function test_passport_number_is_normalized_before_validation(): void
    {
        $input = CensusFields::collect($this->validPost());

        self::assertSame('OP1234567', $input['passport_number']);
    }

    public function test_required_fields_are_reported(): void
    {
        $errors = CensusFields::validate(CensusFields::collect([]));

        // Tout est obligatoire (décision 2026-10-09), y compris post-nom,
        // immeuble, appartement et culte.
        foreach ([
            'last_name', 'middle_name', 'first_name', 'community_role', 'address_street', 'address_building',
            'address_apartment', 'district', 'passport_number', 'passport_expiry', 'religion',
        ] as $field) {
            self::assertArrayHasKey($field, $errors, $field);
        }
    }

    public function test_prefer_not_to_say_is_an_accepted_religion_answer(): void
    {
        $errors = CensusFields::validate(CensusFields::collect($this->validPost(['religion' => 'prefer_not_to_say'])));

        self::assertArrayNotHasKey('religion', $errors);
    }

    public function test_values_outside_the_configured_lists_are_refused(): void
    {
        $errors = CensusFields::validate(CensusFields::collect($this->validPost([
            'community_role' => 'admin',
            'district'       => 'paris',
            'religion'       => 'jedi',
        ])));

        self::assertArrayHasKey('community_role', $errors);
        self::assertArrayHasKey('district', $errors);
        self::assertArrayHasKey('religion', $errors);
    }

    public function test_malformed_passport_data_is_refused(): void
    {
        $errors = CensusFields::validate(CensusFields::collect($this->validPost([
            'passport_number' => 'AB-12/34',
            'passport_expiry' => '2030-02-30',
        ])));

        self::assertArrayHasKey('passport_number', $errors);
        self::assertArrayHasKey('passport_expiry', $errors);
    }

    public function test_columns_rebuild_full_name(): void
    {
        $columns = CensusFields::toColumns(CensusFields::collect($this->validPost()));

        self::assertSame('Jean Kasongo Mbuyi', $columns['full_name']);
        self::assertSame('Résidence Lale', $columns['address_building']);
        self::assertSame('prefer_not_to_say', $columns['religion']);
        self::assertSame('girne', $columns['district']);
    }
}
