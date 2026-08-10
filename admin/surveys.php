<?php
// admin/surveys.php — مدیریت یکپارچه سیستم نظرسنجی
// (لیست فرم‌ها، ساخت فرم جدید، مدیریت سؤال‌ها و گزارش پاسخ‌ها در یک صفحه)
require_once 'auth.php';
if (!admin_can('surveys')) { header('Location: index.php'); exit; }
if (!is_module_enabled('surveys')) { header('Location: index.php'); exit; }

$msg = '';
$err = '';

// ---------- پردازش فرم‌ها ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';

    if ($a === 'create') {
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $type   = $_POST['target_entity'] ?? 'project';
        $period = (int) ($_POST['is_periodic'] ?? 0);
        $parent = (int) ($_POST['parent_survey_id'] ?? 0);
        $days   = max(0, (int) ($_POST['delay_days'] ?? 0));

        if (!$title || !in_array($type, ['project', 'product'], true) || ($period && (!$parent || !$days))) {
            $err = 'اطلاعات فرم کامل یا معتبر نیست.';
        } else {
            if (!$period) {
                $dup = $pdo->prepare("SELECT id FROM surveys WHERE target_entity = ? AND is_periodic = 0 LIMIT 1");
                $dup->execute([$type]);
                if ($dup->fetchColumn()) {
                    $err = 'برای این نوع، فرم اولیه از قبل وجود دارد. برای فرم بعدی، فرم دوره‌ای بسازید.';
                }
            }
            if (!$err && $period) {
                $chk = $pdo->prepare("SELECT target_entity, is_periodic FROM surveys WHERE id = ?");
                $chk->execute([$parent]);
                $parent_row = $chk->fetch();
                if (!$parent_row || (int) $parent_row['is_periodic'] !== 0 || $parent_row['target_entity'] !== $type) {
                    $err = 'فرم اولیه مرتبط معتبر نیست.';
                }
            }
            if (!$err) {
                $q = $pdo->prepare(
                    "INSERT INTO surveys (title, description, target_entity, is_periodic, parent_survey_id, delay_days, is_active, target_scope, target_id)
                     VALUES (?, ?, ?, ?, ?, ?, 1, 'general', 0)"
                );
                $q->execute([$title, $desc, $type, $period, $period ? $parent : null, $period ? $days : 0]);
                $msg = 'فرم با موفقیت ایجاد شد.';
            }
        }
    } elseif ($a === 'update') {
        $sid    = (int) ($_POST['survey_id'] ?? 0);
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $period = (int) ($_POST['is_periodic'] ?? 0);
        $parent = (int) ($_POST['parent_survey_id'] ?? 0);
        $days   = max(0, (int) ($_POST['delay_days'] ?? 0));

        $chk = $pdo->prepare("SELECT * FROM surveys WHERE id = ?");
        $chk->execute([$sid]);
        $existing = $chk->fetch();

        if (!$existing) {
            $err = 'فرم مورد نظر یافت نشد.';
        } elseif (!$title) {
            $err = 'عنوان فرم الزامی است.';
        } elseif ($period && (!$parent || !$days)) {
            $err = 'برای فرم دوره‌ای، فرم اولیه مرتبط و تعداد روز الزامی است.';
        } elseif ($period) {
            $chk2 = $pdo->prepare("SELECT target_entity, is_periodic FROM surveys WHERE id = ?");
            $chk2->execute([$parent]);
            $parent_row = $chk2->fetch();
            if (!$parent_row || (int) $parent_row['is_periodic'] !== 0 || $parent_row['target_entity'] !== $existing['target_entity'] || (int) $parent === $sid) {
                $err = 'فرم اولیه مرتبط معتبر نیست.';
            }
        }

        if (!$err) {
            $q = $pdo->prepare("UPDATE surveys SET title = ?, description = ?, is_periodic = ?, parent_survey_id = ?, delay_days = ? WHERE id = ?");
            $q->execute([$title, $desc, $period, $period ? $parent : null, $period ? $days : 0, $sid]);
            log_activity($_SESSION['user_id'], "ویرایش فرم نظرسنجی ID: {$sid}");
            $msg = 'فرم با موفقیت ویرایش شد.';
        }
    } elseif ($a === 'question') {
        $q = $pdo->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type) VALUES (?, ?, ?)");
        $q->execute([(int) ($_POST['survey_id'] ?? 0), trim($_POST['question_text'] ?? ''), $_POST['question_type'] ?? 'yes_no']);
        $msg = 'سؤال اضافه شد.';
    } elseif ($a === 'delete') {
        $id = (int) ($_POST['delete_id'] ?? 0);
        // پاک‌سازی انتساب‌های باقی‌مانده (survey_assignments کلید خارجی ندارد)
        $pdo->prepare('DELETE FROM survey_assignments WHERE survey_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM surveys WHERE id = ?')->execute([$id]);
        $msg = 'فرم و تمام پاسخ‌های آن حذف شد و برای موارد مرتبط از ابتدا قابل تکمیل است.';
    } elseif ($a === 'delete_question') {
        $pdo->prepare('DELETE FROM survey_questions WHERE id = ?')->execute([(int) ($_POST['delete_id'] ?? 0)]);
        $msg = 'سؤال حذف شد.';
    } elseif ($a === 'update_question') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        $qtext = trim($_POST['question_text'] ?? '');
        $qtype = $_POST['question_type'] ?? 'yes_no';
        if ($qid > 0 && $qtext !== '') {
            $pdo->prepare('UPDATE survey_questions SET question_text = ?, question_type = ? WHERE id = ?')->execute([$qtext, $qtype, $qid]);
            $msg = 'سؤال ویرایش شد.';
        } else {
            $err = 'متن سؤال نمی‌تواند خالی باشد.';
        }
    }
}

