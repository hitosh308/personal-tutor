<?php

declare(strict_types=1);

namespace PersonalTutor;

use RuntimeException;

class ContentRepository
{
    private array $data;

    public function __construct(string $jsonPath)
    {
        if (!is_file($jsonPath)) {
            throw new RuntimeException('コンテンツファイルが見つかりません: ' . $jsonPath);
        }

        $json = file_get_contents($jsonPath);
        if ($json === false) {
            throw new RuntimeException('コンテンツファイルを読み込めませんでした。');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['grades']) || !is_array($decoded['grades'])) {
            throw new RuntimeException('コンテンツデータの形式が正しくありません。');
        }

        $this->data = $decoded;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGrades(): array
    {
        return $this->data['grades'];
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
}
