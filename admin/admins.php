<?php
// admin/admins.php — مدیریت مدیران سیستم (فقط سوپر ادمین)
require_once 'auth.php';
if ($_SESSION['role'] !== 'super_admin') {
    header('Location: index.php');
    exit;
}

$msg = '';
$err = '';

// ---------- پردازش فرم‌ها ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';

    if ($a === 'add') {
        // ساخت مدیر جدید
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $first_name  = trim($_POST['first_name'] ?? '');
        $last_name   = trim($_POST['last_name'] ?? '');
        $mobile      = fa_digits_to_en(trim($_POST['mobile'] ?? ''));
        $mobile_db   = $mobile !== '' ? normalize_mobile_db($mobile) : '';

        if ($username === '' || strlen($password) < 8) {
            $err = 'نام کاربری و رمز عبور (حداقل ۸ کاراکتر) الزامی است.';
        } elseif ($mobile_db !== '' && mobile_exists($mobile_db)) {
            $err = 'این شماره موبایل قبلاً برای کاربر دیگری (مشتری یا مدیر) ثبت شده است.';
        } else {
            try {
                $q = $pdo->prepare("INSERT INTO users (username, password, role, first_name, last_name, mobile) VALUES (?, ?, 'admin', ?, ?, ?)");
                $q->execute([$username, password_hash($password, PASSWORD_DEFAULT), $first_name, $last_name, $mobile_db]);

                // دسترسی‌های پیش‌فرض (همه فعال)
                $admin_id = (int) $pdo->lastInsertId();
                $ins = $pdo->prepare("INSERT IGNORE INTO admin_permissions (role, permission) VALUES ('admin', ?)");
                foreach (array_keys(admin_permissions_list()) as $perm) {
                    if ($perm === 'admins') continue;
                    $ins->execute([$perm]);
                }

                log_activity($_SESSION['user_id'], "ایجاد مدیر جدید: {$username}");
                $msg = "مدیر «{$username}» با موفقیت ایجاد شد.";
            } catch (Exception $e) {
                $err = 'خطا در ایجاد مدیر (احتمالا نام کاربری تکراری است).';
            }
        }
    } elseif ($a === 'permissions') {
        // ذخیره دسترسی‌های مدیران عادی (مشترک برای همه مدیران عادی)
        $perms = $_POST['perms'] ?? [];
        if (!is_array($perms)) {
            $perms = [];
        }
        $pdo->prepare("DELETE FROM admin_permissions WHERE role = 'admin'")->execute();
        $ins = $pdo->prepare("INSERT INTO admin_permissions (role, permission) VALUES ('admin', ?)");
        foreach (array_keys(admin_permissions_list()) as $perm) {
            if ($perm === 'admins') {
                continue;
            }
            if (in_array($perm, $perms, true)) {
                $ins->execute([$perm]);
            }
        }
        log_activity($_SESSION['user_id'], "بروزرسانی دسترسی‌های مدیران");
        $msg = 'دسترسی‌های مدیران با موفقیت ذخیره شد.';
    } elseif ($a === 'delete') {
        $admin_id = (int) ($_POST['admin_id'] ?? 0);
        if ($admin_id === (int) $_SESSION['user_id']) {
            $err = 'نمی‌توانید حساب خودتان را حذف کنید.';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$admin_id]);
            log_activity($_SESSION['user_id'], "حذف مدیر ID: {$admin_id}");
            $msg = 'مدیر حذف شد.';
        }
    }
}

// ---------- داده‌ها ----------
$admins = $pdo->query("SELECT id, username, first_name, last_name, mobile, role, created_at FROM users WHERE role IN ('admin', 'super_admin') ORDER BY id ASC")->fetchAll();

// دسترسی‌های فعلی نقش admin
$current_perms = [];
$q = $pdo->query("SELECT permission FROM admin_permissions WHERE role = 'admin'");
foreach ($q->fetchAll() as $p) {
    $current_perms[] = $p['permission'];
}

