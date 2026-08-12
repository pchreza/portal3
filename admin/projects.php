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
    $deadline = portal_date_to_db(trim($_POST['deadline'] ?? '')); // شمسی → میلادی

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
        if ($id === 0) {
            $stmt = $pdo->prepare("INSERT INTO projects (customer_id, title, description, status, deadline, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $title, $description, $status, $deadline, $image]);
            $new_project_id = $pdo->lastInsertId();
            
            // Save custom fields
            save_custom_fields_values('project', $new_project_id);

            log_activity($_SESSION['user_id'], "ایجاد پروژه جدید: {$title}");
            sms_trigger_project_assigned((int) $new_project_id);
            $_SESSION['flash'] = 'پروژه جدید با موفقیت ایجاد و به مشتری منتصب شد.';
            header('Location: projects.php');
            exit;
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET customer_id = ?, title = ?, description = ?, status = ?, deadline = ?, image = ? WHERE id = ?");
            $stmt->execute([$customer_id, $title, $description, $status, $deadline, $image, $id]);
            
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

// Fetch all projects with customer details (با صفحه‌بندی)
$projects_total = (int) $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$pi = pagination_info($projects_total, 15);
$projects = $pdo->query("
    SELECT p.*, u.first_name, u.last_name, u.username, u.company_name 
    FROM projects p 
    JOIN users u ON p.customer_id = u.id 
    ORDER BY p.id DESC LIMIT " . (int) $pi['per_page'] . " OFFSET " . (int) $pi['offset'] . "
")->fetchAll();
?>
<?php render_admin_header(
    'مدیریت پروژه‌ها',
    'p-8 max-w-7xl w-full mx-auto space-y-6',
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
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <!-- هدر فرم -->
                    <div class="bg-gradient-to-l from-indigo-600 to-violet-600 px-6 py-5 flex items-center justify-between">
                        <div class="flex items-center gap-3 text-white">
                            <span class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"><?= icon('folder','w-5 h-5') ?></span>
                            <div>
                                <h3 class="font-bold text-lg"><?php echo $action === 'edit' ? 'ویرایش پروژه' : 'تعریف پروژه جدید'; ?></h3>
                                <p class="text-indigo-100 text-xs mt-0.5"><?php echo $action === 'edit' ? 'اطلاعات پروژه را به‌روزرسانی کنید' : 'پروژه را ثبت و به مشتری منتسب کنید'; ?></p>
                            </div>
                        </div>
                        <a href="projects.php" class="text-xs bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg font-medium transition cursor-pointer">← بازگشت به لیست</a>
                    </div>

                    <form method="POST" class="p-6 md:p-8" enctype="multipart/form-data" novalidate>
<div class="form-error-summary" style="display:none" role="alert"></div>
                        <?php echo csrf_input(); ?>
                        <?php if ($edit_project): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_project['id']; ?>">
                        <?php endif; ?>

                        <!-- بخش ۱: انتصاب و اطلاعات پایه -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm font-bold">۱</span>
                                <h4 class="font-bold text-slate-800">اطلاعات پایه و انتصاب</h4>
                            </div>
                            <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5 space-y-5">
                                <div>
                                    <label class="label">انتصاب به مشتری<span class="required-star" aria-hidden="true">*</span></label>
                                    <input type="search" id="customer_search" autocomplete="off" placeholder="جستجو: نام، نام کاربری، شرکت یا موبایل..." class="w-full px-4 py-3 mb-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                    <select name="customer_id" id="customer_id" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
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
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">📝 عنوان پروژه <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" value="<?php echo htmlspecialchars($edit_project['title'] ?? ''); ?>" required placeholder="مثلا: طراحی سایت فروشگاهی" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">📄 توضیحات پروژه</label>
                                    <textarea name="description" rows="4" placeholder="شرح مختصری از پروژه بنویسید..." class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm"><?php echo htmlspecialchars($edit_project['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- بخش ۲: وضعیت و تاریخ -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-bold">۲</span>
                                <h4 class="font-bold text-slate-800">وضعیت و زمان‌بندی</h4>
                            </div>
                            <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">📌 وضعیت پروژه</label>
                                    <select name="status" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                        <option value="pending" <?php echo (($edit_project['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>در انتظار شروع</option>
                                        <option value="in_progress" <?php echo (($edit_project['status'] ?? 'in_progress') === 'in_progress') ? 'selected' : ''; ?>>در حال انجام</option>
                                        <option value="completed" <?php echo (($edit_project['status'] ?? '') === 'completed') ? 'selected' : ''; ?>>تکمیل شده</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">🗓️ تاریخ تکمیل پروژه</label>
                                    <div class="flex flex-wrap sm:flex-nowrap gap-2 items-stretch">
                                        <input type="text" name="deadline" id="deadline" data-jdp data-jdp-min-date="today" readonly dir="ltr" value="<?php echo htmlspecialchars(portal_date_to_display((string) ($edit_project['deadline'] ?? ''))); ?>" class="value-ltr flex-1 min-w-0 px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white cursor-pointer" placeholder="انتخاب تاریخ شمسی">
                                        <button type="button" class="jdp-trigger shrink-0 inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-300 px-3 py-2 rounded-xl transition cursor-pointer" aria-label="انتخاب تاریخ" data-target="deadline"><?= icon('calendar') ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- بخش ۳: تصویر -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-sm font-bold">۳</span>
                                <h4 class="font-bold text-slate-800">تصویر پروژه</h4>
                            </div>
                            <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5">
                                <div class="flex items-center gap-5">
                                    <div class="w-28 h-28 rounded-xl border-2 border-dashed border-slate-300 bg-white overflow-hidden flex-shrink-0 flex items-center justify-center">
                                        <?php if (!empty($edit_project['image'])): ?>
                                            <img src="<?= htmlspecialchars(asset_url($edit_project['image'])) ?>" class="w-full h-full object-cover" alt="عکس پروژه">
                                        <?php else: ?>
                                            <span class="text-slate-300"><?= icon('folder','w-10 h-10') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="space-y-3 flex-1">
                                        <label class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-50 text-indigo-700 text-sm font-medium cursor-pointer hover:bg-indigo-100 transition">
                                            <?= icon('folder') ?><span>انتخاب فایل</span>
                                            <input type="file" name="image" accept=".png,.jpg,.jpeg,.webp,.gif,.svg" class="hidden">
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
                                <span class="w-7 h-7 rounded-lg bg-violet-100 text-violet-600 flex items-center justify-center text-sm font-bold">۴</span>
                                <h4 class="font-bold text-slate-800">فیلدهای تکمیلی</h4>
                            </div>
                            <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5">
                                <?php echo $custom_fields_html; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- دکمه‌ها -->
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-5 border-t border-slate-100">
                            <a href="projects.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary btn-lg"><?= icon('check') ?><span>ذخیره پروژه</span></button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">لیست پروژه‌ها (<?php echo count($projects); ?>)</h3>
                    <a href="projects.php?action=add" class="btn btn-primary">
                        <?= icon('plus') ?><span>تعریف پروژه جدید</span>
                    </a>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
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