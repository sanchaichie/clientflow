<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // Clients are archived, never hard-deleted while they still have projects.
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            // A manager must be reassigned before their user record can be removed.
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('planning'); // App\Enums\ProjectStatus
            // Cached value derived from tasks (completed / total); never user input.
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