render_admin_header('مدیریت مدیران سیستم', 'p-8 max-w-5xl w-full mx-auto space-y-6');
?>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- افزودن مدیر جدید -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2"><?= icon('plus','w-5 h-5 text-indigo-600') ?> افزودن مدیر جدید</h3>
                <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="add">
                    <div>
                        <label class="label" for="am_username">نام کاربری<span class="required-star" aria-hidden="true">*</span></label>
                        <input type="text" name="username" id="am_username" required class="input">
                    </div>
                    <div>
                        <label class="label" for="am_password">رمز عبور<span class="required-star" aria-hidden="true">*</span> (حداقل ۸)</label>
                        <input type="password" name="password" id="am_password" required minlength="8" class="input">
                    </div>
                    <div>
                        <label class="label" for="am_fn">نام</label>
                        <input type="text" name="first_name" id="am_fn" class="input">
                    </div>
                    <div>
                        <label class="label" for="am_ln">نام خانوادگی</label>
                        <input type="text" name="last_name" id="am_ln" class="input">
                    </div>
                    <div>
                        <label class="label" for="am_mobile">شماره موبایل</label>
                        <input type="text" name="mobile" id="am_mobile" dir="ltr" class="input">
                    </div>
                    <div>
                        <button class="btn btn-primary w-full">ایجاد مدیر</button>
                    </div>
                </form>
            </div>

            <!-- لیست مدیران -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-card-mobile">
                        <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                            <tr>
                                <th class="p-4">مدیر</th>
                                <th class="p-4">نام کاربری</th>
                                <th class="p-4">موبایل</th>
                                <th class="p-4">نقش</th>
                                <th class="p-4">تاریخ ایجاد</th>
                                <th class="p-4 text-center">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($admins)): ?>
                                <tr><td colspan="6" class="p-6 text-center text-slate-400">مدیری ثبت نشده است.</td></tr>
                            <?php else: ?>
                                <?php foreach ($admins as $ad): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4 font-medium text-slate-900">
                                            <?= htmlspecialchars(trim($ad['first_name'] . ' ' . $ad['last_name']) ?: '-') ?>
                                            <?php if ((int) $ad['id'] === (int) $_SESSION['user_id']): ?><span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full mr-1">شما</span><?php endif; ?>
                                        </td>
                                        <td class="p-4 text-slate-600"><?= htmlspecialchars($ad['username']) ?></td>
                                        <td class="p-4 text-xs text-slate-500" dir="ltr"><?= htmlspecialchars($ad['mobile'] ?: '-') ?></td>
                                        <td class="p-4">
                                            <?php if ($ad['role'] === 'super_admin'): ?>
                                                <span class="bg-purple-50 text-purple-700 text-xs px-2.5 py-1 rounded-full font-medium">مدیر ارشد</span>
                                            <?php else: ?>
                                                <span class="bg-slate-100 text-slate-700 text-xs px-2.5 py-1 rounded-full">مدیر</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-xs text-slate-500"><?= htmlspecialchars(fa_datetime($ad['created_at'])) ?></td>
                                        <td class="p-4">
                                            <div class="flex items-center justify-center gap-2">
                                                <?php if ($ad['role'] !== 'super_admin'): ?>
                                                    <a href="#perms-<?= $ad['id'] ?>" onclick="document.getElementById('perm-box').classList.remove('hidden'); document.getElementById('perm-admin-name').textContent='<?= htmlspecialchars(addslashes($ad['username'])) ?>';" class="btn btn-sm btn-ghost !text-indigo-600">دسترسی‌ها</a>
                                                    <form method="post" data-confirm-msg="حذف این مدیر؟">
                                                        <?php echo csrf_input(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="admin_id" value="<?= $ad['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- تنظیم دسترسی‌های مدیران (مشترک برای همه مدیران عادی) -->
            <div id="perm-box" class="hidden bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="mb-4 pb-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">🔐 دسترسی‌های مدیران</h3>
                    <span class="text-xs text-slate-500">برای: <b id="perm-admin-name" class="text-slate-700">—</b></span>
                </div>
                <p class="text-xs text-slate-400 mb-3">این دسترسی‌ها برای <b>همه مدیران عادی</b> اعمال می‌شود (مدیر ارشد همیشه دسترسی کامل دارد).</p>
                <form method="post">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="permissions">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        <?php foreach (admin_permissions_list() as $pkey => $plabel): ?>
                            <?php if ($pkey === 'admins') continue; ?>
                            <label class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 cursor-pointer hover:border-indigo-300 transition <?= in_array($pkey, $current_perms, true) ? 'bg-indigo-50/40' : '' ?>">
                                <input type="checkbox" name="perms[]" value="<?= $pkey ?>" <?= in_array($pkey, $current_perms, true) ? 'checked' : '' ?> class="w-4 h-4 text-indigo-600 rounded border-slate-300">
                                <span class="text-sm text-slate-700"><?= $plabel ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex justify-end pt-4">
                        <button class="btn btn-primary">ذخیره دسترسی‌ها</button>
                    </div>
                </form>
            </div>

        <?php render_admin_footer();
