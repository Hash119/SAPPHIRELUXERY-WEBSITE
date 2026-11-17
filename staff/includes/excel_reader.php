<?php
/**
 * Sapphire Aesthetic & Luxury - Biometric Excel (XLSX) Parser
 * Standalone Pure PHP Parser (Zero dependencies, uses PHP built-in ZipArchive and SimpleXML)
 * Designed specifically for VFT Holdings / ZKTeco SSR Biometric Attendance Reports
 */

class ZkReportReader {

    /**
     * Parse the uploaded XLSX file and return extracted attendance records & summary
     */
    public static function parse($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . htmlspecialchars($filePath));
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            throw new Exception("Failed to open Excel archive. Make sure it is a valid .xlsx file.");
        }

        // 1. Read Shared Strings (xl/sharedStrings.xml)
        $sharedStrings = [];
        $ssContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssContent) {
            $xml = simplexml_load_string($ssContent);
            if ($xml && isset($xml->si)) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read Workbook relations to find sheet targets
        $sheetTargets = [];
        $relsContent = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsContent) {
            $relsXml = simplexml_load_string($relsContent);
            if ($relsXml && isset($relsXml->Relationship)) {
                foreach ($relsXml->Relationship as $rel) {
                    $sheetTargets[(string)$rel['Id']] = (string)$rel['Target'];
                }
            }
        }

        // 3. Read Workbook.xml to map Sheet Name -> Target File
        $sheets = [];
        $wbContent = $zip->getFromName('xl/workbook.xml');
        if ($wbContent) {
            $wbXml = simplexml_load_string($wbContent);
            if ($wbXml && isset($wbXml->sheets->sheet)) {
                foreach ($wbXml->sheets->sheet as $sheet) {
                    $name = (string)$sheet['name'];
                    $rId = (string)$sheet->attributes('r', true)['id'];
                    $target = $sheetTargets[$rId] ?? '';
                    if ($target) {
                        // Normalize target path
                        if (strpos($target, 'worksheets/') === false) {
                            $target = 'worksheets/' . basename($target);
                        }
                        $sheets[$name] = 'xl/' . ltrim($target, '/');
                    }
                }
            }
        }

        // We check for "Att.log report" or fallback to "Att. Stat." or "Sheet1"
        $attLogSheet = $sheets['Att.log report'] ?? null;
        if (!$attLogSheet) {
            // Find any sheet matching att.log
            foreach ($sheets as $sName => $sPath) {
                if (stripos($sName, 'Att.log') !== false || stripos($sName, 'Attendance Record') !== false) {
                    $attLogSheet = $sPath;
                    break;
                }
            }
        }

        if (!$attLogSheet) {
            // Default to sheet3.xml if exists
            $attLogSheet = 'xl/worksheets/sheet3.xml';
        }

        $logXmlContent = $zip->getFromName($attLogSheet);
        if (!$logXmlContent) {
            $zip->close();
            throw new Exception("Attendance Log Sheet not found in the uploaded file.");
        }

        $logXml = simplexml_load_string($logXmlContent);
        if (!$logXml || !isset($logXml->sheetData->row)) {
            $zip->close();
            throw new Exception("Attendance Log Sheet data is empty or corrupted.");
        }

        // 4. Parse Rows in Att.log report
        $rowsData = [];
        foreach ($logXml->sheetData->row as $row) {
            $rowNum = (int)$row['r'];
            $cols = [];
            if (isset($row->c)) {
                foreach ($row->c as $cell) {
                    $cellRef = (string)$cell['r'];
                    $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                    $val = (string)$cell->v;
                    $type = (string)$cell['t'];
                    if ($type === 's' && isset($sharedStrings[(int)$val])) {
                        $val = $sharedStrings[(int)$val];
                    }
                    $cols[$colLetters] = trim($val);
                }
            }
            $rowsData[$rowNum] = $cols;
        }

        $zip->close();

        // 5. Extract Date Range (usually in Row 3)
        $dateRangeStr = '';
        foreach ($rowsData as $rNum => $cells) {
            if ($rNum > 10) break;
            foreach ($cells as $col => $txt) {
                if (preg_match('/(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})/', $txt, $m)) {
                    $dateRangeStr = $m[0];
                    $startDate = $m[1];
                    $endDate = $m[2];
                    break 2;
                }
            }
        }

        if (empty($startDate)) {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        // Parse year & month
        $year = (int)date('Y', strtotime($startDate));
        $month = (int)date('m', strtotime($startDate));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // Helper to convert Column Letters (A=1, B=2, ... Z=26, AA=27, ... AE=31) to Day Number
        $colToDay = [];
        for ($d = 1; $d <= 31; $d++) {
            $colLetter = self::numberToColumnName($d);
            $colToDay[$colLetter] = $d;
        }

        // 6. Loop through rows and group into Employee Attendance Records
        $parsedEmployees = [];
        $currentEmp = null;

        foreach ($rowsData as $rNum => $cells) {
            // Check if this row is an Employee Header (has ID: X and Name: Y)
            $isEmpHeader = false;
            $empId = null;
            $empName = null;
            $dept = 'Sapphire';

            // Scan row cells for "ID:" or numeric ID
            $rowText = implode(' ', $cells);
            if (preg_match('/ID\s*:\s*(\d+)/i', $rowText, $mId)) {
                $empId = (int)$mId[1];
                $isEmpHeader = true;
            } elseif (isset($cells['A']) && $cells['A'] === 'ID:' && isset($cells['C']) && is_numeric($cells['C'])) {
                $empId = (int)$cells['C'];
                $isEmpHeader = true;
            }

            if ($isEmpHeader && $empId !== null) {
                // Find Name
                if (preg_match('/Name\s*:\s*([A-Za-z0-9_,\.\-\s]+?)(?:Dept|$)/i', $rowText, $mName)) {
                    $empName = trim($mName[1]);
                } elseif (isset($cells['K']) && !empty($cells['K'])) {
                    $empName = trim($cells['K']);
                } else {
                    $empName = "Staff #" . $empId;
                }

                // Find Department
                if (preg_match('/Dept\s*\.?\s*:\s*([A-Za-z0-9_\.\-\s]+)/i', $rowText, $mDept)) {
                    $dept = trim($mDept[1]);
                } elseif (isset($cells['U']) && !empty($cells['U'])) {
                    $dept = trim($cells['U']);
                }

                $currentEmp = [
                    'machine_user_id' => $empId,
                    'name' => $empName,
                    'department' => $dept,
                    'records' => []
                ];
                $parsedEmployees[$empId] = $currentEmp;
                continue;
            }

            // If we have a current employee and this row contains attendance stamps
            if ($currentEmp !== null) {
                $hasPunches = false;
                foreach ($cells as $colLetter => $cellVal) {
                    if (isset($colToDay[$colLetter])) {
                        $dayNum = $colToDay[$colLetter];
                        if ($dayNum > $daysInMonth) continue;

                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
                        
                        // Look for all HH:MM matches inside the cell (e.g. 09:3018:18 -> 09:30, 18:18)
                        preg_match_all('/\d{2}:\d{2}/', $cellVal, $timeMatches);
                        if (!empty($timeMatches[0])) {
                            $hasPunches = true;
                            $punches = $timeMatches[0];
                            
                            $firstPunch = reset($punches);
                            $lastPunch = count($punches) > 1 ? end($punches) : null;

                            // Calculate hours and flags
                            $workHours = 0.00;
                            $lateMins = 0;
                            $earlyLeaveMins = 0;
                            $otMins = 0;
                            $status = 'Present';

                            // Standard default shifts: 09:00:00 to 18:00:00
                            $shiftStart = strtotime($dateStr . ' 09:00:00');
                            $graceStart = strtotime($dateStr . ' 09:15:00');
                            $shiftEnd   = strtotime($dateStr . ' 18:00:00');

                            if ($firstPunch && $lastPunch) {
                                $inTime  = strtotime($dateStr . ' ' . $firstPunch . ':00');
                                $outTime = strtotime($dateStr . ' ' . $lastPunch . ':00');
                                
                                $diffSeconds = max(0, $outTime - $inTime);
                                $workHours = round($diffSeconds / 3600, 2);

                                // Check Late
                                if ($inTime > $graceStart) {
                                    $lateMins = round(($inTime - $shiftStart) / 60);
                                    $status = 'Late';
                                }

                                // Check Early Leave
                                if ($outTime < $shiftEnd) {
                                    $earlyLeaveMins = round(($shiftEnd - $outTime) / 60);
                                }

                                // Check Overtime (after 18:00)
                                if ($outTime > $shiftEnd) {
                                    $otMins = round(($outTime - $shiftEnd) / 60);
                                }
                            } elseif ($firstPunch) {
                                $inTime = strtotime($dateStr . ' ' . $firstPunch . ':00');
                                if ($inTime > $graceStart) {
                                    $lateMins = round(($inTime - $shiftStart) / 60);
                                    $status = 'Late';
                                }
                            }

                            $parsedEmployees[$currentEmp['machine_user_id']]['records'][$dateStr] = [
                                'date' => $dateStr,
                                'check_in' => $firstPunch ? $firstPunch . ':00' : null,
                                'check_out' => $lastPunch ? $lastPunch . ':00' : null,
                                'work_hours' => $workHours,
                                'late_minutes' => $lateMins,
                                'early_leave_minutes' => $earlyLeaveMins,
                                'overtime_minutes' => $otMins,
                                'raw_punches' => implode(',', $punches),
                                'status' => $status
                            ];
                        }
                    }
                }

                // If this row was processed as attendance, reset currentEmp for next block
                if ($hasPunches) {
                    $currentEmp = null;
                }
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'year' => $year,
            'month' => $month,
            'employees' => $parsedEmployees,
            'total_employees_found' => count($parsedEmployees)
        ];
    }

    /**
     * Convert 1-based number to Excel column letters (1 -> A, 2 -> B, 27 -> AA, etc.)
     */
    private static function numberToColumnName($number) {
        $name = '';
        while ($number > 0) {
            $mod = ($number - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $number = (int)(($number - $mod) / 26);
        }
        return $name;
    }
}
