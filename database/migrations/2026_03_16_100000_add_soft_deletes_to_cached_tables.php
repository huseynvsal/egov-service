<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('identities', function (Blueprint $table) {
            $table->dropUnique('identities_pin_unique');
            $table->softDeletes();
        });

        Schema::table('residences', function (Blueprint $table) {
            $table->dropUnique('residences_pin_unique');
            $table->softDeletes();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_pin_unique');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('identities', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique('PIN', 'identities_pin_unique');
        });

        Schema::table('residences', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique('PIN', 'residences_pin_unique');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->unique('pin', 'employees_pin_unique');
        });
    }
};
