<?php
// excel.php — ابزار اکسل (XLSX/CSV)
// ساخت و خواندن فایل‌ها با کتابخانه استاندارد PhpSpreadsheet (خروجی ۱۰۰٪ سازگار با Excel).
// اگر vendor/ نصب نباشد (composer install اجرا نشده)، به نسخه ساده داخلی برمی‌گردد.

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}
$portal_has_phpspreadsheet = class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);

/** تبدیل شماره ستون به حرف (0 => A) */
function excel_col_letter(int $index): string
{
    $letter = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = intdiv($index - 1, 26);
    }
    return $letter;
}

/** تبدیل حرف ستون به ایندکس (A => 0) */
function excel_col_index(string $letters): int
{
    $index = 0;
    $letters = strtoupper($letters);
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }
    return $index - 1;
}

// ===========================================================================
// ساخت فایل XLSX
// ===========================================================================

/** ساخت محتوای فایل XLSX از آرایه سطرها (سطر اول = هدر) — نسخه PhpSpreadsheet */
function excel_build_xlsx_ps(array $rows, string $sheetName = 'Sheet1'): string
{
    if (empty($rows)) {
        $rows = [['']];
    }
    // نام ورق نباید بیش از ۳۱ کاراکتر یا شامل کاراکترهای ممنوع باشد
    $sheetName = preg_replace('/[\\\\\/\?\*\[\]:]/u', '-', mb_substr($sheetName, 0, 31));

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetName);
    $sheet->setRightToLeft(true); // چیدمان راست‌به‌چپ

    $colCount = 1;
    foreach ($rows as $r) {
        $colCount = max($colCount, count((array) $r));
    }

    foreach (array_values($rows) as $ri => $row) {
        $row = array_values((array) $row);
        foreach ($row as $ci => $cell) {
            // همه سلول‌ها به‌صورت متن نوشته می‌شوند تا صفرهای ابتدای شماره موبایل حفظ شود
            $sheet->setCellValueExplicit(
                excel_col_letter($ci) . ($ri + 1),
                (string) ($cell ?? ''),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }
    }

    // استایل هدر
    $headerStyle = [
        'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => 'center'],
    ];
    $sheet->getStyle('A1:' . excel_col_letter($colCount) . '1')->applyFromArray($headerStyle);

    // عرض ستون‌ها
    foreach (range(0, $colCount - 1) as $ci) {
        $sheet->getColumnDimension(excel_col_letter($ci))->setWidth(22);
    }
    $sheet->freezePane('A2');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $tmp = tempnam(sys_get_temp_dir(), 'ptx');
    $writer->save($tmp);
    $data = (string) file_get_contents($tmp);
    @unlink($tmp);
    $spreadsheet->disconnectWorksheets();
    return $data;
}

