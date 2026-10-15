<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\Support\BulkInserts;
use Database\Seeders\Support\SeedScale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    use BulkInserts;

    public function run(): void
    {
        $scale = SeedScale::current();

        DB::transaction(function () use ($scale) {
            // A few well-known accounts: created as models (readable, events would fire).
            User::factory()->admin()->create([
                'name' => 'Адміністратор',
                'email' => 'admin@example.com',
            ]);

            $warehouses = Warehouse::query()->orderBy('id')->get(['id']);

            foreach ($warehouses as $i => $warehouse) {
                User::factory()->manager()->create([
                    'email' => 'manager'.($i + 1).'@example.com',
                    'warehouse_id' => $warehouse->id,
                ]);
            }

            // The rest are readers, bulk-inserted (see BulkInserts).
            $readers = max(0, $scale->users - 1 - $warehouses->count());
            $now = now();

            $this->insertChunked('users', (function () use ($readers, $now) {
                for ($i = 0; $i < $readers; $i++) {
                    yield User::factory()->raw() + ['created_at' => $now, 'updated_at' => $now];
                }
            })());

            // One profile per user.
            $this->insertChunked('profiles', (function () use ($now) {
                foreach (DB::table('users')->select('id')->lazyById(1000) as $user) {
                    yield Profile::factory()->raw(['user_id' => $user->id])
                        + ['created_at' => $now, 'updated_at' => $now];
                }
            })());
        });
    }
}
