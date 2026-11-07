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

    // Wallet Balance Check
    if ($payment_method === 'wallet') {
        $w_stmt = $conn->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
        $w_stmt->bind_param("i", $customer_id);
        $w_stmt->execute();
        $w_res = $w_stmt->get_result();
        $curr_balance = ($w_res && $w_res->num_rows > 0) ? floatval($w_res->fetch_assoc()['balance']) : 0;
        $w_stmt->close();

        if ($curr_balance < $grand_total) {
            $_SESSION['error'] = "Insufficient wallet balance! Your balance: Rs. " . number_format($curr_balance, 2) . ", Required: Rs. " . number_format($grand_total, 2);
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

    // Start Transaction
    $conn->autocommit(FALSE);
    $transaction_success = true;

    foreach ($orders_by_pharmacy as $pharmacy_id => $order_data) {
        $total_amount = $order_data['total'];

        // 1. Insert into `orders` table
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, pharmacy_id, total_amount, order_status, delivery_address) VALUES (?, ?, ?, 'pending', ?)");
        $stmt->bind_param("iids", $customer_id, $pharmacy_id, $total_amount, $delivery_address);
        
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;
            $stmt->close();

            foreach ($order_data['items'] as $item) {
                $med_id = intval($item['id']);
                $qty = intval($item['quantity']);
                $unit_price = floatval($item['price']);

                // 2. Insert into `order_items`
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $med_id, $qty, $unit_price);
                if (!$item_stmt->execute()) {
                    $transaction_success = false;
                }
                $item_stmt->close();

                // 3. DEDUCT STOCK QUANTITY FROM `medicines` TABLE IMMEDIATELY
                $stock_stmt = $conn->prepare("UPDATE medicines SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
                $stock_stmt->bind_param("ii", $qty, $med_id);
                if (!$stock_stmt->execute()) {
                    $transaction_success = false;
                }
                $stock_stmt->close();
            }

            // Deduct Wallet Balance if selected
            if ($payment_method === 'wallet' && $transaction_success) {
                $deduct = $conn->prepare("UPDATE user_wallets SET balance = balance - ? WHERE user_id = ?");
                $deduct->bind_param("di", $total_amount, $customer_id);
                $deduct->execute();
                $deduct->close();

                $t_stmt = $conn->prepare("INSERT INTO wallet_transactions (user_id, order_id, amount, type, description) VALUES (?, ?, ?, 'debit', ?)");
                $desc = "Payment for Order #" . $order_id;
                $t_stmt->bind_param("iids", $customer_id, $order_id, $total_amount, $desc);
                $t_stmt->execute();
                $t_stmt->close();
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