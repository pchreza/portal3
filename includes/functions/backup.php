<?php
/**
 * Backup و restore کامل Portal3 بدون وابستگی به shell یا mysqldump.
 * Backup شامل دیتابیس و فایل‌های پروژه است؛ storage/backups و فایل‌های Git عمداً
 * برای جلوگیری از backup تو در تو و افشای history داخل archive قرار نمی‌گیرند.
 */

function portal_backup_dir(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
}

function portal_backup_ensure_dir(): string
{
    $dir = portal_backup_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('پوشهٔ امن backup ساخته نشد. دسترسی نوشتن storage را بررسی کنید.');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('پوشهٔ backup قابل نوشتن نیست. دسترسی storage/backups را بررسی کنید.');
    }
    return $dir;
}

function portal_backup_max_bytes(): int
{
    $configured = getenv('PORTAL_BACKUP_MAX_BYTES');
    if ($configured !== false && ctype_digit((string) $configured)) {
        return max(16 * 1024 * 1024, min(2 * 1024 * 1024 * 1024, (int) $configured));
    }
    return 512 * 1024 * 1024;
}

function portal_backup_project_root(): string
{
    return dirname(__DIR__, 2);
}

function portal_backup_safe_archive_name(string $name): ?string
{
    $baseName = basename($name);
    if ($baseName !== $name) {
        return null;
    }
    $name = $baseName;
    if (!preg_match('/^portal3-backup-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{16}\.zip$/', $name)) {
        return null;
    }
    return $name;
}

function portal_backup_path(string $name): string
{
    $safe = portal_backup_safe_archive_name($name);
    if ($safe === null) {
        throw new InvalidArgumentException('نام backup نامعتبر است.');
    }
    return portal_backup_dir() . DIRECTORY_SEPARATOR . $safe;
}

function portal_backup_sql_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function portal_backup_sql_value(PDO $db, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_resource($value)) {
        $value = stream_get_contents($value);
    }
    $quoted = $db->quote((string) $value);
    if ($quoted === false) {
        throw new RuntimeException('مقدار دیتابیس برای backup قابل quote کردن نیست.');
    }
    return $quoted;
}

/** @return array{tables:int,rows:int} */
function portal_backup_write_sql_dump(PDO $db, string $path): array
{
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('فایل موقت dump دیتابیس ساخته نشد.');
    }

    $tables = 0;
    $rows = 0;
    try {
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
        $objects = $db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($objects as $object) {
            $name = (string) ($object[0] ?? '');
            $type = strtoupper((string) ($object[1] ?? 'BASE TABLE'));
            if ($name === '') {
                continue;
            }
            $quotedName = portal_backup_sql_identifier($name);
            if ($type === 'VIEW') {
                $create = $db->query('SHOW CREATE VIEW ' . $quotedName)->fetch(PDO::FETCH_NUM);
                if ($create) {
                    fwrite($handle, "DROP VIEW IF EXISTS {$quotedName};\n" . (string) ($create[1] ?? '') . ";\n\n");
                }
                continue;
            }

            $create = $db->query('SHOW CREATE TABLE ' . $quotedName)->fetch(PDO::FETCH_NUM);
            if (!$create) {
                continue;
            }
            fwrite($handle, "DROP TABLE IF EXISTS {$quotedName};\n" . (string) ($create[1] ?? '') . ";\n");
            $columns = $db->query('SHOW COLUMNS FROM ' . $quotedName)->fetchAll(PDO::FETCH_ASSOC);
            $columnSql = implode(', ', array_map(
                static fn(array $column): string => portal_backup_sql_identifier((string) $column['Field']),
                $columns
            ));
            $rowsStatement = $db->query('SELECT * FROM ' . $quotedName);
            while ($row = $rowsStatement->fetch(PDO::FETCH_ASSOC)) {
                $values = [];
                foreach ($columns as $column) {
                    $field = (string) $column['Field'];
                    $values[] = portal_backup_sql_value($db, $row[$field] ?? null);
                }
                fwrite($handle, 'INSERT INTO ' . $quotedName . ' (' . $columnSql . ') VALUES (' . implode(', ', $values) . ");\n");
                $rows++;
            }
            fwrite($handle, "\n");
            $tables++;
        }
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    } finally {
        fclose($handle);
    }

    return ['tables' => $tables, 'rows' => $rows];
}

