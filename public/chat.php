<?php

declare(strict_types=1);

use PersonalTutor\ContentRepository;

require_once __DIR__ . '/../src/ContentRepository.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body = file_get_contents('php://input');
$payload = json_decode($body ?: '[]', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => '不正なリクエストです。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$question = trim((string) ($payload['question'] ?? ''));
$subjectId = isset($payload['subject']) ? (string) $payload['subject'] : '';
$unitId = isset($payload['unit']) ? (string) $payload['unit'] : '';
$history = $payload['history'] ?? [];

if ($question === '' || $subjectId === '' || $unitId === '') {
    http_response_code(400);
    echo json_encode(['error' => '教科・単元・質問は必須です。'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $repository = new ContentRepository(__DIR__ . '/../data/contents.json');
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => '教材データの読み込みに失敗しました。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$subject = $repository->findSubject($subjectId);
$unit = $subject ? $repository->findUnit($subjectId, $unitId) : null;

if ($subject === null || $unit === null) {
    http_response_code(404);
    echo json_encode(['error' => '指定された教材が見つかりません。'], JSON_UNESCAPED_UNICODE);
    exit;
}

$contextText = $repository->buildContextText($subject, $unit);
$apiKey = getenv('OPENAI_API_KEY') ?: '';

if ($apiKey === '') {
    $response = buildFallbackResponse($subject, $unit, $question, $contextText);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$messages = buildChatMessages($contextText, $history, $question);

try {
    $answer = requestOpenAi($apiKey, $messages);
    echo json_encode([
        'answer' => $answer,
        'source' => 'openai'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $debugInfo = formatOpenAiDebugInfo('Failed to obtain response from OpenAI.', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    echo json_encode([
        'error' => '家庭教師からの返信に失敗しました。',
        'details' => $debugInfo
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function buildChatMessages(string $contextText, $history, string $question): array
{
    $messages = [];

    $systemPrompt = "あなたは小学生・中学生を優しくサポートする家庭教師です。" .
        "生徒の理解度に合わせて丁寧に日本語で説明し、必要に応じて例やステップを示してください。" .
        "以下は現在取り組んでいる教材情報です。回答の際は必ずこの情報を踏まえてください。\n---\n" .
        $contextText . "\n---";

    $messages[] = [
        'role' => 'system',
        'content' => $systemPrompt,
    ];

    if (is_array($history)) {
        foreach ($history as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = $message['role'] ?? '';
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }
    }

    $messages[] = [
        'role' => 'user',
        'content' => $question,
    ];

    return $messages;
}

function requestOpenAi(string $apiKey, array $messages): string
{
    $payloadData = [
        'model' => 'gpt-5-nano',
        'messages' => $messages,
        'max_completion_tokens' => 512,
    ];

    $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        $debugInfo = formatOpenAiDebugInfo('Failed to encode request payload for OpenAI API.', [
            'messages_count' => count($messages),
        ]);
        throw new RuntimeException('リクエストの作成に失敗しました。' . "\n" . $debugInfo);
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    if ($ch === false) {
        $debugInfo = formatOpenAiDebugInfo('Failed to initialize cURL for OpenAI API request.');
        throw new RuntimeException('リクエストの初期化に失敗しました。' . "\n" . $debugInfo);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawResponse = curl_exec($ch);

    if ($rawResponse === false) {
        $error = curl_error($ch) ?: 'Unknown error';
        $errno = curl_errno($ch);
        $debugInfo = formatOpenAiDebugInfo('cURL execution for OpenAI API failed.', [
            'curl_error' => $error,
            'curl_errno' => $errno,
        ]);
        curl_close($ch);
        throw new RuntimeException('OpenAI API の呼び出しに失敗しました: ' . $error . "\n" . $debugInfo);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        $debugInfo = formatOpenAiDebugInfo('Failed to decode OpenAI API response.', [
            'status_code' => $statusCode,
            'raw_response' => $rawResponse,
        ]);
        throw new RuntimeException('OpenAI API のレスポンスが解析できませんでした。' . "\n" . $debugInfo);
    }

    if ($statusCode >= 400) {
        $errorMessage = $decoded['error']['message'] ?? 'Unexpected error';
        $debugInfo = formatOpenAiDebugInfo('OpenAI API returned an error response.', [
            'status_code' => $statusCode,
            'error' => $decoded['error'] ?? null,
            'raw_response' => $rawResponse,
        ]);
        throw new RuntimeException('OpenAI API エラー: ' . $errorMessage . "\n" . $debugInfo);
    }

    $answer = extractOpenAiAnswer($decoded);
    if ($answer === null) {
        $debugInfo = formatOpenAiDebugInfo('OpenAI API response did not contain an answer.', [
            'status_code' => $statusCode,
            'decoded_response' => $decoded,
        ]);
        throw new RuntimeException('OpenAI API から有効な回答が得られませんでした。' . "\n" . $debugInfo);
    }

    logOpenAiPrompt($payloadData, $answer, $decoded);

    return $answer;
}

function extractOpenAiAnswer(array $response): ?string
{
    if (!isset($response['choices']) || !is_array($response['choices'])) {
        return null;
    }

    foreach ($response['choices'] as $choice) {
        if (!is_array($choice)) {
            continue;
        }

        $message = $choice['message'] ?? null;
        if (!is_array($message)) {
            continue;
        }

        $content = $message['content'] ?? null;
        $text = normalizeOpenAiContent($content);
        if ($text !== null) {
            return $text;
        }

        if (isset($message['refusal']) && is_string($message['refusal'])) {
            $refusal = trim($message['refusal']);
            if ($refusal !== '') {
                return $refusal;
            }
        }
    }

    return null;
}

function normalizeOpenAiContent(mixed $content): ?string
{
    if (is_string($content)) {
        return trim($content) === '' ? null : $content;
    }

    if (!is_array($content)) {
        return null;
    }

    $parts = [];

    foreach ($content as $part) {
        if (is_string($part)) {
            $parts[] = $part;
            continue;
        }

        if (!is_array($part)) {
            continue;
        }

        if (isset($part['text']) && is_string($part['text'])) {
            $parts[] = $part['text'];
            continue;
        }

        if (isset($part['content'])) {
            $nested = normalizeOpenAiContent($part['content']);
            if ($nested !== null) {
                $parts[] = $nested;
            }
        }
    }

    if ($parts === []) {
        return null;
    }

    $joined = implode('', $parts);

    return trim($joined) === '' ? null : $joined;
}

function formatOpenAiDebugInfo(string $message, array $context = []): string
{
    $lines = ['[OpenAI][error] ' . $message];

    if ($context !== []) {
        $encodedContext = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        if ($encodedContext !== false) {
            $lines[] = 'Context: ' . $encodedContext;
        } else {
            $lines[] = 'Context: ' . print_r($context, true);
        }
    }

    return trim(implode("\n", $lines));
}

function logOpenAiPrompt(array $payloadData, string $answer, array $responseData): void
{
    $logDirectory = __DIR__ . '/../data/logs';

    if (!is_dir($logDirectory)) {
        if (!mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
            return;
        }
    }

    $messages = [];
    if (isset($payloadData['messages']) && is_array($payloadData['messages'])) {
        foreach ($payloadData['messages'] as $message) {
            if (!is_array($message)) {
                continue;
            }

            $messages[] = [
                'role' => (string) ($message['role'] ?? ''),
                'content' => (string) ($message['content'] ?? ''),
            ];
        }
    }

    $entry = [
        'timestamp' => date('c'),
        'request' => [
            'model' => $payloadData['model'] ?? null,
            'max_completion_tokens' => $payloadData['max_completion_tokens'] ?? null,
            'messages' => $messages,
        ],
        'response' => [
            'answer' => $answer,
        ],
    ];

    if (isset($responseData['id'])) {
        $entry['response']['id'] = $responseData['id'];
    }

    if (isset($responseData['usage'])) {
        $entry['response']['usage'] = $responseData['usage'];
    }

    $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($encoded === false) {
        return;
    }

    $logFile = $logDirectory . '/openai_prompts.log';
    @file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function buildFallbackResponse(array $subject, array $unit, string $question, string $contextText): array
{
    $lines = [];
    $lines[] = '（デモ応答）OpenAI API キーが設定されていないため、教材のポイントを元にヒントを表示します。';
    $lines[] = '学習中: ' . ($subject['name'] ?? $subject['id'] ?? '') . ' / ' . ($unit['name'] ?? $unit['id'] ?? '');
    $lines[] = '質問: ' . $question;
    $lines[] = '--- 教材のまとめ ---';
    $lines[] = $contextText;
    $lines[] = '----------------------';
    $lines[] = '環境変数 OPENAI_API_KEY にキーを設定すると、AI 家庭教師からの回答が有効になります。';

    return [
        'answer' => implode("\n", $lines),
        'source' => 'fallback',
    ];
}
