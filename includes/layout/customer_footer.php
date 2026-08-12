<?php
// پاصفحه مشترک صفحات پنل مشتری — بستن تگ‌های بازشده در customer_header.php
?>
        </main>
    </div>

<?= datepicker_assets_js() ?>
<?php if (!empty($_SESSION['error_report_flash'])): ?>
<script nonce="<?= e(portal_csp_nonce()) ?>">window.addEventListener('DOMContentLoaded',function(){ if(window.portalToast) window.portalToast('<?= addslashes($_SESSION['error_report_flash']) ?>','info'); });</script>
<?php unset($_SESSION['error_report_flash']); endif; ?>
<?php if (!empty($_SESSION['gamification_award_flash']['message'])): $gamificationAwardMessage = (string) $_SESSION['gamification_award_flash']['message']; ?>
<script nonce="<?= e(portal_csp_nonce()) ?>">window.addEventListener('DOMContentLoaded',function(){ if(window.portalToast) window.portalToast(<?= json_encode($gamificationAwardMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,'success'); });</script>
<?php unset($_SESSION['gamification_award_flash']); endif; ?>
<?= error_report_widget() ?>
<?= portal_toast_region() ?>
<?= portal_confirm_modal() ?>
<?= portal_darkmode_script() ?>
<?= portal_confirm_script() ?>
<?= portal_validation_script() ?>
<?= portal_toast_script() ?>
</body>
</html>
