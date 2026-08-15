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
        $surveyId = (int) ($_POST['survey_id'] ?? 0);
        $questionText = trim((string) ($_POST['question_text'] ?? ''));
        $allowed_qtypes = array_keys(survey_question_types());
        $qtype = in_array($_POST['question_type'] ?? '', $allowed_qtypes, true) ? (string) $_POST['question_type'] : 'yes_no';
        $options = $qtype === 'multiple_choice' ? survey_multiple_choice_options_from_text((string) ($_POST['question_options'] ?? '')) : [];
        if ($surveyId <= 0 || $questionText === '') {
            $err = 'متن سؤال الزامی است.';
        } elseif ($qtype === 'multiple_choice' && count($options) < 2) {
            $err = 'برای سؤال چندگزینه‌ای، دست‌کم دو گزینهٔ متفاوت وارد کنید.';
        } else {
            $q = $pdo->prepare("INSERT INTO survey_questions (survey_id, question_text, question_type, question_options) VALUES (?, ?, ?, ?)");
            $q->execute([$surveyId, $questionText, $qtype, $qtype === 'multiple_choice' ? survey_multiple_choice_options_json($options) : null]);
            $msg = 'سؤال اضافه شد.';
        }
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
        $qtext = trim((string) ($_POST['question_text'] ?? ''));
        $allowed_qtypes = array_keys(survey_question_types());
        $qtype = in_array($_POST['question_type'] ?? '', $allowed_qtypes, true) ? (string) $_POST['question_type'] : 'yes_no';
        $options = $qtype === 'multiple_choice' ? survey_multiple_choice_options_from_text((string) ($_POST['question_options'] ?? '')) : [];
        if ($qid <= 0 || $qtext === '') {
            $err = 'متن سؤال نمی‌تواند خالی باشد.';
        } elseif ($qtype === 'multiple_choice' && count($options) < 2) {
            $err = 'برای سؤال چندگزینه‌ای، دست‌کم دو گزینهٔ متفاوت وارد کنید.';
        } else {
            $pdo->prepare('UPDATE survey_questions SET question_text = ?, question_type = ?, question_options = ? WHERE id = ?')->execute([$qtext, $qtype, $qtype === 'multiple_choice' ? survey_multiple_choice_options_json($options) : null, $qid]);
            $msg = 'سؤال ویرایش شد.';
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
$result_customers = [];

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
        $customersQuery = $pdo->prepare("SELECT DISTINCT u.id, u.username, u.first_name, u.last_name FROM survey_responses r JOIN users u ON u.id = r.customer_id WHERE r.survey_id = ? ORDER BY u.first_name, u.last_name, u.username");
        $customersQuery->execute([$result_id]);
        $result_customers = $customersQuery->fetchAll();
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
                    "SELECT q.id, q.question_text, q.question_type, q.question_options, a.answer_value
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
                    $stats[$k] = ['text' => $row['question_text'], 'type' => $row['question_type'], 'options' => survey_multiple_choice_options($row['question_options'] ?? null), 'sum' => 0, 'count' => 0, 'yes' => 0, 'no' => 0, 'text_count' => 0, 'distribution' => []];
                }
                if ($row['question_type'] === 'yes_no') {
                    if ($row['answer_value'] === 'بله') { $stats[$k]['yes']++; } else { $stats[$k]['no']++; }
                } elseif (in_array($row['question_type'], ['satisfaction_5', 'multiple_choice'], true)) {
                    $answerKey = (string) $row['answer_value'];
                    $stats[$k]['distribution'][$answerKey] = ($stats[$k]['distribution'][$answerKey] ?? 0) + 1;
                } elseif ($row['question_type'] === 'text_free') {
                    $stats[$k]['text_count']++;
                } else {
                    $stats[$k]['sum'] += (float) $row['answer_value'];
                    $stats[$k]['count']++;
                }
            }
        }
    }
}

$question_type_labels = array_map(
    static fn (array $type): string => $type['label'],
    survey_question_types()
);

