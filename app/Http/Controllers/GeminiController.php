<?php
    namespace App\Http\Controllers;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Log;

    class GeminiController extends Controller
    {
        // Lista Twoich kluczy API
        private array $apiKeys;
        
        public function __construct()
        {
            $this->apiKeys = [
                env('GOOGLE_API_KEY_1'),
                env('GOOGLE_API_KEY_2'),
                env('GOOGLE_API_KEY_3',"AIzaSyDIfVJcg_oODZzmKLTBOiT2x62rYrPOVkg"),
                env('GOOGLE_API_KEY_4',"AIzaSyDIfVJcg_oODZzmKLTBOiT2x62rYrPOVkg"),
            ];
        }
        /**
         * Zwraca listę narzędzi (tools), które AI może wywoływać
         */
        private function getTools()
        {   
            return [[
                "functionDeclarations" => [
                    [
                        "name" => "add_task",
                        "description" => "Dodaj nowe zadanie. Używaj równoważników zdań w nazwie.",
                        "parameters" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "text" => ["type" => "STRING", "description" => "Treść zadania (równoważnik zdania, np. 'Czytanie książki')"],
                                "time" => ["type" => "STRING", "description" => "HH:mm"],
                                "frequency" => ["type" => "STRING", "enum" => ["once", "daily", "weekly"]],
                                "date" => ["type" => "STRING"],
                                "dayOfWeek" => ["type" => "INTEGER"]
                            ],
                            "required" => ["text", "time", "frequency"]
                        ]
                    ],
                    [
                        "name" => "add_event",
                        "description" => "Dodaj wydarzenie do harmonogramu. Używaj równoważników zdań w tytule.",
                        "parameters" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "title" => ["type" => "STRING", "description" => "Tytuł (równoważnik zdania, np. 'Spotkanie z zespołem')"],
                                "time" => ["type" => "STRING", "description" => "HH:mm"],
                                "frequency" => ["type" => "STRING", "enum" => ["once", "daily", "weekly"]],
                                "date" => ["type" => "STRING"],
                                "dayOfWeek" => ["type" => "INTEGER"]
                            ],
                            "required" => ["title", "time", "frequency"]
                        ]
                    ],
                    [
                        "name" => "add_reminder",
                        "description" => "Ustaw przypomnienie. Używaj równoważników zdań.",
                        "parameters" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "text" => ["type" => "STRING", "description" => "Treść (równoważnik zdania, np. 'Wypicie szklanki wody')"],
                                "time" => ["type" => "STRING", "description" => "HH:mm"],
                                "frequency" => ["type" => "STRING", "enum" => ["once", "daily", "weekly"]],
                                "date" => ["type" => "STRING"],
                                "dayOfWeek" => ["type" => "INTEGER"]
                            ],
                            "required" => ["text", "time", "frequency"]
                        ]
                    ],
                    [
                        "name" => "add_water",
                        "description" => "Dodaj szklanki wody.",
                        "parameters" => [
                            "type" => "OBJECT",
                            "properties" => [
                                "glasses" => ["type" => "INTEGER"]
                            ],
                            "required" => ["glasses"]
                        ]
                    ]
                ]
            ]];
        }
        /**
         * Buduje dynamiczny System Prompt na podstawie danych przesłanych z aplikacji
         */
        private function getSystemPrompt($context)
        {
            $systemPrompt = <<<EOT
    Jesteś "Vitality Coach" - ultra-inteligentnym asystentem zdrowia, snu i produktywności w aplikacji Versec Health.
    Twoim celem jest pomaganie użytkownikowi w osiągnięciu jego celów (np. lepszy sen, więcej energii).
    Masz dostęp do danych użytkownika takich jak zadania, harmonogram (wydarzenia) oraz przypomnienia (powiadomienia).
    ZASADY:
    1. Odpowiadaj krótko i konkretnie, ale w motywującym tonie.
    2. Używaj języka polskiego.
    3. FORMATOWANIE: Używaj markdown, aby Twoje odpowiedzi były przejrzyste. **Pogrubiaj** istotne terminy, stosuj listy wypunktowane i numerowane.
    4. Jeśli użytkownik prosi o plan, ankietę lub diagnozę, możesz zaproponować "Interaktywny Widget".
    5. Aby stworzyć widget, umieść na końcu swojej odpowiedzi blok JSON o strukturze:
    [WIDGET:SURVEY]
    {
        "title": "Tytuł ankiety",
        "questions": [
        {"id": 1, "text": "Pytanie?", "type": "range", "min": 0, "max": 10},
        {"id": 2, "text": "Inne pytanie?", "type": "text"}
        ]
    }
    [/WIDGET]
    6. KONWENCJA NAZEWNICTWA: Tworząc zadania, wydarzenia lub przypomnienia, ZAWSZE używaj **równoważników zdań** (np. "Spacer z psem" zamiast "Wyprowadź psa", "Przygotowanie posiłku" zamiast "Zrób obiad", "Trening siłowy" zamiast "Idź na trening"). Unikaj form czasownikowych.
    7. DYNAMICZNE KOLEJNE PROMPTY: Na samym końcu każdej wiadomości, MUSISZ dodać blok JSON z 3 krótkimi propozycjami kolejnych pytań/akcji, które użytkownik i będą dotyczyuć usera.
    Naprawdę dbaj o tą strukturę.
    Format: [SUGGESTIONS]["Prompt 1", "Prompt 2", "Prompt 3"][/SUGGESTIONS]
    8. DZIEŃ TYGODNIA: Dzisiaj jest {{DAY_OF_WEEK}} (0-Niedziela, 1-Poniedziałek, ..., 6-Sobota). Data: {{DATE}}.
    DANE UŻYTKOWNIKA:
    - Imię: {{NAME}}
    - Cel: {{GOAL}}
    - Dzisiejsze zadania: {{TASKS}}
    - Harmonogram (wydarzenia): {{SCHEDULE}}
    - Przypomnienia (notyfikacje): {{REMINDERS}}
    EOT;
            $now = new \DateTime();
            
            $name = $context['user']['name'] ?? 'Użytkownik';
            $goal = $context['user']['goal'] ?? 'Brak';
            $tasks = json_encode($context['tasks'] ?? []);
            $schedule = json_encode($context['schedule'] ?? []);
            $reminders = json_encode($context['reminders'] ?? []);
            $date = $now->format('Y-m-d');
            $dayOfWeek = $now->format('w'); // 0 (Sun) to 6 (Sat)
            return str_replace(
                ['{{NAME}}', '{{GOAL}}', '{{TASKS}}', '{{SCHEDULE}}', '{{REMINDERS}}', '{{DATE}}', '{{DAY_OF_WEEK}}'],
                [$name, $goal, $tasks, $schedule, $reminders, $date, $dayOfWeek],
                $systemPrompt
            );
        }
