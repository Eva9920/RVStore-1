<?php
// chat_api.php
require_once 'config.php'; // Include config.php for database connection if needed, though not strictly for Groq API

// Set your Groq API Key
// IMPORTANT: In a real production environment, store your API key in environment variables
// or a more secure configuration, not directly in the code or a publicly accessible file.
$groq_api_key = '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'; // <<< GANTI DENGAN API KEY GROQ ANDA

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_message = $input['message'] ?? '';

    if (empty($user_message)) {
        echo json_encode(['error' => 'No message provided.']);
        exit;
    }

    $url = 'https://api.groq.com/openai/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $groq_api_key
    ];

    $data = [
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant for a game top-up management system. You can answer questions about transactions, games, stock, sales reports, and user management. Be concise and helpful.'],
            ['role' => 'user', 'content' => $user_message]
        ],
        'model' => 'llama3-8b-8192', // Or 'mixtral-8x7b-32768', or 'gemma-7b-it'
        'temperature' => 0.7,
        'max_tokens' => 150,
        'top_p' => 1,
        'stream' => false
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['error' => 'cURL Error: ' . $curl_error]);
    } elseif ($http_code != 200) {
        echo json_encode(['error' => 'API Error: HTTP ' . $http_code, 'details' => json_decode($response, true)]);
    } else {
        $decoded_response = json_decode($response, true);
        if (isset($decoded_response['choices'][0]['message']['content'])) {
            echo json_encode(['reply' => $decoded_response['choices'][0]['message']['content']]);
        } else {
            echo json_encode(['error' => 'Invalid API response format.', 'details' => $decoded_response]);
        }
    }

} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>