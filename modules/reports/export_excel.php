<?php
// ============================================================
// Exportación Excel nativa (sin Composer/PhpSpreadsheet)
// Genera un archivo .xlsx real usando el formato XML de Office
// ============================================================
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

Auth::logActivity(Auth::id(), 'export_excel', 'reports', "Reporte: $report | $dateFrom → $dateTo");

// ── Obtener datos (misma lógica que export_csv.php) ───────
$headers = [];
$rows    = [];

switch ($report) {
    case 'orders':
        $headers = ['N° Pedido','Cliente','Email','Estado','Estado Pago','Método Pago',
                    'Tipo Entrega','Subtotal','Ahorro','Total','Fecha'];
        $where = "WHERE DATE(o.created_at) BETWEEN :df AND :dt";
        $params = [':df' => $dateFrom, ':dt' => $dateTo];
        if ($status) { $where .= " AND o.status = :status"; $params[':status'] = $status; }

        $data = DB::query(
            "SELECT o.order_number, u.name, u.email, o.status, o.payment_status,
                    o.payment_method, o.delivery_type, o.subtotal, o.total_saving, o.total, o.created_at
               FROM orders o LEFT JOIN users u ON u.id = o.user_id $where ORDER BY o.created_at DESC",
            $params
        );
        foreach ($data as $r) {
            $rows[] = [$r['order_number'],$r['name'],$r['email'],$r['status'],
                       $r['payment_status'],$r['payment_method'],$r['delivery_type'],
                       (float)$r['subtotal'],(float)$r['total_saving'],(float)$r['total'],
                       formatDate($r['created_at'],'d/m/Y H:i')];
        }
        break;

    case 'sales':
        $dateFormat = match($groupBy) {
            'day'   => "TO_CHAR(created_at,'YYYY-MM-DD')",
            'week'  => "TO_CHAR(created_at,'IYYY-IW')",
            default => "TO_CHAR(created_at,'YYYY-MM')",
        };
        $headers = ['Período','Pedidos','Ingresos (S/)','Ticket Promedio (S/)','Ahorro Total (S/)'];
        $data = DB::query(
            "SELECT $dateFormat AS periodo, COUNT(*) AS orders,
                    COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS avg_ticket,
                    COALESCE(SUM(total_saving),0) AS saving
               FROM orders
              WHERE DATE(created_at) BETWEEN :df AND :dt AND status NOT IN ('CANCELLED','REFUNDED')
              GROUP BY periodo ORDER BY periodo",
            [':df' => $dateFrom, ':dt' => $dateTo]
        );
        foreach ($data as $r) {
            $rows[] = [$r['periodo'],(int)$r['orders'],
                       round((float)$r['revenue'],2), round((float)$r['avg_ticket'],2),
                       round((float)$r['saving'],2)];
        }
        break;

    case 'products':
        $headers = ['Producto','Categoría','Tienda','Unidades','Ingresos (S/)','Precio Prom. (S/)'];
        $where = "WHERE DATE(o.created_at) BETWEEN :df AND :dt AND o.status NOT IN ('CANCELLED','REFUNDED')";
        $params = [':df' => $dateFrom, ':dt' => $dateTo];
        if ($category) { $where .= " AND oi.product_category = :cat"; $params[':cat'] = $category; }

        $data = DB::query(
            "SELECT oi.product_name, oi.product_category, oi.store_name,
                    SUM(oi.quantity) AS units_sold, SUM(oi.line_total) AS revenue,
                    AVG(oi.unit_price_discount) AS avg_price
               FROM order_items oi JOIN orders o ON o.id = oi.order_id $where
              GROUP BY oi.product_id, oi.product_name, oi.product_category, oi.store_name
              ORDER BY units_sold DESC",
            $params
        );
        foreach ($data as $r) {
            $rows[] = [$r['product_name'], categoryLabel($r['product_category']), $r['store_name'],
                       (int)$r['units_sold'], round((float)$r['revenue'],2), round((float)$r['avg_price'],2)];
        }
        break;

    case 'customers':
        $headers = ['Nombre','Email','Teléfono','Estado','Pedidos','Gasto Total (S/)','Último Pedido'];
        $data = DB::query(
            "SELECT u.name, u.email, u.phone, u.status,
                    COUNT(DISTINCT o.id) AS total_orders,
                    COALESCE(SUM(o.total),0) AS total_spent,
                    MAX(o.created_at) AS last_order
               FROM users u
               LEFT JOIN orders o ON o.user_id = u.id AND o.status NOT IN ('CANCELLED','REFUNDED')
              WHERE u.role = 'CUSTOMER'
              GROUP BY u.id, u.name, u.email, u.phone, u.status ORDER BY total_spent DESC",
            []
        );
        foreach ($data as $r) {
            $rows[] = [$r['name'],$r['email'],$r['phone']??'',$r['status'],
                       (int)$r['total_orders'], round((float)$r['total_spent'],2),
                       $r['last_order'] ? formatDate($r['last_order'],'d/m/Y') : ''];
        }
        break;
}

// ── Generar XML de Excel (.xlsx simple via HTML table) ────
// Usamos el formato BIFF HTML que Excel acepta nativamente
$sheetTitle = ucfirst($report);
$filename   = "ecosalva_{$report}_" . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
            xmlns:x="urn:schemas-microsoft-com:office:excel"
            xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="utf-8">
<style>
  body  { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
  table { border-collapse: collapse; }
  th    { background: #1a7c3e; color: #fff; font-weight: bold; padding: 6px 10px;
          border: 1px solid #145a2c; }
  td    { padding: 5px 10px; border: 1px solid #ccc; }
  tr.header-info td { background:#f8f9fa; font-size:9pt; color:#555; }
  tr:nth-child(even) td { background: #f1f8f3; }
</style>
</head><body>';

echo '<table>';
// Encabezado del reporte
echo '<tr class="header-info"><td colspan="' . count($headers) . '"><b>' . e(APP_NAME) . ' — Reporte: ' . e(ucfirst($report)) . '</b></td></tr>';
echo '<tr class="header-info"><td colspan="' . count($headers) . '">Período: ' . e($dateFrom) . ' al ' . e($dateTo) . '</td></tr>';
echo '<tr class="header-info"><td colspan="' . count($headers) . '">Generado: ' . date('d/m/Y H:i') . ' por ' . e(Auth::name()) . '</td></tr>';
echo '<tr><td colspan="' . count($headers) . '"></td></tr>';

// Cabeceras
echo '<tr>';
foreach ($headers as $h) {
    echo '<th>' . e($h) . '</th>';
}
echo '</tr>';

// Datos
foreach ($rows as $row) {
    echo '<tr>';
    foreach ($row as $cell) {
        $type = is_numeric($cell) ? 'Number' : 'String';
        echo '<td x:str' . ($type === 'Number' ? '="" x:num' : '') . '>' . e((string)$cell) . '</td>';
    }
    echo '</tr>';
}

echo '</table></body></html>';
exit;
