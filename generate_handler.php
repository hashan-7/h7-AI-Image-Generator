<?php

session_start();

require_once 'config_secrets.php';
require_once 'db_connection.php';

header('Content-Type: application/json');

// --- Basic Sanity Check: Ensure cURL is enabled ---
if (!function_exists('curl_init')) {
    error_log("FATAL ERROR: cURL extension is not enabled in this PHP environment.");
    echo json_encode(['success' => false, 'message' => 'Server configuration error: cURL is not enabled.']);
    exit;
}

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

if (!$conn || $conn->connect_error) {
    error_log("Database connection failed before transaction.");
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit;
}

$conn->begin_transaction();

try {
    $stmt_refresh_check = $conn->prepare("SELECT daily_credits_remaining, daily_credits_refreshed_at FROM users WHERE id = ? FOR UPDATE");
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
        $do_refresh = true;
    } else {
        $interval = $now->diff($last_refresh);
        $hours_passed = ($interval->days * 24) + $interval->h;
        if ($hours_passed >= 24) {
            $do_refresh = true;
        }
    }

    if ($do_refresh) {
        $daily_credits = 3;
        $update_stmt = $conn->prepare("UPDATE users SET daily_credits_remaining = 3, daily_credits_refreshed_at = NOW() WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
             error_log("Failed to prepare credit refresh statement for user ID: {$user_id}");
        }
    }

    if ($daily_credits <= 0) {
        throw new Exception("You have used all your daily credits. Please wait until tomorrow.");
    }

    $daily_credits--;
    $stmt_update_credit = $conn->prepare("UPDATE users SET daily_credits_remaining = ? WHERE id = ?");
    if (!$stmt_update_credit) throw new Exception("DB Error (prepare update daily): " . $conn->error);
    $stmt_update_credit->bind_param("ii", $daily_credits, $user_id);

    $api_key = HUGGING_FACE_API_KEY;
    $models_to_try = HUGGING_FACE_MODELS;
    $image_data = null;
    $successful_model_name = '';

    foreach ($models_to_try as $model_url) {
        error_log("Attempting to generate image with model: " . $model_url);
        
        $curl = curl_init();
        $post_data = json_encode(['inputs' => $prompt]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $model_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $api_key,
                "Content-Type: application/json"
            ],
            // SSL verification is still disabled for this test
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response_body = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            error_log("cURL Error for model " . $model_url . ": " . $err);
            continue; 
        }

        if ($http_code == 200) {
            $image_data = $response_body;
            $successful_model_name = basename($model_url);
            error_log("Successfully generated image with model: " . $successful_model_name);
            break; 
        } else {
            // --- ENHANCED LOGGING ---
            // Log the detailed error response from the API
            error_log("API Error for model " . $model_url . " (HTTP {$http_code}). Full Response: " . $response_body);
        }
    }

    if ($image_data === null) {
        throw new Exception("Image generation failed. All available AI models are currently busy or unavailable. Please try again later.");
    }

    $image_filename = 'generated_img_' . $user_id . '_' . time() . '_' . uniqid() . '.png';
    $image_dir = 'generated_images';
    $image_save_path = $image_dir . '/' . $image_filename;

    if (!is_dir($image_dir)) {
        if (!mkdir($image_dir, 0777, true)) throw new Exception("Failed to create image directory.");
    }
    if (!is_writable($image_dir)) throw new Exception("Image directory is not writable.");

    if (file_put_contents($image_save_path, $image_data) === false) throw new Exception("Failed to save the generated image file.");

    $image_url = $image_save_path;

    if (!$stmt_update_credit->execute()) {
        throw new Exception("DB Error (execute update credit): " . $stmt_update_credit->error);
    }
    $stmt_update_credit->close();

    $stmt_insert_img = $conn->prepare("INSERT INTO images (user_id, image_url, prompt, api_used) VALUES (?, ?, ?, ?)");
    if (!$stmt_insert_img) throw new Exception("DB Error (prepare insert image): " . $conn->error);
    $stmt_insert_img->bind_param("isss", $user_id, $image_url, $prompt, $successful_model_name);
    if (!$stmt_insert_img->execute()) {
        error_log("DB Error (execute insert image): " . $stmt_insert_img->error);
    }
    $stmt_insert_img->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'image_url' => $image_url,
        'credits' => [
            'daily' => $daily_credits
        ]
    ]);
    exit;

} catch (Exception $e) {
    if ($conn->errno === 0) {
        $conn->rollback();
    }
    error_log("Generation Handler Exception for User ID {$user_id}: " . $e->getMessage());
    if (!headers_sent()) { header('Content-Type: application/json'); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} finally {
    if (isset($conn) && is_object($conn) && $conn->ping()) {
        $conn->close();
    }
}

?>
