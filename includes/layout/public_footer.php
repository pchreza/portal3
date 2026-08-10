<?php
// پاصفحه مشترک صفحات عمومی — بستن تگ‌های بازشده در public_header.php
?>
<?= datepicker_assets_js() ?>
<?php if (!empty($_SESSION['error_report_flash'])): ?>
<script>window.addEventListener('DOMContentLoaded',function(){ if(window.portalToast) window.portalToast('<?= addslashes($_SESSION['error_report_flash']) ?>','info'); });</script>
<?php unset($_SESSION['error_report_flash']); endif; ?>
<?= error_report_widget() ?>
<?= portal_toast_region() ?>
<?= portal_confirm_modal() ?>
<?= portal_confirm_script() ?>
<?= portal_validation_script() ?>
<?= portal_toast_script() ?>
<?= portal_darkmode_script() ?>
</body>
</html>
