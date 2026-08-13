<?php
// admin/custom_fields.php - Dynamic Custom Fields Builder
require_once 'auth.php';
if (!admin_can('custom_fields')) { header('Location: index.php'); exit; }
if (!is_module_enabled('custom_fields')) { header('Location: index.php'); exit; }

$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
    $target_entity = trim($_POST['target_entity'] ?? '');
    $field_name = trim($_POST['field_name'] ?? '');
    $field_label = trim($_POST['field_label'] ?? '');
    $field_type = trim($_POST['field_type'] ?? '');
    $allowed_entities = ['customer', 'project', 'product'];
    $allowed_types = ['text', 'textarea', 'number', 'date'];
    if (!in_array($target_entity, $allowed_entities, true) || !in_array($field_type, $allowed_types, true)) {
        $error = 'نوع موجودیت یا نوع فیلد نامعتبر است.';
    }
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $show_in_customer_panel = isset($_POST['show_in_customer_panel']) ? 1 : 0;

    if ($error || empty($field_name) || empty($field_label)) {
        $error = 'نام سیستمی و برچسب فیلد الزامی است.';
    } else {
        // Slugify field_name و جلوگیری از نام سیستمی خالی یا تکراری در همان موجودیت
        $field_name = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($field_name)), '_');
        if ($field_name === '' || strlen($field_name) > 100) {
            $error = 'نام سیستمی باید حداقل یک حرف یا عدد انگلیسی داشته باشد و حداکثر ۱۰۰ نویسه باشد.';
        }
        if (!$error) {
            $duplicate = $pdo->prepare('SELECT id FROM custom_fields WHERE target_entity = ? AND field_name = ? LIMIT 1');
            $duplicate->execute([$target_entity, $field_name]);
            if ($duplicate->fetchColumn()) {
                $error = 'این نام سیستمی برای همین نوع موجودیت قبلاً ثبت شده است.';
            }
        }

        try {
            if ($error) {
                throw new InvalidArgumentException($error);
            }
            $stmt = $pdo->prepare("INSERT INTO custom_fields (target_entity, field_name, field_label, field_type, is_required, show_in_customer_panel) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$target_entity, $field_name, $field_label, $field_type, $is_required, $show_in_customer_panel]);
            log_activity($_SESSION['user_id'], "ایجاد فیلد سفارشی جدید: {$field_label} ({$target_entity})");
            $success = 'فیلد سفارشی با موفقیت ایجاد شد.';
            $action = 'list';
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            error_log('[Admin Custom Field Create] ' . $e->getMessage());
            $error = 'ایجاد فیلد سفارشی انجام نشد. اطلاعات واردشده را بررسی کنید.';
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['delete_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM custom_fields WHERE id = ?");
    $stmt->execute([$del_id]);
    // پاک‌سازی مقادیر ثبت‌شده آن فیلد (جلوگیری از داده‌های یتیم)
    $pdo->prepare("DELETE FROM custom_field_values WHERE field_id = ?")->execute([$del_id]);
    log_activity($_SESSION['user_id'], "حذف فیلد سفارشی ID: {$del_id}");
    $success = 'فیلد سفارشی با موفقیت حذف شد.';
    $action = 'list';
}

// Fetch all custom fields
$fields = $pdo->query("SELECT * FROM custom_fields ORDER BY id DESC")->fetchAll();
?>
<?php render_admin_header(
    'فیلدهای سفارشی پویا (Custom Fields Builder)',
    'portal-page-main portal-admin-page portal-custom-fields-page p-8 max-w-7xl w-full mx-auto space-y-6',
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

            <?php if ($action === 'add'): ?>
                <div class="card portal-form-card portal-custom-field-form max-w-2xl mx-auto">
                    <div class="portal-form-header flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold">تعریف فیلد سفارشی جدید</h3>
                            <p class="portal-form-subtitle">فیلد را برای مشتری، پروژه یا محصول تعریف کنید.</p>
                        </div>
                        <a href="custom_fields.php" class="btn btn-secondary portal-form-back">بازگشت</a>
                    </div>

                    <form method="POST" class="portal-form-body space-y-6"><?php echo csrf_input(); ?>
                        <div>
                            <label for="cf-target-entity" class="block text-sm font-medium text-slate-700 mb-1.5">موجودیت هدف *</label>
                            <select id="cf-target-entity" name="target_entity" required class="input portal-form-control">
                                <option value="customer">مشتری (کاربر)</option>
                                <option value="project">پروژه</option>
                                <option value="product">محصول</option>
                            </select>
                        </div>
                        <div>
                            <label for="cf-field-label" class="block text-sm font-medium text-slate-700 mb-1.5">برچسب نمایشی فیلد (Label) *</label>
                            <input id="cf-field-label" type="text" name="field_label" required class="input portal-form-control" placeholder="مثلا: کد ملی یا آدرس دقیق">
                        </div>
                        <div>
                            <label for="cf-field-name" class="block text-sm font-medium text-slate-700 mb-1.5">نام سیستمی (انگلیسی، بدون فاصله) *</label>
                            <input id="cf-field-name" type="text" name="field_name" required dir="ltr" autocomplete="off" spellcheck="false" class="input portal-form-control value-ltr font-mono" placeholder="national_code">
                        </div>
                        <div>
                            <label for="cf-field-type" class="block text-sm font-medium text-slate-700 mb-1.5">نوع فیلد</label>
                            <select id="cf-field-type" name="field_type" class="input portal-form-control">
                                <option value="text">متن کوتاه (Text)</option>
                                <option value="textarea">متن طولانی (Textarea)</option>
                                <option value="number">عدد (Number)</option>
                                <option value="date">تاریخ (Date)</option>
                            </select>
                        </div>
                        <div class="space-y-3 pt-2">
                            <label class="portal-checkbox-row flex items-center gap-3 cursor-pointer" for="cf-is-required">
                                <input type="checkbox" id="cf-is-required" name="is_required" value="1" class="accent-indigo-600 w-4 h-4 rounded border-slate-300">
                                <span class="text-sm font-medium text-slate-700">فیلد اجباری باشد؟</span>
                            </label>
                            <label class="portal-checkbox-row flex items-center gap-3 cursor-pointer" for="cf-show-customer">
                                <input type="checkbox" id="cf-show-customer" name="show_in_customer_panel" value="1" checked class="accent-indigo-600 w-4 h-4 rounded border-slate-300">
                                <span class="text-sm font-medium text-slate-700">در پنل مشتری نمایش داده شود؟</span>
                            </label>
                        </div>

                        <div class="portal-form-actions flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                            <a href="custom_fields.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary">ذخیره فیلد سفارشی</button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="portal-list-toolbar flex items-center justify-between gap-4">
                    <h3 class="text-lg font-bold text-slate-800">لیست فیلدهای سفارشی (<?php echo count($fields); ?>)</h3>
                    <a href="custom_fields.php?action=add" class="btn btn-primary">
                        <?= icon('plus') ?><span>تعریف فیلد جدید</span>
                    </a>
                </div>

                <div class="card portal-list-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4">برچسب فیلد</th>
                                    <th class="p-4">نام سیستمی</th>
                                    <th class="p-4">موجودیت هدف</th>
                                    <th class="p-4">نوع فیلد</th>
                                    <th class="p-4">تنظیمات</th>
                                    <th class="p-4 text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($fields)): ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400"><?php echo empty_state('هیچ فیلد سفارشی تعریف نشده است.', '', 'info'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($fields as $f): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="p-4 font-medium text-slate-900"><?php echo htmlspecialchars($f['field_label']); ?></td>
                                            <td class="p-4 font-mono text-xs text-slate-600"><?php echo htmlspecialchars($f['field_name']); ?></td>
                                            <td class="p-4">
                                                <?php 
                                                    $te = $f['target_entity'];
                                                    if ($te === 'customer') echo '<span class="bg-indigo-50 text-indigo-700 text-xs px-2.5 py-1 rounded-full font-medium">مشتری</span>';
                                                    elseif ($te === 'project') echo '<span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">پروژه</span>';
                                                    else echo '<span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">محصول</span>';
                                                ?>
                                            </td>
                                            <td data-label="نوع فیلد" class="p-4 text-xs text-slate-600 value-ltr" dir="ltr"><?php echo htmlspecialchars($f['field_type']); ?></td>
                                            <td class="p-4 text-xs space-y-1">
                                                <div><?php echo $f['is_required'] ? '<span class="text-red-600 font-bold">اجباری</span>' : 'اختیاری'; ?></div>
                                                <div><?php echo $f['show_in_customer_panel'] ? '<span class="text-emerald-600">نمایش در پنل مشتری</span>' : 'مخفی در پنل مشتری'; ?></div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <form method="POST" style="display:inline" data-confirm-msg="آیا از حذف این مورد اطمینان دارید؟"><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?php echo (int)$f['id']; ?>"><?php echo csrf_input(); ?><button type="submit" class="btn btn-sm btn-outline-danger">حذف</button></form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php render_admin_footer(); ?>