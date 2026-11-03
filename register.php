<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow mx-auto">

                <div class="card-header bg-success text-white text-center py-3">
                    <h4 class="mb-0 fw-bold">Create an Account</h4>
                </div>

                <div class="card-body p-4">
                    <?php
                    //checking the error in the backend processing script
                    if (isset($_SESSION['error'])):
                        ?>

                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form action="process_register.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name <span class="text-danger">*</span> </label>
                            <input type="text" name="full_name" class="form-control" placeholder="Enter your name"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                placeholder="Enter your email (user@example.com" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control"
                                placeholder="Enter Phone number 03001234567">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Register As <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="customer" selected>Customer</option>
                                <option value="pharmacy">Pharmacy Owner</option>
                                <option value="rider">Delivery Rider</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Enter password"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Confirm Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" name="confirm_password" class="form-control"
                                placeholder="Re-enter your password" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Register Account</button>
                    </form>

                    <div class="mt-3 text-center">
                        <span>Already have an account?</span>
                        <a href="login.php" class="text-success text-decoration-none fw-bold">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>