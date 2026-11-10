<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo'); // App\Enums\TaskStatus
            $table->string('priority', 20)->default('medium'); // App\Enums\TaskPriority
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0); // order within a Kanban column
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_soon_notified_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status', 'position']); // Kanban columns per project
            $table->index(['assignee_id', 'status']);              // "my tasks"
            $table->index('due_date');                              // overdue / due today
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
