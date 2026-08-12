<?php
// customer/profile.php - Customer Profile & Mandatory Fields Completion
require_once 'auth.php';

$success = '';
$error = '';

$user_id = $_SESSION['user_id'];

// Handle Skip action — فقط از طریق POST با CSRF (قبلاً GET بود → CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'skip_profile') {
    require_valid_csrf();
    $stmt = $pdo->prepare("UPDATE users SET profile_skipped = 1 WHERE id = ?");
    $stmt->execute([$user_id]);
    log_activity($user_id, "رد موقت تکمیل پروفایل");
    header('Location: index.php');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $mobile = fa_digits_to_en(trim($_POST['mobile'] ?? ''));
    $mobile = $mobile !== '' ? normalize_mobile_db($mobile) : null; // NULL تا ایندکس یکتا موبایل نشکند
    $company_name = trim($_POST['company_name'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    $birth_date = portal_date_to_db(trim($_POST['birth_date'] ?? '')); // شمسی → میلادی
    $gender = trim($_POST['gender'] ?? '');
    $password = $_POST['password'];

    // Check mandatory fields based on admin settings
    $fields_config = ['first_name', 'last_name', 'mobile', 'company_name', 'job_title', 'birth_date', 'gender'];
    $labels = [
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'mobile' => 'شماره موبایل',
        'company_name' => 'نام شرکت',
        'job_title' => 'سمت سازمانی',
        'birth_date' => 'تاریخ تولد',
        'gender' => 'جنسیت'
    ];

    $has_error = false;
    foreach ($fields_config as $f) {
        $is_req = get_setting('req_' . $f, '0') === '1';
        if ($is_req && empty($$f)) {
            $error = 'فیلد «' . $labels[$f] . '» اجباری است و نمی‌تواند خالی باشد.';
            $has_error = true;
            break;
        }
    }

    // شماره موبایل نباید با کاربر دیگری یکسان باشد (مشتری یا مدیر)
    if (!$has_error && $mobile !== null && $mobile !== '' && mobile_exists($mobile, $user_id)) {
        $error = 'این شماره موبایل قبلاً برای کاربر دیگری (مشتری یا مدیر) ثبت شده است.';
        $has_error = true;
    }

    if (!$has_error) {
        try {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, first_name = ?, last_name = ?, mobile = ?, company_name = ?, job_title = ?, birth_date = ?, gender = ?, profile_skipped = 0 WHERE id = ?");
                $stmt->execute([$hashed, $first_name, $last_name, $mobile, $company_name, $job_title, $birth_date, $gender, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, mobile = ?, company_name = ?, job_title = ?, birth_date = ?, gender = ?, profile_skipped = 0 WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $mobile, $company_name, $job_title, $birth_date, $gender, $user_id]);
            }
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            // ذخیره فیلدهای سفارشی (اگر ماژول فعال باشد)
            save_custom_fields_values('customer', $user_id);
            $awardedPoints = gamification_award_profile_completion((int) $user_id);
            if ($awardedPoints > 0) {
                gamification_award_feedback((int) $user_id, 'profile_completed', $awardedPoints);
            }
            log_activity($user_id, "بروزرسانی پروفایل شخصی");
            $success = 'پروفایل شما با موفقیت بروزرسانی شد.';
        } catch (Exception $e) {
            error_log('[Customer Profile] ' . $e->getMessage());
            $error = $e instanceof PDOException && $e->getCode() === '23000'
                ? 'این شماره موبایل قبلاً برای کاربر دیگری ثبت شده است.'
                : 'به‌روزرسانی پروفایل انجام نشد. اطلاعات واردشده را بررسی کنید.';
        }
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$full_name = trim($user['first_name'] . ' ' . $user['last_name']) !== '' ? $user['first_name'] . ' ' . $user['last_name'] : $user['username'];
?>
<?php render_customer_header(
    'پروفایل کاربری',
    'portal-page-main portal-profile-page p-8 max-w-4xl w-full mx-auto space-y-6',
    '',
    '',
    $full_name
); ?>


            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php
            // Check if any mandatory fields are missing
            $fields_check = ['first_name', 'last_name', 'mobile', 'company_name', 'job_title', 'birth_date', 'gender'];
            $missing_fields = [];
            foreach ($fields_check as $fc) {
                if (get_setting('req_' . $fc, '0') === '1' && empty($user[$fc])) {
                    $missing_fields[] = $fc;
                }
            }
            $profile_offer = gamification_context_offer((int) $user_id, 'profile_completed');
            $profile_is_complete = gamification_profile_is_complete((int) $user_id);
            if (!empty($missing_fields)):
            ?>
                <div class="portal-completion-banner portal-context-banner bg-amber-50 border border-amber-200 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-amber-800 text-base">تکمیل اطلاعات الزامی</h4>
                        <p class="microcopy text-sm text-amber-700 mt-1">برخی از اطلاعات پروفایل شما باید تکمیل شوند. لطفاً فیلدهای خواسته‌شده را وارد کنید.</p>
                    </div>
                    <form method="POST" class="inline"><?php echo csrf_input(); ?><input type="hidden" name="action" value="skip_profile"><button type="submit" class="btn btn-primary">تکمیل بعداً</button></form>
                </div>
            <?php endif; ?>

            <?php if ($profile_offer): ?>
                <aside class="alert alert-info items-center justify-between gap-4" role="note">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="shrink-0 mt-0.5"><?= icon('star', 'w-5 h-5') ?></span>
                        <div class="min-w-0">
                            <h4 class="font-bold text-sm"><?= $profile_is_complete ? 'با ذخیرهٔ پروفایل ' : 'با تکمیل پروفایل ' ?><?= e(gamification_points_label($profile_offer['points'])) ?> بگیرید</h4>
                            <p class="microcopy text-xs mt-1"><?= $profile_is_complete ? 'اطلاعات شما کامل است؛ تغییرات را ذخیره کنید تا امتیاز این فعالیت ثبت شود.' : 'فیلدهای ستاره‌دار را کامل کنید و تغییرات را ذخیره کنید تا امتیاز شما ثبت شود.' ?></p>
                        </div>
                    </div>
                    <a href="#profile-form" class="btn btn-primary btn-sm shrink-0"><?= $profile_is_complete ? 'ذخیره و دریافت' : 'تکمیل پروفایل' ?></a>
                </aside>
            <?php endif; ?>

            <div id="profile-form" class="portal-form-card card p-6 md:p-8">
                <div class="portal-section-heading mb-6 pb-4 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-slate-800">ویرایش اطلاعات شخصی</h3>
                    <p class="text-sm text-slate-500 mt-1">اطلاعات سازمانی و شخصی خود را در این بخش مشاهده و ویرایش کنید.</p>
                </div>

                <form method="POST" class="space-y-6" novalidate><?php echo csrf_input(); ?>
                    <div class="form-error-summary" style="display:none" role="alert"></div>
                    <div class="portal-form-grid grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="label" for="pf_username">نام کاربری</label>
                            <input type="text" id="pf_username" value="<?php echo htmlspecialchars($user['username']); ?>" dir="ltr" disabled class="value-ltr input bg-slate-100 text-slate-500 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="label" for="pf_password">تغییر رمز عبور (در صورت نیاز)</label>
                            <input type="password" name="password" id="pf_password" class="input" placeholder="••••••••">
                        </div>
                        <div>
                            <label class="label" for="pf_first_name">نام<?php echo get_setting('req_first_name') === '1' ? '<span class="required-star" aria-hidden="true">*</span>' : ''; ?></label>
                            <input type="text" name="first_name" id="pf_first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" <?php echo get_setting('req_first_name') === '1' ? 'required' : ''; ?> class="input">
                        </div>
                        <div>
                            <label class="label" for="pf_last_name">نام خانوادگی<?php echo get_setting('req_last_name') === '1' ? '<span class="required-star" aria-hidden="true">*</span>' : ''; ?></label>
                            <input type="text" name="last_name" id="pf_last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" <?php echo get_setting('req_last_name') === '1' ? 'required' : ''; ?> class="input">
                        </div>
                        <div>
                            <label class="label" for="pf_mobile">شماره موبایل<?php echo get_setting('req_mobile') === '1' ? '<span class="required-star" aria-hidden="true">*</span>' : ''; ?></label>
                            <input type="text" name="mobile" id="pf_mobile" dir="ltr" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" <?php echo get_setting('req_mobile') === '1' ? 'required' : ''; ?> class="value-ltr input">
                        </div>
                        <div>
                            <label class="label" for="pf_company">نام شرکت<?php echo get_setting('req_company_name') === '1' ? '<span class="required-star" aria-hidden="true">*</span>' : ''; ?></label>
                            <input type="text" name="company_name" id="pf_company" value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>" <?php echo get_setting('req_company_name') === '1' ? 'required' : ''; ?> class="input">
                        </div>
                        <div>
                            <label class="label" for="pf_job">سمت سازمانی<?php echo get_setting('req_job_title') === '1' ? '<span class="required-star" aria-hidden="true">*</span>' : ''; ?></label>
                            <input type="text" name="job_title" id="pf_job" value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>" <?php echo get_setting('req_job_title') === '1' ? 'required' : ''; ?> class="input">
                        </div>
                        <div>
                            <label class="label" for="pf_birth">تاریخ تولد<?php echo get_setting('req_birth_date') === '1' ? '<span class="required-star" aria-hidden="true">*</span>' : ''; ?></label>
                            <div class="flex flex-wrap sm:flex-nowrap gap-2 items-stretch">
                                <input type="text" name="birth_date" id="pf_birth" data-jdp data-jdp-max-date="today" readonly dir="ltr" value="<?php echo htmlspecialchars(portal_date_to_display((string) ($user['birth_date'] ?? ''))); ?>" <?php echo get_setting('req_birth_date') === '1' ? 'required' : ''; ?> class="value-ltr input cursor-pointer" placeholder="انتخاب تاریخ شمسی">
                                <button type="button" class="jdp-trigger btn btn-secondary shrink-0" aria-label="انتخاب تاریخ" data-target="pf_birth"><?= icon('calendar') ?><span>انتخاب تاریخ</span></button>
                            </div>
                        </div>
                        <div>
                            <label class="label" for="pf_gender">جنسیت<?php echo get_setting('req_gender') === '1' ? '<span class="required-star" aria-hidden="true">*</span>' : ''; ?></label>
                            <select name="gender" id="pf_gender" <?php echo get_setting('req_gender') === '1' ? 'required' : ''; ?> class="input cursor-pointer">
                                <option value="">انتخاب کنید...</option>
                                <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>مرد</option>
                                <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>زن</option>
                                <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>سایر</option>
                            </select>
                        </div>
                    </div>

                    <?php $cust_fields_html = render_custom_fields_inputs('customer', $user_id, true); ?>
                    <?php if ($cust_fields_html): ?>
                    <div class="portal-form-section mt-6 pt-6 border-t border-slate-200">
                        <h4 class="font-bold text-slate-800 text-sm mb-4 flex items-center gap-2"><?= icon('wrench','w-4 h-4 text-indigo-600') ?> فیلدهای تکمیلی</h4>
                        <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5">
                            <?php echo $cust_fields_html; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="portal-form-actions desktop-form-actions flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-4 border-t border-slate-200">
                        <a href="index.php" class="btn btn-secondary">بازگشت</a>
                        <button type="submit" class="btn btn-primary"><?= icon('check') ?><span>ذخیره تغییرات پروفایل</span></button>
                    </div>
                    <div class="portal-mobile-form-actions mobile-action-bar">
                        <a href="index.php" class="btn btn-secondary">بازگشت</a>
                        <button type="submit" class="btn btn-primary"><?= icon('check') ?><span>ذخیره</span></button>
                    </div>
                </form>
            </div>

        <?php render_customer_footer(); ?>