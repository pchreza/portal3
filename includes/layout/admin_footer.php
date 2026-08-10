<?php
// پاصفحه مشترک صفحات پنل مدیریت — بستن تگ‌های بازشده در admin_header.php
?>
        </main>
    </div>

<?= datepicker_assets_js() ?>
<?php if (!empty($_SESSION['error_report_flash'])): ?>
<script>window.addEventListener('DOMContentLoaded',function(){ if(window.portalToast) window.portalToast('<?= addslashes($_SESSION['error_report_flash']) ?>','info'); });</script>
<?php unset($_SESSION['error_report_flash']); endif; ?>
<?= error_report_widget() ?>
<?= portal_toast_region() ?>
<?= portal_confirm_modal() ?>
<?= portal_darkmode_script() ?>
<?= portal_confirm_script() ?>
<?= portal_validation_script() ?>
<?= portal_toast_script() ?>
</body>
</html>
