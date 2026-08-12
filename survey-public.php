<?php
// survey-public.php — صفحه عمومی تکمیل نظرسنجی از طریق لینک پیامک (بدون نیاز به ورود)
// لینک:  {site_url}/survey-public.php?token=xxxxxxxx
// توکن یکتای هر انتساب (survey_assignments.token) در پیامک یادآوری ارسال می‌شود.

require_once __DIR__ . '/config.php';

$token = trim((string) ($_GET['token'] ?? ''));
$done  = isset($_GET['done']);
$err   = '';
$data  = null;   // اطلاعات انتساب + فرم
$questions = [];
$already    = false;

if ($token === '') {
    $err = 'لینک معتبر نیست. برای دریافت لینک جدید، لطفاً با پشتیبانی تماس بگیرید.';
} else {
    // ---------- یافتن انتساب با توکن ----------
    $x = $pdo->prepare(
        "SELECT sa.*, s.title, s.description, s.is_active,
                CASE WHEN sa.entity_type = 'project' THEN p.title ELSE pr.title END entity_title,
                CASE WHEN (sa.entity_type = 'project' AND p.id IS NULL) OR (sa.entity_type = 'product' AND pr.id IS NULL)
                     THEN 0 ELSE 1 END entity_exists
         FROM survey_assignments sa
         JOIN surveys s ON s.id = sa.survey_id
         LEFT JOIN projects p ON sa.entity_type = 'project' AND p.id = sa.entity_id
         LEFT JOIN products pr ON sa.entity_type = 'product' AND pr.id = sa.entity_id
         WHERE sa.token = ?"
    );
    $x->execute([$token]);
    $data = $x->fetch();

    if (!$data || (int) $data['is_active'] !== 1) {
        $err = 'این نظرسنجی در دسترس نیست یا حذف شده است.';
        $data = null;
    } elseif (!$data['entity_exists']) {
        $err = 'مورد مربوط به این نظرسنجی حذف شده است.';
        $data = null;
    } elseif (strtotime((string) $data['available_at']) > time()) {
        $err = 'این نظرسنجی هنوز فعال نشده است. لطفاً بعداً مراجعه کنید.';
        $data = null;
    } else {
        // آیا قبلاً پاسخ داده شده؟
        $ck = $pdo->prepare("SELECT id FROM survey_responses WHERE survey_id = ? AND customer_id = ? AND entity_type = ? AND entity_id = ? AND created_at >= ?");
        $ck->execute([$data['survey_id'], $data['customer_id'], $data['entity_type'], $data['entity_id'], $data['available_at']]);
        if ($ck->fetch()) {
            $already = true;
            $data = null;
        } else {
            $qq = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY id");
            $qq->execute([$data['survey_id']]);
            $questions = $qq->fetchAll();
            if (empty($questions)) {
                $err = 'این فرم هنوز سؤالی ندارد.';
                $data = null;
            }
        }
    }
}

// ---------- ثبت پاسخ ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data && !$done) {
    require_valid_csrf();

    $aid = (int) $data['id'];
    // بررسی دوباره عدم وجود پاسخ (جلوگیری از ثبت تکراری)
    $ck = $pdo->prepare("SELECT id FROM survey_responses WHERE survey_id = ? AND customer_id = ? AND entity_type = ? AND entity_id = ?");
    $ck->execute([$data['survey_id'], $data['customer_id'], $data['entity_type'], $data['entity_id']]);

    if ($ck->fetch()) {
        $err = 'پاسخ شما قبلاً ثبت شده است.';
    } else {
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO survey_responses (survey_id, customer_id, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$data['survey_id'], $data['customer_id'], $data['entity_type'], $data['entity_id'], $_SERVER['REMOTE_ADDR'] ?? '']);
            $rid = (int) $pdo->lastInsertId();

            $answ = $pdo->prepare("INSERT INTO survey_answers (response_id, question_id, answer_value) VALUES (?, ?, ?)");
            foreach ($questions as $q) {
                $key = 'q_' . $q['id'];
                if (!isset($_POST[$key])) {
                    throw new Exception('لطفاً به همه سؤال‌ها پاسخ دهید.');
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

                $answ->execute([$rid, $q['id'], $value]);
            }

            $pdo->commit();
            header('Location: survey-public.php?token=' . rawurlencode($token) . '&done=1');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $err = $e->getCode() === '23000'
                ? 'پاسخ شما قبلاً برای این مورد ثبت شده است.'
                : 'ثبت پاسخ انجام نشد. لطفاً دوباره تلاش کنید.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $err = $e->getMessage();
        }
    }
}

