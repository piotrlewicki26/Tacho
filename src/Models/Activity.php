<?php
declare(strict_types=1);
namespace Models;

use Core\Database;

/**
 * Activity model + EU Regulation 561/2006 violation detection.
 */
class Activity
{
    // ── CRUD ───────────────────────────────────────────────────────────────

    public function saveMany(array $activities): void
    {
        foreach ($activities as $a) {
            Database::insert('activities', $a);
        }
    }

    /** Activities for a driver on a given date. */
    public function forDay(int $driverId, string $date): array
    {
        return Database::fetchAll(
            'SELECT * FROM activities
             WHERE driver_id = :did AND activity_date = :dt
             ORDER BY start_time',
            ['did' => $driverId, 'dt' => $date]
        );
    }

    /** Activities for a driver in a date range. */
    public function forRange(int $driverId, string $from, string $to): array
    {
        return Database::fetchAll(
            'SELECT * FROM activities
             WHERE driver_id = :did AND activity_date BETWEEN :f AND :t
             ORDER BY activity_date, start_time',
            ['did' => $driverId, 'f' => $from, 't' => $to]
        );
    }

    /** Activities grouped by date for a given file. */
    public function forFile(int $fileId): array
    {
        return Database::fetchAll(
            'SELECT * FROM activities WHERE tacho_file_id = :fid ORDER BY activity_date, start_time',
            ['fid' => $fileId]
        );
    }

    /** Daily totals (minutes per activity type). */
    public function dailyTotals(int $driverId, string $date): array
    {
        $rows = Database::fetchAll(
            'SELECT activity_type, SUM(duration_minutes) AS total
             FROM activities
             WHERE driver_id = :did AND activity_date = :dt
             GROUP BY activity_type',
            ['did' => $driverId, 'dt' => $date]
        );
        $out = ['driving' => 0, 'work' => 0, 'availability' => 0, 'rest' => 0, 'break' => 0];
        foreach ($rows as $r) $out[$r['activity_type']] = (int) $r['total'];
        return $out;
    }