/** @return Generator<int, array{path:string,relative:string}, void, void> */
function portal_backup_file_iterator(string $root, string $backupDir): Generator
{
    $flags = FilesystemIterator::SKIP_DOTS;
    $directory = new RecursiveDirectoryIterator($root, $flags);
    $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::LEAVES_ONLY);
    $excludedDirectories = ['.git', 'storage/backups', 'storage/cache', 'storage/phpunit-cache'];

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->isLink()) {
            continue;
        }
        $path = $fileInfo->getPathname();
        $normalized = str_replace('\\', '/', $path);
        $normalizedBackup = rtrim(str_replace('\\', '/', $backupDir), '/') . '/';
        if (str_starts_with($normalized, $normalizedBackup)) {
            continue;
        }
        $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
        $parts = explode('/', $relative);
        $skip = false;
        foreach ($excludedDirectories as $excluded) {
            $excludedParts = explode('/', $excluded);
            if (array_slice($parts, 0, count($excludedParts)) === $excludedParts) {
                $skip = true;
                break;
            }
        }
        if (!$skip && $relative !== '') {
            yield ['path' => $path, 'relative' => $relative];
        }
    }
}

/** @return array{path:string,filename:string,size:int,sha256:string,tables:int,rows:int,files:int,created_at:string} */
function portal_backup_create(PDO $db): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('افزونهٔ PHP ZipArchive روی سرور فعال نیست.');
    }
    $backupDir = portal_backup_ensure_dir();
    $root = portal_backup_project_root();
    $nonce = bin2hex(random_bytes(8));
    $workDir = $backupDir . DIRECTORY_SEPARATOR . '.work-' . $nonce;
    if (!mkdir($workDir, 0750, true)) {
        throw new RuntimeException('پوشهٔ موقت backup ساخته نشد.');
    }

    $zip = null;
    try {
        $createdAt = gmdate('c');
        $sqlPath = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
        $dbSummary = portal_backup_write_sql_dump($db, $sqlPath);
        $filename = 'portal3-backup-' . gmdate('Ymd\THis\Z') . '-' . $nonce . '.zip';
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . $filename;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('فایل ZIP backup ساخته نشد.');
        }
        if (!$zip->addFile($sqlPath, 'database.sql')) {
            throw new RuntimeException('dump دیتابیس به ZIP اضافه نشد.');
        }

        $fileCount = 0;
        foreach (portal_backup_file_iterator($root, $backupDir) as $file) {
            if (!$zip->addFile($file['path'], 'files/' . $file['relative'])) {
                throw new RuntimeException('فایل ' . $file['relative'] . ' به backup اضافه نشد.');
            }
            $fileCount++;
        }
        $manifest = [
            'application' => 'Portal3 customer portal',
            'backup_format' => 1,
            'created_at_utc' => $createdAt,
            'application_version' => trim((string) @file_get_contents($root . '/VERSION')),
            'database' => $dbSummary,
            'files' => $fileCount,
            'note' => 'Backup contains project files and database data. Protect this archive as it may contain credentials and personal data.',
        ];
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $zip->addFromString('manifest.json', $manifestJson);
        $zip->close();
        $zip = null;

        $size = filesize($zipPath);
        if ($size === false || $size <= 0) {
            throw new RuntimeException('فایل backup خالی ساخته شد.');
        }
        return [
            'path' => $zipPath,
            'filename' => $filename,
            'size' => $size,
            'sha256' => hash_file('sha256', $zipPath),
            'tables' => $dbSummary['tables'],
            'rows' => $dbSummary['rows'],
            'files' => $fileCount,
            'created_at' => $createdAt,
        ];
    } finally {
        if ($zip instanceof ZipArchive) {
            $zip->close();
        }
        portal_backup_remove_tree($workDir);
    }
}

/** @return array{filename:string,size:int,sha256:string,modified:int}[] */
function portal_backup_list(): array
{
    $dir = portal_backup_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $items = [];
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'portal3-backup-*.zip') ?: [] as $path) {
        $filename = basename($path);
        if (portal_backup_safe_archive_name($filename) === null || !is_file($path)) {
            continue;
        }
        $items[] = [
            'filename' => $filename,
            'size' => (int) filesize($path),
            'sha256' => (string) hash_file('sha256', $path),
            'modified' => (int) filemtime($path),
        ];
    }
    usort($items, static fn(array $a, array $b): int => $b['modified'] <=> $a['modified']);
    return $items;
}

function portal_backup_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

function portal_backup_validate_zip_entry(string $name): bool
{
    if ($name === '' || str_contains($name, '\0') || str_starts_with($name, '/') || preg_match('~^[A-Za-z]:~', $name)) {
        return false;
    }
    $normalized = str_replace('\\', '/', $name);
    foreach (explode('/', $normalized) as $part) {
        if ($part === '..') {
            return false;
        }
    }
    return true;
}

