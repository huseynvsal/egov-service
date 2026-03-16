<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identities', function (Blueprint $table) {
            $table->id();
            $table->string('PIN', 10)->unique();
            $table->string('DocumentSeria', 5)->nullable();
            $table->string('DocumentNumber', 20)->nullable();
            $table->string('Name', 100)->nullable();
            $table->string('Surname', 100)->nullable();
            $table->string('NameEn', 100)->nullable();
            $table->string('SurnameEn', 100)->nullable();
            $table->string('Patronymic', 100)->nullable();
            $table->string('BirthDate', 20)->nullable();
            $table->text('BirthAddress')->nullable();
            $table->string('Gender', 10)->nullable();
            $table->text('RegistrationAddress')->nullable();
            $table->string('GivenDate', 20)->nullable();
            $table->string('ActivationDate', 20)->nullable();
            $table->string('ExpireDate', 20)->nullable();
            $table->string('MaritalStatus', 30)->nullable();
            $table->string('GivenOrganization', 200)->nullable();
            $table->string('Citizenship', 100)->nullable();
            $table->longText('Image')->nullable();
            $table->longText('Sign')->nullable();
            $table->string('MilitaryStatus', 50)->nullable();
            $table->string('BloodType', 5)->nullable();
            $table->string('EyeColor', 30)->nullable();
            $table->unsignedSmallInteger('Height')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identities');
    }
};
