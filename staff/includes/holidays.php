<?php
/**
 * Sapphire Aesthetic & Luxury - Sri Lanka Holidays & Google Calendar Integration
 * Handles Full Moon Poya Days, Mercantile Holidays, and Public Holidays
 */

if (!defined('SRI_LANKA_HOLIDAYS_LOADED')) {
    define('SRI_LANKA_HOLIDAYS_LOADED', true);
}

/**
 * Ensure holidays table exists and has seed data
 */
function ensure_holidays_table($pdo) {
    if (!isset($pdo)) return;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `holidays` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `date` DATE NOT NULL UNIQUE,
                `title` VARCHAR(128) NOT NULL,
                `type` ENUM('Poya', 'Mercantile', 'Public', 'Bank', 'Custom') NOT NULL DEFAULT 'Mercantile',
                `is_working_day` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = Off Day, 1 = Open with OT',
                `description` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Check if table is empty
        $count = (int)$pdo->query("SELECT COUNT(*) FROM holidays")->fetchColumn();
        if ($count === 0) {
            seed_default_sri_lanka_holidays($pdo);
        }
    } catch (Exception $e) {
        // Silently catch in case of table permission limits
    }
}

/**
 * Seed statutory Sri Lankan holidays (2025 - 2027)
 */
function seed_default_sri_lanka_holidays($pdo) {
    if (!isset($pdo)) return 0;
    
    $defaultHolidays = [
        // 2025 Holidays
        ['2025-01-13', 'Duruthu Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-01-14', 'Tamil Thai Pongal Day', 'Mercantile', 'Mercantile & Public Holiday'],
        ['2025-02-04', 'National Day', 'Mercantile', 'Independence Day - Mercantile Holiday'],
        ['2025-02-12', 'Navam Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-02-26', 'Mahasivarathri Day', 'Public', 'Public Holiday'],
        ['2025-03-13', 'Madin Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-03-31', 'Eid al-Fitr (Ramazan Festival Day)', 'Public', 'Public Holiday'],
        ['2025-04-12', 'Bak Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-04-13', 'Sinhala and Tamil New Year Eve', 'Mercantile', 'Mercantile Holiday'],
        ['2025-04-14', 'Sinhala and Tamil New Year Day', 'Mercantile', 'Mercantile Holiday'],
        ['2025-04-18', 'Good Friday', 'Mercantile', 'Mercantile Holiday'],
        ['2025-05-01', 'May Day (International Workers\' Day)', 'Mercantile', 'Mercantile Holiday'],
        ['2025-05-12', 'Vesak Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-05-13', 'Day after Vesak Full Moon Poya Day', 'Poya', 'Mercantile & Public Holiday'],
        ['2025-06-07', 'Eid al-Adha (Hajj Festival Day)', 'Public', 'Public Holiday'],
        ['2025-06-10', 'Poson Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-07-10', 'Esala Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-08-08', 'Nikini Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-09-05', 'Milad-Un-Nabi (Holy Prophet\'s Birthday)', 'Mercantile', 'Mercantile Holiday'],
        ['2025-09-07', 'Binara Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-10-06', 'Vap Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-10-20', 'Deepavali Festival Day', 'Public', 'Public Holiday'],
        ['2025-11-05', 'Il Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-12-04', 'Unduvap Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2025-12-25', 'Christmas Day', 'Mercantile', 'Mercantile Holiday'],

        // 2026 Holidays
        ['2026-01-03', 'Duruthu Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-01-15', 'Tamil Thai Pongal Day', 'Mercantile', 'Mercantile & Public Holiday'],
        ['2026-02-01', 'Navam Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-02-04', 'National Day', 'Mercantile', 'Independence Day - Mercantile Holiday'],
        ['2026-02-15', 'Mahasivarathri Day', 'Public', 'Public Holiday'],
        ['2026-03-02', 'Madin Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-03-21', 'Eid al-Fitr (Ramazan Festival Day)', 'Public', 'Public Holiday'],
        ['2026-04-01', 'Bak Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-04-03', 'Good Friday', 'Mercantile', 'Mercantile Holiday'],
        ['2026-04-13', 'Sinhala and Tamil New Year Eve', 'Mercantile', 'Mercantile Holiday'],
        ['2026-04-14', 'Sinhala and Tamil New Year Day', 'Mercantile', 'Mercantile Holiday'],
        ['2026-05-01', 'Adhi Vesak Full Moon Poya Day / May Day', 'Mercantile', 'Full Moon Poya & May Day (Mercantile)'],
        ['2026-05-28', 'Eid al-Adha (Hajj Festival Day)', 'Public', 'Public Holiday'],
        ['2026-05-30', 'Vesak Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-05-31', 'Day after Vesak Full Moon Poya Day', 'Poya', 'Mercantile & Public Holiday'],
        ['2026-06-29', 'Poson Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-07-29', 'Esala Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-08-27', 'Nikini Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-08-26', 'Milad-Un-Nabi (Holy Prophet\'s Birthday)', 'Mercantile', 'Mercantile Holiday'],
        ['2026-09-26', 'Binara Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-10-25', 'Vap Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-11-08', 'Deepavali Festival Day', 'Public', 'Public Holiday'],
        ['2026-11-24', 'Il Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-12-23', 'Unduvap Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2026-12-25', 'Christmas Day', 'Mercantile', 'Mercantile Holiday'],

        // 2027 Holidays
        ['2027-01-14', 'Tamil Thai Pongal Day', 'Mercantile', 'Mercantile Holiday'],
        ['2027-01-22', 'Duruthu Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-02-04', 'National Day', 'Mercantile', 'Mercantile Holiday'],
        ['2027-02-21', 'Navam Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-03-22', 'Madin Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-04-13', 'Sinhala and Tamil New Year Eve', 'Mercantile', 'Mercantile Holiday'],
        ['2027-04-14', 'Sinhala and Tamil New Year Day', 'Mercantile', 'Mercantile Holiday'],
        ['2027-04-21', 'Bak Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-05-01', 'May Day', 'Mercantile', 'Mercantile Holiday'],
        ['2027-05-20', 'Vesak Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-05-21', 'Day after Vesak Full Moon Poya Day', 'Poya', 'Mercantile Holiday'],
        ['2027-06-19', 'Poson Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-07-18', 'Esala Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-08-17', 'Nikini Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-09-15', 'Binara Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-10-15', 'Vap Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-11-13', 'Il Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-12-13', 'Unduvap Full Moon Poya Day', 'Poya', 'Full Moon Poya Day'],
        ['2027-12-25', 'Christmas Day', 'Mercantile', 'Mercantile Holiday']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO holidays (date, title, type, is_working_day, description)
        VALUES (?, ?, ?, 0, ?)
        ON DUPLICATE KEY UPDATE title = VALUES(title), type = VALUES(type), description = VALUES(description)
    ");

    $count = 0;
    foreach ($defaultHolidays as $h) {
        $stmt->execute([$h[0], $h[1], $h[2], $h[3]]);
        $count++;
    }
    return $count;
}

/**
 * Live Sync Sri Lankan Holidays from Google Calendar Public iCal Feed
 * Google Calendar ID: en.lk#holiday@group.v.calendar.google.com
 */
function sync_google_calendar_holidays($pdo) {
    if (!isset($pdo)) {
        return ['success' => false, 'message' => 'No database connection.'];
    }

    $gcalUrl = 'https://calendar.google.com/calendar/ical/en.lk%23holiday%40group.v.calendar.google.com/public/basic.ics';
    
    // Set timeout context
    $ctx = stream_context_create([
        'http' => ['timeout' => 8],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    $icsData = @file_get_contents($gcalUrl, false, $ctx);
    if (!$icsData) {
        // Fallback to internal seed if network fails
        $seeded = seed_default_sri_lanka_holidays($pdo);
        return [
            'success' => true,
            'count' => $seeded,
            'source' => 'Built-in Sri Lanka Statutory Calendar (Offline Fallback)',
            'message' => "Google Calendar connection timed out. Updated {$seeded} holidays from verified Sri Lankan statutory database."
        ];
    }

    preg_match_all('/BEGIN:VEVENT([\s\S]*?)END:VEVENT/', $icsData, $events);
    if (empty($events[1])) {
        return ['success' => false, 'message' => 'No events found in Google Calendar feed.'];
    }

    $skipObservances = ["Valentine's Day", "Mother's Day", "Father's Day", "Halloween", "Christmas Eve", "New Year's Eve", "Fuel Conservation", "Bad Weather"];

    $stmt = $pdo->prepare("
        INSERT INTO holidays (date, title, type, is_working_day, description)
        VALUES (?, ?, ?, 0, ?)
        ON DUPLICATE KEY UPDATE title = VALUES(title), type = VALUES(type), description = VALUES(description)
    ");

    $synced = 0;
    foreach ($events[1] as $ev) {
        preg_match('/SUMMARY:(.*?)\r?\n/', $ev, $s);
        preg_match('/DTSTART(?:;VALUE=DATE)?:(\d{8})/', $ev, $d);
        preg_match('/DESCRIPTION:(.*?)\r?\n/', $ev, $desc);

        if (empty($s[1]) || empty($d[1])) continue;

        $summary = trim(str_replace(['\,', '\;'], [',', ';'], $s[1]));
        $description = trim(str_replace(['\,', '\;'], [',', ';'], $desc[1] ?? ''));
        $dateStr = substr($d[1], 0, 4) . '-' . substr($d[1], 4, 2) . '-' . substr($d[1], 6, 2);
        $year = (int)substr($d[1], 0, 4);

        if ($year < 2024 || $year > 2028) continue;

        // Skip non-statutory observances
        $skip = false;
        foreach ($skipObservances as $so) {
            if (stripos($summary, $so) !== false) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        // Categorize
        $type = 'Public';
        if (stripos($summary, 'Poya') !== false) {
            $type = 'Poya';
        } elseif (
            stripos($summary, 'New Year') !== false ||
            stripos($summary, 'May Day') !== false ||
            stripos($summary, 'Christmas') !== false ||
            stripos($summary, 'National Day') !== false ||
            stripos($summary, 'Thai Pongal') !== false ||
            stripos($summary, 'Milad') !== false ||
            stripos($summary, 'Good Friday') !== false
        ) {
            $type = 'Mercantile';
        }

        $stmt->execute([$dateStr, $summary, $type, $description ?: 'Google Calendar Official Holiday']);
        $synced++;
    }

    return [
        'success' => true,
        'count' => $synced,
        'source' => 'Google Calendar (Sri Lanka Official Holidays Feed)',
        'message' => "Successfully synchronized {$synced} Sri Lankan Poya Days & Mercantile Holidays directly from Google Calendar!"
    ];
}

/**
 * Fetch map of holidays indexed by 'YYYY-MM-DD'
 */
function get_holidays_map($pdo, $startDate, $endDate) {
    if (!isset($pdo)) return [];
    try {
        $stmt = $pdo->prepare("SELECT date, title, type, is_working_day, description FROM holidays WHERE date BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[$row['date']] = $row;
        }
        return $map;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Calculate complete daily timesheet and adjusted No-Pay days for an employee over a date range
 */
function calculate_employee_timesheet($pdo, $employeeId, $startDate, $endDate) {
    if (!isset($pdo)) return ['employee' => null, 'days' => [], 'totals' => []];

    // Fetch employee details
    $empStmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $empStmt->execute([$employeeId]);
    $emp = $empStmt->fetch();
    if (!$emp) return ['employee' => null, 'days' => [], 'totals' => []];

    // Fetch attendance logs
    $attStmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
    $attStmt->execute([$employeeId, $startDate, $endDate]);
    $punches = [];
    while ($r = $attStmt->fetch(PDO::FETCH_ASSOC)) {
        $punches[$r['date']] = $r;
    }

    // Fetch approved leaves overlapping date range
    $lStmt = $pdo->prepare("
        SELECT * FROM leaves 
        WHERE employee_id = ? AND status = 'Approved' 
          AND NOT (end_date < ? OR start_date > ?)
        ORDER BY start_date ASC
    ");
    $lStmt->execute([$employeeId, $startDate, $endDate]);
    $approvedLeaves = $lStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch holidays
    $holidaysMap = get_holidays_map($pdo, $startDate, $endDate);

    $cur = strtotime($startDate);
    $end = strtotime($endDate);
    $todayStr = date('Y-m-d');

    $days = [];
    $totals = [
        'calendar_days'   => 0,
        'working_days'    => 0,
        'present_days'    => 0,
        'late_count'      => 0,
        'total_late_mins' => 0,
        'total_ot_mins'   => 0,
        'total_work_hrs'  => 0.0,
        'leave_days'      => 0,
        'nopay_days'      => 0,
        'holiday_days'    => 0,
        'off_days'        => 0
    ];

    while ($cur <= $end) {
        $dStr = date('Y-m-d', $cur);
        $dayName = date('l', $cur);
        $isSunday = ($dayName === 'Sunday');
        $isFuture = ($dStr > $todayStr);

        $totals['calendar_days']++;

        $row = [
            'date'              => $dStr,
            'day'               => $dayName,
            'day_short'         => date('D', $cur),
            'check_in'          => null,
            'check_out'         => null,
            'work_hours'        => 0.0,
            'late_minutes'      => 0,
            'overtime_minutes'  => 0,
            'status'            => '',
            'status_code'       => '', // present, late, half_day, leave, nopay, holiday, off, future
            'badge_color'       => '',
            'notes'             => '',
            'is_nopay'          => false
        ];

        // 1. Employee punched in on this date
        if (isset($punches[$dStr])) {
            $p = $punches[$dStr];
            $row['check_in'] = $p['check_in'];
            $row['check_out'] = $p['check_out'];
            $row['work_hours'] = (float)$p['work_hours'];
            $row['late_minutes'] = (int)$p['late_minutes'];
            $row['overtime_minutes'] = (int)$p['overtime_minutes'];
            $row['status'] = $p['status'];
            $row['notes'] = $p['remarks'] ?: '';

            $totals['present_days']++;
            $totals['total_work_hrs'] += $row['work_hours'];

            if ($row['late_minutes'] > 0) {
                $totals['late_count']++;
                $totals['total_late_mins'] += $row['late_minutes'];
                $row['status_code'] = 'late';
                $row['badge_color'] = 'bg-amber-500/15 text-amber-300 border-amber-500/30';
            } else {
                $row['status_code'] = 'present';
                $row['badge_color'] = 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
            }

            if ($row['overtime_minutes'] > 0) {
                $totals['total_ot_mins'] += $row['overtime_minutes'];
            }

            if (!$isSunday && !isset($holidaysMap[$dStr])) {
                $totals['working_days']++;
            }
        } else {
            // 2. No punch recorded - Evaluate leave / holiday / weekly off / No-Pay
            $leaveMatch = null;
            foreach ($approvedLeaves as $al) {
                if ($dStr >= $al['start_date'] && $dStr <= $al['end_date']) {
                    $leaveMatch = $al;
                    break;
                }
            }

            if ($leaveMatch) {
                if ($leaveMatch['leave_type'] === 'No Pay') {
                    $row['status'] = 'No-Pay Leave';
                    $row['status_code'] = 'nopay';
                    $row['badge_color'] = 'bg-rose-500/15 text-rose-300 border-rose-500/30';
                    $row['notes'] = 'Authorized No-Pay Leave';
                    $row['is_nopay'] = true;
                    $totals['nopay_days']++;
                } else {
                    $row['status'] = 'Leave (' . $leaveMatch['leave_type'] . ')';
                    $row['status_code'] = 'leave';
                    $row['badge_color'] = 'bg-purple-500/15 text-purple-300 border-purple-500/30';
                    $row['notes'] = 'Approved ' . $leaveMatch['leave_type'] . ' Leave';
                    $totals['leave_days']++;
                }
                if (!$isSunday && !isset($holidaysMap[$dStr])) {
                    $totals['working_days']++;
                }
            } elseif (isset($holidaysMap[$dStr])) {
                $h = $holidaysMap[$dStr];
                $row['status'] = 'Holiday (' . $h['type'] . ')';
                $row['status_code'] = 'holiday';
                $row['badge_color'] = ($h['type'] === 'Poya') ? 'bg-amber-400/15 text-amber-300 border-amber-400/30' : 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30';
                $row['notes'] = $h['title'];
                $totals['holiday_days']++;
            } elseif ($isSunday) {
                $row['status'] = 'Weekly Off';
                $row['status_code'] = 'off';
                $row['badge_color'] = 'bg-slate-800 text-slate-400 border-slate-700';
                $row['notes'] = 'Sunday (Weekly Off)';
                $totals['off_days']++;
            } elseif ($isFuture) {
                $row['status'] = 'Upcoming';
                $row['status_code'] = 'future';
                $row['badge_color'] = 'bg-slate-800/50 text-slate-500 border-slate-800';
                $row['notes'] = 'Scheduled Work Day';
                $totals['working_days']++;
            } else {
                // 3. Regular working day, in the past, NO punch and NO leave -> NO-PAY (ABSENT)
                $row['status'] = 'No-Pay (Absent)';
                $row['status_code'] = 'nopay';
                $row['badge_color'] = 'bg-rose-500/20 text-rose-300 border-rose-500/40 font-bold';
                $row['notes'] = 'Unexcused Absence (Loss of Pay)';
                $row['is_nopay'] = true;
                $totals['nopay_days']++;
                $totals['working_days']++;
            }
        }

        $days[] = $row;
        $cur = strtotime('+1 day', $cur);
    }

    return [
        'employee' => $emp,
        'days'     => $days,
        'totals'   => $totals
    ];
}