/** ساخت محتوای فایل XLSX — نسخه ساده داخلی (فالبک وقتی PhpSpreadsheet نصب نیست) */
function excel_build_xlsx_legacy(array $rows, string $sheetName = 'Sheet1'): string
{
    if (empty($rows)) {
        $rows = [['']];
    }
    $esc = static fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $sheetName = preg_replace('/[\\\\\/\?\*\[\]:]/u', '-', mb_substr($sheetName, 0, 31));

    $colCount = 1;
    foreach ($rows as $r) {
        $colCount = max($colCount, count((array) $r));
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . excel_col_letter($colCount - 1) . count($rows) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews>'
        . '<cols>';
    $width = max(12, min(38, (int) round(90 / max(1, $colCount))));
    for ($c = 1; $c <= $colCount; $c++) {
        $sheet .= '<col min="' . $c . '" max="' . $c . '" width="' . $width . '" customWidth="1"/>';
    }
    $sheet .= '</cols><sheetData>';
    foreach (array_values($rows) as $ri => $row) {
        $row = array_values((array) $row);
        $sheet .= '<row r="' . ($ri + 1) . '">';
        foreach ($row as $ci => $cell) {
            $ref = excel_col_letter($ci) . ($ri + 1);
            $val = (string) ($cell ?? '');
            $style = $ri === 0 ? ' s="1"' : '';
            $sheet .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . $esc($val) . '</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<bookViews><workbookView xWindow="240" yWindow="15" windowWidth="16095" windowHeight="9660" activeTab="0"/></bookViews>'
        . '<sheets><sheet name="' . $esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '</styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'ptx');
    @unlink($tmp); // فایل خالی ساخته‌شده توسط tempnam — باز کردنش با ZipArchive در PHP 8.4 منسوخ است
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
        @unlink($tmp);
        return '';
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
    $data = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $data;
}

/** ساخت محتوای CSV (UTF-8 با BOM — سازگار با اکسل فارسی) */
function excel_build_csv(array $rows): string
{
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($fh, array_map(static fn($v) => (string) ($v ?? ''), array_values((array) $row)), ',', '"', '\\');
    }
    rewind($fh);
    $data = stream_get_contents($fh);
    fclose($fh);
    return $data !== false ? $data : '';
}

/** ساخت محتوای فایل XLSX (فالبک خودکار در صورت نبودن PhpSpreadsheet) */
function excel_build_xlsx(array $rows, string $sheetName = 'Sheet1'): string
{
    global $portal_has_phpspreadsheet;
    if ($portal_has_phpspreadsheet) {
        return excel_build_xlsx_ps($rows, $sheetName);
    }
    if (!class_exists('ZipArchive')) {
        // بدون ext-zip ساخت XLSX ممکن نیست — محتوای CSV معادل برمی‌گردد
        return excel_build_csv($rows);
    }
    return excel_build_xlsx_legacy($rows, $sheetName);
}

// ===========================================================================
// خواندن فایل‌ها
// ===========================================================================

/** خواندن فایل XLSX با PhpSpreadsheet و برگرداندن آرایه سطرها (سطر اول = هدر) */
function excel_parse_xlsx_ps(string $content): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'ptx');
    file_put_contents($tmp, $content);
    try {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmp);
        $rows = [];
        foreach ($spreadsheet->getActiveSheet()->toArray(null, true, true, false) as $row) {
            $row = array_map(static fn($v) => trim((string) ($v ?? '')), $row);
            if (count(array_filter($row, static fn($v) => $v !== '')) === 0) {
                continue;
            }
            $rows[] = $row;
        }
        $spreadsheet->disconnectWorksheets();
        return $rows;
    } catch (Throwable $e) {
        return [];
    } finally {
        @unlink($tmp);
    }
}

/** خواندن فایل XLSX با پارسر ساده داخلی (فالبک) */
function excel_parse_xlsx_legacy(string $content): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'ptx');
    file_put_contents($tmp, $content);
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        @unlink($tmp);
        return [];
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    @unlink($tmp);
    if ($sheetXml === false) {
        return [];
    }

    $sharedStrings = [];
    if ($sharedXml !== false) {
        $sx = @simplexml_load_string($sharedXml);
        if ($sx !== false) {
            foreach ($sx->si as $si) {
                $sharedStrings[] = (string) $si;
            }
        }
    }

    $sx = @simplexml_load_string($sheetXml);
    if ($sx === false) {
        return [];
    }
    $rows = [];
    foreach ($sx->sheetData->row as $rowEl) {
        $cells = [];
        foreach ($rowEl->c as $cEl) {
            $attrs = $cEl->attributes();
            $t = (string) ($attrs['t'] ?? '');
            $ref = (string) ($attrs['r'] ?? '');
            $colIdx = 0;
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                $colIdx = excel_col_index($m[1]);
            }
            if ($t === 'inlineStr') {
                $val = trim((string) ($cEl->is->t ?? ''));
            } elseif ($t === 's') {
                $idx = (int) ($cEl->v ?? 0);
                $val = trim($sharedStrings[$idx] ?? '');
            } else {
                $val = trim((string) ($cEl->v ?? ''));
            }
            $cells[$colIdx] = $val;
        }
        if (empty($cells)) {
            continue;
        }
        ksort($cells);
        $rows[] = array_values($cells);
    }
    return $rows;
}

/** خواندن فایل XLSX (فالبک خودکار) */
function excel_parse_xlsx(string $content): array
{
    global $portal_has_phpspreadsheet;
    if ($portal_has_phpspreadsheet) {
        return excel_parse_xlsx_ps($content);
    }
    if (!class_exists('ZipArchive')) {
        return []; // بدون ext-zip خواندن XLSX ممکن نیست — خطای آرام
    }
    return excel_parse_xlsx_legacy($content);
}

