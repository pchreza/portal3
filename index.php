<?php
// index.php - Login Landing Page (دو روش ورود: نام کاربری/رمز یا شماره موبایل + کد OTP)
require_once 'config.php';

$error = '';
$success = '';

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if (in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
        header('Location: admin/index.php');
        exit;
    } else {
        header('Location: customer/index.php');
        exit;
    }
}

$login_method = login_method();

// لغو ورود با کد و بازگشت به مرحله ۱
if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['otp_mobile']);
    header('Location: index.php');
    exit;
}

// ---------- پردازش فرم‌ها ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // === ورود با نام کاربری و رمز عبور ===
    if ($login_method === 'username' && $action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $login_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // قفل ورود (پیام اختصاصی؛ نباید با پیام دیگر بازنویسی شود)
        if (!verify_csrf()) {
            $error = 'درخواست نامعتبر است. صفحه را بازخوانی کنید.';
        } elseif (login_is_locked($username, $login_ip)) {
            $error = 'به‌دلیل چند تلاش ناموفق، ورود موقتاً برای ۱۵ دقیقه محدود شده است.';
        } elseif (empty($username) || empty($password)) {
            $error = 'لطفا نام کاربری و رمز عبور را وارد کنید.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                record_login_attempt($username, $login_ip, true);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                if (in_array($user['role'], ['admin', 'super_admin'], true)) {
                    header('Location: admin/index.php');
                    exit;
                } else {
                    header('Location: customer/index.php');
                    exit;
                }
            } else {
                record_login_attempt($username, $login_ip, false);
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        }
    }

    // === ورود با شماره موبایل: مرحله ۱ — ارسال کد ===
    if ($login_method === 'mobile' && $action === 'send_otp') {
        if (!verify_csrf()) {
            $error = 'درخواست نامعتبر است. صفحه را بازخوانی کنید.';
        } else {
            $mobile = fa_digits_to_en(trim($_POST['mobile'] ?? ''));
            $result = send_otp_code($mobile);
            if ($result['ok']) {
                $_SESSION['otp_mobile'] = normalize_mobile_db($mobile);
                $success = 'کد تایید برای شماره شما ارسال شد. لطفا کد را وارد کنید.';
            } else {
                $error = $result['message'];
            }
        }
    }

    // === ورود با شماره موبایل: مرحله ۲ — تایید کد ===
    if ($login_method === 'mobile' && $action === 'verify_otp') {
        if (!verify_csrf()) {
            $error = 'درخواست نامعتبر است. صفحه را بازخوانی کنید.';
        } else {
            $mobile = $_SESSION['otp_mobile'] ?? normalize_mobile_db(fa_digits_to_en($_POST['mobile'] ?? ''));
            $code = fa_digits_to_en(trim($_POST['otp_code'] ?? ''));

            if ($mobile === '' || $code === '') {
                $error = 'شماره موبایل و کد تایید الزامی است.';
            } elseif (verify_otp_code($mobile, $code)) {
                // پیدا کردن کاربر با این شماره (مشتری یا مدیر)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ? AND role IN ('customer','admin','super_admin')");
                $stmt->execute([$mobile]);
                $matches = $stmt->fetchAll();

                if (count($matches) === 0) {
                    $error = 'کاربری با این شماره موبایل یافت نشد.';
                } elseif (count($matches) > 1) {
                    // امنیت: چند کاربر با یک شماره — ورود رد می‌شود تا وضعیت رفع شود
                    $error = 'این شماره موبایل برای چند کاربر ثبت شده است. لطفا با پشتیبانی تماس بگیرید.';
                } else {
                    $user = $matches[0];
                    unset($_SESSION['otp_mobile']);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    if (in_array($user['role'], ['admin', 'super_admin'], true)) {
                        header('Location: admin/index.php');
                    } else {
                        header('Location: customer/index.php');
                    }
                    exit;
                }
            } else {
                $error = 'کد تایید اشتباه است یا منقضی شده است.';
            }
        }
    }
}

$site_title = get_setting('site_title', 'پورتال مشتریان');
$login_subtitle = get_setting('login_subtitle', 'لطفا برای ورود به حساب کاربری خود اطلاعات زیر را وارد کنید');
$footer_text = get_setting('footer_text', 'سیستم هوشمند پورتال مشتریان');

$otp_step = ($login_method === 'mobile' && !empty($_SESSION['otp_mobile'])) ? 2 : 1;
$login_layout = active_login_layout();

