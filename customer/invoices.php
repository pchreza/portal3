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
    'portal-page-main portal-customer-invoices-page p-8 max-w-7xl w-full mx-auto space-y-6',
    '',
    '',
    '',
    'فاکتورها'
); ?>


            <div class="portal-list-toolbar flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">فاکتورهای صادرشده (<?php echo count($invoices); ?>)</h3>
            </div>

            <div class="portal-list-card card overflow-hidden">
                <div class="table-scroll overflow-x-auto">
                    <table class="table table-card-mobile">
                        <thead class="bg-slate-50 text-slate-500 text-xs font-semibold">
                            <tr>
                                <th class="p-4 min-w-[13rem]">شماره / عنوان فاکتور</th>
                                <th class="p-4">مبلغ</th>
                                <th class="p-4">تاریخ سررسید</th>
                                <th class="p-4">وضعیت</th>
                                <th class="p-4 text-center min-w-[11rem]">تاریخ صدور</th>
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
                                        <td data-label="شماره و عنوان" class="p-4 min-w-[13rem]">
                                            <div class="flex-1 min-w-0 text-end">
                                                <div class="font-bold text-slate-900 value-ltr whitespace-nowrap" dir="ltr" title="<?= htmlspecialchars($inv['invoice_number']) ?>"><?php echo htmlspecialchars($inv['invoice_number']); ?></div>
                                                <div class="text-xs text-slate-500"><bdi dir="auto"><?php echo htmlspecialchars($inv['title']); ?></bdi></div>
                                            </div>
                                        </td>
                                        <td data-label="مبلغ" class="p-4 font-semibold text-slate-900"><span class="value-ltr" dir="ltr"><?php echo htmlspecialchars(number_format((float) $inv['amount'], 0, '.', ',')); ?></span> <span dir="rtl">تومان</span></td>
                                        <td data-label="تاریخ سررسید" class="p-4 text-xs text-slate-600 value-ltr whitespace-nowrap" dir="ltr"><?php echo htmlspecialchars($inv['due_date'] ?: '-'); ?></td>
                                        <td data-label="وضعیت" class="p-4">
                                            <?php 
                                                $st = $inv['status'];
                                                if ($st === 'paid') echo '<span class="badge badge-success">پرداخت شده</span>';
                                                elseif ($st === 'unpaid') echo '<span class="badge badge-warning">پرداخت نشده</span>';
                                                else echo '<span class="badge badge-muted">لغو شده</span>';
                                            ?>
                                        </td>
                                        <td data-label="تاریخ صدور" class="p-4 text-center text-xs text-slate-500 value-ltr whitespace-nowrap" dir="ltr"><?php echo htmlspecialchars($inv['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php render_customer_footer(); ?>