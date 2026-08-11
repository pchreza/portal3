<?php
// admin/products.php - Product Management
require_once 'auth.php';
if (!admin_can('products')) { header('Location: index.php'); exit; }
if (!is_module_enabled('products')) { header('Location: index.php'); exit; }

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error = '';
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
// ========== اکسل: خروجی / نمونه / ورود دسته‌جمعی ==========
if (isset($_GET['export']) && $_GET['export'] === 'xlsx') {
    $q = $pdo->query("SELECT p.*, u.first_name, u.last_name, u.username, u.mobile FROM products p JOIN users u ON u.id = p.customer_id ORDER BY p.id DESC");
    $rows = [['شناسه', 'نام محصول', 'مشتری', 'نام کاربری مشتری', 'توضیحات', 'قیمت', 'وضعیت', 'تاریخ خرید', 'کد لایسنس', 'تاریخ ثبت']];
    foreach ($q->fetchAll() as $p) {
        $rows[] = [
            $p['id'], $p['title'], trim($p['first_name'] . ' ' . $p['last_name']), $p['username'],
            $p['description'], $p['price'], product_status_label($p['product_status'] ?? 'purchased'),
            $p['purchase_date'] ? portal_date_to_display($p['purchase_date']) : '-', $p['license_key'] ?: '-', fa_datetime($p['created_at'] ?? null),
        ];
    }
    excel_output('products', $rows, 'محصولات');
}
if (isset($_GET['sample']) && $_GET['sample'] === 'xlsx') {
    $rows = [
        ['نام محصول', 'نام کاربری مشتری', 'توضیحات', 'قیمت', 'وضعیت', 'تاریخ خرید', 'کد لایسنس'],
        ['نرم‌افزار حسابداری', 'ali.rezaei', 'نسخه کامل با پشتیبانی', '2500000', 'محصول خریداری شده', '1405/02/10', 'ABCD-1234-EFGH'],
    ];
    excel_output('sample-products', $rows, 'نمونه محصولات');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'excel_import') {
    require_valid_csrf();
    $rows = excel_parse_upload($_FILES['excel_file'] ?? []);
    if (empty($rows)) {
        $error = 'فایل اکسل خوانده نشد یا خالی است.';
    } else {
        $result = excel_import_products($rows);
        $success = $result['added'] . ' محصول با موفقیت از اکسل وارد شد.';
        if (!empty($result['errors'])) {
            $error = count($result['errors']) . ' سطر نادیده گرفته شد: ' . implode(' | ', array_slice($result['errors'], 0, 6)) . (count($result['errors']) > 6 ? ' …' : '');
        }
        log_activity($_SESSION['user_id'], "ورود دسته‌جمعی محصولات از اکسل: " . $result['added'] . " مورد");
    }
}