/** خواندن فایل CSV (UTF-8 با/بدون BOM) — تشخیص خودکار جداکننده */
function excel_parse_csv(string $content): array
{
    $content = str_replace("\xEF\xBB\xBF", '', $content); // حذف BOM
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1256');
    }
    $firstLine = strtok($content, "\r\n") ?: '';
    $delim = ',';
    $counts = [
        ',' => substr_count($firstLine, ','),
        ';' => substr_count($firstLine, ';'),
        "\t" => substr_count($firstLine, "\t"),
    ];
    arsort($counts);
    $best = array_key_first($counts);
    if ($best !== null && $counts[$best] > 0) {
        $delim = $best;
    }

    $rows = [];
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $content);
    rewind($fh);
    while (($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
        $row = array_map('trim', $row);
        if (count(array_filter($row, static fn($v) => $v !== '')) === 0) {
            continue;
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

/** تشخیص نوع فایل و خواندن آن به آرایه سطرها */
function excel_parse_upload(array $file): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return [];
    }
    $content = (string) file_get_contents($file['tmp_name']);
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        return excel_parse_xlsx($content);
    }
    if ($ext === 'xls') {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Reader\Xls::class)) {
            return []; // .xls بدون PhpSpreadsheet قابل خواندن نیست — خطای آرام به‌جای داده‌ی خراب
        }
        // فایل اکسل قدیمی (.xls) — فقط با PhpSpreadsheet خوانده می‌شود
        $tmp = tempnam(sys_get_temp_dir(), 'ptx');
        file_put_contents($tmp, $content);
        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmp);
            $rows = [];
            foreach ($spreadsheet->getActiveSheet()->toArray(null, true, true, false) as $row) {
                $row = array_map(static fn($v) => trim((string) ($v ?? '')), $row);
                if (count(array_filter($row, static fn($v) => $v !== '')) === 0) {
                    continue;
                }
                $rows[] = $row;
            }
            $spreadsheet->disconnectWorksheets();
            return $rows;
        } catch (Throwable $e) {
            return [];
        } finally {
            @unlink($tmp);
        }
    }
    if ($ext === 'csv') {
        return excel_parse_csv($content);
    }
    // بدون پسوند: تشخیص از روی محتوا (ZIP = xlsx)
    if (str_starts_with($content, "PK\x03\x04")) {
        return excel_parse_xlsx($content);
    }
    return excel_parse_csv($content);
}

