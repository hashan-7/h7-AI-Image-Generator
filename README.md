h7 AI Image Generator
Welcome to the h7 AI Image Generator, an intuitive web application designed to bring your creative visions to life through artificial intelligence. This project provides a seamless platform for users to generate unique images from simple text prompts, leveraging advanced AI models.

Overview
The h7 AI Image Generator offers a user-friendly interface for transforming textual descriptions into captivating visuals. It integrates robust authentication via Google Sign-In, a fair daily credit system for image generation, and a personalized gallery where users can manage their AI-created masterpieces. The application is built with a strong focus on a clean architecture, responsive design, and the utmost security for sensitive API and database credentials.

Features
Google Sign-In: Easy and secure user authentication using Google accounts.

AI-Powered Image Generation: Generate unique images from text prompts using cutting-edge AI models (via Stability AI API).

Daily Credit System: Users receive a set number of daily credits for image generation, ensuring fair usage.

Personalized Image Gallery: A private space for users to view, download, and manage all their generated images.

Responsive User Interface: A modern and adaptive design built with Bootstrap, ensuring optimal experience across all devices.

Secure Credential Management: All sensitive API keys and database credentials are kept out of the public codebase through a secure configuration file.

Technologies Used
Backend: PHP

MySQLi for database interactions.

Composer for dependency management (e.g., Google Client Library).

cURL for API communication.

Frontend:

HTML5

CSS3 (Bootstrap 5)

JavaScript (for dynamic interactions and animations)

Database: MySQL

Version Control: Git

Setup Instructions
To get this project running on your local machine, please follow these steps carefully:

Clone the Repository:
Begin by cloning this project to your local development environment.

git clone https://github.com/YourGitHubUsername/h7-AI-Image-Generator.git
cd h7-AI-Image-Generator

(Please replace YourGitHubUsername and h7-AI-Image-Generator with your actual GitHub username and repository name).

Install Composer:
If you don't already have Composer installed, please download it from the official website: getcomposer.org.

Install PHP Dependencies:
Navigate to the project's root directory in your terminal and execute the Composer install command. This will download all necessary PHP libraries.

composer install

This process will create a vendor/ directory, which is automatically excluded from version control by the .gitignore file.

Configure Secret Keys:
This is a crucial step for security. You need to provide your API keys and database credentials.

Create config_secrets.php: In the root directory of your project, create a new file named config_secrets.php.

Add Your Credentials: Open this config_secrets.php file and populate it with your actual keys and credentials.

<?php
// config_secrets.php - This file contains sensitive API keys and database credentials.
// It is crucial that this file is NOT committed to public repositories.
// Ensure 'config_secrets.php' is listed in your .gitignore file.

// Google OAuth 2.0 Credentials:
// Obtain these from your Google Cloud Console (APIs & Services -> Credentials).
// Ensure 'Authorized redirect URIs' in Google Cloud Console matches GOOGLE_REDIRECT_URI below.
define('GOOGLE_CLIENT_ID', 'YOUR_ACTUAL_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_ACTUAL_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'http://localhost/your_project_name/google_callback.php'); // Adjust 'your_project_name' for your local setup

// Stability AI API Key:
// Obtain this from your Stability AI account.
define('STABILITY_API_KEY', 'YOUR_ACTUAL_STABILITY_API_KEY');

// Database Credentials:
// Configure these based on your local MySQL setup.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'YOUR_DATABASE_PASSWORD'); // IMPORTANT: Use a strong password for production environments
define('DB_NAME', 'ai_image_generator');

?>

Remember to replace all YOUR_ACTUAL_... and YOUR_DATABASE_PASSWORD placeholders with your genuine credentials. This config_secrets.php file is designed to be ignored by Git and will not be uploaded to your public repository.

Set up Your Database:
You need a MySQL database for the application to store user and image data.

Create a new database named ai_image_generator in your MySQL server (e.g., using phpMyAdmin or MySQL Workbench).

Execute the following SQL queries to create the necessary tables:

-- Table for storing user information
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    picture_url TEXT,
    daily_credits_remaining INT DEFAULT 3,
    daily_credits_refreshed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for storing generated images
CREATE TABLE images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    prompt TEXT NOT NULL,
    api_used VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

Set up a Local Web Server:
Ensure you have a local PHP development environment installed (such as XAMPP, WAMP, or MAMP). Place the entire h7-AI-Image-Generator project folder within your web server's document root (e.g., htdocs for XAMPP, www for WAMP).

Access the Application:
Open your web browser and navigate to the local URL where your server hosts the project (e.g., http://localhost/h7imagegenerator/ or http://localhost/).

Usage
Google Sign-In: Securely log in using your Google account to access the application's features.

Generate Images: Navigate to the Dashboard and enter a descriptive text prompt to generate unique AI images.

Daily Credits: Benefit from a system that provides daily replenished credits, allowing for continuous creative exploration.

Personal Gallery: All your generated images are saved to a private gallery, where you can view, download, and manage them at your convenience.

This project is proudly presented under h7.