// Handle Delete FIRST — تا درخواست حذف وارد بلاک افزودن/ویرایش نشود
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = intval($_POST['delete_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$del_id]);

    // پاک‌سازی نظرسنجی‌های مرتبط با محصول حذف‌شده
    $pdo->prepare("DELETE FROM survey_assignments WHERE entity_type = 'product' AND entity_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM survey_responses WHERE entity_type = 'product' AND entity_id = ?")->execute([$del_id]);

    log_activity($_SESSION['user_id'], "حذف محصول ID: {$del_id}");
    $_SESSION['flash'] = 'محصول با موفقیت حذف شد.';
    header('Location: products.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'excel_import') {
    // Handle Form Submission (افزودن / ویرایش)
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $purchase_date = portal_date_to_db(trim($_POST['purchase_date'] ?? '')); // شمسی → میلادی
    $product_status = in_array($_POST['product_status'] ?? '', array_keys(product_status_list()), true) ? $_POST['product_status'] : 'purchased';

    // پردازش آپلود عکس
    $image = trim($_POST['current_image'] ?? ''); // تصویر قبلی (پیش‌فرض: خالی)
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $f = $_FILES['image'];
        $v = validate_upload_image($f, 4 * 1024 * 1024);
        if ($v === true) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $uploads_dir = __DIR__ . '/../uploads';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }
            $fname = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $uploads_dir . '/' . $fname)) {
                $image = 'uploads/' . $fname;
            } else {
                $error = 'آپلود عکس انجام نشد.';
            }
        } else {
            $error = $v;
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
        $image = ''; // حذف عکس
    }

    if (empty($title) || $customer_id <= 0) {
        $error = 'انتخاب مشتری و عنوان محصول الزامی است.';
    } elseif (!$error) {
        if ($id === 0) {
            $stmt = $pdo->prepare("INSERT INTO products (customer_id, title, description, purchase_date, product_status, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $title, $description, $purchase_date, $product_status, $image]);
            $new_product_id = $pdo->lastInsertId();

            // Save custom fields
            save_custom_fields_values('product', $new_product_id);

            log_activity($_SESSION['user_id'], "ثبت محصول جدید: {$title}");
            sms_trigger_product_assigned((int) $new_product_id);
            $_SESSION['flash'] = 'محصول جدید با موفقیت ثبت و به مشتری منتصب شد.';
            header('Location: products.php');
            exit;
        } else {
            $stmt = $pdo->prepare("UPDATE products SET customer_id = ?, title = ?, description = ?, purchase_date = ?, product_status = ?, image = ? WHERE id = ?");
            $stmt->execute([$customer_id, $title, $description, $purchase_date, $product_status, $image, $id]);

            // Save custom fields
            save_custom_fields_values('product', $id);

            log_activity($_SESSION['user_id'], "ویرایش محصول ID: {$id}");
            $_SESSION['flash'] = 'اطلاعات محصول با موفقیت ویرایش شد.';
            header('Location: products.php');
            exit;
        }
    }
}

// Handle Delete removed (moved to top)

$edit_product = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_product = $stmt->fetch();
    if (!$edit_product) $action = 'list';
}

// Fetch all customers for dropdown
$customers = $pdo->query("SELECT id, first_name, last_name, username, company_name, mobile FROM users WHERE role = 'customer' ORDER BY first_name ASC")->fetchAll();

// Fetch all products with customer details (با صفحه‌بندی)
$products_total = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$pi = pagination_info($products_total, 15);
$products = $pdo->query("
    SELECT pr.*, u.first_name, u.last_name, u.username, u.company_name 
    FROM products pr 
    JOIN users u ON pr.customer_id = u.id 
    ORDER BY pr.id DESC LIMIT " . (int) $pi['per_page'] . " OFFSET " . (int) $pi['offset'] . "
")->fetchAll();
?>
<?php render_admin_header(
    'مدیریت محصولات',
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
                    'page' => 'products.php',
                    'withSample' => true,
                    'withImport' => true,
                    'importHint' => 'ستون‌ها: نام محصول*، نام کاربری مشتری*، توضیحات، قیمت، وضعیت، تاریخ خرید، کد لایسنس. وضعیت می‌تواند فارسی یا انگلیسی باشد.',
                    'importExtra' => '',
                ]); ?>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <!-- فرم محصول — طراحی جدید -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <!-- هدر فرم -->
                    <div class="bg-gradient-to-l from-amber-500 to-orange-500 px-6 py-5 flex items-center justify-between">
                        <div class="flex items-center gap-3 text-white">
                            <span class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"><?= icon('box','w-5 h-5') ?></span>
                            <div>
                                <h3 class="font-bold text-lg"><?php echo $action === 'edit' ? 'ویرایش محصول' : 'تعریف محصول جدید'; ?></h3>
                                <p class="text-amber-100 text-xs mt-0.5"><?php echo $action === 'edit' ? 'اطلاعات محصول را به‌روزرسانی کنید' : 'محصول را ثبت و به مشتری منتسب کنید'; ?></p>
                            </div>
                        </div>
                        <a href="products.php" class="text-xs bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg font-medium transition cursor-pointer">← بازگشت به لیست</a>
                    </div>

                    <form method="POST" class="p-6 md:p-8" enctype="multipart/form-data" novalidate>
