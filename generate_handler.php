<?php

// Start session at the very beginning
session_start();

require_once 'config_secrets.php';

// Include the database connection file
require_once 'db_connection.php';

// --- Configuration & Security Checks ---
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in. Please login again.']);
    exit;
}
if (!isset($_POST['prompt']) || empty(trim($_POST['prompt']))) {
    echo json_encode(['success' => false, 'message' => 'Prompt cannot be empty.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$prompt = trim($_POST['prompt']);

// --- Stability AI API Key ---
$stabilityApiKey = STABILITY_API_KEY; // Use the constant from config_secrets.php


// --- Credit Check, Refresh & Deduction Logic ---
if (!$conn || $conn->connect_error) {
     error_log("Database connection failed before transaction.");
     echo json_encode(['success' => false, 'message' => 'Database connection error.']);
     exit;
}

$conn->begin_transaction();

try {
    // --- Check and Refresh Daily Credits ---
    // Select only necessary columns (permanent_credits removed)
    $stmt_refresh_check = $conn->prepare("SELECT daily_credits_remaining, daily_credits_refreshed_at FROM users WHERE id = ? FOR UPDATE"); // Lock row
    if(!$stmt_refresh_check) throw new Exception("DB Prepare Error (refresh check): " . $conn->error);
    $stmt_refresh_check->bind_param("i", $user_id);
    $stmt_refresh_check->execute();
    $result_refresh = $stmt_refresh_check->get_result();
    $user_refresh_data = $result_refresh->fetch_assoc();
    $stmt_refresh_check->close();

    if(!$user_refresh_data) {
        throw new Exception("User not found for credit check.");
    }

    $daily_credits = (int)$user_refresh_data['daily_credits_remaining'];
    $last_refresh_str = $user_refresh_data['daily_credits_refreshed_at'];
    $last_refresh = $last_refresh_str ? new DateTime($last_refresh_str) : null;
    $do_refresh = false;
    $now = new DateTime();

    if ($last_refresh === null) {
        $do_refresh = true; // Refresh if never refreshed
    } else {
        $interval = $now->diff($last_refresh);
        $hours_passed = ($interval->days * 24) + $interval->h;
        if ($hours_passed >= 24) {
            $do_refresh = true; // Refresh if 24+ hours passed
        }
    }

    if ($do_refresh) {
        $daily_credits = 3; // Reset credits to 3
        $update_stmt = $conn->prepare("UPDATE users SET daily_credits_remaining = 3, daily_credits_refreshed_at = NOW() WHERE id = ?");
         if ($update_stmt) {
             $update_stmt->bind_param("i", $user_id);
             $update_stmt->execute();
             $update_stmt->close();
         } else {
              error_log("Failed to prepare credit refresh statement for user ID: {$user_id}");
         }
    }
    // --- End Credit Refresh Check ---

    // --- Check if credits are available AFTER potential refresh ---
    if ($daily_credits <= 0) {
        // No permanent credits to check, only daily
        throw new Exception("You have used all your daily credits. Please wait until tomorrow.");
    }

    // --- Prepare to Deduct Credit (Only daily) ---
    $daily_credits--; // Decrement daily credits
    $stmt_update_credit = $conn->prepare("UPDATE users SET daily_credits_remaining = ? WHERE id = ?");
    if (!$stmt_update_credit) throw new Exception("DB Error (prepare update daily): " . $conn->error);
    $stmt_update_credit->bind_param("ii", $daily_credits, $user_id);


    // --- Call Stability AI API (v2beta Core Endpoint) ---
    $api_host = 'https://api.stability.ai';
    $api_endpoint = "/v2beta/stable-image/generate/core";

    $curl = curl_init();
    $post_fields = [
        'prompt' => $prompt,
        'output_format' => 'png',
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => $api_host . $api_endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $post_fields,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $stabilityApiKey,
            "Accept: application/json"
        ],
        // --- IMPORTANT for Localhost SSL Verification ---
        CURLOPT_CAINFO => 'C:/wamp64/bin/php/php8.2.18/cacert.pem', // Adjust path if needed
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // --- End SSL Fix ---
    ]);

    $response_body = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($err) throw new Exception("cURL Error calling Stability AI: " . $err);

    $response_data = json_decode($response_body, true);
    if (json_last_error() !== JSON_ERROR_NONE && !empty(trim($response_body))) {
         throw new Exception("Invalid response received from API (Not JSON). HTTP Code: {$http_code}. Response: " . substr(strip_tags($response_body), 0, 200));
    }

    // Check for successful image generation
    if ($http_code == 200 && isset($response_data['image'])) {

        $base64_image = $response_data['image'];
        $image_seed = $response_data['seed'] ?? 'unknown';

        // --- Save base64 image as a file ---
        $image_filename = 'generated_img_' . $user_id . '_' . time() . '_' . $image_seed . '.png';
        $image_dir = 'generated_images';
        $image_save_path = $image_dir . '/' . $image_filename;

        if (!is_dir($image_dir)) {
            if (!mkdir($image_dir, 0777, true)) throw new Exception("Failed to create image directory.");
        }
        if (!is_writable($image_dir)) throw new Exception("Image directory is not writable.");

        $image_data = base64_decode($base64_image);
        if ($image_data === false) throw new Exception("Failed to decode base64 image data from JSON.");
        if (file_put_contents($image_save_path, $image_data) === false) throw new Exception("Failed to save the generated image file.");

        $image_url = $image_save_path;

        // --- Deduct Credit (Execute update) ---
        if (!$stmt_update_credit->execute()) {
             throw new Exception("DB Error (execute update credit): " . $stmt_update_credit->error);
        }
        $stmt_update_credit->close();

        // --- Save Image Info to DB ---
        $api_name_to_save = 'stabilityai-core-v2';
        $stmt_insert_img = $conn->prepare("INSERT INTO images (user_id, image_url, prompt, api_used) VALUES (?, ?, ?, ?)");
        if (!$stmt_insert_img) throw new Exception("DB Error (prepare insert image): " . $conn->error);
        $stmt_insert_img->bind_param("isss", $user_id, $image_url, $prompt, $api_name_to_save);
        if (!$stmt_insert_img->execute()) {
             error_log("DB Error (execute insert image): " . $stmt_insert_img->error);
        }
        $stmt_insert_img->close();

        // Commit the transaction
        $conn->commit();

        // --- Send Success Response to Frontend ---
        echo json_encode([
            'success' => true,
            'image_url' => $image_url,
            'credits' => [
                // 'permanent' => 0, // Removed permanent credits
                'daily' => $daily_credits // Send the updated daily credit count
            ]
        ]);
        exit;

    } else {
        // Handle API errors
        $error_message = "Stability AI API Error (HTTP {$http_code})";
        if (isset($response_data['message'])) { $error_message .= ": " . $response_data['message']; }
        elseif (isset($response_data['errors'])) { $error_message .= ": " . implode(', ', $response_data['errors']); }
        elseif (!empty($response_body)) { $error_message .= ": Response - " . substr(strip_tags($response_body), 0, 150); }
        else { $error_message .= ": Empty or invalid response from API."; }
        throw new Exception($error_message);
    }

} catch (Exception $e) {
    // --- Handle Errors ---
    if ($conn->errno === 0) { // Rollback only if transaction was started
        $conn->rollback();
    }
    error_log("Generation Handler Exception for User ID {$user_id}: " . $e->getMessage());
    if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} finally {
    // Ensure connection is closed
    if (isset($conn) && is_object($conn) && $conn->ping()) {
        $conn->close();
    }
}

?>
