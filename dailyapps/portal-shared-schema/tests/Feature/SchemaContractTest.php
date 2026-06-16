<?php

namespace Dailyapps\PortalShared\Tests\Feature;

use Functional\Organizations\Models\Client;
use Functional\Organizations\Models\Site;
use Functional\Users\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaContractTest extends TestCase
{
    public function test_portal_users_core_shape_is_pinned(): void
    {
        $this->assertShape(fn (Blueprint $table) => $table->portalUsers(), [
            'created_at' => true,
            'email' => false,
            'firstname' => false,
            'id' => false,
            'language' => true,
            'lastname' => false,
            'updated_at' => true,
        ]);
    }

    public function test_portal_clients_core_shape_is_pinned(): void
    {
        $this->assertShape(fn (Blueprint $table) => $table->portalClients(), [
            'created_at' => true,
            'id' => false,
            'name' => false,
            'updated_at' => true,
        ]);
    }

    public function test_portal_sites_core_shape_is_pinned(): void
    {
        $this->assertShape(fn (Blueprint $table) => $table->portalSites(), [
            'country' => false,
            'country_alpha' => false,
            'created_at' => true,
            'id' => false,
            'name' => false,
            'subdivision' => true,
            'subdivision_code' => true,
            'updated_at' => true,
        ]);
    }

    public function test_portal_foreign_id_is_chainable_and_honours_nullable(): void
    {
        $table = $this->build('__contract_fk', fn (Blueprint $table) => $table
            ->portalForeignId(Site::class)
            ->portalForeignId(User::class, 'manager_id', nullable: true));

        try {
            $signature = $this->signature($table);

            $this->assertArrayHasKey('site_id', $signature);
            $this->assertArrayHasKey('manager_id', $signature);
            $this->assertStringEndsNotWith('|null', $signature['site_id']);
            $this->assertStringEndsWith('|null', $signature['manager_id']);
        } finally {
            Schema::dropIfExists($table);
        }
    }

    public function test_users_table_matches_macro_core_plus_mother_only(): void
    {
        $this->assertMotherTableMatches(
            'users',
            fn (Blueprint $table) => $table->portalUsers()
                ->portalForeignId(Site::class)
                ->portalForeignId(User::class, 'manager_id', nullable: true)
                ->softDeletes(),
            [
                'password',
                'email_verified_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]
        );
    }

    public function test_clients_table_matches_macro_exactly(): void
    {
        $this->assertMotherTableMatches(
            'clients',
            fn (Blueprint $table) => $table->portalClients()->softDeletes(),
            []
        );
    }

    public function test_sites_table_matches_macro_exactly(): void
    {
        $this->assertMotherTableMatches(
            'sites',
            fn (Blueprint $table) => $table->portalSites()
                ->portalForeignId(Client::class)
                ->softDeletes(),
            []
        );
    }

    public function test_soft_deletes_are_opt_in_via_chaining(): void
    {
        $withoutSoftDeletes = $this->build('__contract_softdeletes', fn (Blueprint $table) => $table->portalClients());

        try {
            $this->assertNotContains('deleted_at', $this->columnNames($withoutSoftDeletes));
        } finally {
            Schema::dropIfExists($withoutSoftDeletes);
        }

        $withSoftDeletes = $this->build('__contract_softdeletes', fn (Blueprint $table) => $table->portalClients()->softDeletes());

        try {
            $this->assertContains('deleted_at', $this->columnNames($withSoftDeletes));
        } finally {
            Schema::dropIfExists($withSoftDeletes);
        }
    }

    /**
     * Guarantee 1 — pin a macro's column names + nullability.
     *
     * @param  array<string, bool>  $expected  column name => nullable
     */
    private function assertShape(callable $columns, array $expected): void
    {
        $table = $this->build('__contract_shape', $columns);

        try {
            $actual = [];
            foreach (Schema::getColumns($table) as $column) {
                $actual[$column['name']] = (bool) $column['nullable'];
            }
            ksort($actual);
            ksort($expected);

            $this->assertSame($expected, $actual, 'Core macro column shape drifted from the pinned contract.');
        } finally {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Guarantee 2 — the migrated mother table = the table rebuilt exactly as the
     * mother migration builds it, plus the listed mother-only columns, nothing else.
     *
     * @param  array<int, string>  $motherOnly
     */
    private function assertMotherTableMatches(string $motherTable, callable $columns, array $motherOnly): void
    {
        $rebuilt = $this->build('__contract_'.$motherTable, $columns);

        try {
            $rebuiltSignature = $this->signature($rebuilt);
            $motherSignature = $this->signature($motherTable);

            foreach ($rebuiltSignature as $name => $signature) {
                $this->assertArrayHasKey($name, $motherSignature, "{$motherTable} is missing shared column {$name}.");
                $this->assertSame(
                    $signature,
                    $motherSignature[$name],
                    "{$motherTable}.{$name} has drifted from the shared schema (type/nullability mismatch)."
                );
            }

            $expected = array_merge(array_keys($rebuiltSignature), $motherOnly);
            sort($expected);
            $actual = array_keys($motherSignature);
            sort($actual);

            $this->assertSame(
                $expected,
                $actual,
                "$motherTable columns are not exactly the shared schema plus the known mother-only columns."
            );
        } finally {
            Schema::dropIfExists($rebuilt);
        }
    }

    private function build(string $name, callable $columns): string
    {
        Schema::dropIfExists($name);
        Schema::create($name, function (Blueprint $table) use ($columns) {
            $columns($table);
        });

        return $name;
    }

    /**
     * @return array<int, string>
     */
    private function columnNames(string $table): array
    {
        return array_map(fn (array $column) => $column['name'], Schema::getColumns($table));
    }

    /**
     * @return array<string, string> column name => "type_name" or "type_name|null"
     */
    private function signature(string $table): array
    {
        $signature = [];
        foreach (Schema::getColumns($table) as $column) {
            $signature[$column['name']] = $column['type_name'].($column['nullable'] ? '|null' : '');
        }

        return $signature;
    }
}
