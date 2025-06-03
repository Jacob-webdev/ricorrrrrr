<?php
require_once '../config/db.php';
require_once 'functions.php';

// Require user to be logged in and be premium
requireLogin();
header('Content-Type: application/json');

if (!isPremium()) {
    echo json_encode(['success' => false, 'error' => 'This feature is only available for premium users']);
    exit;
}

// Get the posted data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['text']) || empty($data['text'])) {
    echo json_encode(['success' => false, 'error' => 'No text provided']);
    exit;
}

$text = $data['text'];
$task = isset($data['task']) ? $data['task'] : 'summarize';
$prompt = isset($data['prompt']) ? $data['prompt'] : '';

// Character limit check
if (strlen($text) > 5000) {
    echo json_encode(['success' => false, 'error' => 'Text exceeds the 5000 character limit']);
    exit;
}

try {
    $modelUrl = '';
    $payload = [];

    // Select the appropriate model based on the task
    switch ($task) {
        case 'generate':
            $modelUrl = 'Qwen/Qwen2.5-0.5B-Instruct';
            $payload = [
                'inputs' => $prompt . "\n\n" . $text,
                'parameters' => [
                    'max_new_tokens' => 250,
                    'temperature' => 0.7,
                ]
            ];
            break;

        case 'summarize':
            $modelUrl = 'facebook/bart-large-cnn';
            $payload = [
                'inputs' => $text,
                'parameters' => [
                    'max_length' => 150,
                    'min_length' => 30,
                ]
            ];
            break;

        case 'translate':
            $modelUrl = 'alirezamsh/small100';
            $targetLang = isset($data['target_lang']) ? $data['target_lang'] : 'en';
            $payload = [
                'inputs' => $text,
                'parameters' => [
                    'src_lang' => 'auto',
                    'tgt_lang' => $targetLang
                ]
            ];
            break;

        case 'transcribe':
            $modelUrl = 'openai/whisper-large-v3';
            $payload = [
                'inputs' => $text
            ];
            break;

        default:
            throw new Exception('Invalid task specified');
    }

    // API endpoint for Hugging Face Inference API
    $url = 'https://api-inference.huggingface.co/models/' . $modelUrl;

    // Add your API key here
    $apiKey = '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'; // Replace with your actual API key

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    // Set a reasonable timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new Exception('cURL Error: ' . $err);
    }

    if ($httpCode == 503) {
        // Model loading response
        $responseData = json_decode($response, true);
        if (isset($responseData['error']) && strpos($responseData['error'], 'loading') !== false) {
            echo json_encode([
                'success' => false,
                'error' => 'The AI model is currently loading. Please try again in a few seconds.',
                'retry' => true,
                'retry_after' => isset($responseData['estimated_time']) ? ceil($responseData['estimated_time']) : 20
            ]);
            exit;
        }
    }

    if ($httpCode >= 400) {
        throw new Exception("API returned error code: $httpCode with message: $response");
    }

    // Process and return the response based on task
    $result = json_decode($response, true);
    $processedResult = '';

    switch ($task) {
        case 'generate':
            if (isset($result[0]['generated_text'])) {
                $processedResult = $result[0]['generated_text'];
            } else {
                $processedResult = is_string($result[0] ?? null) ? $result[0] : json_encode($result);
            }
            break;

        case 'summarize':
            if (isset($result[0]['summary_text'])) {
                $processedResult = $result[0]['summary_text'];
            } elseif (isset($result[0]['generated_text'])) {
                $processedResult = $result[0]['generated_text'];
            } else {
                $processedResult = is_string($result[0] ?? null) ? $result[0] : json_encode($result);
            }
            break;

        case 'translate':
            if (isset($result[0]['translation_text'])) {
                $processedResult = $result[0]['translation_text'];
            } else {
                $processedResult = is_string($result[0] ?? null) ? $result[0] : json_encode($result);
            }
            break;

        case 'transcribe':
            if (isset($result['text'])) {
                $processedResult = $result['text'];
            } elseif (isset($result[0]['text'])) {
                $processedResult = $result[0]['text'];
            } else {
                $processedResult = is_string($result[0] ?? null) ? $result[0] : json_encode($result);
            }
            break;
    }

    echo json_encode([
        'success' => true,
        'result' => $processedResult,
        'task' => $task,
        'model' => $modelUrl
    ]);

} catch (Exception $e) {
    error_log('AI processing error: ' . $e->getMessage());

    echo json_encode(['success' => false, 'error' => 'Failed to process with AI: ' . $e->getMessage()]);
}
?>