<?php

declare(strict_types=1);

use PersonalTutor\ContentRepository;

require_once __DIR__ . '/../src/ContentRepository.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$repository = new ContentRepository(__DIR__ . '/../data/contents.json');

$subjectId = isset($_GET['subject']) ? (string) $_GET['subject'] : null;
$unitId = isset($_GET['unit']) ? (string) $_GET['unit'] : null;

$selectedSubject = null;
$selectedUnit = null;
$message = null;

if ($subjectId === null || $subjectId === '' || $unitId === null || $unitId === '') {
    $message = '学習を始めるには、教科と単元を選び直してください。';
} else {
    $selectedSubject = $repository->findSubject($subjectId);
    if ($selectedSubject === null) {
        $message = '指定された教科が見つかりませんでした。';
    } else {
        $selectedUnit = $repository->findUnit($selectedSubject['id'], $unitId);
        if ($selectedUnit === null) {
            $message = '指定された単元が見つかりませんでした。';
        }
    }
}

$pageTitle = 'Personal Tutor 学習ルーム';
if ($selectedSubject !== null) {
    $pageTitle = $selectedSubject['name'] . ' - ' . $pageTitle;
}
if ($selectedUnit !== null) {
    $pageTitle = $selectedUnit['name'] . ' - ' . $pageTitle;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="app-header">
    <div class="header-inner">
        <h1><a href="./">Personal Tutor</a></h1>
        <p class="tagline">小・中学生向けの家庭教師型学習アプリ</p>
    </div>
</header>
<main class="app-main app-main--learning">
    <?php if ($message !== null): ?>
        <section class="panel">
            <h2>学習ルームを開けませんでした</h2>
            <p><?= h($message) ?></p>
            <p><a class="link-button" href="./">教科と単元の選択に戻る</a></p>
        </section>
    <?php else: ?>
        <section class="panel learning-panel">
            <div class="learning-content">
                <div class="learning-header">
                    <div class="learning-heading">
                        <p class="learning-return"><a class="link-button" href="./">教科と単元の選択に戻る</a></p>
                        <h2><?= h($selectedUnit['name']) ?></h2>
                        <p class="learning-meta">
                            教科: <?= h($selectedSubject['name']) ?> /
                            対象: <?= h($selectedUnit['grade'] ?? '---') ?>
                        </p>
                    </div>
                    <button type="button" class="chat-toggle-button" id="chat-toggle-button" aria-controls="chat-section" aria-expanded="false">
                        <span class="chat-toggle-icon" aria-hidden="true">&#9776;</span>
                        <span class="chat-toggle-label">家庭教師チャット</span>
                    </button>
                </div>

                <?php if (!empty($selectedUnit['goals']) && is_array($selectedUnit['goals'])): ?>
                    <div class="goals">
                        <h3>学習のめあて</h3>
                        <ul>
                            <?php foreach ($selectedUnit['goals'] as $goal): ?>
                                <li><?= h($goal) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="explanation">
                    <h3>解説</h3>
                    <div class="explanation-body">
                        <?= $selectedUnit['explanation'] ?? '<p>解説が登録されていません。</p>' ?>
                    </div>
                </div>

                <?php if (!empty($selectedUnit['exercises']) && is_array($selectedUnit['exercises'])): ?>
                    <div class="exercises">
                        <h3>問題に挑戦</h3>
                        <ol>
                            <?php foreach ($selectedUnit['exercises'] as $exercise): ?>
                                <li>
                                    <h4><?= h($exercise['title'] ?? '問題') ?></h4>
                                    <p><?= nl2br(h($exercise['question'] ?? '')) ?></p>
                                    <?php if (!empty($exercise['hint'])): ?>
                                        <details>
                                            <summary>ヒントを見る</summary>
                                            <p><?= nl2br(h($exercise['hint'])) ?></p>
                                        </details>
                                    <?php endif; ?>
                                    <?php if (!empty($exercise['answer'])): ?>
                                        <details>
                                            <summary>答えを見る</summary>
                                            <p><?= nl2br(h($exercise['answer'])) ?></p>
                                        </details>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php if ($message === null && $selectedSubject !== null && $selectedUnit !== null): ?>
    <aside class="tutor-chat" id="chat-section" data-subject="<?= h($selectedSubject['id']) ?>" data-unit="<?= h($selectedUnit['id']) ?>" tabindex="-1" aria-labelledby="tutor-chat-title" aria-hidden="true" inert>
        <div class="chat-header">
            <h2 id="tutor-chat-title">家庭教師に質問しよう</h2>
            <button type="button" class="chat-close-button" id="chat-close-button">閉じる</button>
        </div>
        <p class="chat-description">分からないことがあれば、メッセージを送ってみましょう。学習中の内容を踏まえてヒントや解説が返ってきます。</p>
        <div id="chat-history" class="chat-history" aria-live="polite"></div>
        <form id="tutor-form" class="chat-form">
            <label for="question">質問を入力</label>
            <textarea id="question" name="question" rows="3" placeholder="例: 通分の方法をもう一度教えてください"></textarea>
            <button type="submit">送信</button>
        </form>
    </aside>
    <div class="chat-overlay" id="chat-overlay" hidden></div>
<?php endif; ?>
<footer class="app-footer">
    <p>&copy; <?= date('Y') ?> Personal Tutor</p>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
