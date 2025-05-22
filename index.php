<?php
// Start session
session_start();

require_once 'config_secrets.php';

// If user is logged in, redirect to dashboard
if (isset($_SESSION['google_user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// --- Google Client ID (Pass to JavaScript) ---
$googleClientId = GOOGLE_CLIENT_ID; // Use the constant from config_secrets.php

// Continue with HTML if user is not logged in
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>h7 - Visionary AI Image Generation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #00f2ea; /* Cyan/Turquoise */
            --secondary-color: #a050ff; /* Brighter Violet */
            --gold-accent: #FFD700; /* Gold Accent Color */
            --dark-bg: #05050d; /* Even Darker background */
            --card-bg: #11111b; /* Darker Card background */
            --text-light: #e8e8f0;
            --text-muted-dark: #a0a0c0;
            --gradient-main: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            --gradient-gold: linear-gradient(90deg, rgba(255,215,0,0.5) 0%, rgba(255,215,0,0.9) 50%, rgba(255,215,0,0.5) 100%); /* Gold gradient for lines */
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-light);
            overflow-x: hidden;
            padding-top: 70px; /* Adjust if navbar height changes */
        }

        /* --- Navbar --- */
        .navbar {
            background-color: rgba(5, 5, 13, 0.8); /* Darker transparent */
            backdrop-filter: blur(15px);
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1); /* Subtle gold border */
            transition: background-color 0.3s ease;
        }
        .navbar-brand img {
            height: 50px; /* Slightly larger logo */
            width: auto;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), filter 0.3s ease;
            filter: brightness(1);
        }
        .navbar-brand:hover img {
            transform: scale(1.1);
            filter: brightness(1.2);
        }

        /* --- Hero Section --- */
        .hero-section {
            background: var(--dark-bg); /* Keep dark */
            /* Animated Gradient Background */
            background: linear-gradient(-45deg, #0f0f1a, #1a1a2e, #0f0f1a, #2a2a4e);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: white;
            padding: 160px 0 180px; /* More padding */
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 2px solid transparent; /* Prepare for gold border */
            border-image: var(--gradient-gold) 1; /* Apply gold gradient border */
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .hero-section h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 5rem; /* Larger H1 */
            font-weight: 800;
            margin-bottom: 30px;
            text-shadow: 0 5px 25px rgba(0, 0, 0, 0.5);
            color: #fff;
            line-height: 1.2;
            opacity: 0; /* For JS animation */
            transform: translateY(-30px); /* For JS animation */
        }

        .hero-section p {
            font-size: 1.5rem; /* Larger paragraph */
            margin-bottom: 60px;
            opacity: 0; /* For JS animation */
            transform: translateY(30px); /* For JS animation */
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            color: var(--text-light);
            font-weight: 400; /* Slightly lighter weight */
        }

        /* --- LEGENDARY Get Started Button Style v3 --- */
        .get-started-btn {
            padding: 20px 50px;
            font-size: 1.4rem;
            font-weight: 700;
            border-radius: 50px;
            color: var(--primary-color); /* Start with primary color text */
            border: 2px solid var(--primary-color); /* Primary color border */
            cursor: pointer;
            text-decoration: none;
            position: relative;
            z-index: 1;
            overflow: hidden;
            background: transparent; /* Transparent background initially */
            /* Apply default pulse animation */
            animation: pulseBorder 3s infinite ease-in-out;
            transition: color 0.5s ease, transform 0.4s ease, box-shadow 0.4s ease, border-color 0.4s ease;
            opacity: 0;
            transform: scale(0.9);
        }

        @keyframes pulseBorder {
            0%, 100% {
                box-shadow: 0 0 15px rgba(0, 242, 234, 0.3), inset 0 0 10px rgba(0, 242, 234, 0.1);
                border-color: var(--primary-color);
            }
            50% {
                box-shadow: 0 0 25px rgba(0, 242, 234, 0.5), inset 0 0 15px rgba(0, 242, 234, 0.2);
                border-color: #66fff7; /* Slightly lighter cyan */
            }
        }

        .get-started-btn::before { /* Inner fill/reveal effect (for hover) */
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 0%;
            height: 100%;
            background: var(--gradient-main);
            border-radius: 50px;
            transition: width 0.5s cubic-bezier(0.77, 0, 0.175, 1);
            z-index: -1;
        }

        .get-started-btn::after { /* Shimmer effect layer */
             content: '';
             position: absolute;
             top: -50%; left: -50%;
             width: 200%; height: 200%; /* Large enough to cover */
             background: linear-gradient(
                 to right,
                 rgba(255, 255, 255, 0) 0%,
                 rgba(255, 255, 255, 0) 40%,
                 rgba(255, 255, 255, 0.3) 50%, /* White shimmer */
                 rgba(255, 255, 255, 0) 60%,
                 rgba(255, 255, 255, 0) 100%
             );
             transform: translateX(-100%); /* Start off-screen left */
             animation: shimmer 4s infinite linear; /* Shimmer animation */
             z-index: 3; /* Above text, below cursor */
             pointer-events: none; /* Allow clicks through */
             opacity: 0.6;
        }

        @keyframes shimmer {
             100% {
                 transform: translateX(100%); /* Move across */
             }
        }


        .get-started-btn:hover::before {
            width: 100%; /* Expand width to fill button */
        }

        .get-started-btn span { /* Text and icon container */
             position: relative;
             z-index: 2;
             display: inline-flex;
             align-items: center;
             transition: color 0.5s ease; /* Transition text color */
        }

        .get-started-btn:hover {
            color: #ffffff; /* Change text color on hover */
            border-color: transparent; /* Hide border when background fills */
            transform: translateY(-6px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 242, 234, 0.3), 0 10px 25px rgba(160, 80, 255, 0.2);
            animation-play-state: paused; /* Pause pulse animation on hover */
        }
         .get-started-btn:hover::after {
             animation-play-state: paused; /* Pause shimmer on hover */
             opacity: 0; /* Hide shimmer on hover */
         }
        /* --- End Get Started Button Style --- */


        /* --- General Section Styling --- */
        .content-section { padding: 120px 0; text-align: center; position: relative; }
        .content-section::before { content: ''; position: absolute; top: 0; left: 20%; right: 20%; height: 2px; background: var(--gradient-gold); box-shadow: 0 0 15px rgba(255, 215, 0, 0.4); opacity: 0; }
        .section-title { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 3.2rem; margin-bottom: 80px; color: var(--primary-color); text-shadow: 0 0 15px rgba(0, 242, 234, 0.4); opacity: 0; transform: translateY(30px); }
        .section-title::after { content: ''; display: block; width: 80px; height: 3px; background: var(--gold-accent); margin: 20px auto 0; border-radius: 2px; opacity: 0; transform: scaleX(0); }

        /* --- Features Section Enhancements --- */
        .feature-card { background: var(--card-bg); padding: 40px 30px; border-radius: 20px; margin-bottom: 30px; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: 1px solid rgba(255, 255, 255, 0.05); height: 100%; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; opacity: 0; transform: translateY(40px); box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2); }
        .feature-card::before { content: ''; position: absolute; top: 0; left: 0; width: 50px; height: 50px; border-top: 3px solid var(--gold-accent); border-left: 3px solid var(--gold-accent); border-top-left-radius: 20px; opacity: 0; transition: all 0.4s ease; transform: translate(-100%, -100%); }
        .feature-card:hover::before { opacity: 0.7; transform: translate(0, 0); }
        .feature-card:hover { transform: translateY(-10px); box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4); border-color: rgba(255, 215, 0, 0.3); }
        .feature-card i { font-size: 3.2rem; margin-bottom: 30px; background: var(--gradient-main); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; text-fill-color: transparent; display: inline-block; transition: transform 0.4s ease; }
        .feature-card:hover i { transform: scale(1.15) rotate(-5deg); }
        .feature-card h5 { font-family: 'Poppins', sans-serif; font-weight: 700; margin-bottom: 15px; font-size: 1.4rem; color: #ffffff; }
        .feature-card p { color: var(--text-muted-dark); font-size: 1rem; line-height: 1.7; }

        /* --- About Section --- */
        .about-section { background: linear-gradient(160deg, var(--card-bg), #1a1a29); border-radius: 25px; padding: 70px 50px; margin-top: 100px; text-align: left; border: 1px solid rgba(138, 43, 226, 0.1); opacity: 0; transform: translateY(40px); box-shadow: inset 0 0 30px rgba(0,0,0,0.3); }
        .about-section h2 { color: var(--secondary-color); font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 2.8rem; margin-bottom: 40px; text-align: center; position: relative; }
        .about-section h2::after { content: ''; display: block; width: 70px; height: 3px; background: var(--gold-accent); margin: 15px auto 0; border-radius: 2px; }
        .about-section p { color: var(--text-light); font-size: 1.15rem; line-height: 1.9; margin-bottom: 25px; }
        .about-section strong { color: var(--primary-color); font-weight: 600; }

        /* --- Footer --- */
        .footer { background-color: #05050d; color: var(--text-muted-dark); padding: 60px 0; margin-top: 120px; text-align: center; font-size: 0.9rem; border-top: 1px solid rgba(255, 215, 0, 0.1); }

        .google-btn-icon { width: 22px; height: 22px; margin-right: 15px; vertical-align: middle; }

        /* --- Scroll Animation Utility --- */
        .scroll-animate { opacity: 0; transition: opacity 1s cubic-bezier(0.165, 0.84, 0.44, 1), transform 1s cubic-bezier(0.165, 0.84, 0.44, 1); }
        .scroll-animate.fade-in-up { transform: translateY(50px); }
        .scroll-animate.fade-in-down { transform: translateY(-30px); }
        .scroll-animate.zoom-in { transform: scale(0.9); }
        .scroll-animate.slide-in-left { transform: translateX(-50px); }
        .scroll-animate.slide-in-right { transform: translateX(50px); }
        .scroll-animate.scale-underline { transform: scaleX(0); }
        .scroll-animate.visible { opacity: 1; transform: translateY(0) scale(1) translateX(0); }

    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="images/h7-logo.jpg" alt="h7 Logo">
            </a>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container position-relative" style="z-index: 1;">
            <h1 class="scroll-animate fade-in-down">h7 Visionary AI</h1>
            <p class="scroll-animate fade-in-up">Where Your Imagination Meets Intelligent Creation. Generate breathtaking visuals instantly.</p>
            <a href="#" id="googleSignInButton" class="get-started-btn scroll-animate zoom-in">
                 <span> <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo" class="google-btn-icon">
                     Get Started with Google
                 </span>
            </a>
        </div>
    </section>

    <section class="content-section features-section">
        <div class="container">
            <h2 class="section-title scroll-animate fade-in-up">Core Features</h2>
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card scroll-animate fade-in-up" data-animation-delay="0.1">
                         <i class="bi bi-aspect-ratio-fill"></i> <h5>High-Quality Output</h5>
                        <p>Generate stunning, high-resolution images suitable for any project.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card scroll-animate fade-in-up" data-animation-delay="0.2">
                        <i class="bi bi-clock-history"></i>
                        <h5>Daily Creative Fuel</h5>
                        <p>Receive <strong>3 fresh credits</strong> every 24 hours. Unused daily credits expire.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="feature-card scroll-animate fade-in-up" data-animation-delay="0.3">
                        <i class="bi bi-images"></i>
                        <h5>Personal Gallery</h5>
                        <p>Access, review, and manage all your unique AI-generated images in your <strong>private gallery</strong>.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="content-section about-section-container">
        <div class="container">
             <div class="about-section scroll-animate fade-in-up" data-animation-delay="0.2">
                 <h2 class="section-title scroll-animate fade-in-up">About h7</h2>
                <p>
                    Welcome to <strong>h7</strong>, your portal to the next frontier of digital artistry. We leverage state-of-the-art artificial intelligence to transform simple text prompts into extraordinary, high-fidelity images. Whether you're a professional designer, a content creator, or simply exploring the bounds of creativity, <strong>h7</strong> provides the tools to bring your vision to life.
                </p>
                <p>
                    Our platform is built on powerful AI models, offering a seamless and intuitive experience. With features like <strong>daily creative top-ups</strong>, and a <strong>personal gallery</strong>, <strong>h7</strong> is designed to empower your imagination without limits. Join us and start creating the unimaginable, today.
                </p>
             </div>
        </div>
    </section>


    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> h7 Visionary AI. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Google Sign-In Button Logic
        document.getElementById('googleSignInButton').addEventListener('click', function(event) {
            event.preventDefault();
            const clientId = '<?php echo $googleClientId; ?>';
            const currentPath = window.location.pathname;
            const directoryPath = currentPath.substring(0, currentPath.lastIndexOf('/'));
            const redirectUri = window.location.origin + directoryPath + "/google_callback.php";
            const scope = 'email profile';
            const responseType = 'code';
            const authUrl = `https://accounts.google.com/o/oauth2/v2/auth?` +
                `client_id=${encodeURIComponent(clientId)}` +
                `&redirect_uri=${encodeURIComponent(redirectUri)}` +
                `&scope=${encodeURIComponent(scope)}` +
                `&response_type=${encodeURIComponent(responseType)}` +
                `&access_type=offline` +
                `&prompt=consent`;
            window.location.href = authUrl;
        });

        // Intersection Observer for scroll animations
        document.addEventListener('DOMContentLoaded', () => {
            const animatedElements = document.querySelectorAll('.scroll-animate');
            const observerOptions = { root: null, rootMargin: '0px', threshold: 0.15 };
            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const delay = entry.target.dataset.animationDelay || '0';
                        entry.target.style.transitionDelay = `${delay}s`;
                        entry.target.classList.add('visible');
                        if (entry.target.matches('.section-title')) { entry.target.classList.add('underline-visible'); }
                        if (entry.target.matches('.features-section, .about-section-container')) { entry.target.classList.add('divider-visible'); }
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            animatedElements.forEach(el => {
                 if(el.matches('.section-title')) { el.classList.add('has-underline-animation'); }
                 if(el.matches('.features-section, .about-section-container')) { el.classList.add('has-divider-animation'); }
                observer.observe(el);
            });
             const styleSheet = document.styleSheets[0];
             if (styleSheet) {
                 try {
                    styleSheet.insertRule('.has-underline-animation.visible::after { opacity: 1; transform: scaleX(1); transition: transform 0.8s ease-out 0.5s, opacity 0.5s ease-out 0.5s; }', styleSheet.cssRules.length);
                    styleSheet.insertRule('.has-divider-animation.visible::before { opacity: 1; transition: opacity 0.8s ease-out 0.7s; }', styleSheet.cssRules.length);
                 } catch (e) { console.warn("Could not insert dynamic CSS rule for animations:", e); }
             }
        }); // End DOMContentLoaded

    </script>
</body>
</html>
