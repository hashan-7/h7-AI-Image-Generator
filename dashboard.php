<?php
// Start session at the very beginning
session_start();

// Check if the user is logged in, if not, redirect to index.php
if (!isset($_SESSION['user_id']) || !isset($_SESSION['google_user_id'])) {
    header('Location: index.php');
    exit;
}

// Include the database connection file
require_once 'db_connection.php';

// --- Fetch latest user data & Check/Perform Credit Refresh ---
$user_id = $_SESSION['user_id'];
$daily_credits = 0;
$last_refresh_timestamp = null; // Timestamp of last refresh
$next_refresh_timestamp = null; // Timestamp when next refresh should happen
$user_name = $_SESSION['user_name'] ?? 'User'; // Get from session first
$user_picture = $_SESSION['user_picture'] ?? 'images/default-avatar.png'; // Default avatar

// Use try-catch for database operations
try {
    // Ensure $conn is a valid mysqli object before proceeding
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {

        // --- Check and Refresh Daily Credits ON PAGE LOAD ---
        $conn->begin_transaction(); // Use transaction for read-then-update

        // Lock the row for update to prevent race conditions
        $stmt_refresh_check = $conn->prepare("SELECT name, picture_url, daily_credits_remaining, daily_credits_refreshed_at FROM users WHERE id = ? FOR UPDATE");
        if(!$stmt_refresh_check) throw new Exception("DB Prepare Error (refresh check): " . $conn->error);
        $stmt_refresh_check->bind_param("i", $user_id);
        $stmt_refresh_check->execute();
        $result_refresh = $stmt_refresh_check->get_result();
        $user_data = $result_refresh->fetch_assoc();
        $stmt_refresh_check->close();

        if(!$user_data) {
             session_destroy(); // Destroy potentially invalid session
             throw new Exception("User not found for credit check. Session cleared.");
        }

        // Update session variables
        $_SESSION['user_name'] = $user_data['name'];
        $_SESSION['user_picture'] = $user_data['picture_url'];
        $user_name = $user_data['name']; // Use fetched name
        $user_picture = $user_data['picture_url']; // Use fetched picture

        $daily_credits = (int)$user_data['daily_credits_remaining'];
        $last_refresh_str = $user_data['daily_credits_refreshed_at'];
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
                 $last_refresh = new DateTime(); // Update last refresh time for calculation below
                 error_log("Credits refreshed for user ID: {$user_id}"); // Log refresh
             } else {
                  error_log("Failed to prepare credit refresh statement for user ID: {$user_id}");
             }
        }
        $conn->commit(); // Commit transaction
        // --- End Credit Refresh Check ---


        // Calculate next refresh time if credits are 0 and last refresh time is known
        if($daily_credits <= 0 && $last_refresh !== null) {
            $next_refresh_time = clone $last_refresh;
            $next_refresh_time->add(new DateInterval('PT24H')); // Add 24 hours
            $next_refresh_timestamp = $next_refresh_time->getTimestamp(); // Get Unix timestamp for JS
        }


    } else {
         throw new Exception("Database connection is not valid for fetching images.");
    }
} catch (Exception $e) {
     error_log("Error fetching/refreshing user data for User ID {$user_id}: " . $e->getMessage());
     $daily_credits = 0; // Default value on error
     if(isset($conn) && $conn->errno !== 0 && $conn->ping()) $conn->rollback(); // Rollback if transaction started and connection is valid
} finally {
    // Ensure connection is closed if it was opened and is valid
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        $conn->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>h7 Dashboard - AI Image Generation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #00f2ea; /* Cyan/Turquoise */
            --secondary-color: #a050ff; /* Brighter Violet */
            --dark-bg: #0a0a13;
            --card-bg: #161622;
            --input-bg: #222230;
            --text-light: #e8e8f0;
            --text-muted-dark: #9090a8;
            --gradient-main: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            --gradient-border: linear-gradient(135deg, rgba(0, 242, 234, 0.5) 0%, rgba(160, 80, 255, 0.5) 100%);
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
            padding-top: 80px;
            background-image: radial-gradient(circle at 1% 1%, rgba(0, 242, 234, 0.05) 0px, transparent 50%),
                              radial-gradient(circle at 99% 99%, rgba(160, 80, 255, 0.05) 0px, transparent 50%);
            background-attachment: fixed;
        }

        /* --- Navbar Styles --- */
        .navbar { background-color: rgba(10, 10, 19, 0.8); backdrop-filter: blur(15px); padding-top: 0.8rem; padding-bottom: 0.8rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .navbar-brand img { height: 45px; width: auto; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), filter 0.3s ease; filter: brightness(1); }
        .navbar-brand:hover img { transform: scale(1.1); filter: brightness(1.15); }
        .profile-pic { width: 40px; height: 40px; border-radius: 50%; margin-left: 15px; border: 2px solid var(--primary-color); object-fit: cover; }
        .nav-link { color: var(--text-muted-dark) !important; font-weight: 500; transition: color 0.3s ease, transform 0.2s ease; padding: 0.5rem 1rem !important; }
        .nav-link:hover { color: var(--primary-color) !important; transform: translateY(-2px); }
        .nav-link.active { color: var(--primary-color) !important; font-weight: 600; }
        .dropdown-menu { background-color: var(--card-bg); border: 1px solid rgba(0, 242, 234, 0.2); border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .dropdown-item { color: var(--text-light); padding: 0.5rem 1rem; }
        .dropdown-item:hover { background-color: var(--input-bg); color: var(--primary-color); }

        /* --- Main Content & Card Styles --- */
        .dashboard-container { margin-top: 50px; }
        .styled-card { background-color: var(--card-bg); padding: 35px; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.05); margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .styled-card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3); }
        .card-header-custom { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.6rem; color: var(--primary-color); margin-bottom: 25px; padding-bottom: 10px; border-bottom: 1px solid rgba(0, 242, 234, 0.2); display: flex; align-items: center; }
        .card-header-custom i { margin-right: 12px; font-size: 1.8rem; }
        .credit-info p { margin-bottom: 5px; font-size: 1rem; color: var(--text-light); }
        .credit-info span { font-weight: 700; font-size: 1.3rem; background: var(--gradient-main); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; text-fill-color: transparent; }
        .credit-info .text-muted { font-size: 0.85rem; color: var(--text-muted-dark) !important; display: block; margin-top: 5px; }
        .form-label { font-weight: 600; margin-bottom: 8px; color: var(--text-muted-dark); font-size: 0.9rem; text-transform: uppercase; }
        .form-control, .form-select { background-color: var(--input-bg); color: var(--text-light); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; padding: 14px 18px; font-size: 1rem; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
        .form-control:focus, .form-select:focus { background-color: var(--input-bg); color: var(--text-light); border-color: var(--primary-color); box-shadow: 0 0 15px rgba(0, 242, 234, 0.15); }
        .form-control::placeholder { color: var(--text-muted-dark); opacity: 0.6; }
        textarea.form-control { min-height: 120px; resize: vertical; }
        .generate-btn { padding: 15px 40px; font-size: 1.2rem; font-weight: 700; border-radius: 50px; background: var(--gradient-main); color: #ffffff; border: none; cursor: pointer; transition: all 0.4s ease; box-shadow: 0 6px 20px rgba(0, 242, 234, 0.3); width: 100%; margin-top: 25px; letter-spacing: 0.5px; min-height: 60px; display: flex; align-items: center; justify-content: center; }
        .generate-btn:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 10px 25px rgba(160, 80, 255, 0.4); filter: brightness(1.1); }
        .generate-btn:disabled { background: linear-gradient(135deg, #555, #333); cursor: not-allowed; box-shadow: none; color: #aaa; }
        .generate-btn .spinner-border-sm { margin-right: 8px; }
        .generate-btn .countdown-text { font-size: 0.9rem; opacity: 0.8; margin-left: 5px; }

        /* --- Result Area --- */
        .result-area {
            background-color: rgba(0,0,0,0.2); padding: 20px; border-radius: 16px; margin-top: 0;
            min-height: 400px; display: flex; flex-direction: column; justify-content: center; align-items: center;
            border: 2px dashed rgba(255, 255, 255, 0.1); position: relative; overflow: hidden;
            transition: border-color 0.3s ease, background-color 0.3s ease;
        }
        .result-area.loading { border-color: var(--primary-color); background-color: rgba(0, 242, 234, 0.02); }
        .result-area img { max-width: 100%; max-height: 450px; border-radius: 10px; display: none; box-shadow: 0 5px 25px rgba(0,0,0,0.3); animation: fadeIn 0.5s ease-out; margin-bottom: 20px; }
        .result-area .placeholder-text { color: var(--text-muted-dark); font-size: 1.1rem; font-style: italic; transition: opacity 0.3s ease; }
        .result-area.loading .placeholder-text,
        .result-area.counting-down .placeholder-text { opacity: 0; display: none; } /* Hide placeholder during load AND countdown */

        /* --- Loading Animation Styles (Keep as before) --- */
        .loading-animation { width: 100px; height: 100px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: none; z-index: 10; }
        .orb { width: 100%; height: 100%; border-radius: 50%; background: radial-gradient(circle, var(--primary-color) 10%, rgba(0, 242, 234, 0.3) 40%, rgba(160, 80, 255, 0.1) 70%, transparent 80%); position: absolute; top: 0; left: 0; animation: pulse-orb 2s infinite ease-in-out; box-shadow: 0 0 30px rgba(0, 242, 234, 0.4), 0 0 50px rgba(160, 80, 255, 0.2); }
        .orb::before, .orb::after { content: ''; position: absolute; border-radius: 50%; border: 2px solid; opacity: 0.6; }
        .orb::before { top: -10px; left: -10px; right: -10px; bottom: -10px; border-color: rgba(0, 242, 234, 0.5); animation: pulse-ring 2s infinite ease-in-out 0.2s; }
        .orb::after { top: 10px; left: 10px; right: 10px; bottom: 10px; border-color: rgba(160, 80, 255, 0.5); animation: pulse-ring 2s infinite ease-in-out 0.4s; }
        @keyframes pulse-orb { 0%, 100% { transform: scale(0.9); opacity: 0.7; } 50% { transform: scale(1); opacity: 1; } }
        @keyframes pulse-ring { 0%, 100% { transform: scale(1); opacity: 0.3; } 50% { transform: scale(1.1); opacity: 0.7; } }

        /* --- NEW Next-Gen Countdown Display --- */
        .countdown-display {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 11; /* Above loading orb if needed */
            display: none; /* Hidden by default */
            color: var(--text-light);
        }
        .countdown-display .countdown-label {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-muted-dark);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 15px; /* Space below label */
        }
        .countdown-display .timer {
            font-family: 'Orbitron', sans-serif; /* Digital-style font */
            font-size: 3.5rem; /* Large timer text */
            font-weight: 700;
            line-height: 1;
            color: var(--primary-color);
            text-shadow: 0 0 15px rgba(0, 242, 234, 0.5), 0 0 5px rgba(0, 242, 234, 0.7);
            background: rgba(10, 10, 19, 0.5); /* Semi-transparent dark background */
            padding: 15px 30px;
            border-radius: 10px;
            border: 1px solid rgba(0, 242, 234, 0.2);
            backdrop-filter: blur(3px);
        }
        .countdown-display .timer span { /* Style individual HH, MM, SS */
            display: inline-block;
            min-width: 60px; /* Ensure segments have width */
        }
        /* --- End Countdown Display --- */

        /* --- Download Section --- */
        #download-section { display: none; margin-top: 20px; padding: 20px; background-color: rgba(var(--card-bg), 0.5); border-radius: 10px; text-align: center; }
        #download-section h6 { color: var(--primary-color); margin-bottom: 15px; font-weight: 600; }
        .download-btn { background: none; border: 1px solid var(--primary-color); color: var(--primary-color); padding: 8px 15px; border-radius: 5px; margin: 0 5px; font-size: 0.9rem; cursor: pointer; transition: background-color 0.3s ease, color 0.3s ease; text-decoration: none; }
        .download-btn:hover { background-color: var(--primary-color); color: var(--dark-bg); }
        .download-btn.disabled { border-color: var(--text-muted-dark); color: var(--text-muted-dark); cursor: not-allowed; opacity: 0.6; }
        .download-btn.disabled:hover { background-color: transparent; }
        #error-message { background-color: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.5); color: #f8d7da; border-radius: 8px; padding: 15px; }
        .scroll-animate { opacity: 0; transition: opacity 1s cubic-bezier(0.165, 0.84, 0.44, 1), transform 1s cubic-bezier(0.165, 0.84, 0.44, 1); }
        .scroll-animate.fade-in-up { transform: translateY(50px); }
        .scroll-animate.zoom-in { transform: scale(0.9); }
        .scroll-animate.visible { opacity: 1; transform: translateY(0) scale(1); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
       <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/h7-logo.jpg" alt="h7 Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gallery.php">My Gallery</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($user_name); ?>
                            <img src="<?php echo htmlspecialchars($user_picture); ?>" alt="Profile" class="profile-pic">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container dashboard-container">
        <div class="row g-4">
            <div class="col-lg-5 order-lg-1">
                 <div class="styled-card credit-info scroll-animate fade-in-up" data-animation-delay="0.1">
                    <h5 class="card-header-custom"><i class="bi bi-coin"></i>Daily Credits</h5>
                    <p>Available: <span id="daily-credits"><?php echo $daily_credits; ?></span> / 3</p>
                     <small class="text-muted">(Refreshes approx. 24h after last use/refresh)</small>
                </div>

                <div class="styled-card generation-card scroll-animate fade-in-up" data-animation-delay="0.2">
                    <h2 class="card-header-custom"><i class="bi bi-stars"></i>Generate Image</h2>
                    <form id="generate-form">
                        <div class="mb-3 scroll-animate fade-in-up" data-animation-delay="0.3">
                            <label for="prompt" class="form-label">Your Prompt</label>
                            <textarea class="form-control" id="prompt" name="prompt" rows="5" placeholder="e.g., ancient library hidden in a nebula, cosmic horror style..." required></textarea>
                        </div>
                        <button type="submit" class="generate-btn scroll-animate fade-in-up" data-animation-delay="0.4" id="generate-button">
                            </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7 order-lg-2">
                <div class="result-area styled-card scroll-animate zoom-in" data-animation-delay="0.3" id="result-area">
                    <div class="loading-animation" id="loading-spinner">
                        <div class="orb"></div>
                    </div>
                    <div class="countdown-display" id="countdown-display">
                        <div class="countdown-label">Next Credits Available In</div>
                        <div class="timer" id="timer-display">00:00:00</div>
                    </div>
                    <img src="" alt="Generated AI Image" id="result-image">
                    <p class="placeholder-text" id="placeholder-text">Your generated image will appear here...</p>
                    <div id="download-section">
                        <h6>Download Image:</h6>
                        <a href="#" id="download-original" class="download-btn" download="h7_generated_image.png">
                           <i class="bi bi-download me-1"></i> Original
                        </a>
                        <button class="download-btn disabled" disabled title="Resolution options coming soon!">Medium</button>
                        <button class="download-btn disabled" disabled title="Resolution options coming soon!">Small</button>
                    </div>
                </div>
                 <div id="error-message" class="alert alert-danger mt-3 d-none scroll-animate fade-in-up" data-animation-delay="0.4" role="alert"></div>
            </div>
        </div>
    </div>

    <footer class="footer mt-5" style="background-color: #0a0a10; color: var(--text-muted-dark); padding: 50px 0; text-align: center; font-size: 0.9rem; border-top: 1px solid rgba(255, 255, 255, 0.05);">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> h7 Visionary AI. All Rights Reserved.</p>
        </div>
    </footer>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
    <script>
        // Initialize Lightbox
        if (typeof lightbox !== 'undefined') {
            lightbox.option({ 'resizeDuration': 200, 'wrapAround': true, 'fadeDuration': 300 });
        } else {
            console.error("Lightbox script not loaded correctly.");
        }

        // --- Dashboard Elements ---
        const generateForm = document.getElementById('generate-form');
        const generateButton = document.getElementById('generate-button');
        const resultArea = document.getElementById('result-area');
        const resultImage = document.getElementById('result-image');
        const loadingSpinner = document.getElementById('loading-spinner');
        const placeholderText = document.getElementById('placeholder-text');
        const errorMessageDiv = document.getElementById('error-message');
        const dailyCreditsSpan = document.getElementById('daily-credits');
        const downloadSection = document.getElementById('download-section');
        const downloadOriginalBtn = document.getElementById('download-original');
        const countdownDisplay = document.getElementById('countdown-display'); // Get countdown display div
        const timerDisplay = document.getElementById('timer-display'); // Get timer text element

        let currentImageUrl = null;
        let countdownInterval = null; // Variable to hold the interval timer

        // --- Initial State from PHP ---
        let currentDailyCredits = <?php echo $daily_credits; ?>;
        const nextRefreshTimestamp = <?php echo $next_refresh_timestamp ? $next_refresh_timestamp : 'null'; ?>;

        // --- Countdown Timer Function ---
        function startCountdown(targetTimestamp) {
            // Clear any existing timer
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }

            generateButton.disabled = true; // Disable generate button
            countdownDisplay.style.display = 'block'; // Show the countdown display in result area
            resultArea.classList.add('counting-down'); // Add class to result area
            placeholderText.style.display = 'none'; // Ensure placeholder is hidden
            loadingSpinner.style.display = 'none'; // Ensure loading spinner is hidden
            resultImage.style.display = 'none'; // Ensure image is hidden


            countdownInterval = setInterval(() => {
                const now = Math.floor(Date.now() / 1000); // Current time in seconds
                const remainingSeconds = targetTimestamp - now;

                if (remainingSeconds <= 0) {
                    clearInterval(countdownInterval); // Stop timer
                    generateButton.disabled = false; // Enable button
                    generateButton.innerHTML = 'Generate Image (<span id="generate-cost">1</span> Credit)';
                    countdownDisplay.style.display = 'none'; // Hide countdown display
                    resultArea.classList.remove('counting-down');
                    placeholderText.style.display = 'block'; // Show placeholder again
                    dailyCreditsSpan.textContent = '3'; // Optimistically update UI
                    currentDailyCredits = 3; // Update local count
                } else {
                    // Calculate HH:MM:SS
                    const hours = Math.floor(remainingSeconds / 3600);
                    const minutes = Math.floor((remainingSeconds % 3600) / 60);
                    const seconds = remainingSeconds % 60;

                    // Format time with leading zeros
                    const formattedTime =
                        String(hours).padStart(2, '0') + ':' +
                        String(minutes).padStart(2, '0') + ':' +
                        String(seconds).padStart(2, '0');

                    // Update button text AND the timer display
                    generateButton.innerHTML = `Next Credits in <span class="countdown-text">${formattedTime}</span>`;
                    timerDisplay.textContent = formattedTime; // Update the next-gen timer display
                }
            }, 1000); // Run every second
        }

        // --- Initial Button State ---
        function setInitialButtonState() {
             if (currentDailyCredits <= 0 && nextRefreshTimestamp) {
                 const now = Math.floor(Date.now() / 1000);
                 if (nextRefreshTimestamp > now) {
                     startCountdown(nextRefreshTimestamp); // Start countdown if time remaining
                 } else {
                     // Time is already up, but credits might not be refreshed in DB yet
                     generateButton.disabled = false;
                     generateButton.innerHTML = 'Generate Image (<span id="generate-cost">1</span> Credit)';
                     placeholderText.style.display = 'block'; // Ensure placeholder is visible if no countdown
                     countdownDisplay.style.display = 'none';
                 }
             } else if (currentDailyCredits <= 0) {
                  // Out of credits, no refresh time known
                  generateButton.disabled = true;
                  generateButton.innerHTML = 'No Credits Available';
                  placeholderText.style.display = 'block'; // Ensure placeholder is visible
                  countdownDisplay.style.display = 'none'; // Ensure countdown is hidden
             }
             else {
                 generateButton.disabled = false;
                 generateButton.innerHTML = 'Generate Image (<span id="generate-cost">1</span> Credit)';
                 placeholderText.style.display = 'block'; // Ensure placeholder is visible
                 countdownDisplay.style.display = 'none'; // Ensure countdown is hidden
             }
        }

        // Set initial state when page loads
        setInitialButtonState();


        // --- Form Submission Logic ---
        generateForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            // Clear existing countdown if user tries to generate
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            // Hide countdown display when starting generation
            countdownDisplay.style.display = 'none';
            resultArea.classList.remove('counting-down');


            // --- UI Updates - Start Loading ---
            generateButton.disabled = true;
            generateButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
            resultImage.style.display = 'none';
            placeholderText.style.display = 'none'; // Hide placeholder during loading
            loadingSpinner.style.display = 'block'; // Show loading animation
            resultArea.classList.add('loading');
            errorMessageDiv.classList.add('d-none');
            downloadSection.style.display = 'none';
            currentImageUrl = null;
            downloadOriginalBtn.href = '#';

            const prompt = document.getElementById('prompt').value;
            const formData = new FormData();
            formData.append('prompt', prompt);

            try {
                const response = await fetch('generate_handler.php', {
                    method: 'POST',
                    body: formData
                });
                const responseClone = response.clone();

                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error("DEBUG: Failed to parse JSON response:", jsonError);
                    const textResponse = await responseClone.text();
                    console.error("DEBUG: Response text:", textResponse);
                    throw new Error("Received an invalid response from the server.");
                }


                if (response.ok && data.success && data.image_url) {
                    currentImageUrl = data.image_url;
                    resultImage.src = currentImageUrl;
                    resultImage.onload = () => {
                        resultImage.style.display = 'block';
                        loadingSpinner.style.display = 'none';
                        resultArea.classList.remove('loading');
                        downloadSection.style.display = 'block';
                        downloadOriginalBtn.href = currentImageUrl;
                    };
                     resultImage.onerror = () => {
                         throw new Error('Failed to load the generated image.');
                     };

                    // Update credits based on response
                    if (data.credits) {
                        currentDailyCredits = data.credits.daily;
                        dailyCreditsSpan.textContent = currentDailyCredits;
                         // Check if user is now out of credits and start countdown if needed
                        if (currentDailyCredits <= 0) {
                             // Estimate next refresh time (backend should ideally provide this)
                             const estimatedNextRefresh = Math.floor(Date.now() / 1000) + (24 * 60 * 60);
                             startCountdown(estimatedNextRefresh);
                        } else {
                             setInitialButtonState(); // Reset button if credits remain
                        }
                    } else {
                         setInitialButtonState();
                    }


                } else {
                     // Handle backend error message (e.g., out of credits)
                     if (data.message && data.message.toLowerCase().includes("credits")) {
                         if (nextRefreshTimestamp) {
                             const now = Math.floor(Date.now() / 1000);
                             if (nextRefreshTimestamp > now) {
                                 startCountdown(nextRefreshTimestamp); // Start precise countdown
                             } else {
                                 generateButton.disabled = true;
                                 generateButton.innerHTML = 'No Credits Available';
                                 placeholderText.style.display = 'block'; // Show placeholder if error and no countdown
                             }
                         } else {
                             generateButton.disabled = true;
                             generateButton.innerHTML = 'No Credits Available';
                             placeholderText.style.display = 'block';
                         }
                         throw new Error(data.message);
                     } else {
                        throw new Error(data.message || 'Image generation failed. Please try again.');
                     }
                }

            } catch (error) {
                console.error('Error:', error);
                loadingSpinner.style.display = 'none';
                placeholderText.style.display = 'block';
                placeholderText.textContent = 'Generation failed.';
                errorMessageDiv.textContent = "Error: " + error.message;
                errorMessageDiv.classList.remove('d-none');
                resultArea.classList.remove('loading');
                downloadSection.style.display = 'none';
                // Reset button state on error (unless it was an out-of-credits error handled above)
                if (!error.message.toLowerCase().includes("credits")) {
                     setInitialButtonState();
                }
            } finally {
                // Only reset button text here if NOT in countdown or loading state
                 if (!countdownInterval && generateButton.disabled === false) {
                    if(currentDailyCredits > 0) {
                        generateButton.innerHTML = 'Generate Image (<span id="generate-cost">1</span> Credit)';
                    } else {
                         // Re-check if countdown should start (e.g., if error occurred before starting it)
                         setInitialButtonState();
                    }
                 }
            }
        });

         // Scroll animations
         const animatedElements = document.querySelectorAll('.scroll-animate');
         const observerOptions = { root: null, rootMargin: '0px', threshold: 0.15 };
         const observer = new IntersectionObserver((entries, observer) => {
             entries.forEach(entry => {
                 if (entry.isIntersecting) {
                     const delay = entry.target.dataset.animationDelay || '0';
                     entry.target.style.transitionDelay = `${delay}s`;
                     entry.target.classList.add('visible');
                     observer.unobserve(entry.target);
                 }
             });
         }, observerOptions);
         setTimeout(() => { animatedElements.forEach(el => { observer.observe(el); }); }, 100);

    </script>
</body>
</html>
