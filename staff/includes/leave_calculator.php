<?php
/**
 * Sapphire Aesthetic & Luxury - Dynamic Leave Entitlement & Accrual Calculator
 *
 * Rules:
 * 1. Working Days: All 7 days a week (including Sundays & Poya Days) are open working days.
 *    No automatic off days. Staff take their scheduled offs using their Monthly Leaves per month.
 *
 * 2. Admin Custom Leave Override (`custom_leave_enabled = 1`):
 *    - Uses custom Annual, Casual, Monthly & Medical quotas configured by Admin in Employee Directory.
 *
 * 3. Confirmed Permanent Staff (`employment_type = 'confirmed'`):
 *    - 14 Annual Leaves + 7 Casual Leaves + 4 Monthly Leaves per month + 7 Medical Leaves.
 *    - Any annual leaves already taken earlier in the same calendar year remain deducted from the 14-day total.
 *
 * 4. Probation Staff (`employment_type = 'probation'`):
 *    a) Joining Calendar Year (e.g., joined Oct 2025, viewing Oct-Dec 2025):
 *       - 4 Monthly Leaves per month ONLY. Annual = 0, Casual = 0.
 *    b) Calendar Year Following Joining (Accrual Phase leading to Date of Appointment):
 *       - Starting January, accrues 0.5 day (Half Day) of Annual Leave per month
 *         (Jan = 0.5d, Feb = 1.0d, Mar = 1.5d ... up to appointment month, e.g., Oct = 5.0d).
 *       - Unspent half-days accumulate (e.g., Jan 0.5 + Feb 0.5 = 1.0 full day in Feb).
 *       - Casual Leave = 0 until post-appointment unlock.
 *    c) Post Date of Appointment (From 1st of month AFTER appointment month, e.g., Nov 1 for Oct appointment):
 *       - Full leave entitlement activates: 14 Annual + 7 Casual + 4 Monthly Leaves.
 *       - Any accrued annual leaves already used earlier in that year remain in `annual_used`,
 *         so remaining balance = 14.0 - annual_used (e.g., 2 days used -> 12 days remaining).
 */

