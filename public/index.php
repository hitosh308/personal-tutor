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

$pageTitle = 'Personal Tutor 教科と単元の選択';
if ($selectedSubject !== null) {
    $pageTitle = $selectedSubject['name'] . ' - ' . $pageTitle;
}
if ($selectedUnit !== null) {
    $pageTitle = $selectedUnit['name'] . ' - ' . $pageTitle;
}

$startUrl = null;
if ($selectedSubject !== null && $selectedUnit !== null) {
    $startUrl = 'learn.php?subject=' . rawurlencode($selectedSubject['id']) . '&unit=' . rawurlencode($selectedUnit['id']);
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

    <?php if ($selectedSubject !== null && $selectedUnit !== null && $startUrl !== null): ?>
        <section class="panel start-panel">
            <h2>3. 学習を始めよう</h2>
            <p class="start-panel__summary">
                選択中: <strong><?= h($selectedSubject['name']) ?></strong> / <strong><?= h($selectedUnit['name']) ?></strong>
            </p>
            <?php if (!empty($selectedUnit['grade'])): ?>
                <p class="start-panel__meta">対象: <?= h($selectedUnit['grade']) ?></p>
            <?php endif; ?>
            <?php if (!empty($selectedUnit['overview'])): ?>
                <p class="start-panel__overview"><?= h($selectedUnit['overview']) ?></p>
            <?php endif; ?>
            <a class="primary-button" href="<?= h($startUrl) ?>">学習ルームを開く</a>
        </section>
    <?php elseif ($selectedSubject !== null): ?>
        <section class="panel info-panel">
            <p>学習を始める単元を選んでください。</p>
        </section>
    <?php else: ?>
        <section class="panel info-panel">
            <p>興味のある教科を選ぶと、単元と学習コンテンツの一覧が表示されます。</p>
        </section>
    <?php endif; ?>
</main>
<footer class="app-footer">
    <p>&copy; <?= date('Y') ?> Personal Tutor</p>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
