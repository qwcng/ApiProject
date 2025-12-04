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
            'image'  => 'nullable|file',
            'roles'   => 'nullable|string',
        ]);

        $prompt = $request->input('prompt');
        $content = [];
        $content[] = [
            "type" => "text",
            "text" => $prompt
        ];
        $role = $request->input('roles', 'Jesteś pomocnym asystentem.');
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $mime = $file->getMimeType();
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $content[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => "data:$mime;base64,$base64"
                ]
            ];
            Storage::disk('local')->put('uploads/' . $file->getClientOriginalName().time(), file_get_contents($file->getRealPath()));
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