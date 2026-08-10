<?php
// customer/invoices.php - Customer Invoices
require_once 'auth.php';
if (!is_module_enabled('invoices')) { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];

// Fetch customer invoices
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$invoices = $stmt->fetchAll();
?>
<?php render_customer_header(
    'فاکتورها و صورتحساب‌ها',
    'p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
); ?>


            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">لیست صورتحساب‌های صادر شده (<?php echo count($invoices); ?>)</h3>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="table table-card-mobile">
                        <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                            <tr>
                                <th class="p-4">شماره / عنوان فاکتور</th>
                                <th class="p-4">مبلغ</th>
                                <th class="p-4">تاریخ سررسید</th>
                                <th class="p-4">وضعیت</th>
                                <th class="p-4 text-center">تاریخ صدور</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-slate-400"><?php echo empty_state('هیچ فاکتوری برای شما صادر نشده است.', '', 'info'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4">
                                            <div class="font-bold text-slate-900"><?php echo htmlspecialchars($inv['invoice_number']); ?></div>
                                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($inv['title']); ?></div>
                                        </td>
                                        <td class="p-4 font-semibold text-slate-900"><?php echo htmlspecialchars($inv['amount']); ?> تومان</td>
                                        <td class="p-4 text-xs text-slate-600"><?php echo htmlspecialchars($inv['due_date'] ?: '-'); ?></td>
                                        <td class="p-4">
                                            <?php 
                                                $st = $inv['status'];
                                                if ($st === 'paid') echo '<span class="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-full font-medium">پرداخت شده</span>';
                                                elseif ($st === 'unpaid') echo '<span class="bg-amber-50 text-amber-700 text-xs px-2.5 py-1 rounded-full font-medium">پرداخت نشده</span>';
                                                else echo '<span class="bg-slate-100 text-slate-600 text-xs px-2.5 py-1 rounded-full font-medium">لغو شده</span>';
                                            ?>
                                        </td>
                                        <td class="p-4 text-center text-xs text-slate-500"><?php echo htmlspecialchars($inv['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php render_customer_footer(); ?>