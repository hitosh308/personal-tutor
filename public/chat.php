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
    http_response_code(500);
    echo json_encode([
        'error' => '家庭教師からの返信に失敗しました。',
        'details' => $e->getMessage()
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
    $payload = json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 512,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException('リクエストの作成に失敗しました。');
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    if ($ch === false) {
        throw new RuntimeException('リクエストの初期化に失敗しました。');
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
        curl_close($ch);
        throw new RuntimeException('OpenAI API の呼び出しに失敗しました: ' . $error);
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('OpenAI API のレスポンスが解析できませんでした。');
    }

    if ($statusCode >= 400) {
        $errorMessage = $decoded['error']['message'] ?? 'Unexpected error';
        throw new RuntimeException('OpenAI API エラー: ' . $errorMessage);
    }

    $answer = $decoded['choices'][0]['message']['content'] ?? '';
    if ($answer === '') {
        throw new RuntimeException('OpenAI API から有効な回答が得られませんでした。');
    }

    return $answer;
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