public function chat(Request $request)
    {
        $context = $request->input('context', []);
        $history = $request->input('history', []);
        $userMessage = $request->input('userMessage');
        $functionResponses = $request->input('functionResponses'); // Wyniki wywołanych na froncie funkcji
        $systemPrompt = $this->getSystemPrompt($context);
        $contents = [];
        // 1. Zawsze inicjujemy kontekst jako pierwsza wiadomość usera -> odp modelu
        $contents[] = [
            "role" => "user",
            "parts" => [["text" => $systemPrompt . "\n\nZrozumiałem. Cześć!"]]
        ];
        $contents[] = [
            "role" => "model",
            "parts" => [["text" => "Cześć! Jestem Twoim trenerem Vitality. W czym mogę Ci dzisiaj pomóc?"]]
        ];
        // 2. Dodajemy całą historię chatu z frontu
        foreach ($history as $msg) {
            $parts = [];
            
            if (isset($msg['text'])) {
                $parts[] = ["text" => $msg['text']];
            }
            if (isset($msg['calls'])) {
                foreach ($msg['calls'] as $call) {
                    $parts[] = [
                        "functionCall" => [
                            "name" => $call['name'],
                            "args" => $call['args']
                        ]
                    ];
                }
            }
            
            $contents[] = [
                "role" => $msg['role'] === 'model' ? 'model' : 'user',
                "parts" => $parts
            ];
        }
        // 3. Dodajemy nową "wiadomość" do wysłania
        if ($functionResponses) {
            $parts = [];
            foreach ($functionResponses as $resp) {
                $parts[] = [
                    "functionResponse" => [
                        "name" => $resp['functionResponse']['name'],
                        "response" => $resp['functionResponse']['response']
                    ]
                ];
            }
            $contents[] = [
                "role" => "user",
                "parts" => $parts
            ];
        } else if ($userMessage) {
            $contents[] = [
                "role" => "user",
                "parts" => [["text" => $userMessage]]
            ];
        }
        // --- ROUND-ROBIN Z ZABEZPIECZENIEM (AUTO-RETRY) ---
        $maxRetries = count($this->apiKeys);
        $attempt = 0;
        $response = null;
        while ($attempt < $maxRetries) {
            $currentKeyIndex = (int) Cache::get('gemini_api_key_index', 0);
            if (!isset($this->apiKeys[$currentKeyIndex])) {
                $currentKeyIndex = 0;
            }
            $apiKey = $this->apiKeys[$currentKeyIndex];
            
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";
            $payload = [
                "contents" => $contents,
                "tools" => $this->getTools()
            ];
            // Odpytanie Gemini przez REST API
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                            ->timeout(30)
                            ->post($url, $payload);
            // --- OBSŁUGA SUKCESU ---
            if ($response->successful()) {
                $data = $response->json();
                Log::info('Odpowiedź z Gemini', ['response' => $data]);
                Log::info("Klucz API [Indeks: {$currentKeyIndex}] działa poprawnie.");
                $candidate = $data['candidates'][0]['content']['parts'] ?? [];
                
                $text = "";
                $calls = [];
                foreach ($candidate as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                    if (isset($part['functionCall'])) {
                        $calls[] = $part['functionCall'];
                    }
                }
                // Udane zapytanie. Ustawiamy kolejny klucz na poczet przyszłego zapytania (zwykły round-robin)
                Cache::put('gemini_api_key_index', ($currentKeyIndex + 1) % count($this->apiKeys));
                return response()->json([
                    'text' => $text,
                    'calls' => $calls
                ]);
            }
            // --- OBSŁUGA BŁĘDÓW API ---
            $errorData = $response->json();
            $errorStatus = $errorData['error']['status'] ?? '';
            // Sprawdzamy, czy ten konkretny klucz wyczerpał limit całkowicie
            if ($response->status() === 429 && $errorStatus === 'RESOURCE_EXHAUSTED') {
                Log::warning("Klucz API [Indeks: {$currentKeyIndex}] zablokowany (RESOURCE_EXHAUSTED). Przełączam na następny...");
                
                // Ustawiamy nowy klucz natychmiastowo i kręcimy pętlą jeszcze raz
                Cache::put('gemini_api_key_index', ($currentKeyIndex + 1) % count($this->apiKeys));
                $attempt++;
                continue; 
            }
            // Inny rodzaj błędu (zwykłe przeciążenie serwera, błąd wewnętrzny Google itd.) - przerywamy pętlę.
            break; 
        }
        // --- POZA PĘTLĄ (Jeśli wszystkie klucze wyczerpane lub padło API Google) ---
        if ($response && ($response->status() === 429 || $response->status() === 503)) {
            Log::info('Odpowiedź z Gemini 429 lub 503 (brak kluczy lub przeciążenie)', ['response' => $response->json()]);
            Log::info("Klucz API [Indeks: " . Cache::get('gemini_api_key_index', 0) . "] działa źle.");
            return response()->json([
                'status' => 'overload'
            ]);
        }
        // --- INNE KRYTYCZNE BŁĘDY (np. źle złożony prompt) ---
        return response()->json([
            'error' => 'Błąd podczas komunikacji z API Gemini',
            'details' => $response ? $response->json() : 'Brak odpowiedzi API'
        ], $response ? $response->status() : 500);
    }
    }