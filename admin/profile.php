<?php
// admin/profile.php — پروفایل مدیر سیستم (شماره موبایل برای ورود OTP، تغییر رمز، اطلاعات)
require_once 'auth.php';
if (!admin_can('profile')) { header('Location: index.php'); exit; }

$uid = (int) $_SESSION['user_id'];
$msg = '';
$err = '';

// ذخیره پروفایل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $mobile     = fa_digits_to_en(trim($_POST['mobile'] ?? ''));
    $mobile_db  = $mobile !== '' ? normalize_mobile_db($mobile) : null;

    // اعتبارسنجی موبایل (اختیاری — اگر وارد شد باید معتبر باشد)
    if ($mobile_db !== null && $mobile_db !== '' && (strlen($mobile_db) !== 11 || !str_starts_with($mobile_db, '09'))) {
        $err = 'شماره موبایل معتبر نیست (مثال: 09123456789).';
    } else {
        // بررسی تکراری نبودن موبایل بین کاربران دیگر (مشتری یا مدیر)
        if ($mobile_db !== null && $mobile_db !== '' && mobile_exists($mobile_db, $uid)) {
            $err = 'این شماره موبایل قبلاً برای کاربر دیگری (مشتری یا مدیر) ثبت شده است.';
        }
        if (!$err) {
            $q = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, mobile = ? WHERE id = ? AND role IN ('admin', 'super_admin')");
            $q->execute([$first_name, $last_name, $mobile_db, $uid]);
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            log_activity($uid, "بروزرسانی پروفایل مدیر");
            $msg = 'پروفایل مدیر با موفقیت ذخیره شد.';
        }
    }
}

// تغییر رمز عبور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $current      = $_POST['current_password'] ?? '';
    $new          = $_POST['new_password'] ?? '';
    $new_confirm  = $_POST['new_password_confirm'] ?? '';

    $q = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $q->execute([$uid]);
    $hash = $q->fetchColumn();

    if (!password_verify($current, (string) $hash)) {
        $err = 'رمز عبور فعلی اشتباه است.';
    } elseif (strlen($new) < 8) {
        $err = 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.';
    } elseif ($new !== $new_confirm) {
        $err = 'تکرار رمز عبور جدید مطابقت ندارد.';
    } else {
        $q = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $q->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        log_activity($uid, "تغییر رمز عبور مدیر");
        $msg = 'رمز عبور با موفقیت تغییر کرد.';
    }
}

$q = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$q->execute([$uid]);
$admin = $q->fetch();

render_admin_header('پروفایل مدیر سیستم', 'portal-page-main portal-admin-page portal-profile-page p-8 max-w-3xl w-full mx-auto space-y-6');
?>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <!-- اطلاعات مدیر -->
            <div class="card portal-form-card p-6 md:p-8">
                <div class="portal-section-heading mb-6">
                    <h3><?= icon('user','w-5 h-5 text-indigo-600') ?> اطلاعات مدیر</h3>
                    <p class="text-sm text-slate-500 mt-0.5">شماره موبایل برای ورود با کد تایید (OTP) استفاده می‌شود.</p>
                </div>
                <form method="post" class="space-y-6" novalidate>
                    <div class="form-error-summary" style="display:none" role="alert"></div>
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="profile">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label" for="ap_username">نام کاربری</label>
                            <input type="text" id="ap_username" value="<?= htmlspecialchars($admin['username']) ?>" disabled class="input portal-form-control portal-input-disabled cursor-not-allowed">
                        </div>
                        <div>
                            <label class="label" for="ap_role">نقش</label>
                            <input type="text" id="ap_role" value="<?= htmlspecialchars(admin_role_label($admin['role'] ?? 'admin')) ?>" disabled class="input portal-form-control portal-input-disabled cursor-not-allowed">
                        </div>
                        <div>
                            <label class="label" for="ap_fn">نام</label>
                            <input type="text" name="first_name" id="ap_fn" value="<?= htmlspecialchars($admin['first_name'] ?? '') ?>" class="input portal-form-control">
                        </div>
                        <div>
                            <label class="label" for="ap_ln">نام خانوادگی</label>
                            <input type="text" name="last_name" id="ap_ln" value="<?= htmlspecialchars($admin['last_name'] ?? '') ?>" class="input portal-form-control">
                        </div>
                        <div class="md:col-span-2">
                            <label class="label" for="ap_mobile">شماره موبایل (برای ورود با کد تایید)</label>
                            <input type="text" name="mobile" id="ap_mobile" dir="ltr" value="<?= htmlspecialchars($admin['mobile'] ?? '') ?>" placeholder="09123456789" class="input portal-form-control">
                            <p class="helper">اگر روش ورود «شماره موبایل و کد تایید» فعال باشد، با این شماره وارد سیستم می‌شوید.</p>
                        </div>
                    </div>
                    <div class="desktop-form-actions flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                        <button class="btn btn-primary"><?= icon('check') ?><span>ذخیره اطلاعات</span></button>
                    </div>
                    <div class="mobile-action-bar"><button class="btn btn-primary"><?= icon('check') ?><span>ذخیره</span></button></div>
                </form>
            </div>

            <!-- تغییر رمز عبور -->
            <div class="card portal-form-card p-6 md:p-8">
                <div class="portal-section-heading mb-6">
                    <h3><?= icon('lock','w-5 h-5 text-indigo-600') ?> تغییر رمز عبور</h3>
                </div>
                <form method="post" class="space-y-5" novalidate>
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="action" value="password">
                    <div>
                        <label class="label" for="cp_current">رمز عبور فعلی</label>
                        <input type="password" name="current_password" id="cp_current" required class="input portal-form-control">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="label" for="cp_new">رمز عبور جدید</label>
                            <input type="password" name="new_password" id="cp_new" required minlength="8" class="input portal-form-control">
                        </div>
                        <div>
                            <label class="label" for="cp_new2">تکرار رمز عبور جدید</label>
                            <input type="password" name="new_password_confirm" id="cp_new2" required minlength="8" class="input portal-form-control">
                        </div>
                    </div>
                    <div class="desktop-form-actions flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                        <button class="btn btn-primary"><?= icon('lock') ?><span>تغییر رمز عبور</span></button>
                    </div>
                    <div class="mobile-action-bar"><button class="btn btn-primary"><?= icon('lock') ?><span>تغییر رمز</span></button></div>
                </form>
            </div>

        <?php render_admin_footer();
