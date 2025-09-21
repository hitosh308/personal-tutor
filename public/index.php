<?php

declare(strict_types=1);

use PersonalTutor\ContentRepository;

require_once __DIR__ . '/../src/ContentRepository.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$repository = new ContentRepository(__DIR__ . '/../data/contents.json');
$subjects = $repository->getSubjects();

$subjectId = isset($_GET['subject']) ? (string) $_GET['subject'] : null;
$unitId = isset($_GET['unit']) ? (string) $_GET['unit'] : null;

$selectedSubject = null;
$selectedUnit = null;
$message = null;

if ($subjectId !== null && $subjectId !== '') {
    $selectedSubject = $repository->findSubject($subjectId);
    if ($selectedSubject === null) {
        $message = '指定された教科が見つかりませんでした。';
    }
}

if ($selectedSubject !== null && $unitId !== null && $unitId !== '') {
    $selectedUnit = $repository->findUnit($selectedSubject['id'], $unitId);
    if ($selectedUnit === null) {
        $message = '指定された単元が見つかりませんでした。';
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
<main class="app-main">
    <?php if ($message !== null): ?>
        <div class="alert"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>1. 教科を選ぼう</h2>
        <div class="card-grid">
            <?php foreach ($subjects as $subject): ?>
                <?php $isActive = $selectedSubject !== null && $subject['id'] === $selectedSubject['id']; ?>
                <a class="card <?= $isActive ? 'is-active' : '' ?>" href="?subject=<?= h($subject['id']) ?>">
                    <h3><?= h($subject['name'] ?? $subject['id']) ?></h3>
                    <p><?= h($subject['description'] ?? '') ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($selectedSubject !== null): ?>
        <?php $units = $selectedSubject['units'] ?? []; ?>
        <section class="panel">
            <h2>2. 単元を選ぼう (<?= h($selectedSubject['name']) ?>)</h2>
            <?php if ($units === []): ?>
                <p>この教科の単元はまだ登録されていません。</p>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($units as $unit): ?>
                        <?php $isActiveUnit = $selectedUnit !== null && $unit['id'] === $selectedUnit['id']; ?>
                        <a class="card <?= $isActiveUnit ? 'is-active' : '' ?>" href="?subject=<?= h($selectedSubject['id']) ?>&amp;unit=<?= h($unit['id']) ?>">
                            <h3><?= h($unit['name'] ?? $unit['id']) ?></h3>
                            <p class="meta">対象: <?= h($unit['grade'] ?? '---') ?></p>
                            <p><?= h($unit['overview'] ?? '') ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($selectedSubject !== null && $selectedUnit !== null): ?>
        <section class="panel learning-panel">
            <div class="learning-content">
                <h2>3. 学習コンテンツ (<?= h($selectedUnit['name']) ?>)</h2>
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

            <aside class="tutor-chat" id="chat-section" data-subject="<?= h($selectedSubject['id']) ?>" data-unit="<?= h($selectedUnit['id']) ?>">
                <h2>4. 家庭教師に質問しよう</h2>
                <p class="chat-description">分からないことがあれば、メッセージを送ってみましょう。学習中の内容を踏まえてヒントや解説が返ってきます。</p>
                <div id="chat-history" class="chat-history" aria-live="polite"></div>
                <form id="tutor-form" class="chat-form">
                    <label for="question">質問を入力</label>
                    <textarea id="question" name="question" rows="3" placeholder="例: 通分の方法をもう一度教えてください"></textarea>
                    <button type="submit">送信</button>
                </form>
                <p class="chat-note">※ OpenAI API を利用して回答します。API キーが設定されていない場合はデモ応答になります。</p>
            </aside>
        </section>
    <?php elseif ($selectedSubject !== null): ?>
        <section class="panel">
            <p>学習を始める単元を選んでください。</p>
        </section>
    <?php else: ?>
        <section class="panel">
            <p>興味のある教科を選ぶと、単元と学習コンテンツが表示されます。</p>
        </section>
    <?php endif; ?>
</main>
<footer class="app-footer">
    <p>&copy; <?= date('Y') ?> Personal Tutor</p>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
