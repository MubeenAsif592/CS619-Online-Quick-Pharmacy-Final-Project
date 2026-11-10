<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['admin']);

include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Fetch Pharmacies along with Owner Details, Total Medicines, and User Registration Date safely
$q = "SELECT p.*, u.full_name AS owner_name, u.email, u.created_at AS reg_date,
      (SELECT COUNT(*) FROM medicines m WHERE m.pharmacy_id = p.id) AS total_medicines 
      FROM pharmacies p 
      JOIN users u ON p.user_id = u.id 
      ORDER BY p.id DESC";
$res = $conn->query($q);
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">🏥 Registered Pharmacies Management</h2>
            <p class="text-muted mb-0">Overview of all registered pharmacy vendors and inventory counts.</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary fw-bold">Dashboard</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Pharmacy Name</th>
                            <th>Owner Name</th>
                            <th>Email</th>
                            <!-- <th>Address</th> -->
                            <th class="text-center">Listed Medicines</th>
                            <th>Registration Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res && $res->num_rows > 0): ?>
                            <?php $sr = 1; while ($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td><?= $sr++; ?></td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['pharmacy_name']); ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['owner_name']); ?></td>
                                <td><?= htmlspecialchars($row['email']); ?></td>
                                <!-- <td class="small text-muted"><?= htmlspecialchars($row['address'] ?? 'Address pending update'); ?></td> -->
                                <td class="text-center">
                                    <span class="badge bg-success fs-6"><?= $row['total_medicines']; ?> Items</span>
                                </td>
                                <td class="small">
                                    <?= (!empty($row['reg_date'])) ? date('d-m-Y', strtotime($row['reg_date'])) : 'N/A'; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No pharmacies registered in the system yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
include_once '../includes/footer.php';
?>