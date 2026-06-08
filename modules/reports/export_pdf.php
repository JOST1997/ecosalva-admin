<?php
// ============================================================
// Exportación PDF (HTML nativo que el navegador imprime)
// No requiere librerías externas — usa window.print()
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

Auth::logActivity(Auth::id(), 'export_pdf', 'reports', "Reporte: $report | $dateFrom → $dateTo");

// ── Obtener datos ─────────────────────────────────────────
$headers = [];
$rows    = [];
$totalRow = null;

switch ($report) {
    case 'orders':
        $headers = ['N° Pedido','Cliente','Estado','Método Pago','Entrega','Subtotal','Ahorro','Total','Fecha'];
        $where = "WHERE DATE(o.created_at) BETWEEN :df AND :dt";
        $params = [':df' => $dateFrom, ':dt' => $dateTo];
        if ($status) { $where .= " AND o.status = :status"; $params[':status'] = $status; }

        $data = DB::query(
            "SELECT o.order_number, u.name, o.status, o.payment_method,
                    o.delivery_type, o.subtotal, o.total_saving, o.total, o.created_at
               FROM orders o LEFT JOIN users u ON u.id = o.user_id $where ORDER BY o.created_at DESC",
            $params
        );

        $sumTotal = 0;
        foreach ($data as $r) {
            $sumTotal += (float)$r['total'];
            $rows[] = [$r['order_number'],$r['name'],
                       strip_tags(orderStatusBadge($r['status'])),
                       paymentMethodLabel($r['payment_method']),
                       deliveryTypeLabel($r['delivery_type']),
                       'S/ '.number_format((float)$r['subtotal'],2),
                       'S/ '.number_format((float)$r['total_saving'],2),
                       'S/ '.number_format((float)$r['total'],2),
                       formatDate($r['created_at'],'d/m/Y')];
        }
        $totalRow = ['','','','','','','<b>TOTAL</b>','<b>S/ '.number_format($sumTotal,2).'</b>',''];
        break;

    case 'sales':
        $dateFormat = match($groupBy) {
            'day'   => "TO_CHAR(created_at,'YYYY-MM-DD')",
            'week'  => "TO_CHAR(created_at,'IYYY-IW')",
            default => "TO_CHAR(created_at,'YYYY-MM')",
        };
        $headers = ['Período','Pedidos','Ingresos (S/)','Ticket Prom. (S/)','Ahorro (S/)'];
        $data = DB::query(
            "SELECT $dateFormat AS periodo, COUNT(*) AS orders,
                    COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS avg_ticket,
                    COALESCE(SUM(total_saving),0) AS saving
               FROM orders
              WHERE DATE(created_at) BETWEEN :df AND :dt AND status NOT IN ('CANCELLED','REFUNDED')
              GROUP BY periodo ORDER BY periodo",
            [':df' => $dateFrom, ':dt' => $dateTo]
        );
        $sumRev = 0;
        foreach ($data as $r) {
            $sumRev += (float)$r['revenue'];
            $rows[] = [$r['periodo'],(int)$r['orders'],
                       'S/ '.number_format((float)$r['revenue'],2),
                       'S/ '.number_format((float)$r['avg_ticket'],2),
                       'S/ '.number_format((float)$r['saving'],2)];
        }
        $totalRow = ['<b>TOTAL</b>','','<b>S/ '.number_format($sumRev,2).'</b>','',''];
        break;

    case 'products':
        $headers = ['Producto','Categoría','Tienda','Unidades','Ingresos (S/)'];
        $where = "WHERE DATE(o.created_at) BETWEEN :df AND :dt AND o.status NOT IN ('CANCELLED','REFUNDED')";
        $params = [':df' => $dateFrom, ':dt' => $dateTo];
        if ($category) { $where .= " AND oi.product_category = :cat"; $params[':cat'] = $category; }

        $data = DB::query(
            "SELECT oi.product_name, oi.product_category, oi.store_name,
                    SUM(oi.quantity) AS units_sold, SUM(oi.line_total) AS revenue
               FROM order_items oi JOIN orders o ON o.id = oi.order_id $where
              GROUP BY oi.product_id, oi.product_name, oi.product_category, oi.store_name
              ORDER BY units_sold DESC",
            $params
        );
        foreach ($data as $r) {
            $rows[] = [$r['product_name'],categoryLabel($r['product_category']),$r['store_name'],
                       (int)$r['units_sold'],'S/ '.number_format((float)$r['revenue'],2)];
        }
        break;

    case 'customers':
        $headers = ['Cliente','Email','Pedidos','Gasto Total (S/)','Último Pedido'];
        $data = DB::query(
            "SELECT u.name, u.email, COUNT(DISTINCT o.id) AS total_orders,
                    COALESCE(SUM(o.total),0) AS total_spent, MAX(o.created_at) AS last_order
               FROM users u
               LEFT JOIN orders o ON o.user_id = u.id AND o.status NOT IN ('CANCELLED','REFUNDED')
              WHERE u.role = 'CUSTOMER'
              GROUP BY u.id, u.name, u.email ORDER BY total_spent DESC LIMIT 200",
            []
        );
        foreach ($data as $r) {
            $rows[] = [$r['name'],$r['email'],(int)$r['total_orders'],
                       'S/ '.number_format((float)$r['total_spent'],2),
                       $r['last_order'] ? formatDate($r['last_order'],'d/m/Y') : '—'];
        }
        break;
}

