<?php
// Start session
session_start();

require_once 'config_secrets.php'; 

// Include the database connection file
require_once 'db_connection.php';

// Include Composer's autoloader to load the Google Client Library
require_once 'vendor/autoload.php';

// --- Configuration ---
// Now using constants from config_secrets.php
$googleClientId = GOOGLE_CLIENT_ID;
$googleClientSecret = GOOGLE_CLIENT_SECRET;
$redirectUri = GOOGLE_REDIRECT_URI;


// --- Initialize Google Client ---
$client = new Google_Client();
$client->setClientId($googleClientId);
$client->setClientSecret($googleClientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email"); // Request access to user's email address
$client->addScope("profile"); // Request access to user's basic profile info (name, picture)

// --- Process Google Response ---

// Check if Google sent back an authorization code
if (isset($_GET['code'])) {
    try {
        // Exchange the authorization code for an access token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        // Check if token fetch was successful and didn't return an error
        if (!isset($token['error'])) {
            // Set the access token for the client
            $client->setAccessToken($token['access_token']);

            // Get user profile information from Google using the access token
            $google_oauth = new Google_Service_Oauth2($client);
            $google_account_info = $google_oauth->userinfo->get();

            // Extract user details
            $google_id = $google_account_info->getId();
            $email = $google_account_info->getEmail();
            $name = $google_account_info->getName();
            $picture = $google_account_info->getPicture();

            // --- Database Interaction ---

            // Check if the database connection is valid
            if (!$conn || $conn->connect_error) {
                throw new Exception("Database connection error in callback.");
            }

            // Check if the user already exists in our database using their unique Google ID
            // Select only necessary columns for login/refresh check
            $stmt = $conn->prepare("SELECT id, daily_credits_remaining, daily_credits_refreshed_at FROM users WHERE google_id = ?");
            if (!$stmt) throw new Exception("DB Prepare Error (select user): " . $conn->error);

            $stmt->bind_param("s", $google_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $user_id = null;
            $user_data = null; // To store fetched user data

            if ($result->num_rows > 0) {
                // --- User Exists: Log them in ---
                $user_data = $result->fetch_assoc();
                $user_id = $user_data['id'];
                // We will check for credit refresh later

            } else {
                // --- New User: Register them ---
                // Insert new user with 3 daily credits, refreshed now
                // Removed permanent_credits column from INSERT statement
                $insert_stmt = $conn->prepare("INSERT INTO users (google_id, email, name, picture_url, daily_credits_remaining, daily_credits_refreshed_at) VALUES (?, ?, ?, ?, 3, NOW())");
                if (!$insert_stmt) throw new Exception("DB Prepare Error (insert user): " . $conn->error);

                $insert_stmt->bind_param("ssss", $google_id, $email, $name, $picture);

                if ($insert_stmt->execute()) {
                    $user_id = $conn->insert_id; // Get the ID of the newly created user
                    // For a new user, credits are already set and refreshed
                    $user_data = [ // Create a minimal user data array for session setting
                        'daily_credits_remaining' => 3,
                        'daily_credits_refreshed_at' => date('Y-m-d H:i:s') // Set current time approx
                    ];
                } else {
                    throw new Exception("Error registering user: " . $insert_stmt->error);
                }
                $insert_stmt->close();
            }
            $stmt->close(); // Close the select statement


            // --- Perform Daily Credit Refresh Check (Only for existing users) ---
            if ($user_data && $user_id && $result->num_rows > 0) { // Check if it was an existing user
                 $now = new DateTime();
                 $last_refresh_str = $user_data['daily_credits_refreshed_at'];
                 $last_refresh = $last_refresh_str ? new DateTime($last_refresh_str) : null;
                 $do_refresh = false;

                 if ($last_refresh === null) {
                     $do_refresh = true; // Refresh if never refreshed (shouldn't happen often with new logic)
                 } else {
                     $interval = $now->diff($last_refresh);
                     $hours_passed = ($interval->days * 24) + $interval->h;
                     if ($hours_passed >= 24) {
                         $do_refresh = true; // Refresh if 24+ hours passed
                     }
                 }

                 if ($do_refresh) {
                     // Refresh daily credits to 3 and update the timestamp
                     $update_stmt = $conn->prepare("UPDATE users SET daily_credits_remaining = 3, daily_credits_refreshed_at = NOW() WHERE id = ?");
                      if ($update_stmt) {
                          $update_stmt->bind_param("i", $user_id);
                          $update_stmt->execute();
                          $update_stmt->close();
                      } else {
                           error_log("Failed to prepare credit refresh statement for user ID: {$user_id}");
                           // Consider how to handle this error - maybe proceed without refresh?
                      }
                 }
            }
            // --- End Credit Refresh Check ---


            // --- Set Session and Redirect ---
            if ($user_id) { // Ensure we have a user ID
                $_SESSION['google_user_id'] = $google_id; // Google's unique ID
                $_SESSION['user_id'] = $user_id;         // Your application's internal user ID
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_picture'] = $picture;

                // Redirect the user to the main dashboard
                header('Location: dashboard.php');
                exit; // Stop script execution
            } else {
                 // This case indicates a problem during registration/login
                 throw new Exception("User ID not set after registration/login attempt.");
            }


        } else {
            // Handle error when Google returns an error instead of a token
            $error_description = isset($token['error_description']) ? $token['error_description'] : 'Unknown token error from Google';
            throw new Exception("Error fetching access token: " . $error_description);
        }
    } catch (Exception $e) {
        // --- Handle All Exceptions ---
        error_log('Google Callback Exception: ' .  $e->getMessage());
        // Redirect to index page with a generic error message
        // Avoid showing specific error details to the user in production
        header('Location: index.php?error=callback_failed');
        exit;
    } finally {
         // Ensure connection is closed if it was opened and is valid
         if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
            $conn->close();
        }
    }
} else {
    // Handle case where the 'code' parameter is missing in the URL
    error_log("Authorization code not found in callback URL.");
     header('Location: index.php?error=no_code');
     exit;
}

?>
