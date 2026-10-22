<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
$pageTitle = 'Dashboard';

$totalSales = (float)$pdo->query('SELECT COALESCE(SUM(total_amount),0) FROM sales')->fetchColumn();
$totalOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn();
$totalCustomers = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

$monthly = $pdo->query("
    SELECT DATE_FORMAT(sale_date, '%Y-%m') AS month, ROUND(SUM(total_amount),2) AS sales
    FROM sales
    GROUP BY DATE_FORMAT(sale_date, '%Y-%m')
    ORDER BY month
")->fetchAll();

$categories = $pdo->query("
    SELECT p.category, ROUND(SUM(sd.quantity * sd.unit_price),2) AS sales
    FROM sale_details sd
    JOIN products p ON p.product_id = sd.product_id
    GROUP BY p.category
    ORDER BY sales DESC
")->fetchAll();

$topProducts = $pdo->query("
    SELECT p.product_name, SUM(sd.quantity) AS quantity_sold
    FROM sale_details sd
    JOIN products p ON p.product_id = sd.product_id
    GROUP BY p.product_id, p.product_name
    ORDER BY quantity_sold DESC
    LIMIT 5
")->fetchAll();

$topCustomers = $pdo->query("
    SELECT c.name, COUNT(s.sale_id) AS orders, ROUND(SUM(s.total_amount),2) AS total_spent
    FROM customers c
    JOIN sales s ON s.customer_id = c.customer_id
    GROUP BY c.customer_id, c.name
    ORDER BY total_spent DESC
    LIMIT 5
")->fetchAll();

$lowStock = $pdo->query("
    SELECT product_id, product_name, category, stock, reorder_level
    FROM products
    WHERE stock <= reorder_level
    ORDER BY stock ASC, product_name
")->fetchAll();

$monthlyLabels = array_column($monthly, 'month');
$monthlyValues = array_map('floatval', array_column($monthly, 'sales'));
$categoryLabels = array_column($categories, 'category');
$categoryValues = array_map('floatval', array_column($categories, 'sales'));
$productLabels = array_column($topProducts, 'product_name');
$productValues = array_map('intval', array_column($topProducts, 'quantity_sold'));

include __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Business Dashboard</h1>
        <p class="text-muted mb-0">Live analytics generated from MySQL.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="card kpi-card"><div class="card-body"><div class="text-muted">Total Sales</div><div class="kpi-value">₹<?= number_format($totalSales, 2) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card kpi-card"><div class="card-body"><div class="text-muted">Total Orders</div><div class="kpi-value"><?= number_format($totalOrders) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card kpi-card"><div class="card-body"><div class="text-muted">Total Customers</div><div class="kpi-value"><?= number_format($totalCustomers) ?></div></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="card kpi-card"><div class="card-body"><div class="text-muted">Total Products</div><div class="kpi-value"><?= number_format($totalProducts) ?></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card chart-card"><div class="card-body"><h2 class="h5">Monthly Sales Trend</h2><canvas id="monthlySalesChart"></canvas></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card chart-card"><div class="card-body"><h2 class="h5">Sales by Category</h2><canvas id="categoryChart"></canvas></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card chart-card"><div class="card-body"><h2 class="h5">Top 5 Products</h2><canvas id="topProductsChart"></canvas></div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body"><h2 class="h5">Top 5 Customers</h2>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Customer</th><th>Orders</th><th>Spent</th></tr></thead>
                <tbody>
                <?php foreach ($topCustomers as $row): ?>
                    <tr><td><?= e($row['name']) ?></td><td><?= (int)$row['orders'] ?></td><td>₹<?= number_format((float)$row['total_spent'],2) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div></div>
    </div>
    <div class="col-12">
        <div class="card border-warning"><div class="card-body">
            <h2 class="h5 text-warning">Low Stock Products</h2>
            <?php if (!$lowStock): ?>
                <div class="alert alert-success mb-0">No products are currently at or below the reorder level.</div>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-sm">
                    <thead><tr><th>Product</th><th>Category</th><th>Current Stock</th><th>Reorder Level</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStock as $row): ?>
                        <tr><td><?= e($row['product_name']) ?></td><td><?= e($row['category']) ?></td><td><span class="badge bg-danger"><?= (int)$row['stock'] ?></span></td><td><?= (int)$row['reorder_level'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div></div>
    </div>
</div>

<script>
window.dashboardData = {
    monthlyLabels: <?= json_encode($monthlyLabels, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,
    monthlyValues: <?= json_encode($monthlyValues) ?>,
    categoryLabels: <?= json_encode($categoryLabels, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,
    categoryValues: <?= json_encode($categoryValues) ?>,
    productLabels: <?= json_encode($productLabels, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>,
    productValues: <?= json_encode($productValues) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="assets/js/dashboard.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