if (!function_exists('calculate_employee_annual_leave_accrual')) {

    function calculate_employee_annual_leave_accrual($emp, $year = null, $month = null) {
        if (!$year) $year = (int)date('Y');
        if (!$month) $month = (int)date('m');

        $empType = $emp['employment_type'] ?? 'confirmed';
        $joinDate = !empty($emp['join_date']) ? $emp['join_date'] : date('Y-m-d');
        $joinT = strtotime($joinDate);
        $joinYear = (int)date('Y', $joinT);

        // Use explicit Date of Appointment if set by Admin; otherwise default to Join Date + 1 Year
        if (!empty($emp['appointment_date']) && $emp['appointment_date'] !== '0000-00-00') {
            $appointmentDate = $emp['appointment_date'];
            $appointmentT = strtotime($appointmentDate);
        } else {
            $appointmentT = strtotime('+1 year', $joinT);
            $appointmentDate = date('Y-m-d', $appointmentT);
        }

        $appointmentYear = (int)date('Y', $appointmentT);
        $appointmentMonth = (int)date('m', $appointmentT);

        // Full unlock begins on the 1st of the month AFTER the appointment month
        // (e.g., Appointment Oct 2026 -> Unlocks Nov 1, 2026)
        // (e.g., Appointment Dec 2026 -> Unlocks Jan 1, 2027)
        $unlockTs = mktime(0, 0, 0, $appointmentMonth + 1, 1, $appointmentYear);
        $unlockDateStr = date('M 1, Y', $unlockTs);
        $unlockShortStr = date('M 1', $unlockTs);

        // CASE 0: Admin Custom Leave Override
        if (!empty($emp['custom_leave_enabled']) && (int)$emp['custom_leave_enabled'] === 1) {
            $cAnnual  = isset($emp['custom_annual_quota'])  && $emp['custom_annual_quota']  !== '' ? (float)$emp['custom_annual_quota']  : 14.0;
            $cCasual  = isset($emp['custom_casual_quota'])  && $emp['custom_casual_quota']  !== '' ? (float)$emp['custom_casual_quota']  : 7.0;
            $cMonthly = isset($emp['custom_monthly_quota']) && $emp['custom_monthly_quota'] !== '' ? (float)$emp['custom_monthly_quota'] : 4.0;
            $cMedical = isset($emp['custom_medical_quota']) && $emp['custom_medical_quota'] !== '' ? (float)$emp['custom_medical_quota'] : 7.0;

            return [
                'stage'            => 'custom_override',
                'annual_total'     => $cAnnual,
                'casual_total'     => $cCasual,
                'monthly_quota'    => $cMonthly,
                'medical_total'    => $cMedical,
                'is_accruing'      => false,
                'accrued_months'   => 12,
                'appointment_date' => $appointmentDate,
                'unlock_date'      => $unlockDateStr,
                'badge'            => 'Custom Leave Quota (Admin)',
                'description'      => "Custom Admin Quota: {$cAnnual} Annual, {$cCasual} Casual, {$cMonthly} Monthly/mo, {$cMedical} Medical."
            ];
        }

        // CASE A: Confirmed Permanent Staff (already confirmed by management)
        if ($empType === 'confirmed') {
            return [
                'stage'            => 'confirmed_permanent',
                'annual_total'     => 14.0,
                'casual_total'     => 7.0,
                'monthly_quota'    => 4.0,
                'medical_total'    => 7.0,
                'is_accruing'      => false,
                'accrued_months'   => 12,
                'appointment_date' => $appointmentDate,
                'unlock_date'      => $unlockDateStr,
                'badge'            => 'Confirmed Employee',
                'description'      => 'Full 14 Annual + 7 Casual + 4 Monthly Leaves per month (any earlier annual leaves taken remain deducted).'
            ];
        }

        // CASE B: Probation — Viewing a year before or equal to Joining Calendar Year ($joinYear >= $year)
        if ($joinYear >= $year) {
            return [
                'stage'            => 'probation_joining_year',
                'annual_total'     => 0.0,
                'casual_total'     => 0.0,
                'monthly_quota'    => 4.0,
                'medical_total'    => 7.0,
                'is_accruing'      => false,
                'accrued_months'   => 0,
                'appointment_date' => $appointmentDate,
                'unlock_date'      => $unlockDateStr,
                'badge'            => 'Probation (Joining Year)',
                'description'      => 'Joining Year: 4 Monthly Leaves/month only. Half-day Annual Leave accrual (+0.5d/month) begins in January ' . ($joinYear + 1) . '.'
            ];
        }

        // CASE C: Probation — Calendar Year containing their Date of Appointment ($appointmentYear == $year)
        if ($appointmentYear == $year) {
            $unlockMonth = $appointmentMonth + 1;

            if ($month >= $unlockMonth) {
                return [
                    'stage'            => 'confirmed_post_appointment',
                    'annual_total'     => 14.0,
                    'casual_total'     => 7.0,
                    'monthly_quota'    => 4.0,
                    'medical_total'    => 7.0,
                    'is_accruing'      => false,
                    'accrued_months'   => 12,
                    'appointment_date' => $appointmentDate,
                    'unlock_date'      => $unlockDateStr,
                    'badge'            => 'Appointment Milestone Reached (Full Quota)',
                    'description'      => "Full 14 Annual & 7 Casual leaves unlocked from {$unlockShortStr} (balanced against any accrued Annual leaves already taken)."
                ];
            } else {
                $accruedMonths = max(1, min($month, $appointmentMonth));
                $accruedAnnual = round($accruedMonths * 0.5, 1);

                return [
                    'stage'            => 'probation_accruing',
                    'annual_total'     => $accruedAnnual,
                    'casual_total'     => 0.0,
                    'monthly_quota'    => 4.0,
                    'medical_total'    => 7.0,
                    'is_accruing'      => true,
                    'accrued_months'   => $accruedMonths,
                    'appointment_date' => $appointmentDate,
                    'unlock_date'      => $unlockDateStr,
                    'badge'            => "Accruing Annual ({$accruedAnnual} Days)",
                    'description'      => "Accruing +0.5 Annual day/month ({$accruedAnnual} days accumulated up to " . date('M', mktime(0, 0, 0, $accruedMonths, 1, $year)) . "). Full 14 Annual + 7 Casual unlock on {$unlockDateStr}."
                ];
            }
        }

        // CASE D: Past Appointment Year ($appointmentYear < $year)
        return [
            'stage'            => 'confirmed_post_appointment',
            'annual_total'     => 14.0,
            'casual_total'     => 7.0,
            'monthly_quota'    => 4.0,
            'medical_total'    => 7.0,
            'is_accruing'      => false,
            'accrued_months'   => 12,
            'appointment_date' => $appointmentDate,
            'unlock_date'      => $unlockDateStr,
            'badge'            => 'Confirmed (Past Appointment Date)',
            'description'      => 'Full 14 Annual + 7 Casual + 4 Monthly Leaves per month.'
        ];
    }

    /**
     * Sync leave balances in database for an employee based on dynamic rules or admin custom override.
     * Preserves annual_used, casual_used, medical_used, maternity_used, monthly_used.
     */
    function sync_employee_leave_balances($pdo, $empId, $year = null, $month = null) {
        if (!isset($pdo) || !$empId) return null;
        if (!$year) $year = (int)date('Y');
        if (!$month) $month = (int)date('m');

        try {
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$empId]);
            $emp = $stmt->fetch();
            if (!$emp) return null;

            $ent = calculate_employee_annual_leave_accrual($emp, $year, $month);

            $bStmt = $pdo->prepare("SELECT * FROM leave_balances WHERE employee_id = ? AND year = ?");
            $bStmt->execute([$empId, $year]);
            $curBal = $bStmt->fetch();

            $matTotal = (($emp['gender'] ?? '') === 'Female' && !empty($emp['maternity_eligible'])) ? 84.0 : 0.0;
            $medTotal = (float)($ent['medical_total'] ?? 7.0);

            if ($curBal) {
                $uStmt = $pdo->prepare("
                    UPDATE leave_balances
                    SET annual_total = ?, casual_total = ?, monthly_quota = ?, medical_total = ?, maternity_total = ?
                    WHERE employee_id = ? AND year = ?
                ");
                $uStmt->execute([
                    $ent['annual_total'],
                    $ent['casual_total'],
                    $ent['monthly_quota'],
                    $medTotal,
                    $matTotal,
                    $empId,
                    $year
                ]);
            } else {
                $usedStmt = $pdo->prepare("
                    SELECT leave_type, COALESCE(SUM(total_days), 0) AS used_days
                    FROM leaves
                    WHERE employee_id = ? AND status = 'Approved' AND YEAR(start_date) = ?
                    GROUP BY leave_type
                ");
                $usedStmt->execute([$empId, $year]);
                $usedMap = [];
                foreach ($usedStmt->fetchAll() as $ur) {
                    $usedMap[$ur['leave_type']] = (float)$ur['used_days'];
                }

                $iStmt = $pdo->prepare("
                    INSERT INTO leave_balances
                    (employee_id, year, annual_total, annual_used, casual_total, casual_used, monthly_quota, monthly_used, medical_total, medical_used, maternity_total, maternity_used)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $iStmt->execute([
                    $empId,
                    $year,
                    $ent['annual_total'],
                    $usedMap['Annual'] ?? 0.0,
                    $ent['casual_total'],
                    $usedMap['Casual'] ?? 0.0,
                    $ent['monthly_quota'],
                    $usedMap['Monthly'] ?? 0.0,
                    $medTotal,
                    $usedMap['Medical'] ?? 0.0,
                    $matTotal,
                    $usedMap['Maternity'] ?? 0.0
                ]);
            }

            return $ent;
        } catch (Exception $e) {
            return null;
        }
    }
}
