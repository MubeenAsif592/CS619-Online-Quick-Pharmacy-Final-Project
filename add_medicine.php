<?php 
if(session_status() === PHP_SESSION_NONE){
    session_start();;
}

require_once'includes/auth_check.php';
check_role(['pharmacy']);
require_once'includes/header.php';
require_once'includes/navbar.php';
?>
<link rel="stylesheet" href="Style/add_medicine.css">
<div class="container my-5">
  <?php if(isset($_SESSION['error'])): ?>
    <div id="error-alert" class="alert-error">
        <span><?= htmlspecialchars($_SESSION['error']); ?></span>
        <!-- Added class="btn-close-alert" here -->
        <button type="button" class="btn-close-alert" onclick="this.parentElement.style.display='none';">&times;</button>
    </div>
    <script>
    setTimeout(function() {
        var alertBox = document.getElementById('error-alert');
        if (alertBox) {
            alertBox.style.display = 'none';
        }
    }, 3000);
    </script>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>


    <div class="form-card">
        <div class="form-header">
            <h3>💊 Add New Medicine</h3>
            <a href="manage_inventory.php" class="btn-back">Back to Hub</a>
        </div>
        <form action="process_add_medicine.php" method="POST">
        <div class="form-group">
            <label for="medicine_name">Medicine Name <span class="required">*</span></label>
            <input type="text" name="medicine_name" id="medicine_name" placeholder="e.g., Panadol 500mg" value="<?=  htmlspecialchars($_POST['medicine_name']?? ''); ?>" required>
        </div>

        <div class="form-row">
    <!-- dropdown category-->
            <div class="flex-2">
                <label for="category">Category <span class="required">*</span> </label>
                <input type="text" name="category" id="category" placeholder="e.g., Pain Killer " value="<?=  htmlspecialchars($_POST['category']?? ''); ?>" required>
            </div>

            <div class="flex-1">
                <label for="price">Price (PKR) <span class="required">*</span> </label>
                <input type="number" step="0.01" name="price" id="price" placeholder="0.00" min="0.01" value="<?= htmlspecialchars($_POST['price'] ?? ''); ?>" required>
            </div>

            <div class="flex-1">
                <label for="stock_quantity">Stock <span class="required">*</span> </label>
                <input type="number" name="stock_quantity" id="stock_quantity" placeholder="100" min="0" value="<?= htmlspecialchars($_POST['stock_quantity'] ?? ''); ?>" required>
            </div>
        </div>

        <!-- Description textarea-->
         <div class="form-group">
            <label for="description">Description (Optional)</label>
            <textarea name="description" id="description" rows="3" maxlength="500" placeholder="Enter medicine details....."><?= htmlspecialchars($_POST['description']?? ''); ?></textarea>
         </div>

         <button type="submit" class="btn-submit">Save Medicine</button>

        </form>
    </div>
</div>
<?php
require_once'includes/footer.php';
?>