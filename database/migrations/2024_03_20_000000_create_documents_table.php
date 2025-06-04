<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Enable pgvector extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_file_name');
            $table->text('text');
            $table->timestamps();
        });

        // Add vector column dengan dimensi 3072 untuk model gemini-embedding-exp-03-07
        DB::statement('ALTER TABLE documents ADD COLUMN embedding vector(3072)');
    }

    public function down()
    {
        Schema::dropIfExists('documents');
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
