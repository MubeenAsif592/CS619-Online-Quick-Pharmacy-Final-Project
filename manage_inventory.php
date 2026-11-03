<?php
if(session_status() == PHP_SESSION_NONE){
     session_start();
     }
include_once"includes/db.php";
if(!isset($_SESSION['user_id'])){
    $_SESSION['error'] = "Please login to access the medicine inventory.";
    header("Location: login.php");
    exit();
}
require_once"includes/auth_check.php";
check_role(['pharmacy']);

include_once'includes/header.php';
include_once'includes/navbar.php';

$pharmacy_id = $_SESSION['user_id'];

$find = isset($_GET['search']) ? trim($_GET['search']) :'';
$st_filter = isset($_GET['status']) ? trim($_GET['status']) :'all';

$q = "SELECT * FROM medicines WHERE pharmacy_id = ? ";
$bind_params = array($pharmacy_id);
$type_str = "i";

if(!empty($find)){
    $q .= "AND (medicine_name LIKE ? OR category LIKE ?) ";
    $like_val = "%" . $find . "%";
    $bind_params[] = $like_val;
    $bind_params[] = $like_val;
    $type_str .= "ss";  
}

if($st_filter  === 'available'){
    $q .= "AND stock_quantity > 10";
}
elseif($st_filter === "low_stock"){
    $q .= "AND stock_quantity > 0 AND stock_quantity <=10";    
}
elseif($st_filter === "out_of_stock"){
    $q .= "AND stock_quantity <=0";
}

$q .= " ORDER BY id DESC";

$stmt = $conn->prepare($q);
$stmt->bind_param($type_str, ...$bind_params);
$stmt->execute();
$res = $stmt->get_result();

?>

<div class="container mt-4">
    <!-- Successs Message -->
    <?php if(isset($_SESSION['success'])): ?>
        <div id="successAlert" class="alert alert-success alert-dismissible fade shadow-sm" role="alert">
        <?= htmlspecialchars($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <!-- Error Message-->
     <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
     <?php unset($_SESSION['error']); ?>
     <?php endif; ?>

     <!--Header title & add button-->
     <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Manage Inventory Hub</h2>
        <div>
        <a href="pharmacy_dashboard.php" class="btn btn-outline-secondary me-2 fw-bold">Dashboard</a>
     <a href="add_medicine.php" class="btn btn-success fw-bold">+ Add New Medicine</a>
        </div>
     </div>

     <!-- Search & filter-->
      <div class="card p-3 mb-4 bg-light shadow-sm">
        <form action="manage_inventory.php" method="GET" class="row g-3 align-items-center">

        <div class="col-md-5">
            <input type="text" name="search" class="form-control" placeholder="Search by name or category..." value="<?= htmlspecialchars($find);?>">
        </div>

        <div class="col-md-4">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $st_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="available" <?= $st_filter === 'available' ? 'selected' : ''?>>Available (> 10 items)</option>
                <option value="low_stock" <?= $st_filter === 'low_stock' ? 'selected' : ''?>>Low Stock (1 - 10 items)</option>
                <option value="out_of_stock" <?= $st_filter === 'out_of_stock' ? 'selected' : ''?>>Out of Stock (0 items)</option>
            </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-secondary w-100 fw-bold">Search</button>
            <?php if(!empty($find) || $st_filter !=='all'): ?>
                <a href="manage_inventory.php" class="btn btn-outline-dark fw-bold">Reset</a>
            <?php endif; ?>
        </div>
        </form>
      </div>

      <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Medicine Name</th>
                            <th>Category</th>
                            <th>Price (PKR)</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Added Date & Time</th>
                            <th>Description</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                         <?php if($res && $res->num_rows > 0): ?>
                            <?php $sr_no = 1; 
                            while($data = $res->fetch_assoc()) : 
                            $m_name = $data['medicine_name'] ?? 'N/A';
                            $qty = (int)($data['stock_quantity'] ?? 0);
                            $m_price = $data['price'] ?? 0;
                            $cat = $data['category'] ?? 'General';
                            $desc = $data['description'] ?? '';
                            ?>
                            <tr>
                                <td><?=$sr_no++;?></td> 
                                <td class="fw-bold"><?= htmlspecialchars($m_name);?> </td>
                                <td> <span class="badge bg-secondary"><?= htmlspecialchars($cat); ?></span></td>
                                <td>Rs. <?= number_format((float)$m_price, 2);?></td>
                                <td><strong><?= $qty; ?> Units</strong></td>
                                <td>
                                    <?php if($qty <=0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif($qty<=10): ?>
                                            <span class="badge bg-warning text-dark">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php endif; ?>
                                </td>

                                <td>
                                    <?php 
                                    $timestamp = strtotime($data['created_at']);
                                    echo date('g:i A, d-m-y',$timestamp);
                                    ?>
                                </td>

                                <td><?= htmlspecialchars($desc); ?></td>

                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit_medicine.php?id=<?= $data['id']; ?>" class="btn btn-sm btn-outline-warning fw-bold">Edit</a>

                                     <form action="process_delete_medicine.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Are you sure you want to delete this medicine?');">
                                    <input type="hidden" name="id" value="<?= $data['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger fw-bold">Delete</button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        No medicines found in your inventory.
                                    </td>
                                </tr>
                                <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded",function(){
        var alertBox = document.getElementById("successAlert");
        if(alertBox){
            setTimeout(function(){
               var bsAlert = bootstrap.Alert.getOrCreateInstance(alertBox);
               if(bsAlert){
                bsAlert.close();
               }else{
                alertBox.remove();
               }
            },3000);
        }
    });
</script>
<?php
$stmt->close();
$conn->close();

include_once'includes/footer.php';
?>