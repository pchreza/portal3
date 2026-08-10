<?php
// customer/products.php - Customer Products
require_once 'auth.php';
if (!is_module_enabled('products')) { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$customer = $stmt->fetch();
if (!$customer) { session_unset(); session_destroy(); header('Location: ../index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM products WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$products = $stmt->fetchAll();

$survey_map = [];
$surveys_enabled = is_module_enabled('surveys');
if ($surveys_enabled) {
    ensure_survey_assignments($user_id);
    $ss = $pdo->prepare("SELECT sa.entity_id, sa.id assignment_id, s.title, sa.available_at, (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id=sa.survey_id AND r.customer_id=sa.customer_id AND r.entity_type='product' AND r.entity_id=sa.entity_id) answered FROM survey_assignments sa JOIN surveys s ON s.id=sa.survey_id WHERE sa.customer_id=? AND sa.entity_type='product' AND s.is_active=1");
    $ss->execute([$user_id]); foreach($ss->fetchAll() as $sv) $survey_map[$sv['entity_id']][]=$sv;
}

// استایل کارت انتخابی توسط ادمین
$card_style = get_setting('product_card_style', 'vertical');
if (!array_key_exists($card_style, entity_card_styles())) { $card_style = 'vertical'; }

$full_name = trim($customer['first_name'] . ' ' . $customer['last_name']) !== '' ? $customer['first_name'] . ' ' . $customer['last_name'] : $customer['username'];
?>
<?php render_customer_header(
    'محصولات و سرویس‌های من',
    'p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
    '',
    $full_name
); ?>

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">لیست محصولات شما (<?php echo count($products); ?>)</h3>
            </div>

            <?php if (empty($products)): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
                    هیچ محصولی تاکنون برای شما ثبت نشده است.
                </div>
            <?php else: ?>
                <div class="<?= entity_card_grid_class($card_style) ?>">
                    <?php foreach ($products as $prod): ?>
                        <?php echo render_entity_card('product', $prod, $card_style, $survey_map[$prod['id']] ?? [], $surveys_enabled); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php render_customer_footer();