// ---------- تابع رندر فرم ورود (مشترک بین همه طرح‌ها) ----------
// پارامترها: $ctx = 'step1'|'step2'|'username'
function render_login_form(string $ctx, string $site_title, string $login_subtitle, string $error, string $success, int $otp_step): void
{
    // هدر فرم (لوگو + عنوان)
    echo '<div class="text-center mb-7">'
        . '<div class="mx-auto mb-4 w-14 h-14 flex items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 shadow-sm">' . site_logo_html('w-14 h-14 rounded-2xl text-xl') . '</div>'
        . '<h1 class="text-xl font-bold text-slate-900 leading-tight">' . htmlspecialchars($site_title) . '</h1>'
        . '<p class="text-sm text-slate-500 mt-1.5 leading-relaxed">' . htmlspecialchars($login_subtitle) . '</p>'
        . '</div>';

    if ($error) {
        echo '<div class="alert alert-danger mb-5" role="alert">' . icon('alert') . '<span>' . htmlspecialchars($error) . '</span></div>';
    }
    if ($success) {
        echo '<div class="alert alert-success mb-5" role="status">' . icon('check') . '<span>' . htmlspecialchars($success) . '</span></div>';
    }

    // ---- شاخص مراحل (فقط برای ورود موبایل) ----
    if ($ctx === 'step1' || $ctx === 'step2') {
        $steps = ['شماره موبایل', 'کد تایید', 'ورود به حساب'];
        $current = $ctx === 'step1' ? 0 : 1;
        echo '<div class="flex items-center justify-center gap-1 mb-6" role="list" aria-label="مراحل ورود">';
        foreach ($steps as $si => $slabel) {
            $done = $si < $current;
            $active = $si === $current;
            $cls = $done ? 'bg-emerald-100 text-emerald-700' : ($active ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-500');
            echo '<div class="flex items-center gap-1">';
            if ($si > 0) {
                echo '<span class="w-6 h-px ' . ($done ? 'bg-emerald-400' : 'bg-slate-300') . '"></span>';
            }
            echo '<div class="flex items-center gap-1.5">'
                . '<span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ' . $cls . '">' . ($done ? '✓' : ($si + 1)) . '</span>'
                . '<span class="text-[11px] font-medium ' . ($active ? 'text-indigo-700' : 'text-slate-400') . ' hidden sm:inline">' . $slabel . '</span>'
                . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    if ($ctx === 'username'): ?>
        <!-- ===== ورود با نام کاربری و رمز عبور ===== -->
        <form method="POST" class="space-y-5" novalidate>
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="login">
            <div class="form-error-summary" style="display:none" role="alert"></div>

            <div class="space-y-1.5">
                <label class="label" for="username">نام کاربری</label>
                <div class="relative">
                    <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 text-slate-400 pointer-events-none"><?= icon('user', 'w-4.5 h-4.5') ?></span>
                    <input type="text" name="username" id="username" required autocomplete="username" class="input !ps-10" placeholder="نام کاربری خود را وارد کنید">
                </div>
                <p class="field-error" style="display:none"></p>
            </div>

            <div class="space-y-1.5">
                <label class="label" for="password">رمز عبور</label>
                <div class="relative">
                    <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 text-slate-400 pointer-events-none"><?= icon('lock', 'w-4.5 h-4.5') ?></span>
                    <input type="password" name="password" id="password" required autocomplete="current-password" class="input !ps-10" placeholder="••••••••">
                </div>
                <p class="field-error" style="display:none"></p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-full"><?= icon('logout') ?><span>ورود به پنل کاربری</span></button>
        </form>
    <?php elseif ($ctx === 'step1'): ?>
        <!-- مرحله ۱: شماره موبایل -->
        <form method="POST" class="space-y-5" novalidate>
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="send_otp">
            <div class="form-error-summary" style="display:none" role="alert"></div>

            <div class="space-y-1.5">
                <label class="label" for="mobile">شماره موبایل</label>
                <div class="relative">
                    <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 text-slate-400 pointer-events-none"><?= icon('phone', 'w-4.5 h-4.5') ?></span>
                    <input type="text" name="mobile" id="mobile" inputmode="tel" dir="ltr" autocomplete="tel" required value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" class="input text-left !ps-10" placeholder="09123456789">
                </div>
                <p class="field-error" style="display:none"></p>
                <p class="helper">شماره موبایل ثبت‌شده در حساب خود را وارد کنید.</p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-full"><?= icon('send') ?><span>ارسال کد تایید</span></button>
        </form>
    <?php else: ?>
        <!-- مرحله ۲: کد تایید -->
        <div class="mb-4 flex items-center gap-2 justify-center text-sm text-slate-600">
            <span class="w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><?= icon('phone', 'w-4 h-4') ?></span>
            <span class="truncate font-medium" dir="ltr"><?= htmlspecialchars($_SESSION['otp_mobile']) ?></span>
        </div>
        <form method="POST" class="space-y-5" novalidate>
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-error-summary" style="display:none" role="alert"></div>
            <input type="hidden" name="mobile" value="<?= htmlspecialchars($_SESSION['otp_mobile']) ?>">

            <div class="space-y-1.5">
                <label class="label" for="otp_code">کد تایید</label>
                <input type="text" name="otp_code" id="otp_code" inputmode="numeric" dir="ltr" required maxlength="6" autocomplete="one-time-code" class="input text-center !text-xl tracking-[0.5em] !ps-0" placeholder="••••••">
                <p class="field-error" style="display:none"></p>
                <p class="helper text-center">کد ارسال‌شده به <b dir="ltr"><?= htmlspecialchars($_SESSION['otp_mobile']) ?></b> را وارد کنید (معتبر تا ۲ دقیقه).</p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-full"><?= icon('logout') ?><span>ورود به پنل</span></button>
        </form>
        <div class="mt-5 text-center">
            <a href="?cancel_otp=1" class="text-sm text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 transition"><?= icon('back', 'w-4 h-4') ?> تغییر شماره موبایل</a>
        </div>
    <?php endif;
}

$login_ctx = $login_method === 'mobile' ? ($otp_step === 1 ? 'step1' : 'step2') : 'username';
$lc = login_config();

// --- محاسبه کلاس بدنه بر اساس پس‌زمینه دلخواه ---
$login_body_style = '';
$bg = $lc['bg_image'] ?: '';
$bgm = $lc['bg_mobile_image'] ?: '';
if ($bg || $bgm) {
    $login_body_style = 'background-size:cover;background-position:center;background-repeat:no-repeat;';
    if ($bg) { $login_body_style .= "background-image:url('" . e(asset_url($bg)) . "');"; }
    if ($bgm) { $login_body_style .= "@media(max-width:767px){background-image:url('" . e(asset_url($bgm)) . "');}"; }
}

// --- منوی سفارشی هدر ---
$hmenu = header_menu_items();

render_public_header('ورود به ' . $site_title, 'bg-slate-50 text-slate-800 min-h-screen login-page');
?>
<div class="absolute top-4 end-4 z-30"><?= portal_theme_toggle() ?></div>
<?php if ($bg || $bgm): ?><style>body.login-page{<?= $login_body_style ?>}<?php if ($bgm): ?>@media(max-width:767px){body.login-page{background-image:url('<?= e(asset_url($bgm)) ?>');background-size:cover;background-position:center;}}<?php endif; ?></style><?php endif; ?>
<?php /* منوی هدر فقط در هدر پنل‌های لاگین‌شده نمایش داده می‌شود، نه در صفحهٔ ورود عمومی */ ?>
<main id="main-content" class="w-full min-h-screen flex">

<?php if ($login_layout === 'split'): ?>
    <!-- ===== طرح دوطرفه — تصویر + فرم (قابل تنظیم) ===== -->
    <?php $img = $lc['split_image'] ?: 'assets/login-side.jpg'; $isFormLeft = $lc['split_side'] === 'left';
          $imgCols = max(4, min(8, (int) round($lc['split_ratio'] / 10))); $formCols = 10 - $imgCols; ?>
    <div class="w-full grid lg:grid-cols-10">
        <!-- سمت تصویر -->
        <div class="lg:col-span-<?= $imgCols ?> <?= $isFormLeft ? 'lg:order-2' : '' ?>">
            <div class="relative h-full min-h-[60vh] hidden lg:flex items-center justify-center overflow-hidden" style="background:linear-gradient(135deg,<?= $lc['branded_from'] ?>,<?= $lc['branded_to'] ?>)">
                <?php if ($img): ?><img src="<?= e(asset_url($img)) ?>" alt="" class="absolute inset-0 w-full h-full object-cover"><?php endif; ?>
                <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(0,0,0,.05),rgba(0,0,0,.15))"></div>
                <div class="relative z-10 max-w-md p-8 text-white text-center">
                    <div class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center"><?= site_logo_html('w-10 h-10 rounded-lg text-xl') ?></div>
                    <h2 class="text-3xl font-bold leading-tight"><?= htmlspecialchars($lc['split_title']) ?></h2>
                    <?php if ($lc['split_subtitle']): ?><p class="mt-3 text-indigo-100 leading-relaxed"><?= htmlspecialchars($lc['split_subtitle']) ?></p><?php endif; ?>
                    <div class="mt-8 flex justify-center gap-6 text-sm">
                        <div class="text-center"><div class="text-2xl font-bold"><?= htmlspecialchars($lc['split_feature1']) ?></div><div class="text-indigo-100 mt-1"><?= htmlspecialchars($lc['split_feature1_l']) ?></div></div>
                        <div class="text-center"><div class="text-2xl font-bold"><?= htmlspecialchars($lc['split_feature2']) ?></div><div class="text-indigo-100 mt-1"><?= htmlspecialchars($lc['split_feature2_l']) ?></div></div>
                        <div class="text-center"><div class="text-2xl font-bold"><?= htmlspecialchars($lc['split_feature3']) ?></div><div class="text-indigo-100 mt-1"><?= htmlspecialchars($lc['split_feature3_l']) ?></div></div>
                    </div>
                </div>
            </div>
            <?php if ($lc['split_mobile_image']): ?>
                <div class="lg:hidden relative h-40 overflow-hidden">
                    <img src="<?= e(asset_url($lc['split_mobile_image'])) ?>" alt="" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/20"></div>
                    <div class="relative z-10 h-full flex items-center justify-center text-white"><div class="text-center"><?= site_logo_html('w-12 h-12 rounded-xl text-xl') ?><div class="mt-2 font-bold"><?= htmlspecialchars($lc['split_title']) ?></div></div></div>
                </div>
            <?php endif; ?>
        </div>
        <!-- سمت فرم -->
        <div class="lg:col-span-<?= $formCols ?> <?= $isFormLeft ? 'lg:order-1' : '' ?>">
            <?php
                $vAlign = $lc['split_vertical'];
                $alignCls = $vAlign === 'top' ? 'items-start' : ($vAlign === 'bottom' ? 'items-end' : 'items-center');
            ?>
            <div class="flex <?= $alignCls ?> justify-center p-6 pt-24 lg:pt-6 pb-10 min-h-full">
                <div class="w-full max-w-md">
                    <div class="card p-7 sm:p-9 shadow-xl">
                        <?php render_login_form($login_ctx, $site_title, $login_subtitle, $error, $success, $otp_step); ?>
                    </div>
                    <p class="text-center text-xs text-slate-400 mt-6"><?= htmlspecialchars($footer_text) ?></p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($login_layout === 'branded'): ?>
    <!-- ===== طرح گرادیان برند (رنگ‌بندی قابل تنظیم) ===== -->
    <div class="w-full relative flex items-center justify-center px-4 pt-24 pb-10 sm:pt-16 overflow-hidden"
         style="background:linear-gradient(135deg,<?= $lc['branded_from'] ?> 0%,<?= $lc['branded_to'] ?> 100%)">
        <?php if ($lc['branded_mobile_image']): ?><img src="<?= e(asset_url($lc['branded_mobile_image'])) ?>" alt="" class="lg:hidden absolute inset-0 w-full h-full object-cover opacity-30"><?php endif; ?>
        <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 20% 20%,#fff 0,transparent 40%),radial-gradient(circle at 80% 80%,#fff 0,transparent 40%)"></div>
        <div class="relative z-10 w-full max-w-md">
            <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur rounded-3xl shadow-2xl p-8">
                <?php render_login_form($login_ctx, $site_title, $login_subtitle, $error, $success, $otp_step); ?>
            </div>
            <p class="text-center text-xs text-indigo-100 mt-6"><?= htmlspecialchars($footer_text) ?></p>
        </div>
    </div>

<?php elseif ($login_layout === 'minimal'): ?>
    <!-- ===== طرح مینیمال (بدون لوگوی تکراری) ===== -->
    <div class="w-full flex items-center justify-center px-4 pt-24 pb-10 sm:pt-16">
        <div class="w-full max-w-sm">
            <div class="bg-transparent">
                <?php render_login_form($login_ctx, $site_title, $login_subtitle, $error, $success, $otp_step); ?>
            </div>
            <p class="text-center text-xs text-slate-400 mt-6"><?= htmlspecialchars($footer_text) ?></p>
        </div>
    </div>

<?php else: ?>
    <!-- ===== طرح کارت متمرکز (پیش‌فرض) ===== -->
    <div class="w-full flex items-center justify-center px-4 pt-24 pb-10 sm:pt-16">
        <div class="w-full max-w-md">
            <div class="card p-7 sm:p-9 shadow-xl">
                <?php render_login_form($login_ctx, $site_title, $login_subtitle, $error, $success, $otp_step); ?>
            </div>
            <p class="text-center text-xs text-slate-400 mt-6"><?= htmlspecialchars($footer_text) ?></p>
        </div>
    </div>
<?php endif; ?>

</main>
<?php render_public_footer();