// ---------- استایل اختصاصی (بازطراحی FULLMASTER — فقط presentation) ----------
$styles = '
.survey-form{max-width:760px;margin:auto}
.survey-q-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem;margin:1rem 0;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:border-color .2s}
.survey-q-card.is-focused{border-color:var(--ring);box-shadow:0 0 0 3px color-mix(in srgb, var(--ring) 14%, transparent)}
.survey-q-card b{display:flex;align-items:flex-start;gap:.6rem;margin-bottom:.9rem;font-size:1rem;color:var(--fg)}
.survey-q-card b .qnum{font-weight:700;color:var(--ring);min-width:1.6rem}
/* Yes/No segmented */
.yesno{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.yesno input{position:absolute;opacity:0;pointer-events:none}
.yesno label{display:flex;align-items:center;justify-content:center;gap:.5rem;height:3rem;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);color:var(--fg);font-weight:600;cursor:pointer;transition:all .15s}
.yesno label:hover{border-color:var(--ring);background:color-mix(in srgb, var(--ring) 6%, transparent)}
.yesno input:checked + label{border-color:var(--ring);background:var(--ring);color:#fff}
.yesno input:focus-visible + label{outline:3px solid color-mix(in srgb, var(--ring) 45%, transparent);outline-offset:2px}
/* Star */
.stars-rating{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:6px}
.stars-rating input{position:absolute;opacity:0;pointer-events:none}
.stars-rating label{font-size:2.4rem;line-height:1;color:var(--fg-faint);cursor:pointer;transition:color .15s,transform .15s;padding:.1rem}
.stars-rating label:hover{transform:scale(1.1)}
.stars-rating label:hover, .stars-rating label:hover ~ label,
.stars-rating input:checked ~ label, .stars-rating input:checked ~ label ~ label{color:#f59e0b}
.stars-rating input:checked + label{transform:scale(1.06)}
.stars-rating input:focus-visible + label{outline:3px solid color-mix(in srgb, var(--ring) 45%, transparent);outline-offset:2px;border-radius:.35rem}
/* 1-10 */
.rating-scale{display:flex;gap:.5rem;flex-wrap:wrap;direction:ltr;justify-content:flex-start}
.rating-scale input{position:absolute;opacity:0}
.rating-scale span{width:2.75rem;height:2.75rem;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);color:var(--fg);display:flex;align-items:center;justify-content:center;font-weight:700;cursor:pointer;transition:all .15s}
.rating-scale label:hover span{transform:translateY(-2px);border-color:var(--ring)}
.rating-scale label:has(input:checked) span{background:var(--ring);color:#fff;border-color:var(--ring)}
.rating-scale label:has(input:focus-visible) span{outline:3px solid color-mix(in srgb, var(--ring) 45%, transparent);outline-offset:2px}
.survey-hint{font-size:.75rem;color:var(--fg-faint);margin-top:.5rem;display:flex;justify-content:space-between}
/* Progress */
.survey-progress-wrap{margin:1.25rem 0 .5rem}
.survey-progress-text{display:flex;justify-content:space-between;font-size:.8125rem;color:var(--fg-muted);margin-bottom:.4rem}
.survey-progress{height:.5rem;background:var(--surface-3);border-radius:999px;overflow:hidden}
.survey-progress > span{display:block;height:100%;width:0;background:var(--ring);border-radius:999px;transition:width .3s}
/* Final / states */
.survey-box{max-width:560px;margin:auto;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);padding:2.5rem;box-shadow:0 8px 30px rgba(15,23,42,.06);text-align:center}
.survey-box .ic-big{width:4.5rem;height:4.5rem;margin:0 auto 1rem;border-radius:1.25rem;background:var(--success-soft);color:var(--success);display:flex;align-items:center;justify-content:center}
.survey-box .ic-big.ic-warn{background:var(--warning-soft);color:var(--warning)}
.survey-box .ic-big.ic-muted{background:var(--surface-2);color:var(--fg-faint)}
.survey-tag{display:inline-flex;align-items:center;gap:.4rem;font-size:.75rem;font-weight:600;color:var(--ring);background:color-mix(in srgb, var(--ring) 12%, transparent);padding:.3rem .8rem;border-radius:999px}
@media (max-width:767px){
  .survey-q-card{padding:1rem}
  .rating-scale{justify-content:center}
  .yesno label{height:3.25rem}
}
';

$title = $data ? htmlspecialchars($data['title']) : 'نظرسنجی';
render_public_header($title, 'bg-slate-50 text-slate-800 min-h-screen py-8 px-4', $styles);
?>

<div class="max-w-3xl mx-auto w-full">
    <?php if ($done): ?>
        <div class="survey-box">
            <div class="ic-big"><?= icon('check', 'w-9 h-9') ?></div>
            <h1 class="font-bold text-slate-900 leading-snug mb-2">پاسخ شما ثبت شد</h1>
            <p class="microcopy body-sm text-slate-600 leading-relaxed mb-6">سپاس از زمانی که برای تکمیل این نظرسنجی گذاشتید.</p>
            <a href="<?= e(rtrim(get_setting('site_url', ''), '/') ?: './') ?>" class="btn btn-primary btn-lg"><?= icon('home') ?><span>بازگشت به سایت</span></a>
        </div>

    <?php elseif ($already): ?>
        <div class="survey-box">
            <div class="ic-big ic-muted"><?= icon('info', 'w-9 h-9') ?></div>
            <h1 class="font-bold text-slate-900 leading-snug mb-2">قبلاً ثبت شده است</h1>
            <p class="microcopy body-sm text-slate-600 leading-relaxed">این نظرسنجی قبلاً توسط شما تکمیل شده است. سپاس از همراهی شما.</p>
        </div>

    <?php elseif ($err): ?>
        <div class="survey-box">
            <div class="ic-big ic-warn"><?= icon('alert', 'w-9 h-9') ?></div>
            <h1 class="font-bold text-slate-900 leading-snug mb-2">امکان تکمیل فرم وجود ندارد</h1>
            <p class="body-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($err) ?></p>
        </div>

    <?php elseif ($data): ?>
        <!-- فرم تکمیل نظرسنجی (بازطراحی FULLMASTER) -->
        <form class="survey-form" method="post" id="survey-form">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="assignment_id" value="<?= (int) $data['id'] ?>">

            <div class="card p-6 md:p-8 mb-4 text-center">
                <span class="survey-tag"><?= icon('star', 'w-4 h-4') ?> نظرسنجی</span>
                <h1 class="font-bold text-slate-900 leading-snug mt-3 mb-1"><?= htmlspecialchars($data['title']) ?></h1>
                <p class="body-sm text-slate-600">مربوط به: <b class="text-slate-800"><?= htmlspecialchars($data['entity_title'] ?? '') ?></b></p>
                <?php if (!empty($data['description'])): ?>
                    <p class="body-sm text-slate-600 leading-relaxed bg-slate-50 border border-slate-200 rounded-xl p-4 mt-4 text-right"><?= htmlspecialchars($data['description']) ?></p>
                <?php endif; ?>
            </div>

            <div class="survey-progress-wrap" id="survey-progress-wrap">
                <div class="survey-progress-text"><span id="survey-progress-label">۰ از <?= count($questions) ?> پاسخ داده شده</span><span><?= count($questions) ?> سؤال</span></div>
                <div class="survey-progress"><span id="survey-progress-bar"></span></div>
            </div>

            <?php foreach ($questions as $i => $q): $fieldName = 'q_' . $q['id']; ?>
                <div class="survey-q-card" data-q-card>
                    <b><span class="qnum"><?= $i + 1 ?></span><span><?= htmlspecialchars($q['question_text']) ?></span></b>

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
                if (lbl) lbl.textContent = answered + ' از ' + cards.length + ' پاسخ داده شده';
            }
            form.addEventListener('change', function(e){
                if (e.target.name && e.target.name.indexOf('q_') === 0) update();
            });
            // focus question card on interaction
            cards.forEach(function(card){
                card.addEventListener('focusin', function(){ card.classList.add('is-focused'); });
            });
            update();
        });
        </script>
    <?php endif; ?>
</div>

<?php render_public_footer(); ?>
