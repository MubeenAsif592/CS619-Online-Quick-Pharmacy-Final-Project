<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_SESSION['user_id'];
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'cod');
    $cart_items = $_SESSION['cart'] ?? [];

    if (empty($delivery_address) || empty($cart_items)) {
        $_SESSION['error'] = "Delivery address and cart items are required.";
        header("Location: checkout.php");
        exit();
    }

    $grand_total = 0;
    foreach ($cart_items as $item) {
        $grand_total += ($item['price'] * $item['quantity']);
    }

    // Dynamic Safe Wallet Balance Verification
    if ($payment_method === 'wallet') {
        $curr_balance = 0.00;
        try {
            $bal_stmt = $conn->prepare("SELECT 
                (IFNULL(SUM(CASE WHEN LOWER(type) = 'credit' THEN amount ELSE 0 END), 0) - 
                 IFNULL(SUM(CASE WHEN LOWER(type) = 'debit' THEN amount ELSE 0 END), 0)) AS calculated_balance 
                FROM wallet_transactions WHERE user_id = ?");
            if ($bal_stmt) {
                $bal_stmt->bind_param("i", $customer_id);
                $bal_stmt->execute();
                $bal_res = $bal_stmt->get_result()->fetch_assoc();
                $curr_balance = floatval($bal_res['calculated_balance'] ?? 0.00);
                $bal_stmt->close();
            }
        } catch (Throwable $e) {
            $curr_balance = 0.00;
        }

        if ($curr_balance <= 0.00) {
            try {
                $w_stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
                if ($w_stmt) {
                    $w_stmt->bind_param("i", $customer_id);
                    $w_stmt->execute();
                    $w_res = $w_stmt->get_result();
                    if ($w_res && $w_res->num_rows > 0) {
                        $curr_balance = floatval($w_res->fetch_assoc()['balance']);
                    }
                    $w_stmt->close();
                }
            } catch (Throwable $e) {
                $curr_balance = 0.00;
            }
        }

        if ($curr_balance < $grand_total) {
            $_SESSION['error'] = "Insufficient wallet balance! Available: Rs. " . number_format($curr_balance, 2) . ", Required: Rs. " . number_format($grand_total, 2);
            header("Location: checkout.php");
            exit();
        }
    }

    // Group items by pharmacy_id
    $orders_by_pharmacy = [];
    foreach ($cart_items as $item) {
        $p_id = $item['pharmacy_id'];
        if (!isset($orders_by_pharmacy[$p_id])) {
            $orders_by_pharmacy[$p_id] = [
                'total' => 0,
                'items' => []
            ];
        }
        $orders_by_pharmacy[$p_id]['items'][] = $item;
        $orders_by_pharmacy[$p_id]['total'] += ($item['price'] * $item['quantity']);
    }

    // Start DB Transaction
    $conn->autocommit(FALSE);
    $transaction_success = true;

    foreach ($orders_by_pharmacy as $pharmacy_id => $order_data) {
        $total_amount = $order_data['total'];

        // 1. Insert into orders table
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, pharmacy_id, total_amount, order_status, delivery_address, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())");
        $stmt->bind_param("iids", $customer_id, $pharmacy_id, $total_amount, $delivery_address);
        
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;
            $stmt->close();

            foreach ($order_data['items'] as $item) {
                $med_id = intval($item['id']);
                $qty = intval($item['quantity']);
                $unit_price = floatval($item['price']);

                // 2. Insert into order_items table using unit_price column
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $med_id, $qty, $unit_price);
                if (!$item_stmt->execute()) {
                    $transaction_success = false;
                }
                $item_stmt->close();

                // 3. Deduct medicine stock quantity
                $stock_stmt = $conn->prepare("UPDATE medicines SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
                $stock_stmt->bind_param("ii", $qty, $med_id);
                if (!$stock_stmt->execute()) {
                    $transaction_success = false;
                }
                $stock_stmt->close();
            }

            // 4. Deduct Wallet Balance & Log Transaction
            if ($payment_method === 'wallet' && $transaction_success) {
                try {
                    $deduct = $conn->prepare("UPDATE user_wallets SET balance = balance - ? WHERE user_id = ?");
                    if ($deduct) {
                        $deduct->bind_param("di", $total_amount, $customer_id);
                        $deduct->execute();
                        $deduct->close();
                    }
                } catch (Throwable $e) {}

                try {
                    $t_stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, order_id, amount, type, description, created_at) VALUES (?, ?, ?, 'debit', ?, NOW())");
                    if ($t_stmt) {
                        $desc = "Payment for Order #ORD-" . $order_id;
                        $t_stmt->bind_param("iids", $customer_id, $order_id, $total_amount, $desc);
                        $t_stmt->execute();
                        $t_stmt->close();
                    }
                } catch (Throwable $e) {}
            }
        } else {
            $transaction_success = false;
        }
    }

    if ($transaction_success) {
        $conn->commit();
        $conn->autocommit(TRUE);
        unset($_SESSION['cart']);
        $_SESSION['success'] = "Order placed successfully! Stock updated and pharmacy notified.";
        header("Location: customer_dashboard.php");
        exit();
    } else {
        $conn->rollback();
        $conn->autocommit(TRUE);
        $_SESSION['error'] = "Failed to process order due to database error. Please try again.";
        header("Location: checkout.php");
        exit();
    }

} else {
    header("Location: customer_search.php");
    exit();
}
?>