/** @return array{manifest:array<string,mixed>,zip:ZipArchive} */
function portal_backup_open_and_validate(string $path): array
{
    if (!is_file($path) || filesize($path) > portal_backup_max_bytes()) {
        throw new RuntimeException('فایل backup وجود ندارد یا از سقف مجاز بزرگ‌تر است.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('فایل backup ZIP معتبر نیست.');
    }
    $manifestIndex = $zip->locateName('manifest.json');
    $databaseIndex = $zip->locateName('database.sql');
    if ($manifestIndex === false || $databaseIndex === false) {
        $zip->close();
        throw new RuntimeException('backup باید manifest.json و database.sql داشته باشد.');
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (!is_array($stat) || !portal_backup_validate_zip_entry((string) ($stat['name'] ?? ''))) {
            $zip->close();
            throw new RuntimeException('مسیر ناامن داخل فایل backup شناسایی شد.');
        }
    }
    $manifestRaw = $zip->getFromIndex($manifestIndex);
    $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
    if (!is_array($manifest) || ($manifest['application'] ?? '') !== 'Portal3 customer portal') {
        $zip->close();
        throw new RuntimeException('این فایل backup متعلق به Portal3 نیست.');
    }
    return ['manifest' => $manifest, 'zip' => $zip];
}

/** @return Generator<int, string, void, void> */
function portal_backup_sql_statements(string $path): Generator
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('database.sql از backup خوانده نشد.');
    }
    $statement = '';
    $single = false;
    $double = false;
    $backtick = false;
    $escaped = false;
    try {
        while (($line = fgets($handle)) !== false) {
            if (!$single && !$double && !$backtick && preg_match('/^\s*(--|#)/', $line)) {
                continue;
            }
            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                $statement .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if (($single || $double) && $char === '\\') {
                    $escaped = true;
                    continue;
                }
                if (!$double && !$backtick && $char === "'") {
                    $single = !$single;
                    continue;
                }
                if (!$single && !$backtick && $char === '"') {
                    $double = !$double;
                    continue;
                }
                if (!$single && !$double && $char === '`') {
                    $backtick = !$backtick;
                    continue;
                }
                if (!$single && !$double && !$backtick && $char === ';') {
                    $ready = trim($statement);
                    if ($ready !== '') {
                        yield $ready;
                    }
                    $statement = '';
                }
            }
        }
        $ready = trim($statement);
        if ($ready !== '') {
            yield $ready;
        }
    } finally {
        fclose($handle);
    }
}

function portal_backup_execute_sql(PDO $db, string $path): int
{
    $count = 0;
    foreach (portal_backup_sql_statements($path) as $statement) {
        $db->exec($statement);
        $count++;
    }
    return $count;
}

function portal_backup_copy_files(string $sourceRoot, string $destinationRoot): int
{
    if (!is_dir($sourceRoot)) {
        throw new RuntimeException('بخش فایل‌ها در backup وجود ندارد.');
    }
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->isLink()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($sourceRoot) + 1));
        if ($relative === '' || !portal_backup_validate_zip_entry($relative)) {
            throw new RuntimeException('مسیر فایل backup ناامن است.');
        }
        $target = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) {
            throw new RuntimeException('پوشهٔ مقصد restore ساخته نشد.');
        }
        if (!copy($fileInfo->getPathname(), $target)) {
            throw new RuntimeException('restore فایل ' . $relative . ' ناموفق بود.');
        }
        $count++;
    }
    return $count;
}

/** @return array{pre_restore:string,sql_statements:int,files:int,manifest:array<string,mixed>} */
function portal_backup_restore(PDO $db, string $archivePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('افزونهٔ PHP ZipArchive روی سرور فعال نیست.');
    }
    $opened = portal_backup_open_and_validate($archivePath);
    /** @var ZipArchive $zip */
    $zip = $opened['zip'];
    $manifest = $opened['manifest'];
    $backupBeforeRestore = portal_backup_create($db);
    $workDir = portal_backup_ensure_dir() . DIRECTORY_SEPARATOR . '.restore-' . bin2hex(random_bytes(8));
    if (!mkdir($workDir, 0750, true)) {
        $zip->close();
        throw new RuntimeException('پوشهٔ موقت restore ساخته نشد.');
    }
    $zipClosed = false;
    try {
        if (!$zip->extractTo($workDir)) {
            throw new RuntimeException('استخراج فایل backup ناموفق بود.');
        }
        $zip->close();
        $zipClosed = true;
        $sqlStatements = portal_backup_execute_sql($db, $workDir . DIRECTORY_SEPARATOR . 'database.sql');
        $files = portal_backup_copy_files($workDir . DIRECTORY_SEPARATOR . 'files', portal_backup_project_root());
        return [
            'pre_restore' => $backupBeforeRestore['filename'],
            'sql_statements' => $sqlStatements,
            'files' => $files,
            'manifest' => $manifest,
        ];
    } finally {
        if (!$zipClosed) {
            $zip->close();
        }
        portal_backup_remove_tree($workDir);
    }
}
