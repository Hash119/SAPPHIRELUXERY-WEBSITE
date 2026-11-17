# Sapphire Aesthetic & Luxury - Attendance & HR Management Portal
**Subdomain Target:** `staff.sapphireaesthetic.com` -> Folder: `/public_html/staff`

---

## 🚀 Quick Deployment Guide for Hostinger (පියවරෙන් පියවර)

### 1. Database එක Setup කරගැනීම (phpMyAdmin)
1. Hostinger hPanel එකෙන් **Databases** -> **Enter phpMyAdmin** click කරන්න.
2. ඔබ හැදූ Database එක select කරන්න.
3. උඩ menu එකේ ඇති **Import** tab එකට ගොස් `database.sql` file එක select කර **Import** (හෝ Go) click කරන්න.
   * *මෙහි දැනටමත් Machine එකේ සිටින සේවකයින් 22 දෙනාම (Pavithra, Sonali, etc.) සහ Admin ගිණුම ඇතුලත් කර ඇත.*

### 2. Database Credentials Update කිරීම (`config/db.php`)
`config/db.php` file එක open කර Hostinger වෙතින් ලබාගත් විස්තර ලබා දෙන්න:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_sapphire_hrm'); // Hostinger Database Name
define('DB_USER', 'u123456789_hrm_user');     // Hostinger MySQL Username
define('DB_PASS', 'YourStrongPassword123#');   // Hostinger MySQL Password
```

### 3. Files Upload කිරීම (Hostinger File Manager)
1. hPanel එකේ **File Manager** වෙත යන්න.
2. `public_html/staff/` folder එක ඇතුලට යන්න.
3. මෙහි ඇති සියලුම files (හෝ `staff.zip` file එක) upload කර Extract කරන්න.

---

## 🔑 Default Login Credentials

* **Super Admin Portal:**
  * **Login ID / Email:** `admin@sapphireaesthetic.com` (හෝ `ADM001`)
  * **Password:** `admin123`

* **Employee Self-Service Portal:**
  * **Login ID:** Machine Biometric ID (උදා: `1`, `2`, `3` ... `22`)
  * **Password:** `123456`

---

## 📋 Features Included

1. **1-Click Excel Attendance Importer:**
   * Pen-drive එකෙන් ලබාගන්නා `1_StandardReport.xlsx` file එක කෙලින්ම Drag & Drop කර Upload කළ හැක.
   * Daily punches, In/Out, Late minutes, OT hours සියල්ල auto calculate වේ.

2. **Maternity Leave Module:**
   * කාන්තා සේවිකාවන් සඳහා පමණක් (Ladies) සක්‍රිය වන විශේෂ විකල්පය.
   * ලංකාවේ කම්කරු නීතියට අනුව දින 84 ක quota එකක් ස්වයංක්‍රීයව හිමිවේ.

3. **Leave Management & Approval Permissions:**
   * සේවකයින්ට Leave apply කළ හැක.
   * Admin ට තීරණය කළ හැක leave approve කරන්නේ කවුද යන්න (Admin පමණක්ද නැතහොත් Supervisors ලාටත් අවසර දෙනවාද යන්න).

4. **Manual In/Out & Audit History:**
   * Fingerprint නොවැදුන අවස්ථාවල Manual Punch request දැමීම.
   * Management එකෙන් Authorize කිරීම සහ සම්පූර්ණ History (Audit Trail) සටහන් වීම.

5. **Employee Portal:**
   * සේවකයින්ට තමන්ගේ Late minutes, Overtime, Leave Balance සහ In/Out බලාගැනීම.

6. **Office PC Auto-Sync Tool:**
   * `sync_tool/machine_sync.py` හෝ `machine_sync.js` මගින් machine එකේ IP එකෙන් Hostinger Cloud API එකට live sync කිරීමේ පහසුකම.
