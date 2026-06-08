<?php
// ============================================================
// Funciones auxiliares globales
// ============================================================

// Formatea un valor decimal como moneda (S/ 1,234.50)
function formatCurrency(float $amount): string
{
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2, '.', ',');
}

// Formatea fecha/hora en español
function formatDate(string|null $date, string $format = 'd/m/Y H:i'): string
{
    if (!$date) return '—';
    return (new DateTime($date))->format($format);
}

// Badge de estado de pedido con color Bootstrap
function orderStatusBadge(string $status): string
{
    $map = [
        'PENDING_PAYMENT'  => ['warning',  'Pago Pendiente'],
        'PENDING'          => ['secondary', 'Pendiente'],
        'CONFIRMED'        => ['primary',  'Confirmado'],
        'READY_FOR_PICKUP' => ['info',     'Listo para Recoger'],
        'DELIVERED'        => ['success',  'Entregado'],
        'CANCELLED'        => ['danger',   'Cancelado'],
        'REFUNDED'         => ['dark',     'Reembolsado'],
    ];
    [$color, $label] = $map[$status] ?? ['light', $status];
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

// Badge de estado de pago
function paymentStatusBadge(string $status): string
{
    $map = [
        'PENDING'   => ['warning', 'Pendiente'],
        'COMPLETED' => ['success', 'Completado'],
        'FAILED'    => ['danger',  'Fallido'],
        'REFUNDED'  => ['dark',    'Reembolsado'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', $status];
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

// Badge de método de pago
function paymentMethodLabel(string $method): string
{
    $map = [
        'card'     => '💳 Tarjeta',
        'transfer' => '🏦 Transferencia',
        'yape'     => '📱 Yape',
        'plin'     => '📲 Plin',
        'cash'     => '💵 Efectivo',
    ];
    return $map[$method] ?? $method;
}

// Badge de tipo de entrega
function deliveryTypeLabel(string $type): string
{
    return $type === 'delivery' ? '🚚 Delivery' : '🏪 Recojo en Tienda';
}

// Etiqueta de categoría de producto
function categoryLabel(string $cat): string
{
    $map = [
        'abarrotes'       => 'Abarrotes',
        'beverages'       => 'Bebidas',
        'dairy'           => 'Lácteos',
        'bakery'          => 'Panadería',
        'snacks'          => 'Snacks',
        'prepared'        => 'Preparados',
        'frozen'          => 'Congelados',
        'cleaning'        => 'Limpieza',
        'pets'            => 'Mascotas',
        'refrigerated'    => 'Refrigerados',
        'licores'         => 'Licores',
        'tecnologia'      => 'Tecnología',
        'hogar'           => 'Hogar',
        'cuidado_personal'=> 'Cuidado Personal',
        'perfumeria'      => 'Perfumería',
        'accesorios'      => 'Accesorios',
        'utiles'          => 'Útiles',
        'importados'      => 'Importados',
        'chocolates'      => 'Chocolates',
        'other'           => 'Otros',
        'food'            => 'Alimentos',
        'meat'            => 'Carnes',
        'seafood'         => 'Mariscos',
        'vegetables'      => 'Verduras',
        'fruits'          => 'Frutas',
        'cosmetics'       => 'Cosméticos',
        'flowers'         => 'Flores',
        'medications'     => 'Medicamentos',
        'agricultural'    => 'Agrícola',
    ];
    return $map[$cat] ?? ucfirst($cat);
}

// Badge de estado de queja
function complaintStatusBadge(string $status): string
{
    $map = [
        'OPEN'      => ['danger',  'Abierto'],
        'IN_REVIEW' => ['warning', 'En Revisión'],
        'RESOLVED'  => ['success', 'Resuelto'],
        'CLOSED'    => ['secondary','Cerrado'],
    ];
    [$color, $label] = $map[$status] ?? ['light', $status];
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

// Paginación
function buildPagination(int $total, int $page, int $perPage, string $url): string
{
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';

    $sep = str_contains($url, '?') ? '&' : '?';
    $html = '<nav><ul class="pagination pagination-sm flex-wrap">';

    // Anterior
    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $html .= "<li class=\"page-item{$prevDisabled}\">
        <a class=\"page-link\" href=\"{$url}{$sep}page=" . ($page - 1) . "\">‹</a></li>";

    // Páginas
    for ($i = 1; $i <= $pages; $i++) {
        if (abs($i - $page) > 2 && $i !== 1 && $i !== $pages) {
            if ($i === 2 || $i === $pages - 1) {
                $html .= '<li class="page-item disabled"><a class="page-link">…</a></li>';
            }
            continue;
        }
        $active = $i === $page ? ' active' : '';
        $html .= "<li class=\"page-item{$active}\">
            <a class=\"page-link\" href=\"{$url}{$sep}page={$i}\">{$i}</a></li>";
    }

    // Siguiente
    $nextDisabled = $page >= $pages ? ' disabled' : '';
    $html .= "<li class=\"page-item{$nextDisabled}\">
        <a class=\"page-link\" href=\"{$url}{$sep}page=" . ($page + 1) . "\">›</a></li>";

    $html .= '</ul></nav>';
    return $html;
}

// Sanitiza output HTML
function e(string|null $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Genera token CSRF
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Valida token CSRF
function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Input CSRF oculto
function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

// Cálculo de tasa de crecimiento porcentual
function growthRate(float $current, float $previous): string
{
    if ($previous == 0) return $current > 0 ? '+100%' : '0%';
    $rate = (($current - $previous) / $previous) * 100;
    $sign = $rate >= 0 ? '+' : '';
    return $sign . number_format($rate, 1) . '%';
}

// Clase de color para crecimiento
function growthClass(float $current, float $previous): string
{
    if ($previous == 0) return $current > 0 ? 'text-success' : 'text-muted';
    return $current >= $previous ? 'text-success' : 'text-danger';
}

// Redirect seguro
function redirect(string $url): never
{
    header('Location: ' . BASE_URL . $url);
    exit;
}