$reportLabels = ['orders'=>'Pedidos','sales'=>'Ventas','products'=>'Productos','customers'=>'Clientes'];
$reportLabel  = $reportLabels[$report] ?? ucfirst($report);

// ── Renderizar HTML para impresión PDF ────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte <?= e($reportLabel) ?> — <?= APP_NAME ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; margin: 0; padding: 20px; color: #333; }
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 3px solid #1a7c3e; padding-bottom: 10px; }
        .report-header h1 { font-size: 16pt; color: #1a7c3e; margin: 0; }
        .report-header .meta { text-align: right; font-size: 9pt; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1a7c3e; color: #fff; padding: 6px 8px; text-align: left; font-size: 9pt; }
        td { padding: 5px 8px; border-bottom: 1px solid #e0e0e0; font-size: 9pt; }
        tr:nth-child(even) td { background: #f1f8f3; }
        tr.total-row td { background: #e8f5e9; font-weight: bold; border-top: 2px solid #1a7c3e; }
        .footer { margin-top: 20px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
        .print-btn { display: flex; gap: 10px; margin-bottom: 15px; }
        @media print {
            .print-btn { display: none; }
            body { padding: 10px; }
        }
    </style>
</head>
<body>

<div class="print-btn">
    <button onclick="window.print()" style="background:#1a7c3e;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:10pt;">
        🖨️ Imprimir / Guardar PDF
    </button>
    <button onclick="window.close()" style="background:#6c757d;color:#fff;border:none;padding:8px 18px;border-radius:6px;cursor:pointer;font-size:10pt;">
        ✕ Cerrar
    </button>
</div>

<div class="report-header">
    <div>
        <h1>🌿 <?= APP_NAME ?></h1>
        <div style="font-size:13pt;font-weight:bold;color:#333;">Reporte de <?= e($reportLabel) ?></div>
    </div>
    <div class="meta">
        <div>Período: <b><?= e($dateFrom) ?> al <?= e($dateTo) ?></b></div>
        <div>Generado: <?= date('d/m/Y H:i') ?></div>
        <div>Por: <?= e(Auth::name()) ?></div>
        <div>Total registros: <b><?= number_format(count($rows)) ?></b></div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <?php foreach ($headers as $h): ?>
            <th><?= e($h) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <?php foreach ($row as $cell): ?>
            <td><?= $cell ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php if ($totalRow): ?>
        <tr class="total-row">
            <?php foreach ($totalRow as $cell): ?>
            <td><?= $cell ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    <?= APP_NAME ?> v<?= APP_VERSION ?> — Sistema de Monitoreo Administrativo — <?= date('Y') ?>
</div>

<script>
    // Auto-abrir diálogo de impresión
    setTimeout(() => window.print(), 500);
</script>
</body>
</html>
