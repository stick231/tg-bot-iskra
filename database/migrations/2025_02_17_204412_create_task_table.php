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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category');
            $table->datetime('start_at');
            $table->datetime('end_at')->nullable();
            $table->enum('status', ['in_progress', 'completed']);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('owner_id');
            $table->timestamps();

            $table->foreign('owner_id')
                ->references('telegram_id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task');
    }
};