/** هدرهای دانلود فایل */
function excel_download_headers(string $filename): void
{
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

/** خروجی مستقیم فایل اکسل و توقف — اگر XLSX ممکن نباشد، CSV معادل دانلود می‌شود */
function excel_output(string $baseName, array $rows, string $sheetName = 'ورق1'): void
{
    global $portal_has_phpspreadsheet;
    if (!$portal_has_phpspreadsheet && !class_exists('ZipArchive')) {
        $data = excel_build_csv($rows);
        if ($data === '') {
            http_response_code(500);
            exit('خطا در ساخت فایل اکسل.');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $data;
        exit;
    }
    $data = excel_build_xlsx($rows, $sheetName);
    if ($data === '') {
        http_response_code(500);
        exit('خطا در ساخت فایل اکسل.');
    }
    excel_download_headers($baseName . '.xlsx');
    echo $data;
    exit;
}

// ---------------------------------------------------------------------------
// ورود دسته‌جمعی (Import)
// ---------------------------------------------------------------------------

/** نرمال‌سازی وضعیت محصول از برچسب فارسی یا انگلیسی */
function excel_product_status_key(string $v): string
{
    $v = trim($v);
    $map = [
        'purchased' => 'purchased', 'shipping' => 'shipping', 'delivered' => 'delivered',
        'active' => 'active', 'expired' => 'expired',
        'محصول خریداری شده' => 'purchased', 'خریداری شده' => 'purchased',
        'در حال ارسال' => 'shipping', 'به دست مشتری رسیده' => 'delivered',
        'فعال' => 'active', 'فعال / در حال استفاده' => 'active', 'در حال استفاده' => 'active',
        'منقضی شده' => 'expired',
    ];
    return $map[$v] ?? 'purchased';
}

/** نرمال‌سازی وضعیت پروژه از برچسب فارسی یا انگلیسی */
function excel_project_status_key(string $v): string
{
    $v = trim($v);
    $map = [
        'in_progress' => 'in_progress', 'completed' => 'completed', 'pending' => 'pending',
        'در حال انجام' => 'in_progress', 'تکمیل شده' => 'completed', 'در انتظار شروع' => 'pending', 'در انتظار' => 'pending',
    ];
    return $map[$v] ?? 'in_progress';
}

/**
 * ضدعفونی تزریق فرمول: اگر سلولی با = + - @ شروع شود (فرمول اکسل)،
 * با یک آپاستروف پیشوندگذاری می‌شود تا به‌عنوان متن ذخیره شود و نه فرمول.
 */
function excel_sanitize_formula(string $value): string
{
    $trimmed = ltrim($value);
    if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
        return "'" . $value;
    }
    return $value;
}

/** بررسی اینکه سطر اول هدر است یا نه */
function excel_row_is_header(array $row): bool
{
    $first = mb_strtolower(trim((string) ($row[0] ?? '')));
    return in_array($first, ['نام کاربری', 'username', 'نام محصول', 'title', 'عنوان پروژه', 'موضوع', 'subject'], true)
        || str_contains($first, 'نام کاربری');
}

/**
 * ورود دسته‌جمعی مشتریان از اکسل
 * ستون‌ها: نام کاربری*، رمز عبور، نام، نام خانوادگی، شماره موبایل، نام شرکت، سمت، جنسیت، تاریخ تولد
 * @return array ['added'=>int, 'errors'=>array<string>]
 */
function excel_import_customers(array $rows, string $defaultPassword = ''): array
{
    global $pdo;
    $added = 0;
    $errors = [];
    $seenUsernames = [];

    foreach ($rows as $ri => $row) {
        if ($ri === 0 && excel_row_is_header($row)) {
            continue;
        }
        $username   = trim((string) ($row[0] ?? ''));
        $password   = trim((string) ($row[1] ?? ''));
        $first_name = trim((string) ($row[2] ?? ''));
        $last_name  = trim((string) ($row[3] ?? ''));
        $mobile     = fa_digits_to_en(trim((string) ($row[4] ?? '')));
        $mobile     = $mobile !== '' ? normalize_mobile_db($mobile) : '';
        $company    = trim((string) ($row[5] ?? ''));
        $job        = trim((string) ($row[6] ?? ''));
        $gender     = trim((string) ($row[7] ?? ''));
        $birth      = portal_date_to_db(trim((string) ($row[8] ?? ''))); // شمسی → میلادی (هماهنگ با فرم)

        // ضدعفونی تزریق فرمول در همه فیلدهای متنی
        foreach (['username', 'password', 'first_name', 'last_name', 'company', 'job', 'gender', 'birth'] as $kv) {
            $$kv = excel_sanitize_formula($$kv);
        }

        $line = $ri + 1;
        if ($username === '') {
            $errors[] = "سطر {$line}: نام کاربری خالی است — نادیده گرفته شد.";
            continue;
        }
        if (isset($seenUsernames[$username])) {
            $errors[] = "سطر {$line}: نام کاربری «{$username}» تکراری در فایل است.";
            continue;
        }
        $ck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $ck->execute([$username]);
        if ($ck->fetch()) {
            $errors[] = "سطر {$line}: نام کاربری «{$username}» قبلاً ثبت شده است.";
            continue;
        }
        if ($mobile !== '' && mobile_exists($mobile)) {
            $errors[] = "سطر {$line}: شماره «{$mobile}» برای کاربر دیگری ثبت شده است.";
            continue;
        }

        $finalPass = $password !== '' ? $password : ($defaultPassword !== '' ? $defaultPassword : bin2hex(random_bytes(6)));

        $q = $pdo->prepare("INSERT INTO users (username, password, role, first_name, last_name, mobile, company_name, job_title, gender, birth_date) VALUES (?, ?, 'customer', ?, ?, ?, ?, ?, ?, ?)");
        $q->execute([$username, password_hash($finalPass, PASSWORD_DEFAULT), $first_name, $last_name, $mobile, $company, $job, $gender, $birth]);
        $uid = (int) $pdo->lastInsertId();
        $seenUsernames[$username] = true;
        $added++;

        // پیامک خوش‌آمد (اگر فعال باشد)
        sms_trigger_welcome($uid);
    }
    return ['added' => $added, 'errors' => $errors];
}

/**
 * ورود دسته‌جمعی محصولات از اکسل
 * ستون‌ها: نام محصول*، نام کاربری مشتری*، توضیحات، قیمت، وضعیت، تاریخ خرید، کد لایسنس
 */
function excel_import_products(array $rows): array
{
    global $pdo;
    $added = 0;
    $errors = [];
    $customerCache = [];

    foreach ($rows as $ri => $row) {
        if ($ri === 0 && excel_row_is_header($row)) {
            continue;
        }
        $title      = trim((string) ($row[0] ?? ''));
        $username   = trim((string) ($row[1] ?? ''));
        $desc       = trim((string) ($row[2] ?? ''));
        $price      = trim((string) ($row[3] ?? ''));
        $status     = excel_product_status_key((string) ($row[4] ?? ''));
        $purchase   = portal_date_to_db(trim((string) ($row[5] ?? ''))); // شمسی → میلادی (هماهنگ با فرم)
        $license    = trim((string) ($row[6] ?? ''));

        // ضدعفونی تزریق فرمول
        foreach (['title', 'username', 'desc', 'price', 'purchase', 'license'] as $kv) {
            $$kv = excel_sanitize_formula($$kv);
        }

        $line = $ri + 1;
        if ($title === '') {
            $errors[] = "سطر {$line}: نام محصول خالی است — نادیده گرفته شد.";
            continue;
        }
        if ($username === '') {
            $errors[] = "سطر {$line}: نام کاربری مشتری خالی است.";
            continue;
        }
        if (!array_key_exists($username, $customerCache)) {
            $ck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'customer'");
            $ck->execute([$username]);
            $customerCache[$username] = $ck->fetchColumn() ?: 0;
        }
        if (!$customerCache[$username]) {
            $errors[] = "سطر {$line}: مشتری با نام کاربری «{$username}» یافت نشد.";
            continue;
        }

        $q = $pdo->prepare("INSERT INTO products (customer_id, title, description, price, product_status, purchase_date, license_key) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $q->execute([$customerCache[$username], $title, $desc, $price, $status, $purchase, $license]);
        $added++;
        sms_trigger_product_assigned((int) $pdo->lastInsertId());
    }
    return ['added' => $added, 'errors' => $errors];
}

/**
 * ورود دسته‌جمعی پروژه‌ها از اکسل
 * ستون‌ها: عنوان پروژه*، نام کاربری مشتری*، توضیحات، وضعیت، تاریخ تکمیل
 */
function excel_import_projects(array $rows): array
{
    global $pdo;
    $added = 0;
    $errors = [];
    $customerCache = [];

    foreach ($rows as $ri => $row) {
        if ($ri === 0 && excel_row_is_header($row)) {
            continue;
        }
        $title      = trim((string) ($row[0] ?? ''));
        $username   = trim((string) ($row[1] ?? ''));
        $desc       = trim((string) ($row[2] ?? ''));
        $status     = excel_project_status_key((string) ($row[3] ?? ''));
        $deadline   = portal_date_to_db(trim((string) ($row[4] ?? ''))); // شمسی → میلادی (هماهنگ با فرم)

        // ضدعفونی تزریق فرمول
        foreach (['title', 'username', 'desc', 'deadline'] as $kv) {
            $$kv = excel_sanitize_formula($$kv);
        }

        $line = $ri + 1;
        if ($title === '') {
            $errors[] = "سطر {$line}: عنوان پروژه خالی است — نادیده گرفته شد.";
            continue;
        }
        if ($username === '') {
            $errors[] = "سطر {$line}: نام کاربری مشتری خالی است.";
            continue;
        }
        if (!array_key_exists($username, $customerCache)) {
            $ck = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'customer'");
            $ck->execute([$username]);
            $customerCache[$username] = $ck->fetchColumn() ?: 0;
        }
        if (!$customerCache[$username]) {
            $errors[] = "سطر {$line}: مشتری با نام کاربری «{$username}» یافت نشد.";
            continue;
        }

        $q = $pdo->prepare("INSERT INTO projects (customer_id, title, description, status, deadline) VALUES (?, ?, ?, ?, ?)");
        $q->execute([$customerCache[$username], $title, $desc, $status, $deadline]);
        $added++;
        sms_trigger_project_assigned((int) $pdo->lastInsertId());
    }
    return ['added' => $added, 'errors' => $errors];
}

// ---------------------------------------------------------------------------
// نوار ابزار اکسل (خروجی + نمونه + ورود) — در بالای صفحات لیست
// ---------------------------------------------------------------------------

/**
 * رندر نوار ابزار اکسل برای صفحات مدیریت
 * @param array $opts ['page'=>string, 'withSample'=>bool, 'withImport'=>bool, 'importHint'=>string, 'importExtra'=>string]
 */
function render_excel_toolbar(array $opts): void
{
    $page = $opts['page'] ?? 'customers.php';
    $importId = 'excel-import-panel-' . md5($page);
    $supportsImport = !empty($opts['withImport']);
    $toolbarHint = $supportsImport
        ? 'خروجی و ورود داده‌ها با فایل Excel (XLSX یا CSV)'
        : 'دریافت خروجی داده‌ها با فایل Excel (XLSX یا CSV)';
    ?>
    <div class="excel-toolbar bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="excel-toolbar-actions flex flex-wrap items-center gap-2">
                <a href="<?= e($page) ?>?export=xlsx" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition shadow-sm cursor-pointer">
                    <span>دریافت فایل</span>
                </a>
                <?php if (!empty($opts['withSample'])): ?>
                <a href="<?= e($page) ?>?sample=xlsx" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition cursor-pointer">
                    <span>دریافت نمونه</span>
                </a>
                <?php endif; ?>
                <?php if ($supportsImport): ?>
                <button type="button" aria-controls="<?= $importId ?>" aria-expanded="false" onclick="var panel=document.getElementById('<?= $importId ?>'); var isOpen=panel.classList.toggle('hidden')===false; this.setAttribute('aria-expanded', String(isOpen)); this.classList.toggle('bg-emerald-600', isOpen); this.classList.toggle('text-white', isOpen); this.classList.toggle('bg-emerald-50', !isOpen); this.classList.toggle('text-emerald-700', !isOpen);" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-50 text-emerald-700 text-sm font-medium hover:bg-emerald-100 transition cursor-pointer">
                    <span>ورود فایل</span>
                </button>
                <?php endif; ?>
            </div>
            <div class="excel-toolbar-hint microcopy text-[11px] text-slate-400"><?= e($toolbarHint) ?></div>
        </div>

        <?php if ($supportsImport): ?>
        <div id="<?= $importId ?>" class="hidden mt-4 pt-4 border-t border-slate-100">
            <form method="post" enctype="multipart/form-data" class="flex flex-col sm:flex-row gap-3 items-end flex-wrap">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="excel_import">
                <div class="flex-1 min-w-[220px] w-full sm:w-auto">
                    <label class="block text-[11px] text-slate-500 mb-1" for="<?= $importId ?>-file">فایل Excel (XLSX یا CSV)</label>
                    <input type="file" id="<?= $importId ?>-file" name="excel_file" accept=".xlsx,.csv" required class="block w-full text-xs text-slate-600 file:ms-2 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:text-xs file:font-medium file:cursor-pointer">
                </div>
                <?= $opts['importExtra'] ?? '' ?>
                <button class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium transition cursor-pointer">ورود داده‌ها</button>
            </form>
            <p class="microcopy text-[11px] text-slate-400 mt-2 leading-relaxed"><?= $opts['importHint'] ?? '' ?> از «نمونه فایل» استفاده کنید و داده‌ها را دقیقاً با همان ستون‌ها وارد کنید؛ سطر اول عنوان ستون‌هاست.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
