<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
$pageTitle = 'Reports';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$category = trim($_GET['category'] ?? '');
$filterError = '';
$isValidDate = static function (string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
        return false;
    }
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
};

if (($from !== '' && !$isValidDate($from)) || ($to !== '' && !$isValidDate($to))) {
    $filterError = 'Enter valid calendar dates for the report period.';
    $from = $to = '';
} elseif ($from !== '' && $to !== '' && $from > $to) {
    $filterError = 'The start date must be on or before the end date.';
    $from = $to = '';
}

$categories = $pdo->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll();

$params = [];
$where = [];
if ($from !== '') { $where[] = 'DATE(s.sale_date) >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 'DATE(s.sale_date) <= ?'; $params[] = $to; }
if ($category !== '') { $where[] = 'p.category = ?'; $params[] = $category; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$summaryStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT s.sale_id) orders,
           COALESCE(SUM(sd.quantity),0) quantity,
           COALESCE(SUM(sd.quantity * sd.unit_price),0) sales,
           COALESCE(SUM(sd.quantity * (sd.unit_price - p.purchase_price)),0) profit
    FROM sales s
    JOIN sale_details sd ON sd.sale_id=s.sale_id
    JOIN products p ON p.product_id=sd.product_id
    $whereSql
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$productStmt = $pdo->prepare("
    SELECT p.product_name, p.category, SUM(sd.quantity) quantity_sold,
           ROUND(SUM(sd.quantity*sd.unit_price),2) sales,
           ROUND(SUM(sd.quantity*(sd.unit_price-p.purchase_price)),2) profit
    FROM sales s
    JOIN sale_details sd ON sd.sale_id=s.sale_id
    JOIN products p ON p.product_id=sd.product_id
    $whereSql
    GROUP BY p.product_id,p.product_name,p.category
    ORDER BY sales DESC
");
$productStmt->execute($params);
$productReport = $productStmt->fetchAll();

$customerStmt = $pdo->prepare("
    SELECT c.name, COUNT(DISTINCT s.sale_id) orders,
           SUM(sd.quantity) quantity_bought,
           ROUND(SUM(sd.quantity*sd.unit_price),2) total_spent
    FROM sales s
    JOIN customers c ON c.customer_id=s.customer_id
    JOIN sale_details sd ON sd.sale_id=s.sale_id
    JOIN products p ON p.product_id=sd.product_id
    $whereSql
    GROUP BY c.customer_id,c.name
    ORDER BY total_spent DESC
");
$customerStmt->execute($params);
$customerReport = $customerStmt->fetchAll();

$categoryStmt = $pdo->prepare("
    SELECT p.category, SUM(sd.quantity) quantity_sold,
           ROUND(SUM(sd.quantity*sd.unit_price),2) sales,
           ROUND(SUM(sd.quantity*(sd.unit_price-p.purchase_price)),2) profit
    FROM sales s
    JOIN sale_details sd ON sd.sale_id=s.sale_id
    JOIN products p ON p.product_id=sd.product_id
    $whereSql
    GROUP BY p.category
    ORDER BY sales DESC
");
$categoryStmt->execute($params);
$categoryReport = $categoryStmt->fetchAll();

$inventory = $pdo->query("SELECT product_name,category,stock,reorder_level FROM products ORDER BY (stock <= reorder_level) DESC, stock ASC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="card mb-4"><div class="card-body">
    <h1 class="h5">Reports & Filters</h1>
    <?php if ($filterError): ?><div class="alert alert-danger" role="alert"><?= e($filterError) ?></div><?php endif; ?>
    <form class="row g-2">
        <div class="col-md-3"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="col-md-3"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="col-md-3"><label class="form-label">Category</label><select name="category" class="form-select"><option value="">All categories</option><?php foreach($categories as $c): ?><option <?= $category===$c['category']?'selected':'' ?>><?= e($c['category']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary me-2">Apply</button><a class="btn btn-secondary" href="reports.php">Reset</a></div>
    </form>
</div></div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Orders</div><strong class="fs-4"><?= (int)$summary['orders'] ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Quantity Sold</div><strong class="fs-4"><?= (int)$summary['quantity'] ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Sales</div><strong class="fs-4">₹<?= number_format((float)$summary['sales'],2) ?></strong></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Profit</div><strong class="fs-4">₹<?= number_format((float)$summary['profit'],2) ?></strong></div></div></div>
</div>

<div class="row g-4">
    <div class="col-12"><div class="card"><div class="card-body"><h2 class="h5">Product Sales / Profit Report</h2>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Product</th><th>Category</th><th>Qty</th><th>Sales</th><th>Profit</th></tr></thead><tbody>
        <?php foreach($productReport as $r): ?><tr><td><?= e($r['product_name']) ?></td><td><?= e($r['category']) ?></td><td><?= (int)$r['quantity_sold'] ?></td><td>₹<?= number_format((float)$r['sales'],2) ?></td><td>₹<?= number_format((float)$r['profit'],2) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div></div>

    <div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h5">Category Sales Report</h2>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Category</th><th>Qty</th><th>Sales</th><th>Profit</th></tr></thead><tbody>
        <?php foreach($categoryReport as $r): ?><tr><td><?= e($r['category']) ?></td><td><?= (int)$r['quantity_sold'] ?></td><td>₹<?= number_format((float)$r['sales'],2) ?></td><td>₹<?= number_format((float)$r['profit'],2) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div></div>

    <div class="col-lg-6"><div class="card"><div class="card-body"><h2 class="h5">Customer Purchase Report</h2>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Customer</th><th>Orders</th><th>Qty</th><th>Spent</th></tr></thead><tbody>
        <?php foreach($customerReport as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= (int)$r['orders'] ?></td><td><?= (int)$r['quantity_bought'] ?></td><td>₹<?= number_format((float)$r['total_spent'],2) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div></div>

    <div class="col-12"><div class="card"><div class="card-body"><h2 class="h5">Inventory / Low Stock Report</h2>
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Reorder Level</th><th>Status</th></tr></thead><tbody>
        <?php foreach($inventory as $r): $low=(int)$r['stock'] <= (int)$r['reorder_level']; ?><tr><td><?= e($r['product_name']) ?></td><td><?= e($r['category']) ?></td><td><?= (int)$r['stock'] ?></td><td><?= (int)$r['reorder_level'] ?></td><td><span class="badge <?= $low?'bg-danger':'bg-success' ?>"><?= $low?'LOW':'OK' ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div></div></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
