# ==============================================================================
# Sapphire Aesthetic & Luxury - 1-Click USB Attendance Auto-Sync Tool for Admin PC
# Automatically detects USB Pen Drive, locates machine Excel report & uploads to cloud
# ==============================================================================

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$CloudApiUrl = "https://staff.sapphireaesthetic.com/api/upload_excel_api.php"
$ApiKey = "sapphire_secret_sync_key_2026"

Write-Host "=======================================================" -ForegroundColor Cyan
Write-Host " 💎 Sapphire Luxury - USB Attendance Auto-Sync Tool" -ForegroundColor Yellow
Write-Host "=======================================================" -ForegroundColor Cyan
Write-Host "Scanning for connected USB Pen Drive..." -ForegroundColor White

# 1. Detect Removable USB Drives (DriveType = 2)
$usbDrives = Get-CimInstance -ClassName Win32_LogicalDisk | Where-Object { $_.DriveType -eq 2 }

if (-not $usbDrives -or $usbDrives.Count -eq 0) {
    # Fallback: check if local Source folder or test path exists
    $localFallback = ".\Source\1_StandardReport.xlsx"
    if (Test-Path $localFallback) {
        $targetFile = (Get-Item $localFallback).FullName
        Write-Host "Found local fallback file: $targetFile" -ForegroundColor Yellow
    } else {
        $msg = "⚠️ No USB Pen Drive detected!`n`nPlease plug in the USB Pen Drive from the ZKTeco LX50 machine into this PC and try again."
        [System.Windows.Forms.MessageBox]::Show($msg, "Sapphire Attendance Sync - No USB", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Warning)
        exit
    }
} else {
    $targetFile = $null
    foreach ($drive in $usbDrives) {
        $root = $drive.DeviceID + "\"
        Write-Host "Checking USB Drive: $root" -ForegroundColor Gray
        
        # Look for StandardReport or any xlsx file
        $candidates = Get-ChildItem -Path $root -Filter "*.xlsx" -Recurse -Depth 2 -ErrorAction SilentlyContinue | Sort-Object LastWriteTime -Descending
        if ($candidates -and $candidates.Count -gt 0) {
            $targetFile = $candidates[0].FullName
            Write-Host "Found Attendance Report: $targetFile" -ForegroundColor Green
            break
        }
    }
}

if (-not $targetFile -or -not (Test-Path $targetFile)) {
    $msg = "⚠️ USB Pen Drive found, but no Attendance Excel report (.xlsx) was detected on it!`n`nPlease make sure you downloaded the report from the machine Menu -> Report -> Download Report first."
    [System.Windows.Forms.MessageBox]::Show($msg, "Sapphire Attendance Sync - No Excel Report", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Warning)
    exit
}

Write-Host "`nUploading attendance file to Cloud: $targetFile..." -ForegroundColor Cyan

try {
    # Build Multipart Form Data
    $boundary = [System.Guid]::NewGuid().ToString()
    $LF = "`r`n"
    
    $fileBytes = [System.IO.File]::ReadAllBytes($targetFile)
    $fileName = [System.IO.Path]::GetFileName($targetFile)
    
    $headerTemplate = "--$boundary$LF" +
                      "Content-Disposition: form-data; name=`"excel_file`"; filename=`"$fileName`"$LF" +
                      "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet$LF$LF"
                      
    $footerTemplate = "$LF--$boundary--$LF"
    
    $headerBytes = [System.Text.Encoding]::UTF8.GetBytes($headerTemplate)
    $footerBytes = [System.Text.Encoding]::UTF8.GetBytes($footerTemplate)
    
    $bodyBytes = New-Object byte[] ($headerBytes.Length + $fileBytes.Length + $footerBytes.Length)
    [System.Buffer]::BlockCopy($headerBytes, 0, $bodyBytes, 0, $headerBytes.Length)
    [System.Buffer]::BlockCopy($fileBytes, 0, $bodyBytes, $headerBytes.Length, $fileBytes.Length)
    [System.Buffer]::BlockCopy($footerBytes, 0, $bodyBytes, ($headerBytes.Length + $fileBytes.Length), $footerBytes.Length)
    
    $headers = @{
        "X-API-KEY" = $ApiKey
    }
    
    $response = Invoke-RestMethod -Uri $CloudApiUrl -Method Post -Headers $headers -ContentType "multipart/form-data; boundary=$boundary" -Body $bodyBytes -TimeoutSec 30
    
    if ($response.success -eq $true) {
        $msg = "✅ Attendance Synced Successfully to Cloud!`n`n" +
               "📁 File: $fileName`n" +
               "👥 Total Employees: " + $response.total_employees + "`n" +
               "📊 Total Log Records: " + $response.total_records + "`n" +
               "📅 Period: " + $response.date_range + "`n`n" +
               "All calculations (Late minutes, OT, In/Out) have been updated on staff.sapphireaesthetic.com."
        Write-Host "`n$msg" -ForegroundColor Green
        [System.Windows.Forms.MessageBox]::Show($msg, "Sapphire Attendance Sync - Success", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Information)
    } else {
        $msg = "❌ Server reported error:`n`n" + $response.error
        Write-Host "`n$msg" -ForegroundColor Red
        [System.Windows.Forms.MessageBox]::Show($msg, "Sapphire Attendance Sync - Error", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Error)
    }

} catch {
    $msg = "❌ Failed to connect to Cloud Server:`n`n" + $_.Exception.Message + "`n`nPlease check your internet connection and try again."
    Write-Host "`n$msg" -ForegroundColor Red
    [System.Windows.Forms.MessageBox]::Show($msg, "Sapphire Attendance Sync - Connection Failed", [System.Windows.Forms.MessageBoxButtons]::OK, [System.Windows.Forms.MessageBoxIcon]::Error)
}
