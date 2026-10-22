<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$pageTitle = 'Products';
$message = '';
$error = '';
$editProduct = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['product_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $purchase = (float)($_POST['purchase_price'] ?? 0);
            $selling = (float)($_POST['selling_price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $reorder = (int)($_POST['reorder_level'] ?? 0);

            if ($name === '' || $category === '' || $purchase < 0 || $selling <= 0 || $stock < 0 || $reorder < 0) {
                throw new RuntimeException('Enter valid product details.');
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare('INSERT INTO products (product_name,category,purchase_price,selling_price,stock,reorder_level) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$name,$category,$purchase,$selling,$stock,$reorder]);
                $message = 'Product added successfully.';
            } else {
                $id = (int)$_POST['product_id'];
                $pdo->beginTransaction();
                $stockStmt = $pdo->prepare('SELECT stock FROM products WHERE product_id=? FOR UPDATE');
                $stockStmt->execute([$id]);
                $previousStock = $stockStmt->fetchColumn();
                if ($previousStock === false) {
                    throw new RuntimeException('Product not found.');
                }

                $stmt = $pdo->prepare('UPDATE products SET product_name=?, category=?, purchase_price=?, selling_price=?, stock=?, reorder_level=? WHERE product_id=?');
                $stmt->execute([$name,$category,$purchase,$selling,$stock,$reorder,$id]);
                $stockChange = $stock - (int)$previousStock;
                if ($stockChange !== 0) {
                    $changeType = $stockChange > 0 ? 'RESTOCK' : 'ADJUSTMENT';
                    $logStmt = $pdo->prepare('INSERT INTO inventory_log (product_id,change_type,quantity_change,stock_after) VALUES (?,?,?,?)');
                    $logStmt->execute([$id,$changeType,$stockChange,$stock]);
                }
                $pdo->commit();
                $message = 'Product updated successfully.';
            }
        } elseif ($action === 'delete') {
            requireAdmin();
            $id = (int)$_POST['product_id'];
            $stmt = $pdo->prepare('DELETE FROM products WHERE product_id=?');
            $stmt->execute([$id]);
            $message = 'Product deleted successfully.';
        }
    } catch (Throwable $e) {
        $error = $e instanceof PDOException
            ? ($e->getCode() === '23000'
                ? 'This product has sales or inventory history and cannot be deleted.'
                : 'A database error occurred while saving the product.')
            : $e->getMessage();
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $editProduct = $stmt->fetch();
}
$products = $pdo->query('SELECT * FROM products ORDER BY product_id DESC')->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <h1 class="h5"><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h1>
            <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="<?= $editProduct ? 'edit' : 'add' ?>">
                <?php if ($editProduct): ?><input type="hidden" name="product_id" value="<?= (int)$editProduct['product_id'] ?>"><?php endif; ?>
                <div class="mb-3"><label class="form-label">Product Name</label><input name="product_name" class="form-control" required value="<?= e($editProduct['product_name'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label">Category</label><input name="category" class="form-control" required value="<?= e($editProduct['category'] ?? '') ?>"></div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label">Purchase Price</label><input type="number" step="0.01" min="0" name="purchase_price" class="form-control" required value="<?= e((string)($editProduct['purchase_price'] ?? '')) ?>"></div>
                    <div class="col-6 mb-3"><label class="form-label">Selling Price</label><input type="number" step="0.01" min="0.01" name="selling_price" class="form-control" required value="<?= e((string)($editProduct['selling_price'] ?? '')) ?>"></div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label">Stock</label><input type="number" min="0" name="stock" class="form-control" required value="<?= e((string)($editProduct['stock'] ?? '')) ?>"></div>
                    <div class="col-6 mb-3"><label class="form-label">Reorder Level</label><input type="number" min="0" name="reorder_level" class="form-control" required value="<?= e((string)($editProduct['reorder_level'] ?? '')) ?>"></div>
                </div>
                <button class="btn btn-primary"><?= $editProduct ? 'Update' : 'Add' ?></button>
                <?php if ($editProduct): ?><a class="btn btn-secondary" href="products.php">Cancel</a><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card"><div class="card-body">
            <h2 class="h5">Products</h2>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Product</th><th>Category</th><th>Buy</th><th>Sell</th><th>Stock</th><th>Reorder</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr class="<?= (int)$p['stock'] <= (int)$p['reorder_level'] ? 'table-warning' : '' ?>">
                        <td><?= e($p['product_name']) ?></td><td><?= e($p['category']) ?></td>
                        <td>₹<?= number_format((float)$p['purchase_price'],2) ?></td><td>₹<?= number_format((float)$p['selling_price'],2) ?></td>
                        <td><?= (int)$p['stock'] ?></td><td><?= (int)$p['reorder_level'] ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$p['product_id'] ?>">Edit</a>
                            <?php if (($_SESSION['role'] ?? '') === 'ADMIN'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div></div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