<div class="form-error-summary" style="display:none" role="alert"></div>
                        <?php echo csrf_input(); ?>
                        <?php if ($edit_product): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
                        <?php endif; ?>

                        <!-- بخش ۱: انتصاب و اطلاعات پایه -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-sm font-bold">۱</span>
                                <h4 class="font-bold text-slate-800">اطلاعات پایه و انتصاب</h4>
                            </div>
                            <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5 space-y-5">
                                <div>
                                    <label class="label">انتصاب به خریدار (مشتری)<span class="required-star" aria-hidden="true">*</span></label>
                                    <input type="search" id="customer_search" autocomplete="off" placeholder="جستجو: نام، نام کاربری، شرکت یا موبایل..." class="w-full px-4 py-3 mb-2 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                    <select name="customer_id" id="customer_id" required class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                        <option value="">انتخاب مشتری...</option>
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo (($edit_product['customer_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(trim($c['first_name'] . ' ' . $c['last_name']) !== '' ? $c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['username'] . ')' : $c['username']); ?>
                                                <?php echo $c['company_name'] ? ' - ' . htmlspecialchars($c['company_name']) : ''; ?><?php echo $c['mobile'] ? ' - 📱 ' . htmlspecialchars($c['mobile']) : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">📝 عنوان محصول / سرویس <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" value="<?php echo htmlspecialchars($edit_product['title'] ?? ''); ?>" required placeholder="مثلا: هاست یک‌ساله" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">📄 توضیحات محصول</label>
                                    <textarea name="description" rows="4" placeholder="شرح مختصری از محصول بنویسید..." class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm"><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- بخش ۲: وضعیت و تاریخ -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-sm font-bold">۲</span>
                                <h4 class="font-bold text-slate-800">وضعیت و تاریخ</h4>
                            </div>
                            <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">📌 وضعیت محصول</label>
                                    <select name="product_status" class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                        <?php foreach (product_status_list() as $st_key => $st_label): ?>
                                            <option value="<?php echo $st_key; ?>" <?php echo (($edit_product['product_status'] ?? 'purchased') === $st_key) ? 'selected' : ''; ?>><?php echo $st_label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">🗓️ تاریخ خرید</label>
                                    <div class="flex flex-wrap sm:flex-nowrap gap-2 items-stretch">
                                        <input type="text" name="purchase_date" id="purchase_date" data-jdp data-jdp-max-date="today" readonly dir="ltr" value="<?php echo htmlspecialchars(portal_date_to_display((string) ($edit_product['purchase_date'] ?? ''))); ?>" class="value-ltr flex-1 min-w-0 px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white cursor-pointer" placeholder="انتخاب تاریخ شمسی">
                                        <button type="button" class="jdp-trigger shrink-0 inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-300 px-3 py-2 rounded-xl transition cursor-pointer" aria-label="انتخاب تاریخ" data-target="purchase_date"><?= icon('calendar') ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- بخش ۳: تصویر -->
                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-sm font-bold">۳</span>
                                <h4 class="font-bold text-slate-800">تصویر محصول</h4>
                            </div>
                            <div class="bg-slate-50/70 border border-slate-100 rounded-2xl p-5">
                                <div class="flex items-center gap-5">
                                    <div class="w-28 h-28 rounded-xl border-2 border-dashed border-slate-300 bg-white overflow-hidden flex-shrink-0 flex items-center justify-center">
                                        <?php if (!empty($edit_product['image'])): ?>
                                            <img src="<?= htmlspecialchars(asset_url($edit_product['image'])) ?>" class="w-full h-full object-cover" alt="عکس محصول">
                                        <?php else: ?>
                                            <span class="text-slate-300"><?= icon('box','w-10 h-10') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="space-y-3 flex-1">
                                        <label class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-50 text-amber-700 text-sm font-medium cursor-pointer hover:bg-amber-100 transition">
                                            <?= icon('folder') ?><span>انتخاب فایل</span>
                                            <input type="file" name="image" accept=".png,.jpg,.jpeg,.webp,.gif,.svg" class="hidden">
                                        </label>
                                        <input type="hidden" name="current_image" value="<?= htmlspecialchars($edit_product['image'] ?? '') ?>">
                                        <p class="text-xs text-slate-400">فرمت‌های مجاز: png / jpg / webp / svg — حداکثر ۴MB</p>
                                        <?php if (!empty($edit_product['image'])): ?>
                                            <label class="flex items-center gap-1.5 cursor-pointer text-red-600 text-sm">
                                                <input type="checkbox" name="remove_image" value="1" class="w-4 h-4 text-red-600 rounded border-slate-300"> حذف عکس فعلی
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- فیلدهای سفارشی -->
                        <?php $custom_fields_html = render_custom_fields_inputs('product', $edit_product['id'] ?? 0); ?>
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
                            <a href="products.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary btn-lg bg-amber-500 hover:bg-amber-600"><?= icon('check') ?><span>ذخیره محصول</span></button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">لیست محصولات خریداری شده (<?php echo count($products); ?>)</h3>
                    <a href="products.php?action=add" class="btn btn-primary">
                        <?= icon('plus') ?><span>تعریف محصول جدید</span>
                    </a>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4">عنوان محصول</th>
                                    <th class="p-4">خریدار (مشتری)</th>
                                    <th class="p-4">وضعیت / تاریخ خرید</th>
                                    <th class="p-4 text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-slate-400"><?php echo empty_state('هیچ محصولی ثبت نشده است.', '', 'info'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $pr): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="p-4">
                                                <div class="font-medium text-slate-900"><bdi dir="auto"><?php echo htmlspecialchars($pr['title']); ?></bdi></div>
                                                <div class="text-xs text-slate-500 mt-0.5 truncate max-w-xs" title="<?= htmlspecialchars($pr['description']) ?>"><bdi dir="auto"><?php echo htmlspecialchars($pr['description']); ?></bdi></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="font-medium text-slate-800"><?php echo htmlspecialchars(trim($pr['first_name'] . ' ' . $pr['last_name']) !== '' ? $pr['first_name'] . ' ' . $pr['last_name'] : $pr['username']); ?></div>
                                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($pr['company_name'] ?: ''); ?></div>
                                            </td>
                                            <td class="p-4">
                                                <div class="mb-1"><?php echo product_status_badge($pr['product_status'] ?? null); ?></div>
                                                <div class="text-xs text-slate-500">تاریخ خرید: <span class="value-ltr" dir="ltr"><?php echo htmlspecialchars($pr['purchase_date'] ? portal_date_to_display($pr['purchase_date']) : '-'); ?></span></div>
                                            </td>
                                            <td data-label="عملیات" class="p-4 min-w-[11rem]"><div class="cell-actions flex flex-wrap items-center justify-center gap-2">
                                                <a href="products.php?action=edit&id=<?php echo $pr['id']; ?>" class="btn btn-sm btn-ghost !text-indigo-600">ویرایش</a>
                                                <form method="POST" data-confirm-msg="آیا از حذف این مورد اطمینان دارید؟"><input type="hidden" name="action" value="delete"><input type="hidden" name="delete_id" value="<?php echo (int)$pr['id']; ?>"><?php echo csrf_input(); ?><button type="submit" class="btn btn-sm btn-outline-danger">حذف</button></form>
                                            </div></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php echo render_pagination($pi, 'products.php'); ?>
                </div>
            <?php endif; ?>

        <script>
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