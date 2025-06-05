<?php

namespace App\Providers;

use App\Services\GeminiService;
use App\Services\HuggingFaceService;
use App\Services\LLMServiceInterface;
use Illuminate\Support\ServiceProvider;

class LLMServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LLMServiceInterface::class, function ($app, $params = []) { // Tambahkan $params
            $chosenProvider = $params['chosenProvider'] ?? config('llm.default_provider', 'gemini');

            switch ($chosenProvider) {
                case 'gemini':
                    return $app->make(GeminiService::class);
                case 'huggingface':
                    return $app->make(HuggingFaceService::class);
                default:
                    throw new \Exception("LLM provider '{$chosenProvider}' not supported.");
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}