// ---------- داده‌های صفحه ----------
$forms = $pdo->query(
    "SELECT s.*,
            (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id = s.id) qc,
            (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id = s.id) rc
     FROM surveys s ORDER BY s.id DESC"
)->fetchAll();

$parents = $pdo->query("SELECT id, title, target_entity FROM surveys WHERE is_periodic = 0 ORDER BY id DESC")->fetchAll();

$edit = (int) ($_GET['questions'] ?? 0);
$result_id = (int) ($_GET['results'] ?? 0);
$edit_survey = (int) ($_GET['edit'] ?? 0);
$show_create = ($_GET['action'] ?? '') === 'create';
$form = null;
$questions = [];
$results = [];
$stats = [];

if ($edit_survey) {
    $x = $pdo->prepare('SELECT * FROM surveys WHERE id = ?');
    $x->execute([$edit_survey]);
    $form = $x->fetch();
    if (!$form) { $edit_survey = 0; $err = 'فرم مورد نظر یافت نشد.'; }
}

if ($edit) {
    $x = $pdo->prepare('SELECT * FROM surveys WHERE id = ?');
    $x->execute([$edit]);
    $form = $x->fetch();
    if ($form) {
        $x = $pdo->prepare('SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY id');
        $x->execute([$edit]);
        $questions = $x->fetchAll();
    }
}

