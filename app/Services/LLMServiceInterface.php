<?php

namespace App\Services;

interface LLMServiceInterface
{
    /**
     * Creates an embedding for the given text.
     * @param string $text The text to embed.
     * @return array The embedding vector (empty array if failed).
     */
    public function createEmbedding(string $text): array;

    /**
     * Generates a response based on a prompt and context.
     * @param string $prompt The user's question or prompt.
     * @param array $context An array of relevant text chunks from the knowledge base.
     * @param array $history An array of conversation history (optional).
     * @return string The generated response.
     */
    public function generateResponse(string $prompt, array $context, array $history = []): string;

    /**
     * Gets the name of the LLM provider.
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Gets the embedding dimension of the model.
     * @return int
     */
    public function getEmbeddingDimension(): int;

    /**
     * Finds similar documents based on an embedding.
     * This method might be specific to our DB setup, but is included for completeness in LLM logic flow.
     * @param array $embedding The embedding vector to search with.
     * @param int $limit The maximum number of similar documents to return.
     * @return array An array of similar documents (e.g., ['text' => '...', 'similarity' => '...']).
     */
    public function findSimilarDocuments(array $embedding, int $limit = 5): array;

    /**
     * Chunks a given text into smaller pieces.
     * @param string $text The text to chunk.
     * @param int $chunkSize The desired size of each chunk (in words).
     * @param int $overlap The overlap between consecutive chunks (in words).
     * @return array An array of text chunks.
     */
    public function chunkText(string $text, int $chunkSize = 1000, int $overlap = 100): array; // <-- Tambahkan baris ini
}