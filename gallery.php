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

// --- Fetch User's Generated Images ---
$user_id = $_SESSION['user_id'];
$images = []; // Initialize an empty array to store image data
$gallery_error = null; // Initialize error variable

// Use try-catch for database operations
try {
    // Ensure $conn is a valid mysqli object before proceeding
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $stmt = $conn->prepare("SELECT id, image_url, prompt, created_at FROM images WHERE user_id = ? ORDER BY created_at DESC");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $images[] = $row;
            }
            $stmt->close();
        } else {
            throw new Exception("Failed to prepare statement to fetch images: " . $conn->error);
        }
    } else {
         throw new Exception("Database connection is not valid for fetching images.");
    }
} catch (Exception $e) {
     error_log("Error fetching user images for User ID {$user_id}: " . $e->getMessage());
     $gallery_error = "Could not load gallery images due to a server error.";
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
    <title>h7 Gallery - Your AI Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css" />
    <style>
        /* Previous CSS styles remain the same */
        :root { --primary-color: #00f2ea; --secondary-color: #a050ff; --dark-bg: #0a0a13; --card-bg: #161622; --input-bg: #222230; --text-light: #e8e8f0; --text-muted-dark: #9090a8; --gradient-main: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background-color: var(--dark-bg); color: var(--text-light); padding-top: 80px; background-image: radial-gradient(circle at 1% 1%, rgba(0, 242, 234, 0.05) 0px, transparent 50%), radial-gradient(circle at 99% 99%, rgba(160, 80, 255, 0.05) 0px, transparent 50%); background-attachment: fixed; }
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
        .gallery-container { margin-top: 50px; }
        .gallery-title { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 2.8rem; color: var(--primary-color); text-align: center; margin-bottom: 60px; text-shadow: 0 0 10px rgba(0, 242, 234, 0.3); }
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .gallery-item { background-color: var(--card-bg); border-radius: 15px; overflow: hidden; position: relative; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); transition: transform 0.3s ease, box-shadow 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.05); }
        .gallery-item:hover { transform: translateY(-8px) scale(1.02); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3); }
        .gallery-image-link { display: block; cursor: pointer; }
        .gallery-item img { width: 100%; height: 250px; object-fit: cover; display: block; transition: transform 0.3s ease; background-color: var(--input-bg); }
        .gallery-item:hover img { transform: scale(1.05); }
        .gallery-item-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(to top, rgba(16, 16, 25, 0.95) 0%, transparent 100%); padding: 15px; opacity: 0; transition: opacity 0.3s ease; color: var(--text-light); }
        .gallery-item:hover .gallery-item-overlay { opacity: 1; }
        .gallery-item-prompt { font-size: 0.8rem; color: var(--text-muted-dark); margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
        .gallery-item-actions { display: flex; justify-content: space-between; align-items: center; gap: 5px; }
        .gallery-action-btn { border: none; border-radius: 5px; padding: 5px 10px; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: background-color 0.3s ease, color 0.3s ease, opacity 0.3s ease; display: inline-flex; align-items: center; }
        .gallery-download-btn { background: var(--primary-color); color: var(--dark-bg); }
        .gallery-download-btn:hover { background-color: var(--secondary-color); color: #fff; }
        .gallery-delete-btn { background: none; color: #dc3545; border: 1px solid #dc3545; }
        .gallery-delete-btn:hover { background-color: #dc3545; color: #fff; }
        .gallery-item-date { font-size: 0.75rem; color: var(--text-muted-dark); flex-grow: 1; text-align: left; }
        .no-images-message { text-align: center; color: var(--text-muted-dark); font-size: 1.2rem; margin-top: 50px; padding: 40px; background-color: var(--card-bg); border-radius: 15px; }
        .lb-data .lb-caption { color: var(--text-light) !important; font-size: 1rem !important; }
        .lb-data .lb-number { color: var(--text-muted-dark) !important; }
        .lb-nav a.lb-prev, .lb-nav a.lb-next { opacity: 0.7 !important; transition: opacity 0.3s ease; }
        .lb-nav a.lb-prev:hover, .lb-nav a.lb-next:hover { opacity: 1 !important; }
        .lightboxOverlay { background-color: rgba(10, 10, 19, 0.9) !important; }
        .gallery-item.deleting { opacity: 0.5; pointer-events: none; transform: scale(0.95); }

        /* --- Bootstrap Modal Customization --- */
        .modal-content {
            background-color: var(--card-bg);
            color: var(--text-light);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%); /* Make close button white */
        }
        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .modal-title {
            color: var(--primary-color);
            font-family: 'Poppins', sans-serif;
        }
        .btn-secondary { /* Style cancel button */
             background-color: #6c757d;
             border-color: #6c757d;
        }
        .btn-danger { /* Style delete confirm button */
             background-color: #dc3545;
             border-color: #dc3545;
        }

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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                         <a class="nav-link active" aria-current="page" href="gallery.php">My Gallery</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['user_picture'] ?? 'images/default-avatar.png'); ?>" alt="Profile" class="profile-pic">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container gallery-container">
        <h1 class="gallery-title">My Creations</h1>

        <?php if (isset($gallery_error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo "Error loading gallery. Please try again later."; ?>
            </div>
        <?php endif; ?>

        <div id="gallery-error-message" class="alert alert-danger d-none" role="alert"></div>

        <?php if (empty($images) && !isset($gallery_error)): ?>
            <div class="no-images-message">
                <p><i class="bi bi-cloud-drizzle fs-1 mb-3"></i></p>
                You haven't generated any images yet. Go to the <a href="dashboard.php">Dashboard</a> to start creating!
            </div>
        <?php elseif (!empty($images)): ?>
            <div class="gallery-grid" id="galleryGrid">
                <?php foreach ($images as $index => $image): ?>
                    <?php
                        // Sanitize data
                        $image_url = htmlspecialchars($image['image_url'] ?? '');
                        $prompt_text = htmlspecialchars($image['prompt'] ?? 'No prompt recorded');
                        $created_date = isset($image['created_at']) ? date("M d, Y, g:i a", strtotime($image['created_at'])) : 'Unknown date';
                        $image_id = $image['id'] ?? 'unknown';
                        $download_filename = 'h7_image_' . $image_id . '.png';
                    ?>
                    <div class="gallery-item" data-id="<?php echo $image_id; ?>">
                        <a href="<?php echo $image_url; ?>" data-lightbox="gallery" data-title="<?php echo $prompt_text; ?> <br><small><?php echo $created_date; ?></small>" class="gallery-image-link">
                           <img src="<?php echo $image_url; ?>"
                                alt="Generated image for prompt: <?php echo $prompt_text; ?>"
                                loading="lazy"
                                onerror="this.style.display='none'; this.parentElement.parentElement.style.border='1px solid #dc3545'; this.parentElement.parentElement.insertAdjacentHTML('beforeend', '<p style=\'color:#dc3545; padding:10px; font-size:0.8rem;\'>Image not found or invalid path</p>');">
                        </a>
                        <div class="gallery-item-overlay">
                            <p class="gallery-item-prompt" title="<?php echo $prompt_text; ?>"><?php echo $prompt_text; ?></p>
                            <div class="gallery-item-actions">
                                <span class="gallery-item-date"><?php echo $created_date; ?></span>
                                <div>
                                    <a href="<?php echo $image_url; ?>" download="<?php echo $download_filename; ?>" class="gallery-action-btn gallery-download-btn" title="Download Image">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <button type="button" class="gallery-action-btn gallery-delete-btn"
                                            data-bs-toggle="modal" data-bs-target="#deleteConfirmModal"
                                            data-image-id="<?php echo $image_id; ?>" title="Delete Image">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteModalLabel"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Confirm Deletion</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to permanently delete this image? This action cannot be undone.
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Image</button>
          </div>
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

        // --- Delete Image Logic with Bootstrap Modal ---
        const galleryGrid = document.getElementById('galleryGrid');
        const galleryErrorMessageDiv = document.getElementById('gallery-error-message');
        const deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal')); // Get modal instance
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        let imageIdToDelete = null; // Variable to store the ID of the image to delete
        let galleryItemToDelete = null; // Variable to store the gallery item element

        if (galleryGrid) {
            galleryGrid.addEventListener('click', function(event) {
                const deleteButton = event.target.closest('.gallery-delete-btn');

                if (deleteButton) {
                    imageIdToDelete = deleteButton.dataset.imageId; // Store the ID
                    galleryItemToDelete = deleteButton.closest('.gallery-item'); // Store the item element
                    // console.log("DEBUG: Delete button clicked for image ID:", imageIdToDelete);

                    if (!imageIdToDelete || !galleryItemToDelete) {
                         console.error("DEBUG: Could not find image ID or gallery item for modal.");
                         return;
                    }
                    // Show the Bootstrap modal instead of confirm()
                    deleteConfirmModal.show();
                }
            });
        }

        // Add event listener to the modal's confirm button
        confirmDeleteBtn.addEventListener('click', async function() {
            if (!imageIdToDelete || !galleryItemToDelete) return; // Exit if no ID stored

            // console.log("DEBUG: Confirm delete clicked for image ID:", imageIdToDelete);
            galleryItemToDelete.classList.add('deleting'); // Add deleting style
            galleryErrorMessageDiv.classList.add('d-none'); // Hide previous errors
            deleteConfirmModal.hide(); // Hide the modal

            try {
                const formData = new FormData();
                formData.append('image_id', imageIdToDelete);

                const response = await fetch('delete_image.php', {
                    method: 'POST',
                    body: formData
                });

                // Clone response to read body multiple times if needed
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

                if (response.ok && data.success) {
                    // console.log("DEBUG: Deletion successful according to backend.");
                    galleryItemToDelete.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    galleryItemToDelete.style.opacity = '0';
                    galleryItemToDelete.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        galleryItemToDelete.remove();
                        if (galleryGrid && galleryGrid.children.length === 0) {
                            galleryGrid.insertAdjacentHTML('beforebegin', '<div class="no-images-message"><p><i class="bi bi-cloud-drizzle fs-1 mb-3"></i></p>Your gallery is now empty. Go to the <a href="dashboard.php">Dashboard</a> to create more!</div>');
                        }
                        // Reset stored values after deletion
                        imageIdToDelete = null;
                        galleryItemToDelete = null;
                    }, 500);
                } else {
                    throw new Error(data.message || 'Failed to delete image.');
                }

            } catch (error) {
                console.error('DEBUG: Delete Error Caught:', error);
                galleryErrorMessageDiv.textContent = "Error: " + error.message;
                galleryErrorMessageDiv.classList.remove('d-none');
                 if(galleryItemToDelete) galleryItemToDelete.classList.remove('deleting'); // Remove deleting style on error
                  // Reset stored values on error
                 imageIdToDelete = null;
                 galleryItemToDelete = null;
            }
        });

    </script>
</body>
</html>
