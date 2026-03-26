<?php
/**
 * Breyya.com — ElevenLabs Voice Note Integration
 * 
 * Generates voice notes using ElevenLabs TTS API
 * Stores MP3 files and returns public URLs
 */

// Load credentials from .secrets.php or fallback to hardcoded
$_sf = __DIR__ . '/../../.secrets.php';
if (file_exists($_sf)) require_once $_sf;

if (!defined('ELEVENLABS_API_KEY')) {
    define('ELEVENLABS_API_KEY', 'sk_d84ef329204034030732ba135dadcc7c82ff242243770fc0');
}

if (!defined('ELEVENLABS_VOICE_ID')) {
    define('ELEVENLABS_VOICE_ID', 'j05EIz3iI3JmBTWC3CsA');
}

/**
 * Generate voice note from text using ElevenLabs API
 * 
 * @param string $text The text to convert to speech (10-40 words max)
 * @param int $fanId The fan ID for file naming
 * @return string|null The public URL to the generated MP3, or null if failed
 */
function generateVoiceNote($text, $fanId) {
    // Sanitize text
    $text = trim($text);
    if (empty($text)) {
        error_log("VOICE_ERROR: Empty text provided for fan $fanId");
        return null;
    }
    
    // Check text length (safety check)
    $wordCount = str_word_count($text);
    if ($wordCount > 50) {
        error_log("VOICE_ERROR: Text too long ($wordCount words) for fan $fanId");
        return null;
    }
    
    $apiKey = ELEVENLABS_API_KEY;
    $voiceId = ELEVENLABS_VOICE_ID;
    $url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";
    
    $payload = json_encode([
        'text' => $text,
        'model_id' => 'eleven_multilingual_v2',
        'voice_settings' => [
            'stability' => 0.4,
            'similarity_boost' => 0.8,
            'style' => 0.5,
            'use_speaker_boost' => true
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "xi-api-key: {$apiKey}",
            "Content-Type: application/json",
            "Accept: audio/mpeg"
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $audioData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$audioData) {
        error_log("VOICE_ERROR: ElevenLabs API failed for fan $fanId. HTTP: $httpCode, Error: $curlError");
        return null;
    }
    
    // Create voice-notes directory if it doesn't exist
    $voiceDir = __DIR__ . '/../../voice-notes';
    if (!file_exists($voiceDir)) {
        mkdir($voiceDir, 0755, true);
    }
    
    // Generate unique filename
    $timestamp = time();
    $filename = "voice_{$fanId}_{$timestamp}.mp3";
    $filePath = $voiceDir . '/' . $filename;
    
    // Save MP3 file
    if (file_put_contents($filePath, $audioData) === false) {
        error_log("VOICE_ERROR: Failed to save MP3 file for fan $fanId at $filePath");
        return null;
    }
    
    // Return public URL
    $publicUrl = "/voice-notes/{$filename}";
    
    // Log successful generation
    error_log("VOICE_SUCCESS: Generated voice note for fan $fanId, file: $filename, text: " . substr($text, 0, 50));
    
    return $publicUrl;
}

/**
 * Create database table for voice notes tracking
 */
function createVoiceNotesTable() {
    require_once __DIR__ . '/database.php';
    
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS voice_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fan_id INTEGER NOT NULL,
            voice_text TEXT NOT NULL,
            audio_url TEXT NOT NULL,
            created_at TEXT DEFAULT (datetime('now'))
        )");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_voice_notes_fan_id ON voice_notes(fan_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_voice_notes_created_at ON voice_notes(created_at)");
        return true;
    } catch (Exception $e) {
        error_log("VOICE_ERROR: Failed to create voice_notes table: " . $e->getMessage());
        return false;
    }
}

/**
 * Log voice note to database for tracking
 */
function logVoiceNote($fanId, $voiceText, $audioUrl) {
    require_once __DIR__ . '/database.php';
    
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO voice_notes (fan_id, voice_text, audio_url) VALUES (:fan_id, :text, :url)");
        if ($stmt) {
            $stmt->bindValue(':fan_id', $fanId, SQLITE3_INTEGER);
            $stmt->bindValue(':text', $voiceText, SQLITE3_TEXT);
            $stmt->bindValue(':url', $audioUrl, SQLITE3_TEXT);
            $stmt->execute();
        }
    } catch (Exception $e) {
        error_log("VOICE_ERROR: Failed to log voice note: " . $e->getMessage());
    }
}
?>