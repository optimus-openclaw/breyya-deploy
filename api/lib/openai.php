<?php
/**
 * OpenAI API wrapper for Breyya chat responses
 */

/**
 * Call OpenAI API to generate a response
 * @param string $systemPrompt System prompt
 * @param array $messages Array of message objects with 'role' and 'content'
 * @return string|null Response content or null on error
 */
function callOpenAI(string $systemPrompt, array $messages): ?string {
    $apiKey = OPENAI_API_KEY;
    if ($apiKey === 'sk-PLACEHOLDER') {
        error_log("OpenAI API key not configured");
        return "Hey! 😘";  // Fallback response
    }

    $fullMessages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];
    $fullMessages = array_merge($fullMessages, $messages);

    $payload = [
        'model' => OPENAI_MODEL,
        'messages' => $fullMessages,
        'max_tokens' => 150,
        'temperature' => 0.9,
        'presence_penalty' => 0.6,
        'frequency_penalty' => 0.3,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("OpenAI API error: HTTP $httpCode, Response: $response");
        return "Hey babe! 💕"; // Fallback response
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        error_log("OpenAI API response format error: $response");
        return "Hey sexy! 😍"; // Fallback response
    }

    $content = trim($data['choices'][0]['message']['content']);
    return $content ?: "Hi gorgeous! 😘"; // Final fallback
}

/**
 * Build system prompt based on fan profile and context
 * @param array $profile Fan profile data
 * @param string $classification Fan classification (new, regular, whale, etc.)
 * @param int $messageCount Number of messages from this fan
 * @param int $convoCount Total conversations
 * @return string System prompt
 */
function buildSystemPrompt(array $profile, string $classification, int $messageCount, int $convoCount): string {
    $prompt = "You are Breyya, a flirty 23-year-old content creator. ";
    
    if ($messageCount <= 3) {
        $prompt .= "This is a new conversation, be welcoming but not overwhelming. ";
    } elseif ($messageCount > 20) {
        $prompt .= "You've been chatting a while, be more intimate and personal. ";
    }

    switch ($classification) {
        case 'whale':
            $prompt .= "This fan is a big spender, be extra attentive. ";
            break;
        case 'regular':
            $prompt .= "This fan is a regular, be friendly and familiar. ";
            break;
        case 'new':
            $prompt .= "This is a new fan, make a good first impression. ";
            break;
    }

    $prompt .= "Keep responses under 50 words. Use emojis naturally. Be flirty but not explicit. Show genuine interest. ";
    $prompt .= "Match their energy - if they're casual, be casual. If they're excited, be excited. ";
    $prompt .= "Don't be overly promotional. Focus on connection over sales.";

    return $prompt;
}