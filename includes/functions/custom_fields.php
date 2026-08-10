<?php
// custom_fields.php — فیلدهای سفارشی پویا

/**
 * رندر فیلدهای سفارشی برای یک موجودیت (customer/product/project)
 *
 * @param string $entity_type   نوع موجودیت (customer, product, project)
 * @param int    $entity_id     شناسه رکورد (برای مقدار فعلی فیلدها)
 * @param bool   $customer_view آیا فقط فیلدهایی نمایش داده شوند که در پنل مشتری فعال‌اند
 */
function render_custom_fields_inputs(string $entity_type, int $entity_id = 0, bool $customer_view = false): string
{
    global $pdo;
    if (!$pdo || !is_module_enabled('custom_fields')) {
        return '';
    }

    $sql = "SELECT * FROM custom_fields WHERE target_entity = ?"
        . ($customer_view ? " AND show_in_customer_panel = 1" : '')
        . " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entity_type]);
    $fields = $stmt->fetchAll();

    if (!$fields) {
        return '';
    }

    $html = '<div class="space-y-4"><h4 class="font-bold text-slate-800">فیلدهای تکمیلی</h4>';

    foreach ($fields as $field) {
        $value = '';
        if ($entity_id) {
            $v = $pdo->prepare("SELECT field_value FROM custom_field_values WHERE field_id = ? AND entity_id = ?");
            $v->execute([$field['id'], $entity_id]);
            $value = (string) ($v->fetchColumn() ?: '');
        }

        $name     = 'custom_field_' . (int) $field['id'];
        $required = !empty($field['is_required']) ? ' required' : '';
        $label    = e($field['field_label'] ?: $field['field_name']);
        $safe     = e($value);

        if ($field['field_type'] === 'textarea') {
            $input = "<textarea name=\"{$name}\" class=\"w-full rounded-lg border border-slate-300 px-3 py-2\"{$required}>{$safe}</textarea>";
        } else {
            $input = "<input type=\"" . e($field['field_type']) . "\" name=\"{$name}\" value=\"{$safe}\" class=\"w-full rounded-lg border border-slate-300 px-3 py-2\"{$required}>";
        }

        $html .= '<div><label class="block text-sm font-medium mb-1">' . $label . ($required ? ' *' : '') . '</label>' . $input . '</div>';
    }

    return $html . '</div>';
}

/**
 * ذخیره مقادیر فیلدهای سفارشی ارسال‌شده از فرم
 */
function save_custom_fields_values(string $entity_type, int $entity_id): void
{
    global $pdo;
    if (!$pdo || !$entity_id || !is_module_enabled('custom_fields')) {
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM custom_fields WHERE target_entity = ?");
    $stmt->execute([$entity_type]);

    foreach ($stmt->fetchAll() as $field) {
        $key = 'custom_field_' . (int) $field['id'];
        if (!array_key_exists($key, $_POST)) {
            continue;
        }

        $check = $pdo->prepare("SELECT id FROM custom_field_values WHERE field_id = ? AND entity_id = ?");
        $check->execute([$field['id'], $entity_id]);

        if ($check->fetchColumn()) {
            $up = $pdo->prepare("UPDATE custom_field_values SET field_value = ? WHERE field_id = ? AND entity_id = ?");
            $up->execute([trim((string) $_POST[$key]), $field['id'], $entity_id]);
        } else {
            $in = $pdo->prepare("INSERT INTO custom_field_values (field_id, entity_id, field_value) VALUES (?, ?, ?)");
            $in->execute([$field['id'], $entity_id, trim((string) $_POST[$key])]);
        }
    }
}
