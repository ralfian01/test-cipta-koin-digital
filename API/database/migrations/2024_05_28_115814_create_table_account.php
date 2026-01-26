<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create account table
        Schema::create('account', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 50)->nullable(false);
            $table->string('username', 100)->nullable(false)->unique();
            $table->string('password', 100)->nullable(true);
            // # Relation to table Role
            $table->unsignedBigInteger('role_id')->nullable(false);
            $table->foreign('role_id')->references('id')->on('role')
                ->onDelete('no action')
                ->onUpdate('no action');

            $table->boolean('deletable')->default(true)->nullable(false);
            $table->boolean('status_active')->default(false)->nullable(false);
            $table->boolean('status_delete')->default(false)->nullable(false);

            $table->timestamps();
        });

        // Create account privilege table
        Schema::create('account__privilege', function (Blueprint $table) {
            $table->id();
            // # Relation to table Account
            $table->unsignedBigInteger('account_id')->nullable(false);
            $table->foreign('account_id')->references('id')->on('account')
                ->onDelete('cascade')
                ->onUpdate('no action');

            // # Relation to table Privilege
            $table->unsignedBigInteger('privilege_id')->nullable(false);
            $table->foreign('privilege_id')->references('id')->on('privilege')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->timestamps();
        });

        // Create account metadata table
        Schema::create('account__meta', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable(false);
            $table->string('value', 100)->nullable(true)->default(null);

            // # Relation to table Account
            $table->unsignedBigInteger('account_id')->nullable(false);
            $table->foreign('account_id')->references('id')->on('account')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('expired_at')->nullable(true);
        });

        // Create account table view
        // Here
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account');
        Schema::dropIfExists('account__privilege');
        Schema::dropIfExists('account__meta');
    }
};
