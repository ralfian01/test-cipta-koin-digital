<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create role table
        Schema::create('role', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable(false)->unique();
            $table->string('name', 100);

            $table->timestamps();
        });

        // Create role privilege table
        Schema::create('role__privilege', function (Blueprint $table) {
            $table->id();
            // # Relation to table Role
            $table->unsignedBigInteger('role_id')->nullable(false);
            $table->foreign('role_id')->references('id')->on('role')
                ->onDelete('cascade')
                ->onUpdate('no action');

            // # Relation to table Privilege
            $table->unsignedBigInteger('privilege_id')->nullable(false);
            $table->foreign('privilege_id')->references('id')->on('privilege')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->unique(['role_id', 'privilege_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role');
        Schema::dropIfExists('role__privilege');
    }
};
