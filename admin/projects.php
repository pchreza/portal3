<?php
// admin/projects.php - Project Management
require_once 'auth.php';
if (!admin_can('projects')) { header('Location: index.php'); exit; }
if (!is_module_enabled('projects')) { header('Location: index.php'); exit; }

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
// ========== اکسل: خروجی / نمونه / ورود دسته‌جمعی ==========
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $q = $pdo->query("SELECT p.*, u.first_name, u.last_name, u.username, u.mobile FROM projects p JOIN users u ON u.id = p.customer_id ORDER BY p.id DESC");
    $rows = [['شناسه', 'عنوان پروژه', 'مشتری', 'نام کاربری مشتری', 'توضیحات', 'وضعیت', 'تاریخ تکمیل', 'تاریخ ثبت']];
    foreach ($q->fetchAll() as $p) {
        $rows[] = [
            $p['id'], $p['title'], trim($p['first_name'] . ' ' . $p['last_name']), $p['username'],
            $p['description'], project_status_label($p['status'] ?? 'pending'),
            $p['deadline'] ? portal_date_to_display($p['deadline']) : '-', fa_datetime($p['created_at'] ?? null),
        ];
    }
    excel_output('projects', $rows, 'پروژه‌ها');
}
if (isset($_GET['sample']) && $_GET['sample'] === 'xlsx') {
    $rows = [
        ['عنوان پروژه', 'نام کاربری مشتری', 'توضیحات', 'وضعیت', 'تاریخ تکمیل'],
        ['طراحی وب‌سایت فروشگاهی', 'ali.rezaei', 'طراحی و پیاده‌سازی کامل فروشگاه', 'در حال انجام', '1405/06/30'],
    ];
    excel_output('sample-projects', $rows, 'نمونه پروژه‌ها');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'excel_import') {
    require_valid_csrf();
    $rows = excel_parse_upload($_FILES['excel_file'] ?? []);
    if (empty($rows)) {
        $error = 'فایل اکسل خوانده نشد یا خالی است.';
    } else {
        $result = excel_import_projects($rows);
        $success = $result['added'] . ' پروژه با موفقیت از اکسل وارد شد.';
        if (!empty($result['errors'])) {
            $error = count($result['errors']) . ' سطر نادیده گرفته شد: ' . implode(' | ', array_slice($result['errors'], 0, 6)) . (count($result['errors']) > 6 ? ' …' : '');
        }
        log_activity($_SESSION['user_id'], "ورود دسته‌جمعی پروژه‌ها از اکسل: " . $result['added'] . " مورد");
    }
}


// Handle Delete FIRST — تا درخواست حذف وارد بلاک افزودن/ویرایش نشود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['delete_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$del_id]);

    // پاک‌سازی نظرسنجی‌های مرتبط با پروژه حذف‌شده
    $pdo->prepare("DELETE FROM survey_assignments WHERE entity_type = 'project' AND entity_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM survey_responses WHERE entity_type = 'project' AND entity_id = ?")->execute([$del_id]);

    log_activity($_SESSION['user_id'], "حذف پروژه ID: {$del_id}");
    $_SESSION['flash'] = 'پروژه با موفقیت حذف شد.';
    header('Location: projects.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'excel_import') {
    // Handle Form Submission (افزودن / ویرایش)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'in_progress');
    $deadline = portal_date_to_db(trim($_POST['deadline'] ?? ''));
    $budget = normalize_money_input(trim($_POST['budget'] ?? '')); // شمسی → میلادی

    // پردازش آپلود عکس
    $image = trim($_POST['current_image'] ?? '');
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $f = $_FILES['image'];
        $v = validate_upload_image($f, 4 * 1024 * 1024);
        if ($v === true) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $uploads_dir = __DIR__ . '/../uploads';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }
            $fname = 'project_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $uploads_dir . '/' . $fname)) {
                $image = 'uploads/' . $fname;
            } else {
                $error = 'آپلود عکس انجام نشد.';
            }
        } else {
            $error = $v;
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        $image = '';
    }

    if (empty($title) || $customer_id <= 0) {
        $error = 'انتخاب مشتری و عنوان پروژه الزامی است.';
    } elseif (!$error) {
        $cust_chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'customer'");
        $cust_chk->execute([$customer_id]);
        if (!$cust_chk->fetchColumn()) {
            $error = 'مشتری انتخاب‌شده معتبر نیست.';
        }
    }
    if (!$error) {
        if ($id === 0) {
            $stmt = $pdo->prepare("INSERT INTO projects (customer_id, title, description, budget, status, deadline, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $title, $description, $budget, $status, $deadline, $image]);
            $new_project_id = $pdo->lastInsertId();
            
            // Save custom fields
            save_custom_fields_values('project', $new_project_id);

            log_activity($_SESSION['user_id'], "ایجاد پروژه جدید: {$title}");
            sms_trigger_project_assigned((int) $new_project_id);
            $_SESSION['flash'] = 'پروژه جدید با موفقیت ایجاد و به مشتری منتصب شد.';
            header('Location: projects.php');
            exit;
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET customer_id = ?, title = ?, description = ?, budget = ?, status = ?, deadline = ?, image = ? WHERE id = ?");
            $stmt->execute([$customer_id, $title, $description, $budget, $status, $deadline, $image, $id]);
            
            // Save custom fields
            save_custom_fields_values('project', $id);

            log_activity($_SESSION['user_id'], "ویرایش پروژه ID: {$id}");
            $_SESSION['flash'] = 'پروژه با موفقیت ویرایش شد.';
            header('Location: projects.php');
            exit;
        }
    }
}

