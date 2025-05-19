<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId(column: 'user_id')->constrained()->cascadeOnDelete(); // Foreign key to users table
            $table->string('chat_id'); // Foreign key to chats table
            $table->text('text'); // Message text
            $table->boolean('show')->default(true);
            $table->enum('role', ['system', 'assistant', 'user']);

            $table->timestamps(); // Created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
