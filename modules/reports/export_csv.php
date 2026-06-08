<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModule('reports');

$report   = $_GET['report']    ?? 'orders';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$status   = $_GET['status']    ?? '';
$groupBy  = $_GET['group_by']  ?? 'month';
$category = $_GET['category']  ?? '';
$custType = $_GET['customer_type'] ?? '';

$filename = "ecosalva_{$report}_" . date('Ymd_His') . '.csv';

Auth::logActivity(Auth::id(), 'export_csv', 'reports', "Reporte: $report | $dateFrom → $dateTo");

// ── Obtener datos según tipo de reporte ───────────────────
$headers = [];
$rows    = [];

switch ($report) {

    case 'orders':
        $headers = [
            'N° Pedido','Cliente','Email','Estado','Estado Pago','Método Pago',
            'Tipo Entrega','Subtotal','Ahorro','Total','Fecha','Cancelado el'
        ];
        $where = "WHERE DATE(o.created_at) BETWEEN :df AND :dt";
        $params = [':df' => $dateFrom, ':dt' => $dateTo];
        if ($status) { $where .= " AND o.status = :status"; $params[':status'] = $status; }

        $data = DB::query(
            "SELECT o.order_number, u.name, u.email, o.status, o.payment_status, o.payment_method,
                    o.delivery_type, o.subtotal, o.total_saving, o.total, o.created_at, o.cancelled_at
               FROM orders o LEFT JOIN users u ON u.id = o.user_id $where ORDER BY o.created_at DESC",
            $params
        );

        foreach ($data as $r) {
            $rows[] = [
                $r['order_number'], $r['name'], $r['email'],
                $r['status'], $r['payment_status'], $r['payment_method'],
                $r['delivery_type'],
                number_format((float)$r['subtotal'], 2),
                number_format((float)$r['total_saving'], 2),
                number_format((float)$r['total'], 2),
                formatDate($r['created_at'], 'd/m/Y H:i'),
                $r['cancelled_at'] ? formatDate($r['cancelled_at'], 'd/m/Y H:i') : '',
            ];
        }
        break;

    case 'sales':
        $dateFormat = match($groupBy) {
            'day'   => "TO_CHAR(created_at, 'YYYY-MM-DD')",
            'week'  => "TO_CHAR(created_at, 'IYYY-IW')",
            default => "TO_CHAR(created_at, 'YYYY-MM')",
        };
        $groupLabel = match($groupBy) {
            'day'   => 'Fecha',
            'week'  => 'Semana',
            default => 'Mes',
        };
        $headers = [$groupLabel, 'Pedidos', 'Ingresos (S/)', 'Ticket Promedio (S/)', 'Ahorro Total (S/)'];

        $data = DB::query(
            "SELECT $dateFormat AS periodo,
                    COUNT(*) AS orders,
                    COALESCE(SUM(total),0) AS revenue,
                    COALESCE(AVG(total),0) AS avg_ticket,
                    COALESCE(SUM(total_saving),0) AS saving
               FROM orders
              WHERE DATE(created_at) BETWEEN :df AND :dt
                AND status NOT IN ('CANCELLED','REFUNDED')
              GROUP BY periodo ORDER BY periodo",
            [':df' => $dateFrom, ':dt' => $dateTo]
        );

        foreach ($data as $r) {
            $rows[] = [
                $r['periodo'],
                $r['orders'],
                number_format((float)$r['revenue'],   2),
                number_format((float)$r['avg_ticket'], 2),
                number_format((float)$r['saving'],    2),
            ];
        }
        break;

    case 'products':
        $headers = [
            'Producto','Categoría','Tienda','Unidades Vendidas',
            'Ingresos (S/)','Precio Prom. (S/)','Descuento Otorgado (S/)'
        ];
        $where = "WHERE DATE(o.created_at) BETWEEN :df AND :dt AND o.status NOT IN ('CANCELLED','REFUNDED')";
        $params = [':df' => $dateFrom, ':dt' => $dateTo];
        if ($category) { $where .= " AND oi.product_category = :cat"; $params[':cat'] = $category; }

        $data = DB::query(
            "SELECT oi.product_name, oi.product_category, oi.store_name,
                    SUM(oi.quantity) AS units_sold,
                    SUM(oi.line_total) AS revenue,
                    AVG(oi.unit_price_discount) AS avg_price,
                    SUM(oi.quantity*(oi.unit_price_original-oi.unit_price_discount)) AS discount_given
               FROM order_items oi JOIN orders o ON o.id = oi.order_id $where
              GROUP BY oi.product_id, oi.product_name, oi.product_category, oi.store_name
              ORDER BY units_sold DESC",
            $params
        );

        foreach ($data as $r) {
            $rows[] = [
                $r['product_name'], categoryLabel($r['product_category']), $r['store_name'],
                $r['units_sold'],
                number_format((float)$r['revenue'],        2),
                number_format((float)$r['avg_price'],      2),
                number_format((float)$r['discount_given'], 2),
            ];
        }
        break;

    case 'customers':
        $headers = [
            'Nombre','Email','Teléfono','Estado','Pedidos','Gasto Total (S/)',
            'Ticket Promedio (S/)','Primer Pedido','Último Pedido','Registrado'
        ];
        $having = '';
        $whereExtra = '';
        $params = [':df' => $dateFrom, ':dt' => $dateTo];

        if ($custType === 'new') {
            $whereExtra = " AND DATE(u.created_at) BETWEEN :df AND :dt";
        } elseif ($custType === 'recurring') {
            $having = 'HAVING COUNT(DISTINCT o.id) > 1';
        } elseif ($custType === 'inactive') {
            $having = 'HAVING MAX(o.created_at) < NOW() - INTERVAL \'90 days\'';
        }

        $data = DB::query(
            "SELECT u.name, u.email, u.phone, u.status,
                    COUNT(DISTINCT o.id)    AS total_orders,
                    COALESCE(SUM(o.total),0) AS total_spent,
                    COALESCE(AVG(o.total),0) AS avg_ticket,
                    MIN(o.created_at)        AS first_order,
                    MAX(o.created_at)        AS last_order,
                    u.created_at             AS registered_at
               FROM users u
               LEFT JOIN orders o ON o.user_id = u.id
                     AND o.status NOT IN ('CANCELLED','REFUNDED')
              WHERE u.role = 'CUSTOMER' $whereExtra
              GROUP BY u.id, u.name, u.email, u.phone, u.status, u.created_at
              $having
              ORDER BY total_spent DESC",
            $params
        );

        foreach ($data as $r) {
            $rows[] = [
                $r['name'], $r['email'], $r['phone'] ?? '',
                $r['status'],
                $r['total_orders'],
                number_format((float)$r['total_spent'], 2),
                number_format((float)$r['avg_ticket'],  2),
                $r['first_order'] ? formatDate($r['first_order'], 'd/m/Y') : '',
                $r['last_order']  ? formatDate($r['last_order'],  'd/m/Y') : '',
                formatDate($r['registered_at'], 'd/m/Y'),
            ];
        }
        break;
}

// ── Salida CSV ────────────────────────────────────────────
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// BOM para que Excel abra correctamente con tildes
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Encabezado del reporte
fputcsv($out, [APP_NAME . ' — Reporte de ' . ucfirst($report)], ';');
fputcsv($out, ['Período: ' . $dateFrom . ' al ' . $dateTo], ';');
fputcsv($out, ['Generado: ' . date('d/m/Y H:i')], ';');
fputcsv($out, [], ';');

fputcsv($out, $headers, ';');
foreach ($rows as $row) {
    fputcsv($out, $row, ';');
}

fclose($out);
exit;
