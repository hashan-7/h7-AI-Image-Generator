# h7 AI Image Generator ✨

<p align="center">
  <strong>Bring your imagination to life! This web application transforms your text prompts into stunning, unique images using the power of AI.</strong>
</p>


---

## 🚀 Key Features

* **🔐 Secure Google Authentication:** Easy and secure user login and session management using Google accounts.
* **🧠 Robust AI Image Generation:** Utilizes a powerful fallback system with a list of Hugging Face models, ensuring high availability and successful image generation even if one model is unavailable.
* **🪙 Fair Daily Credit System:** All users receive a daily quota of credits, which refresh every 24 hours, promoting fair and consistent usage.
* **🖼️ Personalized Gallery:** A private, dedicated space for users to view, download, and manage all their AI-generated masterpieces.
* **📱 Fully Responsive Design:** A modern and adaptive interface built with Bootstrap 5, providing an optimal experience on desktops, tablets, and mobile devices.
* **🛡️ Secure by Design:** All sensitive credentials (API keys, database passwords) are kept completely separate from the public codebase using a `config_secrets.php` file, which is ignored by Git.

---

## 🛠️ Tech Stack

This project is built with a reliable and widely-used technology stack:

* **Backend:** PHP
* **Database:** MySQL
* **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5
* **API:** Hugging Face Inference API
* **Dependency Management:** Composer

---

## ⚙️ Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites

* A local web server environment like [XAMPP](https://www.apachefriends.org/index.html) or [WAMP](https://www.wampserver.com/en/).
* [Composer](https://getcomposer.org/) installed globally.

### Installation & Configuration

1.  **Clone the repository into your web server's root directory** (e.g., `htdocs` for XAMPP, `www` for WAMP):
    ```sh
    git clone [https://github.com/hashan-7/h7-AI-Image-Generator.git](https://github.com/hashan-7/h7-AI-Image-Generator.git)
    cd h7-AI-Image-Generator
    ```

2.  **Install PHP Dependencies:**
    Run the Composer install command to download the necessary libraries (like the Google Client Library).
    ```sh
    composer install
    ```

3.  **Set up the Database:**
    * Open phpMyAdmin or your preferred MySQL client.
    * Create a new database named `ai_image_generator`.
    * Execute the following SQL queries to create the required tables:

    ```sql
    -- Table for storing user information
    CREATE TABLE `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `google_id` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `name` varchar(255) DEFAULT NULL,
      `picture_url` text DEFAULT NULL,
      `daily_credits_remaining` int(11) DEFAULT 3,
      `daily_credits_refreshed_at` datetime DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `google_id` (`google_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

    -- Table for storing generated images
    CREATE TABLE `images` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `image_url` varchar(255) NOT NULL,
      `prompt` text NOT NULL,
      `api_used` varchar(100) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `images_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ```

4.  **Create and Configure `config_secrets.php`:**

    **⚠️ This is the most important step.** This file is intentionally ignored by Git to protect your secret keys. You must create it manually.

    * In the project's root directory, create a new file named `config_secrets.php`.
    * Copy the code below into this new file and **replace the placeholder values** with your actual credentials.

    ```php
    <?php

    // Google OAuth 2.0 Credentials
    define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
    define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
    define('GOOGLE_REDIRECT_URI', 'http://localhost/h7-AI-Image-Generator/google_callback.php');

    // Hugging Face Configuration
    define('HUGGING_FACE_API_KEY', 'YOUR_HUGGING_FACE_API_KEY');
    define('HUGGING_FACE_MODELS', [
        '[https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-xl-base-1.0](https://api-inference.huggingface.co/models/stabilityai/stable-diffusion-xl-base-1.0)',
        '[https://api-inference.huggingface.co/models/prompthero/openjourney-v4](https://api-inference.huggingface.co/models/prompthero/openjourney-v4)',
        '[https://api-inference.huggingface.co/models/runwayml/stable-diffusion-v1-5](https://api-inference.huggingface.co/models/runwayml/stable-diffusion-v1-5)'
    ]);

    // Database Credentials
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', ''); // Your database password, if you have one
    define('DB_NAME', 'ai_image_generator');

    ?>
    ```

5.  **Access the Application:**
    Start your WAMP/XAMPP server. Open your web browser and navigate to `http://localhost/h7-AI-Image-Generator/`.

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the [issues page](https://github.com/hashan-7/h7-AI-Image-Generator/issues).

---

## 📄 License

This project is licensed under the MIT License.

---

<p align="center">
  Made with ❤️ by h7
</p>