if ($result_id) {
    $x = $pdo->prepare('SELECT * FROM surveys WHERE id = ?');
    $x->execute([$result_id]);
    $form = $x->fetch();

    if ($form) {
        $filter_customer = (int) ($_GET['customer_id'] ?? 0);
        $filter_entity   = $_GET['entity_type'] ?? '';
        $filter_from     = $_GET['from'] ?? '';
        $filter_to       = $_GET['to'] ?? '';

        $sql = "SELECT r.*, u.username, u.first_name, u.last_name,
                       CASE WHEN r.entity_type = 'project' THEN p.title ELSE pr.title END entity_title
                FROM survey_responses r
                JOIN users u ON u.id = r.customer_id
                LEFT JOIN projects p ON r.entity_type = 'project' AND p.id = r.entity_id
                LEFT JOIN products pr ON r.entity_type = 'product' AND pr.id = r.entity_id
                WHERE r.survey_id = ?";
        $params = [$result_id];

        if ($filter_customer) { $sql .= ' AND r.customer_id = ?'; $params[] = $filter_customer; }
        if (in_array($filter_entity, ['project', 'product'], true)) { $sql .= ' AND r.entity_type = ?'; $params[] = $filter_entity; }
        if ($filter_from) { $sql .= ' AND DATE(r.created_at) >= ?'; $params[] = $filter_from; }
        if ($filter_to) { $sql .= ' AND DATE(r.created_at) <= ?'; $params[] = $filter_to; }

        $sql .= ' ORDER BY r.created_at DESC';
        $x = $pdo->prepare($sql);
        $x->execute($params);
        $results = $x->fetchAll();

        // خلاصه آماری پاسخ‌ها
        if ($results) {
            $result_ids = array_column($results, 'id');
            $stats_rows = [];
            if ($result_ids) {
                $ph = implode(',', array_fill(0, count($result_ids), '?'));
                $st = $pdo->prepare(
                    "SELECT q.id, q.question_text, q.question_type, a.answer_value
                     FROM survey_answers a
                     JOIN survey_questions q ON q.id = a.question_id
                     JOIN survey_responses r ON r.id = a.response_id
                     WHERE r.id IN ($ph)"
                );
                $st->execute($result_ids);
                $stats_rows = $st->fetchAll();
            }
            foreach ($stats_rows as $row) {
                $k = $row['id'];
                if (!isset($stats[$k])) {
                    $stats[$k] = ['text' => $row['question_text'], 'type' => $row['question_type'], 'sum' => 0, 'count' => 0, 'yes' => 0, 'no' => 0];
                }
                if ($row['question_type'] === 'yes_no') {
                    if ($row['answer_value'] === 'بله') { $stats[$k]['yes']++; } else { $stats[$k]['no']++; }
                } else {
                    $stats[$k]['sum'] += (float) $row['answer_value'];
                    $stats[$k]['count']++;
                }
            }
        }
    }
}

$question_type_labels = [
    'rating_1_10' => 'امتیاز ۱ تا ۱۰',
    'yes_no'      => 'بله / خیر',
    'star_rating' => 'ستاره‌ای',
];

