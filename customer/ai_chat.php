<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/auth_check.php';
check_role(['customer']);

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'Customer');

// Initialize chat history in session if empty
if (!isset($_SESSION['ai_chat_history']) || !is_array($_SESSION['ai_chat_history'])) {
    $_SESSION['ai_chat_history'] = [
        [
            'role' => 'assistant',
            'message' => "Hello " . htmlspecialchars($customer_name) . "! 👋 I am your Quick Pharmacy AI Medical Assistant (FR8). Ask me about symptoms, medicine usage, or general medical information!",
            'time' => date('g:i A')
        ]
    ];
}

// Action to clear conversation history
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $_SESSION['ai_chat_history'] = [];
    header("Location: ai_chat.php");
    exit();
}

/**
 * Call OpenRouter / GroqCloud API for medical responses (FR8)
 */
function callExternalMedicalAI($user_prompt, $db_medicines, $conn) {
    $apiKey = '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';

    // Verification check for missing key
    if (empty($apiKey) || str_contains($apiKey, 'YOUR_OPENROUTER')) {
        return getLocalDatabaseRecommendation($user_prompt, $conn);
    }

    $apiUrl = "https://openrouter.ai/api/v1/chat/completions";

    $systemContext = "You are an AI Medical Assistant for an online pharmacy app 'Quick Pharmacy'. "
        . "Answer health questions concisely and accurately. "
        . "In-stock medicines in local database: [" . $db_medicines . "]. "
        . "Always include a brief disclaimer to consult a licensed medical professional.";

    $payload = [
        "model" => "meta-llama/llama-3-8b-instruct:free",
        "messages" => [
            ["role" => "system", "content" => $systemContext],
            ["role" => "user", "content" => $user_prompt]
        ],
        "temperature" => 0.7
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
        "HTTP-Referer: http://localhost/QuickPharmacy",
        "X-Title: Quick Pharmacy App"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
    }

    // Smart Fallback if OpenRouter API is rate-limited or unavailable
    return getLocalDatabaseRecommendation($user_prompt, $conn);
}

/**
 * Local Database Recommendation Fallback Engine
 */
function getLocalDatabaseRecommendation($user_input, $conn) {
    $input = strtolower(trim($user_input));
    
    $stmt = $conn->prepare("SELECT m.medicine_name, m.price, m.stock_quantity, COALESCE(p.pharmacy_name, u.full_name) AS pharmacy_name 
                            FROM medicines m 
                            JOIN pharmacies p ON m.pharmacy_id = p.id 
                            JOIN users u ON p.user_id = u.id 
                            WHERE m.medicine_name LIKE ? OR m.category LIKE ? OR m.description LIKE ?
                            ORDER BY m.price ASC LIMIT 5");
    $like_val = "%" . $input . "%";
    $stmt->bind_param("sss", $like_val, $like_val, $like_val);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $meds = [];
    while ($r = $res->fetch_assoc()) {
        $meds[] = $r;
    }
    $stmt->close();

    if (!empty($meds)) {
        $reply = "Here are matching available medicines from our Quick Pharmacy database:\n\n";
        foreach ($meds as $med) {
            $reply .= "• **" . htmlspecialchars($med['medicine_name']) . "** — Rs. " . number_format($med['price'], 2) . " (Pharmacy: *" . htmlspecialchars($med['pharmacy_name']) . "*, Stock: " . $med['stock_quantity'] . " units)\n";
        }
        $reply .= "\n⚠️ *Disclaimer: Please consult a doctor for official treatment and dosage instructions.*";
        return $reply;
    }

    return "For query '" . htmlspecialchars($user_input) . "', please search our **Search Medicines** page or consult a certified physician.\n\n⚠️ *Disclaimer: Information provided is for general awareness.*";
}

// Process Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_query'])) {
    $user_query = trim($_POST['user_query']);

    if (!empty($user_query)) {
        // Log user message
        $_SESSION['ai_chat_history'][] = [
            'role' => 'user',
            'message' => $user_query,
            'time' => date('g:i A')
        ];

        // Fetch live database context (in-stock medicines)
        $med_list = [];
        $res = $conn->query("SELECT medicine_name, category FROM medicines WHERE stock_quantity > 0 LIMIT 15");
        if ($res && $res->num_rows > 0) {
            while ($r = $res->fetch_assoc()) {
                $med_list[] = $r['medicine_name'] . " (" . $r['category'] . ")";
            }
        }
        $db_meds = implode(", ", $med_list);

        // Call External API with Fallback
        $ai_reply = callExternalMedicalAI($user_query, $db_meds, $conn);

        // Log AI assistant response
        $_SESSION['ai_chat_history'][] = [
            'role' => 'assistant',
            'message' => $ai_reply,
            'time' => date('g:i A')
        ];
    }

    header("Location: ai_chat.php");
    exit();
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<style>
.chat-box {
    height: 440px;
    overflow-y: auto;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
}
.chat-bubble {
    max-width: 80%;
    padding: 12px 16px;
    border-radius: 15px;
    margin-bottom: 12px;
    line-height: 1.5;
    word-wrap: break-word;
}
.chat-user {
    background-color: #198754;
    color: #fff;
    margin-left: auto;
    border-bottom-right-radius: 2px;
}
.chat-assistant {
    background-color: #ffffff;
    border: 1px solid #e0e0e0;
    color: #212529;
    margin-right: auto;
    border-bottom-left-radius: 2px;
}
</style>

<div class="container my-4" style="max-width: 850px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold mb-1">🤖 AI Medical Assistant</h2>
            <p class="text-muted mb-0">Powered by OpenRouter / AI API for medical guidance (FR8).</p>
        </div>
        <div>
            <a href="ai_chat.php?action=clear" class="btn btn-outline-danger btn-sm me-2 fw-bold">Clear Chat</a>
            <a href="customer_dashboard.php" class="btn btn-outline-secondary btn-sm fw-bold">Dashboard</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-3">
            <div class="chat-box mb-3" id="chatWindow">
                <?php foreach ($_SESSION['ai_chat_history'] as $chat): ?>
                    <div class="d-flex flex-column <?= $chat['role'] === 'user' ? 'align-items-end' : 'align-items-start'; ?>">
                        <div class="chat-bubble <?= $chat['role'] === 'user' ? 'chat-user' : 'chat-assistant shadow-sm'; ?>">
                            <strong><?= $chat['role'] === 'user' ? 'You' : '⚡ AI Medical Assistant'; ?>:</strong><br>
                            <?= nl2br(htmlspecialchars($chat['message'])); ?>
                        </div>
                        <small class="text-muted mb-2 px-2" style="font-size: 11px;"><?= $chat['time']; ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <form action="ai_chat.php" method="POST" class="d-flex gap-2">
                <input type="text" name="user_query" class="form-control" placeholder="Ask about symptoms, medical usage, or general health info..." required autocomplete="off">
                <button type="submit" class="btn btn-success fw-bold px-4">Send</button>
            </form>
        </div>
        <div class="card-footer bg-light text-center text-muted small">
            ⚠️ <em>Disclaimer: AI-generated guidance is for general information (FR8). Consult a certified physician for official treatment.</em>
        </div>
    </div>
</div>

<script>
const chatBox = document.getElementById('chatWindow');
if (chatBox) {
    chatBox.scrollTop = chatBox.scrollHeight;
}
</script>

<?php
$conn->close();
include_once '../includes/footer.php';
ob_end_flush();
?>