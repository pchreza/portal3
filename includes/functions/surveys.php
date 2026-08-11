<?php
// surveys.php — توابع کمکی ماژول نظرسنجی

/** جدول مربوط به نوع موجودیت نظرسنجی (project یا product) */
function survey_entity_table(string $entity_type): string
{
    return $entity_type === 'product' ? 'products' : 'projects';
}

/**
 * اطمینان از وجود رکوردهای assignment برای تمام پروژه‌ها/محصولات مشتری
 * نسبت به فرم‌های فعال (اولیه و دوره‌ای)
 */
/** ساخت توکن یکتا برای لینک عمومی تکمیل نظرسنجی */
function survey_assignment_token(): string
{
    return substr(bin2hex(random_bytes(16)), 0, 32);
}

function ensure_survey_assignments(int $customer_id): void
{
    global $pdo;
    if (!$pdo || !$customer_id) {
        return;
    }

    // پر کردن توکن رکوردهای قدیمی (در صورت خالی بودن) — هر رکورد توکن یکتای خودش را می‌گیرد
    $stale = $pdo->query("SELECT id FROM survey_assignments WHERE token IS NULL OR token = ''")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($stale)) {
        $upd = $pdo->prepare("UPDATE survey_assignments SET token = ? WHERE id = ?");
        foreach ($stale as $rid) {
            $upd->execute([survey_assignment_token(), (int) $rid]);
        }
    }

    $items = [
        ['project', 'projects'],
        ['product', 'products'],
    ];

    foreach ($items as [$type, $table]) {
        $stmt = $pdo->prepare("SELECT id, created_at" . ($type === 'product' ? ', purchase_date' : '') . " FROM {$table} WHERE customer_id = ?");
        $stmt->execute([$customer_id]);

        // کوئری فرم‌های فعال فقط یک‌بار برای هر نوع موجودیت (نه داخل حلقه آیتم‌ها) — کاهش N+1
        $forms = $pdo->prepare("SELECT * FROM surveys WHERE is_active = 1 AND target_entity = ?");
        $forms->execute([$type]);
        $formsList = $forms->fetchAll();

        // کش پاسخ‌های فرم‌های اولیه (برای محاسبه available_at فرم‌های دوره‌ای) — یک کوئری به‌جای N کوئری
        $baseSurveyIds = array_values(array_filter(array_map(static fn($f) => (int) ($f['parent_survey_id'] ?? 0), $formsList)));
        $baseAnswers = [];
        if (!empty($baseSurveyIds)) {
            $ph = implode(',', array_fill(0, count($baseSurveyIds), '?'));
            $ba = $pdo->prepare("SELECT survey_id, entity_type, entity_id, created_at FROM survey_responses WHERE customer_id = ? AND survey_id IN ($ph)");
            $ba->execute(array_merge([$customer_id], $baseSurveyIds));
            foreach ($ba->fetchAll() as $br) {
                $baseAnswers[$br['survey_id'] . '|' . $br['entity_type'] . '|' . $br['entity_id']] = $br['created_at'];
            }
        }

        foreach ($stmt->fetchAll() as $item) {
            foreach ($formsList as $form) {
                $available = null;

                if ((int) $form['is_periodic'] === 1) {
                    $base = $form['parent_survey_id'] ? (int) $form['parent_survey_id'] : 0;
                    if (!$base) {
                        continue;
                    }

                    $completedAt = $baseAnswers[$base . '|' . $type . '|' . $item['id']] ?? null;

                    if (!$completedAt) {
                        continue;
                    }

                    // چندمین دوره است؟ (تعداد پاسخ‌های قبلی به همین فرم دوره‌ای)
                    $kc = $pdo->prepare("SELECT COUNT(*) FROM survey_responses WHERE survey_id = ? AND customer_id = ? AND entity_type = ? AND entity_id = ?");
                    $kc->execute([$form['id'], $customer_id, $type, $item['id']]);
                    $k = (int) $kc->fetchColumn();
                    $delay = max(0, (int) $form['delay_days']);

                    // فعال‌سازی دوره بعدی: parent + delay × (تعداد دوره‌های تکمیل‌شده + 1)
                    $available = date('Y-m-d H:i:s', strtotime($completedAt . ' +' . ($delay * ($k + 1)) . ' days'));
                } else {
                    $available = date('Y-m-d H:i:s');
                }

                if ((int) $form['is_periodic'] === 1) {
                    // دوره‌ای: فقط available_at دوره‌ی بعد جلو می‌رود؛ توکن ثابت می‌ماند تا لینک‌های پیامک نشکنند
                    $ins = $pdo->prepare(
                        "INSERT INTO survey_assignments (survey_id, customer_id, entity_type, entity_id, available_at, token)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE available_at = VALUES(available_at)"
                    );
                } else {
                    // غیر دوره‌ای: available_at فقط بار اول ثبت می‌شود — جلوی پاسخ تکراری بعد از تکمیل
                    $ins = $pdo->prepare(
                        "INSERT IGNORE INTO survey_assignments (survey_id, customer_id, entity_type, entity_id, available_at, token)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                }
                $ins->execute([$form['id'], $customer_id, $type, $item['id'], $available, survey_assignment_token()]);
            }
        }
    }
}