$edit_project = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_project = $stmt->fetch();
    if (!$edit_project) $action = 'list';
}

// Fetch all customers for dropdown
$customers = $pdo->query("SELECT id, first_name, last_name, username, company_name, mobile FROM users WHERE role = 'customer' ORDER BY first_name ASC")->fetchAll();

// فیلترهای فهرست پروژه — queryهای prepared و قابل‌اشتراک با pagination.
$project_search = trim((string) ($_GET['q'] ?? ''));
$project_customer_filter = max(0, (int) ($_GET['customer_id'] ?? 0));
$project_status_filter = (string) ($_GET['status'] ?? '');
$project_statuses = ['pending' => 'در انتظار شروع', 'in_progress' => 'در حال انجام', 'completed' => 'تکمیل شده'];
if (!array_key_exists($project_status_filter, $project_statuses)) {
    $project_status_filter = '';
}
$project_where = [];
$project_params = [];
if ($project_search !== '') {
    $project_where[] = '(p.title LIKE ? OR p.description LIKE ? OR u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.company_name LIKE ?)';
    $project_like = '%' . $project_search . '%';
    $project_params = array_fill(0, 6, $project_like);
}
if ($project_customer_filter > 0) {
    $project_where[] = 'p.customer_id = ?';
    $project_params[] = $project_customer_filter;
}
if ($project_status_filter !== '') {
    $project_where[] = 'p.status = ?';
    $project_params[] = $project_status_filter;
}
$project_where_sql = $project_where ? ' WHERE ' . implode(' AND ', $project_where) : '';
$project_count = $pdo->prepare('SELECT COUNT(*) FROM projects p JOIN users u ON p.customer_id = u.id' . $project_where_sql);
$project_count->execute($project_params);
$projects_total = (int) $project_count->fetchColumn();
$pi = pagination_info($projects_total, 15);
$project_list = $pdo->prepare("SELECT p.*, u.first_name, u.last_name, u.username, u.company_name
    FROM projects p
    JOIN users u ON p.customer_id = u.id{$project_where_sql}
    ORDER BY p.id DESC LIMIT " . (int) $pi['per_page'] . " OFFSET " . (int) $pi['offset']);
$project_list->execute($project_params);
$projects = $project_list->fetchAll();
?>
<?php render_admin_header(
    'مدیریت پروژه‌ها',
    'portal-page-main portal-admin-page p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
    ''
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

            <?php if ($action === 'list'): ?>
                <?php render_excel_toolbar([
                    'page' => 'projects.php',
                    'withSample' => true,
                    'withImport' => true,
                    'importHint' => 'ستون‌ها: عنوان پروژه*، نام کاربری مشتری*، توضیحات، وضعیت، تاریخ تکمیل. وضعیت می‌تواند فارسی یا انگلیسی باشد.',
                    'importExtra' => '',
                ]); ?>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- فرم پروژه — طراحی جدید -->
                <div class="portal-form-card card overflow-hidden">
                    <!-- هدر فرم -->
                    <div class="portal-form-header flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 text-white">
                            <span class="portal-form-icon"><?= icon('folder','w-5 h-5') ?></span>
                            <div>
                                <h3 class="font-bold text-lg"><?php echo $action === 'edit' ? 'ویرایش پروژه' : 'تعریف پروژه جدید'; ?></h3>
                                <p class="portal-form-subtitle"><?php echo $action === 'edit' ? 'اطلاعات پروژه را به‌روزرسانی کنید' : 'پروژه را ثبت و به مشتری منتسب کنید'; ?></p>
                            </div>
                        </div>
                            <a href="projects.php" class="btn btn-sm portal-form-back" type="button">← بازگشت به لیست</a>
                    </div>

                    <form method="POST" class="portal-form-body" enctype="multipart/form-data" novalidate>
<div class="form-error-summary" style="display:none" role="alert"></div>
                        <?php echo csrf_input(); ?>
                        <?php if ($edit_project): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_project['id']; ?>">
                        <?php endif; ?>

                        <!-- بخش ۱: انتصاب و اطلاعات پایه -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="portal-form-step">۱</span>
                                <h4 class="portal-form-section-title">اطلاعات پایه و انتصاب</h4>
                            </div>
                            <div class="portal-form-section-panel space-y-5">
                                <div>
                                    <label class="label" for="customer_id">انتصاب به مشتری<span class="required-star" aria-hidden="true">*</span></label>
                                    <label class="helper portal-search-label" for="customer_search">جستجوی مشتری بر اساس نام، نام کاربری یا شرکت</label>
                                    <input type="search" id="customer_search" autocomplete="off" placeholder="جستجو: نام، نام کاربری، شرکت یا موبایل..." class="input portal-form-control mb-2">
                                    <select name="customer_id" id="customer_id" required class="input portal-form-control">
                                        <option value="">انتخاب مشتری...</option>
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo (($edit_project['customer_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name']) !== '' ? $c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['username'] . ')' : $c['username']); ?>
                                                <?php echo $c['company_name'] ? ' - ' . htmlspecialchars($c['company_name']) : ''; ?><?php echo $c['mobile'] ? ' - 📱 ' . htmlspecialchars($c['mobile']) : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="project_title">عنوان پروژه <span class="required-star" aria-hidden="true">*</span></label>
                                    <input type="text" id="project_title" name="title" value="<?php echo htmlspecialchars($edit_project['title'] ?? ''); ?>" required placeholder="مثلاً: طراحی سایت فروشگاهی" class="input portal-form-control">
                                </div>
                                <div>
                                    <label class="label" for="project_description">توضیحات پروژه</label>
                                    <textarea id="project_description" name="description" rows="4" placeholder="شرح مختصری از پروژه بنویسید..." class="input portal-form-control"><?php echo htmlspecialchars($edit_project['description'] ?? ''); ?></textarea>
                                </div>
                                <div>
                                    <label class="label" for="project_budget">بودجه پروژه (تومان)</label>
                                    <input type="text" id="project_budget" name="budget" dir="ltr" value="<?php echo htmlspecialchars($edit_project['budget'] ?? ''); ?>" placeholder="مثال: 5000000" class="input portal-form-control value-ltr">
                                    <p class="helper">فقط عدد، با یا بدون کاما</p>
                                </div>
                            </div>
                        </div>

                        <!-- بخش ۲: وضعیت و تاریخ -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="portal-form-step portal-form-step-success">۲</span>
                                <h4 class="portal-form-section-title">وضعیت و زمان‌بندی</h4>
                            </div>
                            <div class="portal-form-section-panel grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="label" for="project_status">وضعیت پروژه</label>
                                    <select name="status" id="project_status" class="input portal-form-control">
                                        <option value="pending" <?php echo (($edit_project['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>در انتظار شروع</option>
                                        <option value="in_progress" <?php echo (($edit_project['status'] ?? 'in_progress') === 'in_progress') ? 'selected' : ''; ?>>در حال انجام</option>
                                        <option value="completed" <?php echo (($edit_project['status'] ?? '') === 'completed') ? 'selected' : ''; ?>>تکمیل شده</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label" for="deadline">تاریخ تکمیل پروژه</label>
                                    <div class="flex flex-wrap sm:flex-nowrap gap-2 items-stretch">
                                        <input type="text" name="deadline" id="deadline" data-jdp data-jdp-min-date="today" readonly dir="ltr" value="<?php echo htmlspecialchars(portal_date_to_display((string) ($edit_project['deadline'] ?? ''))); ?>" class="input portal-form-control value-ltr flex-1 min-w-0 cursor-pointer" placeholder="انتخاب تاریخ شمسی">
                                        <button type="button" class="jdp-trigger btn btn-secondary btn-icon shrink-0" aria-label="انتخاب تاریخ" data-target="deadline"><?= icon('calendar') ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- بخش ۳: تصویر -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="portal-form-step portal-form-step-accent">۳</span>
                                <h4 class="portal-form-section-title">تصویر پروژه</h4>
                            </div>
                            <div class="portal-form-section-panel">
                                <div class="portal-upload-layout">
                                    <div class="portal-image-preview">
                                        <?php if (!empty($edit_project['image'])): ?>
                                            <img src="<?= htmlspecialchars(asset_url($edit_project['image'])) ?>" class="w-full h-full object-cover" alt="عکس پروژه">
                                        <?php else: ?>
                                            <span class="text-slate-300"><?= icon('folder','w-10 h-10') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="space-y-3 flex-1">
                                        <label class="btn btn-secondary portal-upload-trigger" for="project_image">
                                            <?= icon('folder') ?><span>انتخاب فایل</span>
                                            <input type="file" id="project_image" name="image" accept=".png,.jpg,.jpeg,.webp,.gif,.svg" aria-label="تصویر پروژه" class="hidden">
                                        </label>
                                        <input type="hidden" name="current_image" value="<?= htmlspecialchars($edit_project['image'] ?? '') ?>">
                                        <p class="text-xs text-slate-400">فرمت‌های مجاز: png / jpg / webp / svg — حداکثر ۴MB</p>
                                        <?php if (!empty($edit_project['image'])): ?>
                                            <label class="flex items-center gap-1.5 cursor-pointer text-red-600 text-sm">
                                                <input type="checkbox" name="remove_image" value="1" class="w-4 h-4 text-red-600 rounded border-slate-300"> حذف عکس فعلی
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- فیلدهای سفارشی -->
                        <?php $custom_fields_html = render_custom_fields_inputs('project', $edit_project['id'] ?? 0); ?>
                        <?php if ($custom_fields_html): ?>
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="portal-form-step portal-form-step-muted">۴</span>
                                <h4 class="portal-form-section-title">فیلدهای تکمیلی</h4>
                            </div>
                            <div class="portal-form-section-panel">
                                <?php echo $custom_fields_html; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- دکمه‌ها -->
                        <div class="portal-form-actions flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                            <a href="projects.php" class="btn btn-secondary" type="button">انصراف</a>
                            <button type="submit" class="btn btn-primary btn-lg"><?= icon('check') ?><span>ذخیره پروژه</span></button>
                        </div>
                    </form>
                    <script nonce="<?= e(portal_csp_nonce()) ?>">
                    (function(){
                        var search = document.getElementById('customer_search');
                        var select = document.getElementById('customer_id');
                        if (search && select) {
                            var options = Array.prototype.slice.call(select.options);
                            search.addEventListener('input', function() {
                                var q = this.value.toLowerCase().trim();
                                options.forEach(function(opt) {
                                    if (!opt.value) return;
                                    var text = opt.textContent.toLowerCase();
                                    opt.style.display = q === '' || text.indexOf(q) !== -1 ? '' : 'none';
                                });
                                if (q !== '' && select.selectedIndex === 0) {
                                    for (var i = 1; i < select.options.length; i++) {
                                        if (select.options[i].style.display !== 'none') { select.selectedIndex = i; break; }
                                    }
                                }
                            });
                        }
                        var fileInput = document.getElementById('project_image');
                        var preview = document.querySelector('.portal-image-preview');
                        if (fileInput && preview) {
                            fileInput.addEventListener('change', function() {
                                var file = this.files[0];
                                if (file) {
                                    var reader = new FileReader();
                                    reader.onload = function(e) {
                                        var img = preview.querySelector('img');
                                        if (img) { img.src = e.target.result; }
                                        else {
                                            var ph = preview.querySelector('span'); if (ph) ph.style.display = 'none';
                                            img = document.createElement('img'); img.src = e.target.result;
                                            img.className = 'w-full h-full object-cover'; img.alt = 'پیش‌نمایش';
                                            preview.appendChild(img);
                                        }
                                    };
                                    reader.readAsDataURL(file);
                                }
                            });
                        }
                    })();
                    </script>

                </div>

            <?php else: ?>
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-bold text-slate-800">پروژه‌ها (<?= number_format($projects_total) ?>)</h3>
                    <a href="projects.php?action=add" class="btn btn-primary">
                        <?= icon('plus') ?><span>تعریف پروژه جدید</span>
                    </a>
                </div>

                <form method="get" class="portal-list-toolbar card p-4 flex flex-wrap items-end gap-3" role="search">
                    <div class="min-w-[13rem] flex-1">
                        <label class="label" for="project_list_search">جست‌وجو در پروژه‌ها</label>
                        <input id="project_list_search" name="q" type="search" value="<?= e($project_search) ?>" placeholder="عنوان، مشتری یا شرکت" class="input portal-form-control">
                    </div>
                    <div class="min-w-[11rem]">
                        <label class="label" for="project_list_customer">مشتری</label>
                        <select id="project_list_customer" name="customer_id" class="input portal-form-control">
                            <option value="0">همهٔ مشتریان</option>
                            <?php foreach ($customers as $c): ?>
                                <?php $customerName = trim($c['first_name'] . ' ' . $c['last_name']) ?: $c['username']; ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $project_customer_filter === (int) $c['id'] ? 'selected' : '' ?>><?= e($customerName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="min-w-[10rem]">
                        <label class="label" for="project_list_status">وضعیت</label>
                        <select id="project_list_status" name="status" class="input portal-form-control">
                            <option value="">همهٔ وضعیت‌ها</option>
                            <?php foreach ($project_statuses as $statusKey => $statusLabel): ?>
                                <option value="<?= e($statusKey) ?>" <?= $project_status_filter === $statusKey ? 'selected' : '' ?>><?= e($statusLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                    <?php if ($project_search !== '' || $project_customer_filter > 0 || $project_status_filter !== ''): ?>
                        <a href="projects.php" class="btn btn-secondary">پاک‌کردن</a>
                    <?php endif; ?>
                </form>

                <div class="card portal-list-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4">عنوان پروژه</th>
                                    <th class="p-4">مشتری منتسب شده</th>
                                    <th class="p-4">وضعیت</th>
                                    <th class="p-4">تاریخ تکمیل</th>
                                    <th class="p-4 text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($projects)): ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-slate-400"><?php echo empty_state('هیچ پروژه‌ای ثبت نشده است.', '', 'info'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($projects as $p): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="p-4">
                                                <div class="font-medium text-slate-900"><bdi dir="auto"><?php echo htmlspecialchars($p['title']); ?></bdi></div>
                                                <div class="text-xs text-slate-500 mt-0.5 truncate max-w-xs" title="<?= htmlspecialchars($p['description']) ?>"><bdi dir="auto"><?php echo htmlspecialchars($p['description']); ?></bdi></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-medium text-slate-800"><?php echo htmlspecialchars(trim($p['first_name'] . ' ' . $p['last_name']) !== '' ? $p['first_name'] . ' ' . $p['last_name'] : $p['username']); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($p['company_name'] ?: ''); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <?php 
                                                    $st = $p['status'];
                                                    if ($st === 'completed') echo '<span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">تکمیل شده</span>';
                                                    elseif ($st === 'in_progress') echo '<span class="bg-indigo-50 text-indigo-700 text-xs px-2.5 py-1 rounded-full font-medium">در حال انجام</span>';
                                                    else echo '<span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">در انتظار شروع</span>';
                                                ?>
                                            </td>
                                            <td data-label="تاریخ تکمیل" class="p-4 text-xs text-slate-600 value-ltr" dir="ltr"><?php echo htmlspecialchars($p['deadline'] ? portal_date_to_display($p['deadline']) : '-'); ?></td>
                                            <td data-label="عملیات" class="p-4 min-w-[11rem]"><div class="cell-actions flex flex-wrap items-center justify-center gap-2">
                                                <a href="projects.php?action=edit&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-ghost !text-indigo-600">ویرایش</a>
                                                <form method="POST" data-confirm-msg="آیا از حذف این مورد اطمینان دارید؟"><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?php echo (int)$p['id']; ?>"><?php echo csrf_input(); ?><button type="submit" class="btn btn-sm btn-outline-danger">حذف</button></form>
                                            </div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination($pi, 'projects.php'); ?>
                </div>
            <?php endif; ?>

        <script nonce="<?= e(portal_csp_nonce()) ?>">
(function(){
 const search=document.getElementById('customer_search'), select=document.getElementById('customer_id');
 if(!search||!select)return;
 const options=Array.from(select.options).map(o=>({value:o.value,text:o.text,selected:o.selected}));
 search.addEventListener('input',function(){
  const q=this.value.trim().toLocaleLowerCase();
  const current=select.value;
  select.innerHTML='';
  options.filter(o=>!q||o.text.toLocaleLowerCase().includes(q)).forEach(o=>{const el=new Option(o.text,o.value);if(o.value===current)el.selected=true;select.add(el);});
  if(!select.value && current) select.value=current;
 });
})();
</script>

<?php render_admin_footer(); ?>