<?php
if(session_status() === PHP_SESSION_NONE){   
    session_start();
}
require_once"includes/header.php";
require_once"includes/navbar.php";
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header bg-success text-white text-center py-3">
                    <h4 class="mb-0 fw-bold">Login to Your Account</h4>
                </div>
                <div class="card-body p-4">
                    <!-- Registeration Successfull message -->
                     <?php if(isset($_SESSION['success'])): ?>
                     <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['success']); ?> 
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                     </div>
                     <?php unset($_SESSION['success']);?>
                     <?php endif; ?>

                     <!-- Login Error Message Alert Block -->
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']); ?> 
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>    


                     <form action="process_login.php" method="POST">
                        <!-- Email Field -->
                         <div class="mb-3">
                            <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="Enter email user@example.com" required>
                         </div>

                         <div class="mb-3">
                            <label class="form-label fw-bold" >Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Enter Password" required>
                         </div>

                         <button type="submit" class="btn btn-success w=100 py-2 fw-bold">Login</button>
                     </form>

                     <div class="mb-3 text-center">
                        <span>Don't have an account?</span>
                        <a href="register.php" class="text-success text-decoration-none fw-bold">Register here</a>
                     </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once'includes/footer.php'; ?>