render_admin_header('مدیریت نظرسنجی', 'p-8 max-w-7xl w-full mx-auto space-y-6');
?>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
            <?php endif; ?>

            <?php if ($result_id && $form): ?>
                <!-- ===== گزارش پاسخ‌ها ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">گزارش پاسخ‌ها: <?= htmlspecialchars($form['title']) ?></h3>
                    <a href="surveys.php" class="text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg font-medium">بازگشت به لیست</a>
                </div>

                <!-- فیلتر -->
                <form method="get" class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-wrap items-end gap-3">
                    <input type="hidden" name="results" value="<?= $result_id ?>">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">شناسه مشتری</label>
                        <input type="number" name="customer_id" placeholder="شناسه مشتری" value="<?= htmlspecialchars($_GET['customer_id'] ?? '') ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">نوع مورد</label>
                        <select name="entity_type" class="px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                            <option value="">همه</option>
                            <option value="project" <?= ($filter_entity === 'project' ? 'selected' : '') ?>>پروژه</option>
                            <option value="product" <?= ($filter_entity === 'product' ? 'selected' : '') ?>>محصول</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">از تاریخ</label>
                        <input type="date" name="from" value="<?= htmlspecialchars($filter_from) ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">تا تاریخ</label>
                        <input type="date" name="to" value="<?= htmlspecialchars($filter_to) ?>" class="px-3 py-2 rounded-lg border border-slate-300 text-sm">
                    </div>
                    <button class="btn btn-primary">فیلتر</button>
                </form>

                <p class="text-sm text-slate-500">تعداد پاسخ‌ها: <b class="text-slate-800"><?= count($results) ?></b></p>

                <?php if (!empty($stats)): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <h4 class="font-bold text-slate-800 mb-3">خلاصه آماری</h4>
                        <div class="space-y-2">
                            <?php foreach ($stats as $st): ?>
                                <div class="flex items-center justify-between text-sm bg-slate-50 rounded-lg px-3 py-2">
                                    <span class="text-slate-700"><?= htmlspecialchars($st['text']) ?></span>
                                    <span class="text-slate-500 font-medium">
                                        <?php if ($st['type'] === 'yes_no'): ?>
                                            بله: <b class="text-emerald-600"><?= $st['yes'] ?></b> | خیر: <b class="text-red-500"><?= $st['no'] ?></b>
                                        <?php else: ?>
                                            میانگین: <b class="text-indigo-700"><?= number_format($st['count'] ? $st['sum'] / $st['count'] : 0, 2) ?></b> از <?= $st['type'] === 'rating_1_10' ? 10 : 5 ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($results as $r): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <b class="text-slate-800"><?= htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['username']) ?></b>
                            <span class="text-xs text-slate-500"><?= htmlspecialchars($r['created_at']) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">مربوط به: <b class="text-slate-700"><?= htmlspecialchars($r['entity_title'] ?? 'حذف‌شده') ?></b></p>
                        <div class="space-y-2">
                            <?php
                            $aa = $pdo->prepare("SELECT q.question_text, a.answer_value FROM survey_answers a JOIN survey_questions q ON q.id = a.question_id WHERE a.response_id = ? ORDER BY q.id");
                            $aa->execute([$r['id']]);
                            foreach ($aa->fetchAll() as $ans):
                            ?>
                                <div class="bg-slate-50 rounded-lg p-3 text-sm flex items-center justify-between">
                                    <span class="text-slate-600"><?= htmlspecialchars($ans['question_text']) ?></span>
                                    <span class="text-indigo-700 font-medium"><?= htmlspecialchars($ans['answer_value']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$results): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">هنوز پاسخی ثبت نشده است.</div>
                <?php endif; ?>

            <?php elseif ($edit && $form): ?>
                <!-- ===== مدیریت سؤال‌های فرم ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">سؤالات: <?= htmlspecialchars($form['title']) ?></h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="surveys.php?edit=<?= $edit ?>" class="btn btn-sm btn-secondary"><?= icon('edit') ?><span>ویرایش فرم</span></a>
                        <a href="surveys.php?results=<?= $edit ?>" class="btn btn-sm btn-primary"><?= icon('trending') ?><span>مشاهده گزارش</span></a>
                        <a href="surveys.php" class="btn btn-sm btn-ghost"><?= icon('back') ?><span>بازگشت</span></a>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <h4 class="font-bold text-slate-800 mb-4">افزودن سؤال جدید</h4>
                    <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="question">
                        <input type="hidden" name="survey_id" value="<?= $edit ?>">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">متن سؤال *</label>
                            <textarea name="question_text" required placeholder="متن سؤال" rows="2" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">نوع سؤال</label>
                            <select name="question_type" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                <option value="rating_1_10">امتیاز ۱ تا ۱۰</option>
                                <option value="yes_no">بله / خیر</option>
                                <option value="star_rating">ستاره‌ای</option>
                            </select>
                        </div>
                        <div class="md:col-span-3 flex justify-end">
                            <button class="btn btn-primary"><?= icon('plus') ?><span>افزودن سؤال</span></button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <?php if (empty($questions)): ?>
                        <p class="p-8 text-center text-slate-400 text-sm">هنوز سؤالی برای این فرم ثبت نشده است.</p>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($questions as $i => $item): ?>
                                <div class="px-6 py-4" data-question-item="<?= $item['id'] ?>">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="font-medium text-slate-800 text-sm"><?= $i + 1 ?>. <?= htmlspecialchars($item['question_text']) ?></div>
                                            <span class="inline-block mt-1 text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full"><?= htmlspecialchars($question_type_labels[$item['question_type']] ?? $item['question_type']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button type="button" class="btn btn-sm btn-ghost !text-indigo-600" onclick="document.getElementById('qedit-<?= $item['id'] ?>').classList.toggle('hidden');"><?= icon('edit') ?><span>ویرایش</span></button>
                                            <form method="post" data-confirm-msg="حذف شود؟">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete_question">
                                                <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger"><?= icon('trash') ?><span>حذف</span></button>
                                            </form>
                                        </div>
                                    </div>
                                    <!-- فرم ویرایش سؤال -->
                                    <div id="qedit-<?= $item['id'] ?>" class="hidden mt-3 p-4 bg-slate-50 rounded-xl border border-slate-200">
                                        <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="update_question">
                                            <input type="hidden" name="question_id" value="<?= $item['id'] ?>">
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-slate-700 mb-1">متن سؤال</label>
                                                <textarea name="question_text" required rows="2" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm"><?= htmlspecialchars($item['question_text']) ?></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 mb-1">نوع سؤال</label>
                                                <select name="question_type" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                                    <option value="rating_1_10" <?= $item['question_type'] === 'rating_1_10' ? 'selected' : '' ?>>امتیاز ۱ تا ۱۰</option>
                                                    <option value="yes_no" <?= $item['question_type'] === 'yes_no' ? 'selected' : '' ?>>بله / خیر</option>
                                                    <option value="star_rating" <?= $item['question_type'] === 'star_rating' ? 'selected' : '' ?>>ستاره‌ای</option>
                                                </select>
                                            </div>
                                            <div class="md:col-span-3 flex justify-end gap-2">
                                                <button type="button" class="btn btn-sm btn-secondary" onclick="this.closest('[data-question-item]').querySelector('#qedit-<?= $item['id'] ?>').classList.add('hidden')">انصراف</button>
                                                <button class="btn btn-sm btn-primary"><?= icon('check') ?><span>ذخیره سؤال</span></button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($edit_survey && $form): ?>
                <!-- ===== ویرایش فرم (اطلاعات اصلی) ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">ویرایش فرم نظرسنجی</h3>
                    <div class="flex gap-2">
                        <a href="surveys.php?questions=<?= $edit_survey ?>" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-lg transition">ویرایش سؤال‌ها</a>
                        <a href="surveys.php" class="text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg font-medium">بازگشت به لیست</a>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm max-w-3xl">
                    <form method="post" class="space-y-6">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="survey_id" value="<?= $edit_survey ?>">

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">عنوان فرم *</label>
                            <input type="text" name="title" required value="<?= htmlspecialchars($form['title']) ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">توضیحات</label>
                            <textarea name="description" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm" placeholder="توضیح کوتاه درباره هدف این نظرسنجی"><?= htmlspecialchars($form['description']) ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">نوع فرم</label>
                            <div class="px-4 py-2.5 rounded-lg border border-slate-200 bg-slate-50 text-sm text-slate-500 flex items-center gap-2">
                                <?php if ($form['target_entity'] === 'product'): ?>
                                    <span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-0.5 rounded-full font-medium">محصول</span>
                                <?php else: ?>
                                    <span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-0.5 rounded-full font-medium">پروژه</span>
                                <?php endif; ?>
                                <span>نوع فرم پس از ایجاد قابل تغییر نیست.</span>
                            </div>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_periodic" value="1" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" <?= $form['is_periodic'] ? 'checked' : '' ?> onchange="document.getElementById('period').hidden=!this.checked">
                            <span class="text-sm font-medium text-slate-700">فرم دوره‌ای (بعد از تکمیل فرم اولیه، دوباره فعال می‌شود)</span>
                        </label>

                        <div id="period" <?= $form['is_periodic'] ? '' : 'hidden' ?> class="bg-slate-50 rounded-xl p-5 space-y-4 border border-slate-200">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">فرم اولیه مرتبط</label>
                                <select name="parent_survey_id" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                    <option value="0">انتخاب کنید</option>
                                    <?php foreach ($parents as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= ((int) $form['parent_survey_id'] === (int) $p['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['title']) ?> (<?= $p['target_entity'] === 'product' ? 'محصول' : 'پروژه' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">تعداد روز پس از تکمیل فرم اولیه</label>
                                <input type="number" name="delay_days" min="1" value="<?= (int) $form['delay_days'] ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <a href="surveys.php" class="btn btn-secondary">انصراف</a>
                            <button class="btn btn-primary">ذخیره تغییرات</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($show_create): ?>
                <!-- ===== ساخت فرم جدید ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">ساخت فرم نظرسنجی جدید</h3>
                    <a href="surveys.php" class="text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg font-medium">بازگشت به لیست</a>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm max-w-3xl">
                    <form method="post" class="space-y-6">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="create">

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">عنوان فرم *</label>
                            <input type="text" name="title" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm" placeholder="مثلا: نظرسنجی رضایت از پروژه">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">توضیحات</label>
                            <textarea name="description" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm" placeholder="توضیح کوتاه درباره هدف این نظرسنجی"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">نوع فرم</label>
                            <select name="target_entity" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                <option value="project">فرم پروژه</option>
                                <option value="product">فرم محصول</option>
                            </select>
                        </div>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_periodic" value="1" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500" onchange="document.getElementById('period').hidden=!this.checked">
                            <span class="text-sm font-medium text-slate-700">فرم دوره‌ای (بعد از تکمیل فرم اولیه، دوباره فعال می‌شود)</span>
                        </label>

                        <div id="period" hidden class="bg-slate-50 rounded-xl p-5 space-y-4 border border-slate-200">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">فرم اولیه مرتبط</label>
                                <select name="parent_survey_id" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm bg-white">
                                    <option value="0">انتخاب کنید</option>
                                    <?php foreach ($parents as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?> (<?= $p['target_entity'] === 'product' ? 'محصول' : 'پروژه' ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">تعداد روز پس از تکمیل فرم اولیه</label>
                                <input type="number" name="delay_days" min="1" value="30" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:outline-none text-sm">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button class="btn btn-primary">ذخیره و افزودن سؤال‌ها</button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- ===== لیست فرم‌ها ===== -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-800">فرم‌های نظرسنجی (<?= count($forms) ?>)</h3>
                    <a href="surveys.php?action=create" class="btn btn-primary">
                        <?= icon('plus') ?><span>ساخت فرم نظرسنجی جدید</span>
                    </a>
                </div>

                <div id="survey-reports" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="table table-card-mobile">
                            <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                                <tr>
                                    <th class="p-4">عنوان</th>
                                    <th class="p-4">نوع</th>
                                    <th class="p-4">مرحله</th>
                                    <th class="p-4">سؤال</th>
                                    <th class="p-4">پاسخ</th>
                                    <th class="p-4 text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($forms)): ?>
                                    <tr><td colspan="6" class="p-8 text-center text-slate-400"><?php echo empty_state('هنوز فرمی ساخته نشده است.', '', 'info'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($forms as $f): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td class="p-4 font-medium text-slate-900"><?= htmlspecialchars($f['title']) ?></td>
                                            <td class="p-4">
                                                <?php if ($f['target_entity'] === 'product'): ?>
                                                    <span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">محصول</span>
                                                <?php else: ?>
                                                    <span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">پروژه</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <?php if ($f['is_periodic']): ?>
                                                    <span class="bg-indigo-50 text-indigo-700 text-xs px-2.5 py-1 rounded-full font-medium">دوره‌ای</span>
                                                <?php else: ?>
                                                    <span class="bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-full">اولیه</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 text-slate-600"><?= (int) $f['qc'] ?></td>
                                            <td class="p-4 text-slate-600"><?= (int) $f['rc'] ?></td>
                                            <td class="p-4">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="surveys.php?edit=<?= $f['id'] ?>" class="text-slate-600 hover:text-slate-900 font-medium text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg">ویرایش</a>
                                                    <a href="surveys.php?questions=<?= $f['id'] ?>" class="btn btn-sm btn-ghost !text-indigo-600">سؤالات</a>
                                                    <a href="surveys.php?results=<?= $f['id'] ?>" class="text-emerald-600 hover:text-emerald-800 font-medium text-xs bg-emerald-50 px-3 py-1.5 rounded-lg">گزارش</a>
                                                    <form method="post" data-confirm-msg="با حذف فرم تمام پاسخ‌ها حذف می‌شود. ادامه؟">
                                                        <?php echo csrf_input(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php render_admin_footer();
