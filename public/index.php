<?php

declare(strict_types=1);

use PersonalTutor\ContentRepository;

require_once __DIR__ . '/../src/ContentRepository.php';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$repository = new ContentRepository(__DIR__ . '/../data/grades');
$grades = $repository->getGrades();
$elementaryGrades = array_values(array_filter(
    $grades,
    static fn (array $grade): bool => str_starts_with((string) ($grade['id'] ?? ''), 'elementary-')
));
$middleGrades = array_values(array_filter(
    $grades,
    static fn (array $grade): bool => str_starts_with((string) ($grade['id'] ?? ''), 'middle-')
));

$gradeId = isset($_GET['grade']) ? (string) $_GET['grade'] : null;
$subjectId = isset($_GET['subject']) ? (string) $_GET['subject'] : null;
$unitId = isset($_GET['unit']) ? (string) $_GET['unit'] : null;

$selectedGrade = null;
$selectedSubject = null;
$selectedUnit = null;
$message = null;

if ($gradeId !== null && $gradeId !== '') {
    $selectedGrade = $repository->findGrade($gradeId);
    if ($selectedGrade === null) {
        $message = '指定された学年が見つかりませんでした。';
    }
}

if ($message === null && $subjectId !== null && $subjectId !== '') {
    if ($selectedGrade === null) {
        $message = '学年を選び直してください。';
    } else {
        $selectedSubject = $repository->findSubject($subjectId, $selectedGrade['id']);
        if ($selectedSubject === null) {
            $message = '指定された教科が見つかりませんでした。';
        }
    }
}

if ($message === null && $selectedSubject !== null && $unitId !== null && $unitId !== '') {
    $selectedUnit = $repository->findUnit($selectedSubject['id'], $unitId, $selectedGrade['id']);
    if ($selectedUnit === null) {
        $message = '指定された単元が見つかりませんでした。';
    }
}

$subjects = $selectedGrade !== null ? $repository->getSubjects($selectedGrade['id']) : [];
$units = $selectedGrade !== null && $selectedSubject !== null ? $repository->getUnits($selectedSubject['id'], $selectedGrade['id']) : [];

$pageTitle = 'Personal Tutor 学年・教科・単元の選択';
if ($selectedGrade !== null) {
    $pageTitle = $selectedGrade['name'] . ' - ' . $pageTitle;
}
if ($selectedSubject !== null) {
    $pageTitle = $selectedSubject['name'] . ' - ' . $pageTitle;
}
if ($selectedUnit !== null) {
    $pageTitle = $selectedUnit['name'] . ' - ' . $pageTitle;
}

$startUrl = null;
if ($selectedGrade !== null && $selectedSubject !== null && $selectedUnit !== null) {
    $startUrl = 'learn.php?grade=' . rawurlencode($selectedGrade['id']) . '&subject=' . rawurlencode($selectedSubject['id']) . '&unit=' . rawurlencode($selectedUnit['id']);
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
        <h2>1. 学年を選ぼう</h2>
        <div class="grade-groups">
            <div class="grade-group">
                <h3 class="grade-group__title">小学生</h3>
                <div class="card-grid grade-grid">
                    <?php foreach ($elementaryGrades as $grade): ?>
                        <?php $isActiveGrade = $selectedGrade !== null && $grade['id'] === $selectedGrade['id']; ?>
                        <a class="card <?= $isActiveGrade ? 'is-active' : '' ?>" href="?grade=<?= h($grade['id']) ?>">
                            <h3><?= h($grade['name'] ?? $grade['id']) ?></h3>
                            <p><?= h($grade['description'] ?? '') ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="grade-group">
                <h3 class="grade-group__title">中学生</h3>
                <div class="card-grid grade-grid">
                    <?php foreach ($middleGrades as $grade): ?>
                        <?php $isActiveGrade = $selectedGrade !== null && $grade['id'] === $selectedGrade['id']; ?>
                        <a class="card <?= $isActiveGrade ? 'is-active' : '' ?>" href="?grade=<?= h($grade['id']) ?>">
                            <h3><?= h($grade['name'] ?? $grade['id']) ?></h3>
                            <p><?= h($grade['description'] ?? '') ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($selectedGrade !== null): ?>
        <section class="panel">
            <h2>2. 教科を選ぼう (<?= h($selectedGrade['name']) ?>)</h2>
            <?php if ($subjects === []): ?>
                <p>この学年の教科はまだ登録されていません。</p>
            <?php else: ?>
                <div class="card-grid subjects-grid">
                    <?php foreach ($subjects as $subject): ?>
                        <?php $isActiveSubject = $selectedSubject !== null && $subject['id'] === $selectedSubject['id']; ?>
                        <a class="card <?= $isActiveSubject ? 'is-active' : '' ?>" href="?grade=<?= h($selectedGrade['id']) ?>&amp;subject=<?= h($subject['id']) ?>">
                            <h3><?= h($subject['name'] ?? $subject['id']) ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($selectedGrade !== null && $selectedSubject !== null): ?>
        <section class="panel">
            <h2>3. 単元を選ぼう (<?= h($selectedSubject['name']) ?>)</h2>
            <?php if ($units === []): ?>
                <p>この教科の単元はまだ登録されていません。</p>
            <?php else: ?>
                <div class="card-grid">
                    <?php foreach ($units as $unit): ?>
                        <?php $isActiveUnit = $selectedUnit !== null && $unit['id'] === $selectedUnit['id']; ?>
                        <a
                            class="card <?= $isActiveUnit ? 'is-active' : '' ?>"
                            href="?grade=<?= h($selectedGrade['id']) ?>&amp;subject=<?= h($selectedSubject['id']) ?>&amp;unit=<?= h($unit['id']) ?>#start-panel"
                        >
                            <h3><?= h($unit['name'] ?? $unit['id']) ?></h3>
                            <?php if (!empty($unit['overview'])): ?>
                                <p><?= h($unit['overview']) ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($selectedGrade !== null && $selectedSubject !== null && $selectedUnit !== null && $startUrl !== null): ?>
        <section class="panel start-panel" id="start-panel">
            <h2>4. 学習を始めよう</h2>
            <p class="start-panel__summary">
                選択中: <strong><?= h($selectedGrade['name']) ?></strong> / <strong><?= h($selectedSubject['name']) ?></strong> / <strong><?= h($selectedUnit['name']) ?></strong>
            </p>
            <a class="primary-button" href="<?= h($startUrl) ?>">学習ルームを開く</a>
        </section>
    <?php elseif ($selectedGrade !== null && $selectedSubject !== null): ?>
        <section class="panel info-panel">
            <p>学習を始める単元を選んでください。</p>
        </section>
    <?php elseif ($selectedGrade !== null): ?>
        <section class="panel info-panel">
            <p>興味のある教科を選ぶと、この学年で学べる単元一覧が表示されます。</p>
        </section>
    <?php else: ?>
        <section class="panel info-panel">
            <p>学年を選ぶと、対応する教科と単元の一覧が表示されます。</p>
        </section>
    <?php endif; ?>
</main>
<footer class="app-footer">
    <p>&copy; <?= date('Y') ?> Personal Tutor</p>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
