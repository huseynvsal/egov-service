<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('residences', function (Blueprint $table) {
            $table->id();
            $table->string('PIN', 10)->unique();
            $table->string('Name', 100)->nullable();
            $table->string('Surname', 100)->nullable();
            $table->string('DocumentNumber', 20)->nullable();
            $table->string('DocumentType', 100)->nullable();
            $table->string('BirthDate', 20)->nullable();
            $table->text('BirthAddress')->nullable();
            $table->string('Gender', 10)->nullable();
            $table->json('RegistrationAddress')->nullable();
            $table->string('ExpireDate', 20)->nullable();
            $table->string('GivenDate', 20)->nullable();
            $table->string('Citizenship', 100)->nullable();
            $table->longText('Image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residences');
    }
};
