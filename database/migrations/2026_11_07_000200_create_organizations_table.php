<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organisations the system is given to (banks, valuation companies). Each
 * works in its own workspace: its clients, objects and audit log are not
 * seen by the others, nor by the company that runs the system (whose own
 * records have no organisation). Its company details for contracts and
 * reports are kept in "settings".
 *
 * The organisation written on users as text becomes a workspace, and what
 * those users have already entered moves into it.
 */
return new class extends Migration
{
    /** Tables whose rows belong to a workspace, with the column that says who entered them. */
    private const TABLES = [
        'clients' => 'created_by',
        'real_estates' => 'created_by',
        'assets' => 'created_by',
        'vehicles' => 'created_by',
        'other_assets' => 'created_by',
        'damage_cases' => 'created_by',
        'activities' => 'user_id',
    ];

    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('position')->index();
        });
        foreach (array_keys(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->index();
            });
        }

        $names = DB::table('users')->whereNotNull('organization')->where('organization', '!=', '')->distinct()->orderBy('organization')->pluck('organization');
        foreach ($names as $name) {
            $id = DB::table('organizations')->insertGetId(['name' => trim($name), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->where('organization', $name)->update(['organization_id' => $id]);
        }

        foreach (self::TABLES as $name => $column) {
            DB::table($name)
                ->whereIn($column, DB::table('users')->whereNotNull('organization_id')->select('id'))
                ->update(['organization_id' => DB::raw("(select organization_id from users where users.id = {$name}.{$column})")]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('organization');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('organization', 150)->nullable()->after('position');
        });
        foreach (DB::table('organizations')->get(['id', 'name']) as $organization) {
            DB::table('users')->where('organization_id', $organization->id)->update(['organization' => $organization->name]);
        }

        foreach (array_keys(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
        Schema::dropIfExists('organizations');
    }
};
