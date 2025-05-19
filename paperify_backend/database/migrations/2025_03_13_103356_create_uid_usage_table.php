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
        Schema::create('uid_usage', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique()->index();
            $table->string('usage_type');
            $table->double('credits_count');
            $table->double('credits_usage');
            $table->boolean('is_iran');
            $table->boolean('is_webservice');
            $table->double('weekly_usage');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uid_usage');
    }
};
