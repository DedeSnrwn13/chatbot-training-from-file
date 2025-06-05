<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pgvector extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_file_name');
            $table->text('text');
            $table->string('embedding_provider')->nullable(); // Menandai provider embedding
            $table->integer('embedding_dimension')->nullable(); // Dimensi embedding
            $table->timestamps();
        });

        // Add vector column with a flexible dimension or default large one
        // Jika model Gemini Anda output 3072, maka ini harus 3072.
        // Jika Anda berencana mengganti provider dengan dimensi kecil,
        // pertimbangkan untuk membuat kolom ini nullable vector atau membuat kolom baru.
        // Untuk fleksibilitas, bisa juga vector(X) dimana X adalah dimensi terbesar yang mungkin.
        // Atau: Anda bisa punya banyak kolom embedding (embedding_gemini, embedding_hf)
        // Cara paling fleksibel namun kompleks: buat tabel terpisah untuk embeddings
        DB::statement('ALTER TABLE documents ADD COLUMN embedding vector(3072)'); // Tetap gunakan 3072 sesuai yang ada
        // atau sesuaikan dengan dimensi MAX yang Anda inginkan
        // Jika perlu indeks untuk pgvector
        // DB::statement('CREATE INDEX ON documents USING HNSW (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};