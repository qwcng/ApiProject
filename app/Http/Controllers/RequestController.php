<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RequestController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'image'  => 'nullable|string',
            'roles'   => 'nullable|string',
        ]);

        $prompt = $request->input('prompt');
        $content = [];
        $content[] = [
            "type" => "text",
            "text" => $prompt
        ];
        $role = $request->input('roles', 'Jesteś pomocnym asystentem.');
        if($request->filled('image')) {
            $base64 = $request->input('image');
            if (preg_match('/^data:(.*?);base64,/', $base64, $matches)) {
                $mime = $matches[1];
            } else {
                $mime = 'image/png';
                $base64 = 'data:image/png;base64,' . $base64;
            }
            $imageData = base64_decode($base64);
            $filename = 'image_' . time() . '.' .'jpg';
            Storage::disk('local')->put('uploads/' . $filename, $imageData);
            $content[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => $base64
                ]
            ];
        }
        

        
        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post("https://api.openai.com/v1/chat/completions", [
                "model" => "gpt-4o-mini",
                "messages" => [
                        ['role' => 'system', 'content' => $role],
                        ['role' => 'user', 'content' => $content],
                        
                    
                ],
                "max_tokens" => 300
            ]);
        if($response->successful()){
            
            return response()->json([
                "status" => "success",
                "prompt" => $prompt,
                "response" => $response->json(),
            ]);
        } else {
            return response()->json([
                "status" => "error",
                "prompt" => $prompt,
                "response" => $response->json(),
            ]);
        }
       
    }
}