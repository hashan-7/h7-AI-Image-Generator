<?php
// Start session at the very beginning
session_start();

// Include the database connection file
require_once 'db_connection.php';

// Set header to return JSON response
header('Content-Type: application/json');

// --- Security & Input Validation ---

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// Check if image_id is received and is a valid integer
if (!isset($_POST['image_id']) || !filter_var($_POST['image_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'Invalid image ID provided.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$image_id = (int)$_POST['image_id'];

// --- Database Deletion Logic ---

// Check if $conn is valid before starting transaction
if (!$conn || $conn->connect_error) {
     error_log("Database connection failed before delete transaction.");
     echo json_encode(['success' => false, 'message' => 'Database connection error.']);
     exit;
}

$conn->begin_transaction(); // Start transaction

try {
    // 1. Verify ownership and get the image file path before deleting the record
    $image_url = null;
    $stmt_select = $conn->prepare("SELECT image_url FROM images WHERE id = ? AND user_id = ?");
    if (!$stmt_select) throw new Exception("DB Error (prepare select): " . $conn->error);

    $stmt_select->bind_param("ii", $image_id, $user_id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();

    if ($result_select->num_rows === 1) {
        $image_row = $result_select->fetch_assoc();
        $image_url = $image_row['image_url']; // Get the relative path e.g., "generated_images/image.png"
    } else {
        // Image not found or does not belong to the user
        throw new Exception("Image not found or you do not have permission to delete it.");
    }
    $stmt_select->close();

    // 2. Delete the database record
    $stmt_delete = $conn->prepare("DELETE FROM images WHERE id = ? AND user_id = ?");
     if (!$stmt_delete) throw new Exception("DB Error (prepare delete): " . $conn->error);

    $stmt_delete->bind_param("ii", $image_id, $user_id);

    if (!$stmt_delete->execute()) {
        // Database deletion failed
        throw new Exception("Failed to delete image record from database.");
    }

    // Check if any row was actually deleted
    if ($stmt_delete->affected_rows === 0) {
         // This shouldn't happen if the select worked, but good to check
         throw new Exception("No image record was deleted (already deleted or permission issue).");
    }
    $stmt_delete->close();

    // 3. Delete the actual image file from the server
    if ($image_url) {
        // Construct the full server path (assuming the script is in the project root)
        // IMPORTANT: Adjust this path based on your actual server structure if needed.
        $file_path = __DIR__ . '/' . $image_url; // Use __DIR__ for reliability

        // Check if the file exists before attempting to delete
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                // File deletion failed - Log this error, but maybe don't fail the whole request?
                // Or decide if DB record should be kept if file deletion fails.
                // For now, we'll commit the DB change but log the file error.
                error_log("Failed to delete image file: {$file_path} for User ID {$user_id}");
                // Optionally: throw new Exception("Database record deleted, but failed to delete image file.");
            }
        } else {
            // File doesn't exist - maybe already deleted? Log this.
            error_log("Image file not found for deletion: {$file_path} for User ID {$user_id}");
        }
    } else {
         // This case should ideally not be reached if the select worked
         error_log("Image URL was null, could not attempt file deletion for Image ID {$image_id}");
    }

    // 4. Commit the transaction (only DB deletion is part of the transaction)
    $conn->commit();

    // --- Send Success Response ---
    echo json_encode(['success' => true, 'message' => 'Image deleted successfully.']);
    exit;

} catch (Exception $e) {
    // --- Handle Errors ---
    $conn->rollback(); // Rollback database changes on any error
    error_log("Delete Image Error for User ID {$user_id}, Image ID {$image_id}: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
} finally {
    // Ensure connection is closed
    if (isset($conn) && is_object($conn) && $conn->ping()) {
        $conn->close();
    }
}

?>