    /** Weekly totals (minutes per day per activity type). */
    public function weeklyTotals(int $driverId, string $weekStart): array
    {
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $rows = Database::fetchAll(
            'SELECT activity_date, activity_type, SUM(duration_minutes) AS total
             FROM activities
             WHERE driver_id = :did AND activity_date BETWEEN :s AND :e
             GROUP BY activity_date, activity_type
             ORDER BY activity_date',
            ['did' => $driverId, 's' => $weekStart, 'e' => $weekEnd]
        );

        $out = [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime($weekStart . " +$i days"));
            $out[$d] = ['driving' => 0, 'work' => 0, 'availability' => 0, 'rest' => 0, 'break' => 0];
        }
        foreach ($rows as $r) {
            if (isset($out[$r['activity_date']])) {
                $out[$r['activity_date']][$r['activity_type']] = (int) $r['total'];
            }
        }
        return $out;
    }

    // ── Violation Detection ────────────────────────────────────────────────
    // Implements EU Regulation (EC) No 561/2006

    /**
     * Detect violations for a given set of activities (one day).
     * Returns array of violation records ready for DB insert.
     */
    public function detectDailyViolations(array $activities, int $driverId, int $fileId): array
    {
        $violations = [];

        // Group driving activities
        $drivingMinutes   = 0;
        $continuousDriving = 0; // minutes of uninterrupted driving
        $lastBreakMinutes  = 0; // accumulated break/rest minutes since last 4.5h block
        $prevType          = null;
        $prevEnd           = null;

        foreach ($activities as $act) {
            $type = $act['activity_type'];
            $dur  = (int) $act['duration_minutes'];

            if ($type === 'driving') {
                $drivingMinutes    += $dur;
                $continuousDriving += $dur;
                $lastBreakMinutes   = 0;
            } elseif (in_array($type, ['break', 'rest'], true)) {
                $lastBreakMinutes += $dur;
                if ($lastBreakMinutes >= 45) {
                    $continuousDriving = 0; // reset after sufficient break
                }
            }
        }

        // Art. 6(1) – Daily driving > 9 h (10 h allowed max 2×/week, not tracked here, use 9h)
        if ($drivingMinutes > 540) { // 9 h = 540 min
            $severity = $drivingMinutes > 600 ? 'major' : 'minor'; // > 10 h = major
            $violations[] = $this->buildViolation(
                $driverId, $fileId, $activities[0]['id'] ?? null,
                'DAILY_DRIVING_EXCEEDED',
                sprintf(
                    'Przekroczenie dziennego czasu jazdy: %dh %dm (limit 9h, maks. 10h)',
                    intdiv($drivingMinutes, 60), $drivingMinutes % 60
                ),
                $severity,
                'Art. 6(1) Rozp. 561/2006',
                200.00, 500.00
            );
        }

        // Art. 7 – Continuous driving > 4.5 h without 45-min break
        if ($continuousDriving > 270) { // 4.5 h = 270 min
            $violations[] = $this->buildViolation(
                $driverId, $fileId, null,
                'CONTINUOUS_DRIVING_EXCEEDED',
                sprintf(
                    'Ciągła jazda bez przerwy: %dh %dm (limit 4,5h bez 45 min przerwy)',
                    intdiv($continuousDriving, 60), $continuousDriving % 60
                ),
                'minor',
                'Art. 7 Rozp. 561/2006',
                200.00, 500.00
            );
        }

        // Art. 8(1) – Daily rest < 11 h (reduced: 9 h allowed 3×/week)
        $restMinutes = array_sum(array_map(
            fn($a) => in_array($a['activity_type'], ['rest', 'break']) ? (int)$a['duration_minutes'] : 0,
            $activities
        ));
        if ($restMinutes < 540) { // 9 h minimum reduced rest
            $violations[] = $this->buildViolation(
                $driverId, $fileId, null,
                'DAILY_REST_INSUFFICIENT',
                sprintf(
                    'Niewystarczający dzienny czas odpoczynku: %dh %dm (min. 9h skrócony, 11h normalny)',
                    intdiv($restMinutes, 60), $restMinutes % 60
                ),
                $restMinutes < 480 ? 'major' : 'minor',
                'Art. 8(1) Rozp. 561/2006',
                200.00, 500.00
            );
        }

        return $violations;
    }

    /**
     * Detect weekly violations for a given date range (7 days).
     */
    public function detectWeeklyViolations(int $driverId, int $fileId, string $weekStart): array
    {
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $violations = [];

        $weeklyDriving = (int) Database::fetchColumn(
            "SELECT COALESCE(SUM(duration_minutes),0) FROM activities
             WHERE driver_id = :did AND activity_date BETWEEN :s AND :e AND activity_type = 'driving'",
            ['did' => $driverId, 's' => $weekStart, 'e' => $weekEnd]
        );

        // Art. 6(2) – Weekly driving > 56 h
        if ($weeklyDriving > 3360) {
            $violations[] = $this->buildViolation(
                $driverId, $fileId, null,
                'WEEKLY_DRIVING_EXCEEDED',
                sprintf(
                    'Przekroczenie tygodniowego czasu jazdy: %dh %dm (limit 56h)',
                    intdiv($weeklyDriving, 60), $weeklyDriving % 60
                ),
                'major',
                'Art. 6(2) Rozp. 561/2006',
                500.00, 2000.00
            );
        }

        // Art. 8(6) – Weekly rest < 45 h
        $weeklyRest = (int) Database::fetchColumn(
            "SELECT COALESCE(SUM(duration_minutes),0) FROM activities
             WHERE driver_id = :did AND activity_date BETWEEN :s AND :e
               AND activity_type IN ('rest','break')",
            ['did' => $driverId, 's' => $weekStart, 'e' => $weekEnd]
        );

        if ($weeklyRest < 2700) { // 45 h
            $violations[] = $this->buildViolation(
                $driverId, $fileId, null,
                'WEEKLY_REST_INSUFFICIENT',
                sprintf(
                    'Niewystarczający tygodniowy czas odpoczynku: %dh %dm (min. 45h, skrócony 24h)',
                    intdiv($weeklyRest, 60), $weeklyRest % 60
                ),
                $weeklyRest < 1440 ? 'critical' : 'major',
                'Art. 8(6) Rozp. 561/2006',
                500.00, 2000.00
            );
        }

        return $violations;
    }

    /** Check bi-weekly limit (Art. 6(3) – 90 h over two consecutive weeks). */
    public function detectBiweeklyViolations(int $driverId, int $fileId, string $week1Start): array
    {
        $week2End = date('Y-m-d', strtotime($week1Start . ' +13 days'));
        $violations = [];

        $biweekly = (int) Database::fetchColumn(
            "SELECT COALESCE(SUM(duration_minutes),0) FROM activities
             WHERE driver_id = :did AND activity_date BETWEEN :s AND :e AND activity_type = 'driving'",
            ['did' => $driverId, 's' => $week1Start, 'e' => $week2End]
        );

        if ($biweekly > 5400) { // 90 h
            $violations[] = $this->buildViolation(
                $driverId, $fileId, null,
                'BIWEEKLY_DRIVING_EXCEEDED',
                sprintf(
                    'Przekroczenie czasu jazdy w dwóch tygodniach: %dh %dm (limit 90h)',
                    intdiv($biweekly, 60), $biweekly % 60
                ),
                'critical',
                'Art. 6(3) Rozp. 561/2006',
                500.00, 2000.00
            );
        }

        return $violations;
    }

    public function saveViolations(array $violations): void
    {
        foreach ($violations as $v) {
            Database::insert('violations', $v);
        }
    }

    public function violationsForFile(int $fileId): array
    {
        return Database::fetchAll(
            'SELECT v.*, CONCAT(d.first_name," ",d.last_name) AS driver_name
             FROM violations v
             LEFT JOIN drivers d ON d.id = v.driver_id
             WHERE v.tacho_file_id = :fid
             ORDER BY v.created_at',
            ['fid' => $fileId]
        );
    }

    public function recentViolations(int $companyId, int $limit = 10): array
    {
        return Database::fetchAll(
            'SELECT v.*, CONCAT(d.first_name," ",d.last_name) AS driver_name
             FROM violations v
             JOIN drivers d ON d.id = v.driver_id
             WHERE d.company_id = :cid
             ORDER BY v.created_at DESC LIMIT ' . (int)$limit,
            ['cid' => $companyId]
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function buildViolation(
        int $driverId, int $fileId, ?int $activityId,
        string $type, string $desc, string $severity,
        string $ref, float $fineMin, float $fineMax
    ): array {
        return [
            'driver_id'       => $driverId,
            'tacho_file_id'   => $fileId,
            'activity_id'     => $activityId,
            'violation_type'  => $type,
            'description'     => $desc,
            'severity'        => $severity,
            'regulation_ref'  => $ref,
            'fine_amount_min' => $fineMin,
            'fine_amount_max' => $fineMax,
        ];
    }
}
