<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // History survives the user who performed the action.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Deliberate duplication: lets the project feed avoid joining through
            // polymorphic subjects (see docs/ERD.md, design decision 4).
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('subject'); // subject_type + subject_id, indexed together
            $table->string('action', 50); // e.g. task.created, task.status_changed
            $table->json('properties')->nullable(); // e.g. {"from": "todo", "to": "review"}
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
