<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/test/{prompt?}', function (Request $request, $prompt = null) {
    $roles="123";
    if($prompt) {
        $polecenie=$prompt;
    } else {
        $polecenie="Opowiedz mi coś nieziemsko ciekawego.";
    }
    
    $response = Http::withToken(env('OPENAI_API_KEY'))
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Jesteś pomocnym asystentem.'],
                ['role' => 'user', 'content' => $polecenie],
            ],
                'max_tokens' => 100,
            ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'error' => $response->body(),
            ], 500);
        }

        $answer = $response->json('choices.0.message.content');

        return response()->json([
            'success' => true,
            'question' => $polecenie,
            'answer' => $answer,
        ]);
    
});