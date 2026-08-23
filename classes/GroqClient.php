<?php

declare(strict_types=1);

class GroqClient
{
    private string $apiKey;
    private string $model;
    private string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = GROQ_API_KEY;
        $this->model = GROQ_MODEL;
    }

    /**
     * Given the recent chat history and who's currently waiting,
     * ask Groq for a short, human-sounding "we've notified them" note.
     * Returns null on any failure so a broken API key never breaks the chat.
     */
    public function generateSilenceNotice(array $recentMessages, string $waitingOnRole): ?string
    {
        if ($this->apiKey === '') {
            return null;
        }

        $transcript = '';
        foreach ($recentMessages as $m) {
            $who = $m['sender_type'] === 'ai' ? 'System' : ($m['sender_type'] === 'user' ? 'User' : 'User');
            $transcript .= $who . ': ' . $m['message'] . "\n";
        }

        $systemPrompt = "You are the LUX EMPIRE Assistant, a brief, warm notification voice inside a "
            . "property/moving-logistics chat app. The {$waitingOnRole} has not replied in this "
            . "conversation for a few minutes. Write ONE short message (max 2 sentences) to reassure "
            . "the other person that the {$waitingOnRole} has been notified and should respond soon. "
            . "Base tone/context on the conversation below. Never invent facts about availability, "
            . "location, or timing you don't know. Do not pretend to be the {$waitingOnRole} themselves.";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "Conversation so far:\n{$transcript}"]
            ],
            'max_tokens' => 100,
            'temperature' => 0.6
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || !$response) {
            error_log('LUX EMPIRE Groq error: ' . $error);
            return null;
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        return $content ? trim($content) : null;
    }
}
