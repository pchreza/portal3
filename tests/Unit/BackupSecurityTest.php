<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackupSecurityTest extends TestCase
{
    public function testBackupArchiveNamesAreStrictlyValidated(): void
    {
        self::assertSame(
            'portal3-backup-20260812T102030Z-0123456789abcdef.zip',
            portal_backup_safe_archive_name('portal3-backup-20260812T102030Z-0123456789abcdef.zip')
        );
        self::assertNull(portal_backup_safe_archive_name('../portal3-backup-20260812T102030Z-0123456789abcdef.zip'));
        self::assertNull(portal_backup_safe_archive_name('database.zip'));
    }

    public function testZipEntriesRejectTraversalAndAbsolutePaths(): void
    {
        self::assertTrue(portal_backup_validate_zip_entry('files/customer/profile.png'));
        self::assertFalse(portal_backup_validate_zip_entry('../config.php'));
        self::assertFalse(portal_backup_validate_zip_entry('/etc/passwd'));
        self::assertFalse(portal_backup_validate_zip_entry('C:\\Windows\\system32\\config'));
    }
}
