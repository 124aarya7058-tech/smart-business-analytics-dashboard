<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$pageTitle = 'Sales';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($customerId <= 0 || $productId <= 0 || $quantity <= 0) {
        $error = 'Select a customer, product and valid quantity.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT selling_price, stock FROM products WHERE product_id=? FOR UPDATE');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) throw new RuntimeException('Product not found.');
            if ((int)$product['stock'] < $quantity) throw new RuntimeException('Not enough stock available.');

            $total = (float)$product['selling_price'] * $quantity;

            $stmt = $pdo->prepare('INSERT INTO sales (customer_id, sale_date, total_amount) VALUES (?, NOW(), ?)');
            $stmt->execute([$customerId, $total]);
            $saleId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO sale_details (sale_id, product_id, quantity, unit_price) VALUES (?,?,?,?)');
            $stmt->execute([$saleId, $productId, $quantity, $product['selling_price']]);

            // The AFTER INSERT trigger decreases stock and creates an inventory log.
            $pdo->commit();
            $message = "Sale #{$saleId} recorded successfully. Total: ₹" . number_format($total, 2);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e instanceof PDOException
                ? 'The sale could not be recorded because of a database error. Please try again.'
                : $e->getMessage();
        }
    }
}

$customers = $pdo->query('SELECT customer_id,name FROM customers ORDER BY name')->fetchAll();
$products = $pdo->query('SELECT product_id,product_name,selling_price,stock FROM products WHERE stock > 0 ORDER BY product_name')->fetchAll();

$recentSales = $pdo->query("
    SELECT s.sale_id, s.sale_date, c.name AS customer, s.total_amount,
           p.product_name, sd.quantity
    FROM sales s
    JOIN customers c ON c.customer_id=s.customer_id
    JOIN sale_details sd ON sd.sale_id=s.sale_id
    JOIN products p ON p.product_id=sd.product_id
    ORDER BY s.sale_id DESC
    LIMIT 20
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h1 class="h5">Record Sale</h1>
            <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select customer</option>
                        <?php foreach ($customers as $c): ?><option value="<?= (int)$c['customer_id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Product</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Select product</option>
                        <?php foreach ($products as $p): ?><option value="<?= (int)$p['product_id'] ?>"><?= e($p['product_name']) ?> — ₹<?= number_format((float)$p['selling_price'],2) ?> — Stock <?= (int)$p['stock'] ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Quantity</label><input type="number" name="quantity" min="1" class="form-control" required></div>
                <button class="btn btn-primary w-100">Record Sale</button>
            </form>
            <p class="small text-muted mt-3 mb-0">A transaction is used. The database trigger automatically updates stock and creates an inventory log.</p>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h2 class="h5">Recent Sales</h2>
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>ID</th><th>Date</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($recentSales as $s): ?>
                    <tr><td><?= (int)$s['sale_id'] ?></td><td><?= e($s['sale_date']) ?></td><td><?= e($s['customer']) ?></td><td><?= e($s['product_name']) ?></td><td><?= (int)$s['quantity'] ?></td><td>₹<?= number_format((float)$s['total_amount'],2) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div></div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
