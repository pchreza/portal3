<?php
// customer/surveys.php — نظرسنجی‌های من (لیست، تکمیل و ثبت پاسخ)
require_once 'auth.php';
if (!is_module_enabled('surveys')) { header('Location: index.php'); exit; }

$uid = (int) $_SESSION['user_id'];
ensure_survey_assignments($uid);

$msg = isset($_GET['submitted'])
    ? 'پاسخ شما ثبت شد. سپاس از زمانی که برای تکمیل فرم گذاشتید.'
    : '';
$err = '';

// ---------- ثبت پاسخ ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $aid = (int) $_POST['assignment_id'];

    // اطمینان از اینکه پروژه/محصول هنوز وجود دارد (حذف نشده باشد)
    $a = $pdo->prepare(
        "SELECT sa.*, s.title, s.description
         FROM survey_assignments sa
         JOIN surveys s ON s.id = sa.survey_id
         WHERE sa.id = ? AND sa.customer_id = ? AND s.is_active = 1 AND sa.available_at <= NOW()
           AND (
             (sa.entity_type = 'project' AND EXISTS (SELECT 1 FROM projects p WHERE p.id = sa.entity_id))
             OR
             (sa.entity_type = 'product' AND EXISTS (SELECT 1 FROM products pr WHERE pr.id = sa.entity_id))
           )"
    );
    $a->execute([$aid, $uid]);
    $as = $a->fetch();

    if (!$as) {
        $err = 'این فرم برای شما قابل دسترسی نیست، هنوز فعال نشده یا مورد مربوطه حذف شده است.';
    } else {
        // جلوگیری از پاسخ تکراری
        $ck = $pdo->prepare("SELECT id FROM survey_responses WHERE survey_id = ? AND customer_id = ? AND entity_type = ? AND entity_id = ? AND created_at >= ?");
        $ck->execute([$as['survey_id'], $uid, $as['entity_type'], $as['entity_id'], $as['available_at']]);

        if ($ck->fetch()) {
            $err = 'این فرم قبلاً تکمیل شده است.';
        } else {
            $qs = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY id");
            $qs->execute([$as['survey_id']]);
            $questions = $qs->fetchAll();

            $pdo->beginTransaction();
            try {
                $r = $pdo->prepare("INSERT INTO survey_responses (survey_id, customer_id, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)");
                $r->execute([$as['survey_id'], $uid, $as['entity_type'], $as['entity_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
                $rid = $pdo->lastInsertId();

                $ins = $pdo->prepare("INSERT INTO survey_answers (response_id, question_id, answer_value) VALUES (?, ?, ?)");
                foreach ($questions as $q) {
                    $key = 'q_' . $q['id'];
                    if (!isset($_POST[$key])) {
                        throw new Exception('همه سؤال‌ها الزامی هستند.');
                    }
                    $value = trim((string) $_POST[$key]);

                    if ($q['question_type'] === 'yes_no' && !in_array($value, ['بله', 'خیر'], true)) {
                        throw new Exception('پاسخ بله یا خیر نامعتبر است.');
                    }
                    if ($q['question_type'] === 'rating_1_10' && (!ctype_digit($value) || (int) $value < 1 || (int) $value > 10)) {
                        throw new Exception('امتیاز باید بین ۱ تا ۱۰ باشد.');
                    }
                    if ($q['question_type'] === 'star_rating' && (!ctype_digit($value) || (int) $value < 1 || (int) $value > 5)) {
                        throw new Exception('امتیاز ستاره‌ای باید بین ۱ تا ۵ باشد.');
                    }

                    $ins->execute([$rid, $q['id'], $value]);
                }

                $pdo->commit();
                gamification_award_points($uid, 'survey_submitted', 'survey_submitted:' . (int) $as['survey_id'] . ':' . $uid, 'امتیاز تکمیل نظرسنجی', 'survey_response', (string) $rid);
                header('Location: surveys.php?submitted=1');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $err = $e->getCode() === '23000'
                    ? 'این نظرسنجی قبلاً برای این پروژه یا محصول ثبت شده است.'
                    : 'ثبت پاسخ انجام نشد. لطفاً دوباره تلاش کنید.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                error_log('[Customer Survey] ' . $e->getMessage());
                $err = 'ثبت پاسخ انجام نشد. لطفاً دوباره تلاش کنید.';
            }
        }
    }
}

// ---------- بارگذاری فرم انتخاب‌شده برای تکمیل ----------
$take = (int) ($_GET['take'] ?? 0);
$takeData = null;
$questions = [];

if ($take) {
    $x = $pdo->prepare(
        "SELECT sa.*, s.title, s.description,
                CASE WHEN sa.entity_type = 'project' THEN p.title ELSE pr.title END entity_title
         FROM survey_assignments sa
         JOIN surveys s ON s.id = sa.survey_id
         LEFT JOIN projects p ON sa.entity_type = 'project' AND p.id = sa.entity_id
         LEFT JOIN products pr ON sa.entity_type = 'product' AND pr.id = sa.entity_id
         WHERE sa.id = ? AND sa.customer_id = ? AND s.is_active = 1 AND sa.available_at <= NOW()
           AND (
             (sa.entity_type = 'project' AND p.id IS NOT NULL)
             OR
             (sa.entity_type = 'product' AND pr.id IS NOT NULL)
           )"
    );
    $x->execute([$take, $uid]);
    $takeData = $x->fetch();

    if ($takeData) {
        $x = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY id");
        $x->execute([$takeData['survey_id']]);
        $questions = $x->fetchAll();
    } else {
        $err = 'این نظرسنجی در دسترس نیست یا مورد مربوطه حذف شده است.';
    }
}

// ---------- فهرست فرم‌های مشتری ----------
$x = $pdo->prepare(
    "SELECT sa.*, s.title, s.description,
            CASE WHEN sa.entity_type = 'project' THEN p.title ELSE pr.title END entity_title,
            CASE WHEN (sa.entity_type = 'project' AND p.id IS NULL) OR (sa.entity_type = 'product' AND pr.id IS NULL)
                 THEN 0 ELSE 1 END entity_exists,
            (SELECT COUNT(*) FROM survey_responses r
             WHERE r.survey_id = sa.survey_id AND r.customer_id = sa.customer_id
               AND r.entity_type = sa.entity_type AND r.entity_id = sa.entity_id) answered
     FROM survey_assignments sa
     JOIN surveys s ON s.id = sa.survey_id
     LEFT JOIN projects p ON sa.entity_type = 'project' AND p.id = sa.entity_id
     LEFT JOIN products pr ON sa.entity_type = 'product' AND pr.id = sa.entity_id
     WHERE sa.customer_id = ? AND s.is_active = 1
     ORDER BY sa.available_at"
);
$x->execute([$uid]);
$items = $x->fetchAll();

// ---------- استایل اختصاصی (بازطراحی FULLMASTER — فقط presentation) ----------
$customer_survey_styles = '
.survey-form{max-width:760px;margin:auto}
.survey-q-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;margin:1rem 0;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:border-color .2s}
.survey-q-card.is-focused{border-color:var(--ring);box-shadow:0 0 0 3px color-mix(in srgb, var(--ring) 14%, transparent)}
.survey-q-card b{display:flex;align-items:flex-start;gap:.6rem;margin-bottom:.9rem;font-size:1rem;color:var(--fg)}
.survey-q-card b .qnum{font-weight:700;color:var(--ring);min-width:1.6rem}
.yesno{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.yesno input{position:absolute;opacity:0;pointer-events:none}
.yesno label{display:flex;align-items:center;justify-content:center;gap:.5rem;height:3rem;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);color:var(--fg);font-weight:600;cursor:pointer;transition:all .15s}
.yesno label:hover{border-color:var(--ring);background:color-mix(in srgb, var(--ring) 6%, transparent)}
.yesno input:checked + label{border-color:var(--ring);background:var(--ring);color:#fff}
.yesno input:focus-visible + label{outline:3px solid color-mix(in srgb, var(--ring) 45%, transparent);outline-offset:2px}
.stars-rating{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:6px}
.stars-rating input{position:absolute;opacity:0;pointer-events:none}
.stars-rating label{font-size:2.4rem;line-height:1;color:var(--fg-faint);cursor:pointer;transition:color .15s,transform .15s;padding:.1rem}
.stars-rating label:hover{transform:scale(1.1)}
.stars-rating label:hover, .stars-rating label:hover ~ label,
.stars-rating input:checked ~ label, .stars-rating input:checked ~ label ~ label{color:#f59e0b}
.stars-rating input:checked + label{transform:scale(1.06)}
.stars-rating input:focus-visible + label{outline:3px solid color-mix(in srgb, var(--ring) 45%, transparent);outline-offset:2px;border-radius:.35rem}
.rating-scale{display:flex;gap:.5rem;flex-wrap:wrap;direction:ltr;justify-content:flex-start}
.rating-scale input{position:absolute;opacity:0}
.rating-scale span{width:2.75rem;height:2.75rem;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);color:var(--fg);display:flex;align-items:center;justify-content:center;font-weight:700;cursor:pointer;transition:all .15s}
.rating-scale label:hover span{transform:translateY(-2px);border-color:var(--ring)}
.rating-scale label:has(input:checked) span{background:var(--ring);color:#fff;border-color:var(--ring)}
.rating-scale label:has(input:focus-visible) span{outline:3px solid color-mix(in srgb, var(--ring) 45%, transparent);outline-offset:2px}
.survey-hint{font-size:.75rem;color:var(--fg-faint);margin-top:.5rem;display:flex;justify-content:space-between}
.survey-progress-wrap{margin:1.25rem 0 .5rem}
.survey-progress-text{display:flex;justify-content:space-between;font-size:.8125rem;color:var(--fg-muted);margin-bottom:.4rem}
.survey-progress{height:.5rem;background:var(--surface-3);border-radius:999px;overflow:hidden}
.survey-progress > span{display:block;height:100%;width:0;background:var(--ring);border-radius:999px;transition:width .3s}
.survey-tag{display:inline-flex;align-items:center;gap:.4rem;font-size:.75rem;font-weight:600;color:var(--ring);background:color-mix(in srgb, var(--ring) 12%, transparent);padding:.3rem .8rem;border-radius:999px}
@media (max-width:767px){
  .survey-q-card{padding:1rem}
  .rating-scale{justify-content:center}
  .yesno label{height:3.25rem}
}
';

render_customer_header(
    'نظرسنجی‌های من',
    'p-8 max-w-6xl w-full mx-auto space-y-6',
    $customer_survey_styles,
    ''
);
?>
            <h2 class="text-2xl font-bold text-slate-800">نظرسنجی‌های شما</h2>

            <?php if ($msg): ?>
                <div class="alert alert-success" role="status"><?= icon('check') ?><span><?= htmlspecialchars($msg) ?></span></div>
            <?php endif; ?>

            <?php if ($err): ?>
                <div class="alert alert-danger" role="alert"><?= icon('alert') ?><span><?= htmlspecialchars($err) ?></span></div>
            <?php endif; ?>

            <?php if ($takeData): ?>
                <!-- فرم تکمیل نظرسنجی (بازطراحی FULLMASTER) -->
                <form class="survey-form" method="post" id="survey-form">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="assignment_id" value="<?= $take ?>">

                    <div class="card p-6 md:p-8 mb-4 text-center">
                        <span class="survey-tag"><?= icon('star', 'w-4 h-4') ?> نظرسنجی</span>
                        <h2 class="font-bold text-slate-900 leading-snug mt-3 mb-1"><bdi dir="auto"><?= htmlspecialchars($takeData['title']) ?></bdi></h2>
                        <p class="body-sm text-slate-600">مربوط به: <b class="text-slate-800"><bdi dir="auto"><?= htmlspecialchars($takeData['entity_title'] ?? '') ?></bdi></b></p>
                        <?php if (!empty($takeData['description'])): ?>
                            <p class="body-sm text-slate-600 leading-relaxed bg-slate-50 border border-slate-200 rounded-xl p-4 mt-4 text-right"><bdi dir="auto"><?= htmlspecialchars($takeData['description']) ?></bdi></p>
                        <?php endif; ?>
                    </div>

                    <div class="survey-progress-wrap" id="survey-progress-wrap">
                        <div class="survey-progress-text"><span><span id="survey-progress-label" class="value-ltr" dir="ltr">0 / <?= count($questions) ?></span> پاسخ داده شده</span><span><span class="value-ltr" dir="ltr"><?= count($questions) ?></span> سؤال</span></div>
                        <div class="survey-progress"><span id="survey-progress-bar"></span></div>
                    </div>

                    <?php foreach ($questions as $i => $q): $fieldName = 'q_' . $q['id']; ?>
                        <div class="survey-q-card" data-q-card>
                            <b><span class="qnum value-ltr" dir="ltr"><?= $i + 1 ?></span><span><bdi dir="auto"><?= htmlspecialchars($q['question_text']) ?></bdi></span></b>

                            <?php if ($q['question_type'] === 'yes_no'): ?>
                                <div class="yesno" role="radiogroup" aria-label="<?= e($q['question_text']) ?>">
                                    <input type="radio" id="yn_<?= $q['id'] ?>_1" name="<?= $fieldName ?>" value="بله" required>
                                    <label for="yn_<?= $q['id'] ?>_1"><?= icon('check') ?> بله</label>
                                    <input type="radio" id="yn_<?= $q['id'] ?>_0" name="<?= $fieldName ?>" value="خیر" required>
                                    <label for="yn_<?= $q['id'] ?>_0"><?= icon('x') ?> خیر</label>
                                </div>

                            <?php elseif ($q['question_type'] === 'star_rating'): ?>
                                <div dir="ltr">
                                    <div class="stars-rating" role="radiogroup" aria-label="<?= e($q['question_text']) ?>">
                                        <?php for ($star = 5; $star >= 1; $star--): ?>
                                            <input type="radio" id="q<?= $q['id'] ?>_s<?= $star ?>" name="<?= $fieldName ?>" value="<?= $star ?>" aria-label="<?= $star ?> از ۵ ستاره" required>
                                            <label for="q<?= $q['id'] ?>_s<?= $star ?>" title="<?= $star ?> ستاره">★</label>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="survey-hint"><span>۱ — کمترین امتیاز</span><span>۵ — بیشترین امتیاز</span></div>
                                </div>

                            <?php else: ?>
                                <div class="rating-scale" role="radiogroup" aria-label="<?= e($q['question_text']) ?>">
                                    <?php for ($score = 1; $score <= 10; $score++): ?>
                                        <label title="<?= $score ?>"><input type="radio" name="<?= $fieldName ?>" value="<?= $score ?>" aria-label="<?= $score ?> از ۱۰" required><span><?= $score ?></span></label>
                                    <?php endfor; ?>
                                </div>
                                <div class="survey-hint" dir="ltr"><span>۱ — کمترین</span><span>۱۰ — بیشترین</span></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="desktop-form-actions flex justify-end pt-2 mb-20 md:mb-0">
                        <button type="submit" class="btn btn-primary btn-lg w-full md:w-auto"><?= icon('check') ?><span>ثبت نهایی پاسخ</span></button>
                    </div>
                    <div class="mobile-action-bar">
                        <button type="submit" class="btn btn-primary btn-lg"><?= icon('check') ?><span>ثبت نهایی پاسخ</span></button>
                    </div>
                </form>

                <script nonce="<?= e(portal_csp_nonce()) ?>">
                document.addEventListener('DOMContentLoaded', function(){
                    var form = document.getElementById('survey-form');
                    if (!form) return;
                    var cards = form.querySelectorAll('[data-q-card]');
                    var bar = document.getElementById('survey-progress-bar');
                    var lbl = document.getElementById('survey-progress-label');
                    function update(){
                        var answered = 0;
                        cards.forEach(function(card){
                            var group = card.querySelector('[name^="q_"]');
                            var done = group && group.form && group.form.elements[group.name] && Array.prototype.some.call(form.elements[group.name], function(r){ return r.checked; });
                            if (done) { answered++; card.classList.remove('is-focused'); }
                        });
                        var pct = cards.length ? Math.round(answered / cards.length * 100) : 0;
                        if (bar) bar.style.width = pct + '%';
                        if (lbl) lbl.textContent = answered + ' / ' + cards.length;
                    }
                    form.addEventListener('change', function(e){
                        if (e.target.name && e.target.name.indexOf('q_') === 0) update();
                    });
                    cards.forEach(function(card){ card.addEventListener('focusin', function(){ card.classList.add('is-focused'); }); });
                    update();
                });
                </script>

            <?php else: ?>
                <!-- کارت‌های فرم‌ها -->
                <?php if (empty($items)): ?>
                    <?php echo empty_state('در حال حاضر فرم فعالی برای شما وجود ندارد', 'هنگامی که مدیر سیستم نظرسنجی‌ای به شما اختصاص دهد، اینجا نمایش داده می‌شود.', 'star'); ?>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach ($items as $i): ?>
                            <article class="card card-hover p-5 flex flex-col gap-3">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-bold text-slate-900 leading-snug"><bdi dir="auto"><?= htmlspecialchars($i['title']) ?></bdi></h3>
                                    <?php if ($i['answered']): ?>
                                        <span class="badge badge-success"><?= icon('check', 'w-3.5 h-3.5') ?> تکمیل شده</span>
                                    <?php endif; ?>
                                </div>

                                <div class="body-sm text-slate-600">
                                    مربوط به: <b class="text-slate-800"><bdi dir="auto"><?= htmlspecialchars($i['entity_title'] ?? '') ?></bdi></b>
                                </div>

                                <?php if (!empty($i['description'])): ?>
                                    <p class="body-sm text-slate-600 leading-relaxed"><bdi dir="auto"><?= htmlspecialchars($i['description']) ?></bdi></p>
                                <?php endif; ?>

                                <div class="pt-3 border-t border-slate-100 mt-auto">
                                    <?php if ($i['answered']): ?>
                                        <span class="badge badge-success"><?= icon('check', 'w-3.5 h-3.5') ?> پاسخ شما ثبت شد</span>
                                    <?php elseif (!$i['entity_exists']): ?>
                                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-400 bg-slate-100 px-3 py-2 rounded-lg">مورد مربوطه حذف شده است</span>
                                    <?php elseif (strtotime($i['available_at'] ?? '') > time()): ?>
                                        <?php
                                            $diff_sec = max(0, strtotime($i['available_at']) - time());
                                            $days_left = (int) ceil($diff_sec / 86400);
                                            $hours_left = (int) floor($diff_sec / 3600);
                                            if ($days_left >= 1) {
                                                $remain_text = 'نظرسنجی بعدی ' . $days_left . ' روز دیگر';
                                            } elseif ($hours_left >= 1) {
                                                $remain_text = 'نظرسنجی بعدی ' . $hours_left . ' ساعت دیگر';
                                            } else {
                                                $remain_text = 'نظرسنجی بعدی به‌زودی فعال می‌شود';
                                            }
                                        ?>
                                        <span title="فعال‌سازی در تاریخ <?= htmlspecialchars($i['available_at']) ?>" class="inline-flex items-center gap-1.5 text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-lg">
                                            <?= icon('alert') ?><span><?= htmlspecialchars($remain_text) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <a href="?take=<?= $i['id'] ?>" class="btn btn-primary w-full"><?= icon('star') ?><span>شروع نظرسنجی</span></a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php render_customer_footer();

