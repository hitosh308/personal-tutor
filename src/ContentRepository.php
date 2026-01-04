<?php

declare(strict_types=1);

namespace PersonalTutor;

use RuntimeException;

class ContentRepository
{
    private array $grades;

    public function __construct(string $dataDirectory)
    {
        if (!is_dir($dataDirectory)) {
            throw new RuntimeException('コンテンツフォルダが見つかりません: ' . $dataDirectory);
        }

        $this->grades = $this->loadGrades($dataDirectory);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGrades(): array
    {
        return $this->grades;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findGrade(string $gradeId): ?array
    {
        foreach ($this->getGrades() as $grade) {
            if (($grade['id'] ?? null) === $gradeId) {
                return $grade;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSubjects(string $gradeId): array
    {
        $grade = $this->findGrade($gradeId);

        if (!$grade) {
            return [];
        }

        $subjects = $grade['subjects'] ?? [];

        if (!is_array($subjects)) {
            return [];
        }

        return array_map(fn (array $subject) => $this->attachGradeMetadata($subject, $grade), $subjects);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSubject(string $subjectId, ?string $gradeId = null): ?array
    {
        if ($gradeId !== null && $gradeId !== '') {
            $grade = $this->findGrade($gradeId);
            if ($grade !== null) {
                foreach ($this->getSubjects($gradeId) as $subject) {
                    if (($subject['id'] ?? null) === $subjectId) {
                        return $subject;
                    }
                }
            }

            return null;
        }

        foreach ($this->getGrades() as $grade) {
            $subjects = $grade['subjects'] ?? [];
            if (!is_array($subjects)) {
                continue;
            }

            foreach ($subjects as $subject) {
                if (($subject['id'] ?? null) === $subjectId) {
                    return $this->attachGradeMetadata($subject, $grade);
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUnit(string $subjectId, string $unitId, ?string $gradeId = null): ?array
    {
        $units = $this->getUnits($subjectId, $gradeId);

        foreach ($units as $unit) {
            if (($unit['id'] ?? null) === $unitId) {
                return $unit;
            }
        }

        return null;
    }
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUnits(string $subjectId, ?string $gradeId = null): array
    {
        $subject = $this->findSubject($subjectId, $gradeId);

        if (!$subject) {
            return [];
        }

        $units = $subject['units'] ?? [];

        return is_array($units) ? $units : [];
    }

    public function buildContextText(array $subject, array $unit): string
    {
        $lines = [];
        $lines[] = 'Grade: ' . ($subject['grade_name'] ?? $subject['grade_id'] ?? '');
        $lines[] = 'Subject: ' . ($subject['name'] ?? $subject['id'] ?? '');
        $lines[] = 'Unit: ' . ($unit['name'] ?? $unit['id'] ?? '');

        if (!empty($unit['grade'])) {
            $lines[] = 'Target grade: ' . $unit['grade'];
        }

        if (!empty($unit['overview'])) {
            $lines[] = 'Overview: ' . $unit['overview'];
        }

        if (!empty($unit['goals']) && is_array($unit['goals'])) {
            $lines[] = 'Learning goals: ' . implode('; ', $unit['goals']);
        }

        if (!empty($unit['explanation'])) {
            $lines[] = 'Explanation: ' . $this->htmlToText((string) $unit['explanation']);
        }

        if (!empty($unit['exercises']) && is_array($unit['exercises'])) {
            $exerciseLines = [];
            foreach ($unit['exercises'] as $index => $exercise) {
                $number = $index + 1;
                $exerciseLines[] = sprintf('Q%d: %s', $number, $exercise['question'] ?? '');
                if (!empty($exercise['hint'])) {
                    $exerciseLines[] = sprintf('Hint: %s', $exercise['hint']);
                }
                if (!empty($exercise['answer'])) {
                    $exerciseLines[] = sprintf('Answer: %s', $exercise['answer']);
                }
            }

            if ($exerciseLines !== []) {
                $lines[] = 'Exercises:';
                foreach ($exerciseLines as $exerciseLine) {
                    $lines[] = ' - ' . $exerciseLine;
                }
            }
        }

        return trim(implode("\n", array_filter($lines)));
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<li[^>]*>/i', "\n- ", $html);
        $text = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $text ?? '');
        $text = preg_replace('/<(p|br|div|h[1-6])[^>]*>/i', "\n", $text ?? '');
        $text = preg_replace('/<[^>]+>/', '', $text ?? '');
        $text = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n+/", "\n", $text ?? '');
        $text = preg_replace('/\s+/u', ' ', $text ?? '');

        return trim((string) $text);
    }

    /**
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $grade
     * @return array<string, mixed>
     */
    private function attachGradeMetadata(array $subject, array $grade): array
    {
        $subject['grade_id'] = $grade['id'] ?? null;
        $subject['grade_name'] = $grade['name'] ?? null;

        return $subject;
    }

    private function loadGrades(string $dataDirectory): array
    {
        $entries = scandir($dataDirectory);

        if ($entries === false) {
            throw new RuntimeException('コンテンツフォルダを読み込めませんでした: ' . $dataDirectory);
        }

        $grades = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $gradeDirectory = rtrim($dataDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($gradeDirectory)) {
                continue;
            }

            $gradeData = $this->loadGrade($gradeDirectory, $entry);
            $grades[] = $gradeData;
        }

        usort($grades, fn (array $a, array $b) => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));

        return $grades;
    }

    private function loadGrade(string $gradeDirectory, string $gradeId): array
    {
        $metadata = $this->loadGradeMetadata($gradeDirectory, $gradeId);
        $subjects = $this->loadSubjects($gradeDirectory, $metadata);

        return [
            'id' => $gradeId,
            'name' => $metadata['name'],
            'description' => $metadata['description'],
            'subjects' => $subjects,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function loadGradeMetadata(string $gradeDirectory, string $gradeId): array
    {
        $metadataPath = rtrim($gradeDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'grade.json';

        $defaults = [
            'id' => $gradeId,
            'name' => $gradeId,
            'description' => '',
        ];

        if (!is_file($metadataPath)) {
            return $defaults;
        }

        $json = file_get_contents($metadataPath);
        if ($json === false) {
            throw new RuntimeException('学年のメタデータを読み込めませんでした: ' . $metadataPath);
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('学年のメタデータの形式が正しくありません: ' . $metadataPath);
        }

        $id = (string) ($decoded['id'] ?? $gradeId);
        if ($id !== $gradeId) {
            throw new RuntimeException('学年フォルダ名と grade.json の id が一致しません: ' . $gradeId);
        }

        return [
            'id' => $gradeId,
            'name' => (string) ($decoded['name'] ?? $gradeId),
            'description' => (string) ($decoded['description'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $grade
     * @return array<int, array<string, mixed>>
     */
    private function loadSubjects(string $gradeDirectory, array $grade): array
    {
        $entries = scandir($gradeDirectory);

        if ($entries === false) {
            throw new RuntimeException('学年フォルダを読み込めませんでした: ' . $gradeDirectory);
        }

        $subjects = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'grade.json') {
                continue;
            }

            if (!preg_match('/\.json$/i', $entry)) {
                continue;
            }

            $path = rtrim($gradeDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }

            $subject = $this->loadSubjectFile($path);
            $subjects[] = $this->attachGradeMetadata($subject, $grade);
        }

        usort($subjects, fn (array $a, array $b) => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));

        return $subjects;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSubjectFile(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('教科データを読み込めませんでした: ' . $path);
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('教科データの形式が正しくありません: ' . $path);
        }

        $filename = pathinfo($path, PATHINFO_FILENAME);
        $id = (string) ($decoded['id'] ?? $filename);

        $units = [];
        if (isset($decoded['units']) && is_array($decoded['units'])) {
            foreach ($decoded['units'] as $unit) {
                if (is_array($unit)) {
                    $units[] = $unit;
                }
            }
        }

        return [
            'id' => $id,
            'name' => (string) ($decoded['name'] ?? $id),
            'description' => (string) ($decoded['description'] ?? ''),
            'units' => $units,
        ];
    }
}