render_admin_header('مدیریت نظرسنجی', 'portal-page-main portal-admin-page portal-surveys-page p-8 max-w-7xl w-full mx-auto space-y-6');
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
                    <h3 class="text-lg font-bold text-slate-800">گزارش پاسخ‌ها: <bdi dir="auto"><?= htmlspecialchars($form['title']) ?></bdi></h3>
                    <a href="surveys.php" class="btn btn-sm btn-secondary">بازگشت به لیست</a>
                </div>

                <!-- فیلتر -->
                <form method="get" class="portal-survey-filter card p-5 flex flex-wrap items-end gap-3">
                    <input type="hidden" name="results" value="<?= $result_id ?>">
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="survey_filter_customer">مشتری</label>
                        <select id="survey_filter_customer" name="customer_id" class="input portal-form-control !min-h-0 !h-10 text-sm">
                            <option value="0">همهٔ مشتریان</option>
                            <?php foreach ($result_customers as $resultCustomer): ?>
                                <?php $resultCustomerName = trim(($resultCustomer['first_name'] ?? '') . ' ' . ($resultCustomer['last_name'] ?? '')) ?: $resultCustomer['username']; ?>
                                <option value="<?= (int) $resultCustomer['id'] ?>" <?= $filter_customer === (int) $resultCustomer['id'] ? 'selected' : '' ?>><?= e($resultCustomerName) ?> — #<?= (int) $resultCustomer['id'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="survey_filter_entity">نوع مورد</label>
                        <select id="survey_filter_entity" name="entity_type" class="px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                            <option value="">همه</option>
                            <option value="project" <?= ($filter_entity === 'project' ? 'selected' : '') ?>>پروژه</option>
                            <option value="product" <?= ($filter_entity === 'product' ? 'selected' : '') ?>>محصول</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="survey_filter_from">از تاریخ</label>
                        <input type="date" id="survey_filter_from" name="from" value="<?= htmlspecialchars($filter_from) ?>" dir="ltr" class="value-ltr px-3 py-2 rounded-lg border border-slate-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="survey_filter_to">تا تاریخ</label>
                        <input type="date" id="survey_filter_to" name="to" value="<?= htmlspecialchars($filter_to) ?>" dir="ltr" class="value-ltr px-3 py-2 rounded-lg border border-slate-300 text-sm">
                    </div>
                    <button type="submit" class="btn btn-primary">فیلتر</button>
                </form>

                <p class="text-sm text-slate-500">تعداد پاسخ‌ها: <b class="text-slate-800 value-ltr" dir="ltr"><?= count($results) ?></b></p>

                <?php if (!empty($stats)): ?>
                    <section class="portal-survey-results-summary card p-5" aria-labelledby="survey-results-summary-title">
                        <h4 id="survey-results-summary-title" class="font-bold text-slate-800 mb-3">خلاصه آماری</h4>
                        <div class="space-y-3">
                            <?php foreach ($stats as $st): ?>
                                <article class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                    <h5 class="font-medium text-slate-800"><bdi dir="auto"><?= e($st['text']) ?></bdi></h5>
                                    <?php if ($st['type'] === 'yes_no'): ?>
                                        <p class="text-slate-500 font-medium mt-2">بله: <b class="text-emerald-600 value-ltr" dir="ltr"><?= $st['yes'] ?></b> | خیر: <b class="text-red-500 value-ltr" dir="ltr"><?= $st['no'] ?></b></p>
                                    <?php elseif (in_array($st['type'], ['satisfaction_5', 'multiple_choice'], true)): ?>
                                        <?php
                                        $distributionOptions = $st['type'] === 'satisfaction_5'
                                            ? survey_satisfaction_options()
                                            : array_combine($st['options'], $st['options']);
                                        if (!$distributionOptions) { $distributionOptions = array_combine(array_keys($st['distribution']), array_keys($st['distribution'])) ?: []; }
                                        $distributionTotal = max(1, array_sum($st['distribution']));
                                        ?>
                                        <div class="survey-distribution" role="list" aria-label="توزیع پاسخ‌ها">
                                            <?php foreach ($distributionOptions as $answerKey => $answerLabel): ?>
                                                <?php $answerCount = (int) ($st['distribution'][$answerKey] ?? 0); $answerShare = ($answerCount / $distributionTotal) * 100; ?>
                                                <div class="survey-distribution-row" role="listitem">
                                                    <span><bdi dir="auto"><?= e((string) $answerLabel) ?></bdi></span>
                                                    <span class="survey-distribution-track" aria-hidden="true"><span style="--survey-answer-share: <?= number_format($answerShare, 2, '.', '') ?>%"></span></span>
                                                    <b class="value-ltr" dir="ltr" aria-label="<?= $answerCount ?> پاسخ"><?= $answerCount ?></b>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif ($st['type'] === 'text_free'): ?>
                                        <p class="text-slate-500 font-medium mt-2"><b class="text-indigo-700 value-ltr" dir="ltr"><?= (int) $st['text_count'] ?></b> پاسخ تشریحی ثبت شده است؛ متن کامل در فهرست پاسخ‌ها قابل مشاهده است.</p>
                                    <?php else: ?>
                                        <p class="text-slate-500 font-medium mt-2">میانگین: <b class="text-indigo-700 value-ltr" dir="ltr"><?= number_format($st['count'] ? $st['sum'] / $st['count'] : 0, 2) ?></b> از <span class="value-ltr" dir="ltr"><?= $st['type'] === 'rating_1_10' ? 10 : 5 ?></span></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php foreach ($results as $r): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <div class="flex items-center justify-between mb-3">
                            <b class="text-slate-800"><bdi dir="auto"><?= htmlspecialchars(trim($r['first_name'] . ' ' . $r['last_name']) ?: $r['username']) ?></bdi></b>
                            <span class="text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?= htmlspecialchars($r['created_at']) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">مربوط به: <b class="text-slate-700"><bdi dir="auto"><?= htmlspecialchars($r['entity_title'] ?? 'حذف‌شده') ?></bdi></b></p>
                        <div class="space-y-2">
                            <?php
                            $aa = $pdo->prepare("SELECT q.question_text, q.question_type, a.answer_value FROM survey_answers a JOIN survey_questions q ON q.id = a.question_id WHERE a.response_id = ? ORDER BY q.id");
                            $aa->execute([$r['id']]);
                            foreach ($aa->fetchAll() as $ans):
                            ?>
                                <div class="bg-slate-50 rounded-lg p-3 text-sm flex items-start justify-between gap-4">
                                    <span class="text-slate-600 shrink-0"><bdi dir="auto"><?= htmlspecialchars($ans['question_text']) ?></bdi></span>
                                    <span class="text-indigo-700 font-medium whitespace-pre-wrap break-words text-end"><bdi dir="auto"><?= htmlspecialchars(survey_answer_label((string) ($ans['question_type'] ?? ''), (string) $ans['answer_value'])) ?></bdi></span>
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
                <div class="portal-survey-page-header flex items-center justify-between gap-4">
                    <h3 class="text-lg font-bold text-slate-800">سؤالات: <bdi dir="auto"><?= htmlspecialchars($form['title']) ?></bdi></h3>
                    <div class="flex flex-wrap gap-2">
                        <a href="surveys.php?edit=<?= $edit ?>" class="btn btn-sm btn-secondary"><?= icon('edit') ?><span>ویرایش فرم</span></a>
                        <a href="surveys.php?results=<?= $edit ?>" class="btn btn-sm btn-primary"><?= icon('trending') ?><span>مشاهده گزارش</span></a>
                        <a href="surveys.php" class="btn btn-sm btn-ghost"><?= icon('back') ?><span>بازگشت</span></a>
                    </div>
                </div>

                <div class="portal-survey-author-card card p-6 shadow-sm">
                    <div class="portal-panel-heading">
                        <div>
                            <h4 class="font-bold text-slate-800 mb-1">افزودن سؤال جدید</h4>
                            <p class="helper">نوع نمایش پاسخ را متناسب با هدف سؤال انتخاب کنید.</p>
                        </div>
                    </div>
                    <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="question">
                        <input type="hidden" name="survey_id" value="<?= $edit ?>">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="survey_question_new">متن سؤال *</label>
                            <textarea id="survey_question_new" name="question_text" required dir="auto" placeholder="متن سؤال" rows="2" class="input portal-form-control"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="survey_question_type_new">نوع سؤال</label>
                            <select id="survey_question_type_new" name="question_type" class="input portal-form-control" data-question-type>
                                <?php foreach ($question_type_labels as $typeKey => $typeLabel): ?>
                                    <option value="<?= e($typeKey) ?>"><?= e($typeLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3" data-question-options-wrap hidden>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="survey_question_options_new">گزینه‌ها <span class="text-xs font-normal text-slate-500">(هر گزینه در یک خط)</span></label>
                            <textarea id="survey_question_options_new" name="question_options" rows="4" class="input portal-form-control" data-question-options placeholder="مثال: عالی&#10;خوب&#10;نیازمند پیگیری"></textarea>
                            <p class="helper mt-1">حداقل دو و حداکثر دوازده گزینهٔ متفاوت وارد کنید. گزینهٔ تکراری خودکار حذف می‌شود.</p>
                        </div>
                        <div class="md:col-span-3 flex justify-end">
                            <button type="submit" class="btn btn-primary"><?= icon('plus') ?><span>افزودن سؤال</span></button>
                        </div>
                    </form>
                </div>

                <div class="portal-question-list card shadow-sm overflow-hidden">
                    <?php if (empty($questions)): ?>
                        <p class="p-8 text-center text-slate-400 text-sm">هنوز سؤالی برای این فرم ثبت نشده است.</p>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($questions as $i => $item): ?>
                                <?php $questionOptionText = implode("\n", survey_multiple_choice_options($item['question_options'] ?? null)); ?>
                                <div class="portal-question-item px-6 py-4" data-question-item="<?= $item['id'] ?>">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <div class="font-medium text-slate-800 text-sm"><span class="value-ltr" dir="ltr"><?= $i + 1 ?>.</span> <bdi dir="auto"><?= htmlspecialchars($item['question_text']) ?></bdi></div>
                                            <span class="inline-block mt-1 text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full"><?= htmlspecialchars($question_type_labels[$item['question_type']] ?? $item['question_type']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button type="button" class="btn btn-sm btn-ghost !text-indigo-600" data-question-edit="<?= (int) $item['id'] ?>"><?= icon('edit') ?><span>ویرایش</span></button>
                                            <form method="post" data-confirm-msg="حذف شود؟">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete_question">
                                                <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash') ?><span>حذف</span></button>
                                            </form>
                                        </div>
                                    </div>
                                    <!-- فرم ویرایش سؤال -->
                                    <div id="qedit-<?= $item['id'] ?>" class="portal-question-edit hidden mt-3 p-4 rounded-xl border">
                                        <form method="post" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="action" value="update_question">
                                            <input type="hidden" name="question_id" value="<?= $item['id'] ?>">
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-slate-700 mb-1" for="survey_question_<?= $item['id'] ?>">متن سؤال</label>
                                                <textarea id="survey_question_<?= $item['id'] ?>" name="question_text" required dir="auto" rows="2" class="input portal-form-control"><?= htmlspecialchars($item['question_text']) ?></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700 mb-1" for="survey_question_type_<?= $item['id'] ?>">نوع سؤال</label>
                                                <select id="survey_question_type_<?= $item['id'] ?>" name="question_type" class="input portal-form-control" data-question-type>
                                                    <?php foreach ($question_type_labels as $typeKey => $typeLabel): ?>
                                                        <option value="<?= e($typeKey) ?>" <?= $item['question_type'] === $typeKey ? 'selected' : '' ?>><?= e($typeLabel) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="md:col-span-3" data-question-options-wrap <?= $item['question_type'] === 'multiple_choice' ? '' : 'hidden' ?>>
                                                <label class="block text-sm font-medium text-slate-700 mb-1" for="survey_question_options_<?= $item['id'] ?>">گزینه‌ها <span class="text-xs font-normal text-slate-500">(هر گزینه در یک خط)</span></label>
                                                <textarea id="survey_question_options_<?= $item['id'] ?>" name="question_options" rows="4" class="input portal-form-control" data-question-options><?= e($questionOptionText) ?></textarea>
                                                <p class="helper mt-1">حداقل دو و حداکثر دوازده گزینهٔ متفاوت وارد کنید.</p>
                                            </div>
                                            <div class="md:col-span-3 flex justify-end gap-2">
                                                <button type="button" class="btn btn-sm btn-secondary" data-question-cancel>انصراف</button>
                                                <button type="submit" class="btn btn-sm btn-primary"><?= icon('check') ?><span>ذخیره سؤال</span></button>
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
                <div class="portal-survey-page-header flex items-center justify-between gap-4">
                    <h3 class="text-lg font-bold text-slate-800">ویرایش فرم نظرسنجی</h3>
                    <div class="flex gap-2">
                        <a href="surveys.php?questions=<?= $edit_survey ?>" class="btn btn-sm btn-secondary">ویرایش سؤال‌ها</a>
                        <a href="surveys.php" class="btn btn-sm btn-secondary">بازگشت به لیست</a>
                    </div>
                </div>

                <div class="portal-survey-form-card card p-6 md:p-8 shadow-sm max-w-3xl">
                    <form method="post" class="space-y-6">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="survey_id" value="<?= $edit_survey ?>">

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_title">عنوان فرم *</label>
                            <input type="text" id="survey_title" name="title" required dir="auto" value="<?= htmlspecialchars($form['title']) ?>" class="input portal-form-control">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_description">توضیحات</label>
                            <textarea id="survey_description" name="description" rows="3" dir="auto" class="input portal-form-control" placeholder="توضیح کوتاه درباره هدف این نظرسنجی"><?= htmlspecialchars($form['description']) ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">نوع فرم</label>
                            <div class="portal-survey-type-summary flex items-center gap-2">
                                <?php if ($form['target_entity'] === 'product'): ?>
                                    <span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-0.5 rounded-full font-medium">محصول</span>
                                <?php else: ?>
                                    <span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-0.5 rounded-full font-medium">پروژه</span>
                                <?php endif; ?>
                                <span>نوع فرم پس از ایجاد قابل تغییر نیست.</span>
                            </div>
                        </div>

                        <label class="portal-checkbox-row flex items-center gap-3 cursor-pointer" for="survey_periodic">
                            <input type="checkbox" id="survey_periodic" name="is_periodic" value="1" class="accent-indigo-600 w-4 h-4 rounded border-slate-300" <?= $form['is_periodic'] ? 'checked' : '' ?> data-periodic-toggle>
                            <span class="text-sm font-medium text-slate-700">فرم دوره‌ای (بعد از تکمیل فرم اولیه، دوباره فعال می‌شود)</span>
                        </label>

                        <div id="period" <?= $form['is_periodic'] ? '' : 'hidden' ?> class="portal-survey-period-panel space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_parent">فرم اولیه مرتبط</label>
                                <select id="survey_parent" name="parent_survey_id" class="input portal-form-control">
                                    <option value="0">انتخاب کنید</option>
                                    <?php foreach ($parents as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= ((int) $form['parent_survey_id'] === (int) $p['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['title']) ?> (<?= $p['target_entity'] === 'product' ? 'محصول' : 'پروژه' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_delay_days">تعداد روز پس از تکمیل فرم اولیه</label>
                                <input type="number" id="survey_delay_days" name="delay_days" min="1" value="<?= (int) $form['delay_days'] ?>" dir="ltr" class="input portal-form-control value-ltr">
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <a href="surveys.php" class="btn btn-secondary">انصراف</a>
                            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($show_create): ?>
                <!-- ===== ساخت فرم جدید ===== -->
                <div class="portal-survey-page-header flex items-center justify-between gap-4">
                    <h3 class="text-lg font-bold text-slate-800">ساخت فرم نظرسنجی جدید</h3>
                    <a href="surveys.php" class="btn btn-sm btn-secondary">بازگشت به لیست</a>
                </div>

                <div class="portal-survey-form-card card p-6 md:p-8 shadow-sm max-w-3xl">
                    <form method="post" class="space-y-6">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="create">

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_title">عنوان فرم *</label>
                            <input type="text" id="survey_title" name="title" required dir="auto" class="input portal-form-control" placeholder="مثلا: نظرسنجی رضایت از پروژه">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_description">توضیحات</label>
                            <textarea id="survey_description" name="description" rows="3" dir="auto" class="input portal-form-control" placeholder="توضیح کوتاه درباره هدف این نظرسنجی"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_target_entity">نوع فرم</label>
                            <select id="survey_target_entity" name="target_entity" class="input portal-form-control">
                                <option value="project">فرم پروژه</option>
                                <option value="product">فرم محصول</option>
                            </select>
                        </div>

                        <label class="portal-checkbox-row flex items-center gap-3 cursor-pointer" for="survey_periodic">
                            <input type="checkbox" id="survey_periodic" name="is_periodic" value="1" class="accent-indigo-600 w-4 h-4 rounded border-slate-300" data-periodic-toggle>
                            <span class="text-sm font-medium text-slate-700">فرم دوره‌ای (بعد از تکمیل فرم اولیه، دوباره فعال می‌شود)</span>
                        </label>

                        <div id="period" hidden class="portal-survey-period-panel space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_parent">فرم اولیه مرتبط</label>
                                <select id="survey_parent" name="parent_survey_id" class="input portal-form-control">
                                    <option value="0">انتخاب کنید</option>
                                    <?php foreach ($parents as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?> (<?= $p['target_entity'] === 'product' ? 'محصول' : 'پروژه' ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5" for="survey_delay_days">تعداد روز پس از تکمیل فرم اولیه</label>
                                <input type="number" id="survey_delay_days" name="delay_days" min="1" value="30" dir="ltr" class="input portal-form-control value-ltr">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="btn btn-primary">ذخیره و افزودن سؤال‌ها</button>
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
                                    <th class="p-4 min-w-[15rem]">عنوان</th>
                                    <th class="p-4">نوع</th>
                                    <th class="p-4">مرحله</th>
                                    <th class="p-4">سؤال</th>
                                    <th class="p-4">پاسخ</th>
                                    <th class="p-4 text-center min-w-[18rem]">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($forms)): ?>
                                    <tr><td colspan="6" class="p-8 text-center text-slate-400"><?php echo empty_state('هنوز فرمی ساخته نشده است.', '', 'info'); ?></td></tr>
                                <?php else: ?>
                                    <?php foreach ($forms as $f): ?>
                                        <tr class="hover:bg-slate-50 transition">
                                            <td data-label="عنوان" class="p-4 font-medium text-slate-900 min-w-[15rem]"><bdi dir="auto"><?= htmlspecialchars($f['title']) ?></bdi></td>
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
                                            <td data-label="سؤال" class="p-4 text-slate-600 value-ltr" dir="ltr"><?= (int) $f['qc'] ?></td>
                                            <td data-label="پاسخ" class="p-4 text-slate-600 value-ltr" dir="ltr"><?= (int) $f['rc'] ?></td>
                                            <td data-label="عملیات" class="p-4 min-w-[18rem]">
                                                <div class="cell-actions flex flex-wrap items-center justify-center gap-2">
                                                    <a href="surveys.php?edit=<?= $f['id'] ?>" class="text-slate-600 hover:text-slate-900 font-medium text-xs bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg">ویرایش</a>
                                                    <a href="surveys.php?questions=<?= $f['id'] ?>" class="btn btn-sm btn-ghost !text-indigo-600">سؤالات</a>
                                                    <a href="surveys.php?results=<?= $f['id'] ?>" class="text-emerald-600 hover:text-emerald-800 font-medium text-xs bg-emerald-50 px-3 py-1.5 rounded-lg">گزارش</a>
                                                    <form method="post" data-confirm-msg="با حذف فرم تمام پاسخ‌ها حذف می‌شود. ادامه؟">
                                                        <?php echo csrf_input(); ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
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

            <script nonce="<?= e(portal_csp_nonce()) ?>">
            document.addEventListener('DOMContentLoaded', function(){
                document.querySelectorAll('[data-question-edit]').forEach(function(button){
                    button.addEventListener('click', function(){
                        var edit = document.getElementById('qedit-' + button.dataset.questionEdit);
                        if (edit) edit.classList.toggle('hidden');
                    });
                });
                document.querySelectorAll('[data-question-cancel]').forEach(function(button){
                    button.addEventListener('click', function(){
                        var item = button.closest('[data-question-item]');
                        var edit = item ? item.querySelector('[id^="qedit-"]') : null;
                        if (edit) edit.classList.add('hidden');
                    });
                });
                document.querySelectorAll('[data-periodic-toggle]').forEach(function(toggle){
                    toggle.addEventListener('change', function(){
                        var period = toggle.form ? toggle.form.querySelector('#period') : null;
                        if (period) period.hidden = !toggle.checked;
                    });
                });
                function toggleQuestionOptions(select){
                    var form = select.closest('form');
                    var wrap = form ? form.querySelector('[data-question-options-wrap]') : null;
                    if (wrap) wrap.hidden = select.value !== 'multiple_choice';
                }
                document.querySelectorAll('[data-question-type]').forEach(function(select){
                    select.addEventListener('change', function(){ toggleQuestionOptions(select); });
                    toggleQuestionOptions(select);
                });
            });
            </script>

        <?php render_admin_footer();
