<?php
require_once 'config.php';
require_once 'auth_functions.php';
require_once 'homepage_news.php';

$pr = '';
if (defined('PROMO_WEB_ROOT')) {
    $promo_web_root = constant('PROMO_WEB_ROOT');
    if (is_string($promo_web_root)) {
        $pr = $promo_web_root;
    }
}
$logo = $pr . 'assets/imgs/logo.jpeg';

function dpm_promo_build_img_pool(string $projectRoot): array
{
    $pool = [];
    $dir = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imgs';
    if (!is_dir($dir)) return $pool;
    $skip = ['logo.jpeg', 'calendar-icon.png', 'uk-flag.jpg', 'ph-flag.png'];
    $skipLower = array_map('strtolower', $skip);
    $items = @scandir($dir);
    if ($items === false) return $pool;
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $dir . DIRECTORY_SEPARATOR . $f;
        if (!is_file($full)) continue;
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) continue;
        if (in_array(strtolower($f), $skipLower, true)) continue;
        $pool[] = 'assets/imgs/' . $f;
    }
    return array_values(array_unique($pool));
}

function dpm_promo_img_src(string $pr, array $pool, string $fallbackUrl): string
{
    if ($pool !== []) {
        $rel = $pool[array_rand($pool)];
        $parts = explode('/', str_replace('\\', '/', $rel));
        $enc = array_map('rawurlencode', $parts);
        return $pr . implode('/', $enc);
    }
    return $fallbackUrl;
}

function dpm_promo_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$dpm_promo_pool = dpm_promo_build_img_pool(dirname(__DIR__));
$dpm_vendor_bg = dpm_promo_img_src(
    $pr, $dpm_promo_pool,
    'https://images.unsplash.com/photo-1567449303078-57ad995bd329?w=1600&q=80'
);

$hero_bg = htmlspecialchars($pr) . 'assets/imgs/palengke.jpg';
$hero_marketing = htmlspecialchars($pr) . 'assets/imgs/marketing.jpg';

$homepage_news_cards = homepage_news_get_cards();
$homepage_news_fallback_img = htmlspecialchars($pr) . 'assets/imgs/undermaintenance.jpg';

// Redirect logged-in users to the correct portal
if (isset($_SESSION['user_id'])) {
    $is_admin = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')
        || (isset($_SESSION['email']) && strtolower($_SESSION['email']) === 'daetpublicmarket@gmail.com');
    header('Location: ' . ($is_admin ? 'adminpanel.php' : 'menu.php'));
    exit();
}

$error_message = '';
$success_message = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_portal_login'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    
    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        
        $result = authenticateUser($email, $password, $remember_me);
        if ($result['success']) {
            $is_admin = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')
                || strtolower($email) === 'daetpublicmarket@gmail.com';
            header('Location: ' . ($is_admin ? 'adminpanel.php' : 'menu.php'));
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daet Public Market</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Serif+Display:ital@0;1&family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="assets/imgs/logo.jpeg">
    <style>
        :root {
            --indigo-deep: #2e236c;
            --indigo: #3f4191;
            --purple: #5d5fef;
            --purple-soft: #e8e9fc;
            --text: #1e1b4b;
            --muted: #6b7280;
            --surface: #f0f4f8;
            --white: #fff;
            --hero-dark: #2e2a77;
            --shadow: 0 20px 50px rgba(46, 35, 108, 0.12);
            --radius: 16px;
            --radius-sm: 12px;
            --font-sans: 'Manrope', system-ui, sans-serif;
            --font-serif: 'DM Serif Display', Georgia, serif;
            --font-display: 'Bebas Neue', Impact, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-sans);
            color: var(--text);
            background: var(--white);
            line-height: 1.6;
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .container {
            width: min(1180px, calc(100% - clamp(1.25rem, 4vw, 2.5rem)));
            margin-inline: auto;
        }

        /* ——— Nav ——— */
        .site-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 200;
            background: transparent;
            transition: background 0.3s, box-shadow 0.3s;
            -webkit-transform: translateZ(0);
            transform: translateZ(0);
        }
        .site-nav.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 4px 24px rgba(45, 35, 108, 0.08);
        }
        body.nav-open {
            position: fixed;
            width: 100%;
            overflow: hidden;
        }
        body.nav-open .site-nav {
            z-index: 230;
        }
        .site-nav__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.1rem 0;
            min-width: 0;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            transition: color 0.3s;
            min-width: 0;
        }
        .brand__stack { display: flex; flex-direction: column; gap: 0.1rem; }
        .brand__text {
            font-family: var(--font-serif);
            font-weight: 400;
            font-size: clamp(0.88rem, 1.55vw, 1.08rem);
            line-height: 1.15;
            letter-spacing: 0;
            color: #fff;
            transition: color 0.3s;
        }
        .site-nav.scrolled .brand__text { color: var(--indigo-deep); }
        .brand__place {
            display: block;
            font-family: var(--font-sans);
            font-size: 0.62rem;
            letter-spacing: 0.14em;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.78);
            margin-top: 0.15rem;
            transition: color 0.3s;
        }
        .site-nav.scrolled .brand__place { color: var(--muted); }

        .brand__mark {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            flex-shrink: 0;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.85);
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .site-nav.scrolled .brand__mark { border-color: #efeefe; }
        .brand__mark img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            list-style: none;
            font-weight: 600;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.92);
            transition: color 0.3s;
            min-width: 0;
        }
        .site-nav.scrolled .nav-links { color: var(--indigo); }
        .nav-links a:hover { color: var(--purple); }
        .btn-login {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
            color: var(--white) !important;
            padding: 0.55rem 1.15rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.9rem;
            border: 1.5px solid rgba(255,255,255,0.4);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }
        .site-nav.scrolled .btn-login {
            background: var(--purple);
            border-color: var(--purple);
        }
        .btn-login:hover {
            background: var(--white);
            color: var(--indigo) !important;
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.5), 0 0 15px rgba(255, 107, 53, 0.3);
        }
        .nav-toggle {
            display: none;
            width: 44px;
            height: 44px;
            border: none;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            color: var(--white);
            font-size: 1.25rem;
            cursor: pointer;
        }
        .nav-close-wrap {
            display: none;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .nav-close {
            width: 44px;
            height: 44px;
            border: none;
            background: rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            color: #fff;
            font-size: 1.25rem;
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: background 0.15s;
        }
        .nav-close:hover { background: rgba(255, 255, 255, 0.22); }
        @media (max-width: 900px) {
            .nav-toggle { display: grid; place-items: center; }
            .site-nav.scrolled .nav-toggle {
                background: rgba(46, 35, 108, 0.08);
                color: var(--indigo);
            }
            .nav-links {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: auto;
                width: min(280px, 85vw);
                height: 100vh;
                height: 100dvh;
                max-height: 100dvh;
                background: var(--indigo-deep);
                flex-direction: column;
                align-items: flex-start;
                padding: 4.5rem 1.5rem 2rem;
                gap: 1.25rem;
                box-shadow: -12px 0 40px rgba(0,0,0,0.25);
                transform: translateX(100%);
                transition: transform 0.25s ease;
                color: var(--white) !important;
                z-index: 220;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            .nav-links.is-open { transform: translateX(0); }
            .nav-links li:not(.nav-close-wrap) { width: 100%; }
            .nav-links a,
            .nav-links .btn-login,
            .nav-close {
                pointer-events: auto;
                position: relative;
                z-index: 1;
            }
            .nav-links .btn-login { width: 100%; justify-content: center; }
            .nav-close-wrap {
                display: block;
                position: absolute;
                top: 1rem;
                right: 1rem;
                left: auto;
                width: auto;
                margin: 0;
                padding: 0;
            }
        }
        .nav-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 27, 75, 0.35);
            z-index: 210;
        }
        .nav-backdrop.is-open { display: block; }

        
        .immersive-hero {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 600px;
            max-height: 900px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: clamp(2rem, 5vh, 3.5rem);
        }

        
        .hero-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Gradient overlays */
        .hero-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(to top,
                    rgba(10, 8, 40, 0.82) 0%,
                    rgba(10, 8, 40, 0.30) 38%,
                    rgba(10, 8, 40, 0.08) 60%,
                    transparent 100%),
                linear-gradient(to right,
                    rgba(10, 8, 40, 0.38) 0%,
                    transparent 55%);
        }

        /* Search bar */
        .hero-search {
            position: absolute;
            top: clamp(5rem, 10vh, 7rem);
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            width: min(540px, 90vw);
            animation: fadeDown 0.7s 0.2s both;
        }
        .hero-search__inner {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.14);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1.5px solid rgba(255,255,255,0.3);
            border-radius: 999px;
            padding: 0.65rem 0.65rem 0.65rem 1.4rem;
            gap: 0.75rem;
        }
        .hero-search__inner input {
            flex: 1;
            background: none;
            border: none;
            outline: none;
            font-family: var(--font-sans);
            font-size: 0.95rem;
            color: var(--white);
            font-weight: 500;
        }
        .hero-search__inner input::placeholder { color: rgba(255,255,255,0.65); }

        /* Search suggestions */
        .hero-search__suggestions {
            position: absolute;
            top: calc(100% + 0.6rem);
            left: 0; right: 0;
            background: rgba(15, 12, 50, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 18px;
            overflow: hidden;
            display: none;
            z-index: 20;
        }
        .hero-search__suggestions.is-open { display: block; }
        .hero-suggestion {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.85rem 1.25rem;
            cursor: pointer;
            color: rgba(255,255,255,0.88);
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.15s;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .hero-suggestion:last-child { border-bottom: none; }
        .hero-suggestion:hover, .hero-suggestion.is-highlighted {
            background: rgba(93, 95, 239, 0.35);
            color: var(--white);
        }
        .hero-suggestion__icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: rgba(165, 180, 252, 0.18);
            display: grid; place-items: center;
            color: #a5b4fc;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .hero-suggestion__label { font-weight: 700; }
        .hero-suggestion__desc { font-size: 0.75rem; opacity: 0.65; margin-top: 0.05rem; }
        .hero-search__btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--white);
            border: none;
            display: grid;
            place-items: center;
            cursor: pointer;
            color: var(--indigo);
            font-size: 0.9rem;
            transition: transform 0.15s;
            flex-shrink: 0;
        }
        .hero-search__btn:hover { transform: scale(1.08); }

        /* Main hero content */
        .hero-content {
            position: relative;
            z-index: 5;
            padding: 0 clamp(1.25rem, 5vw, 4rem);
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 2rem;
            flex-wrap: wrap;
            width: 100%;
            min-width: 0;
        }

        .hero-left {
            flex: 1 1 400px;
            max-width: 620px;
            min-width: 0;
        }

        .hero-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255,255,255,0.8);
            font-size: 0.88rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 0.6rem;
            animation: fadeUp 0.7s 0.3s both;
        }
        .hero-location i { color: #a5b4fc; font-size: 0.8rem; }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(3.5rem, min(9vw, 4.25rem + 2.5vw), 7.5rem);
            line-height: 0.92;
            letter-spacing: 0.01em;
            color: var(--white);
            text-shadow: 0 4px 30px rgba(0,0,0,0.3);
            margin-bottom: 1.1rem;
            animation: fadeUp 0.7s 0.4s both;
            max-width: 100%;
        }

        .hero-desc {
            font-size: clamp(0.88rem, 1.5vw, 1rem);
            color: rgba(255,255,255,0.82);
            max-width: 38rem;
            line-height: 1.65;
            margin-bottom: 1.75rem;
            font-weight: 400;
            animation: fadeUp 0.7s 0.5s both;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: fadeUp 0.7s 0.6s both;
        }

        .btn-apply {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--white);
            color: var(--indigo-deep);
            font-family: var(--font-sans);
            font-weight: 800;
            font-size: 1rem;
            padding: 0.95rem 2.25rem;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            letter-spacing: 0.01em;
        }
        .btn-apply:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 12px 32px rgba(0,0,0,0.25);
        }

        .btn-bookmark {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1.5px solid rgba(255,255,255,0.3);
            color: var(--white);
            display: grid;
            place-items: center;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-bookmark:hover {
            background: rgba(255,255,255,0.28);
            transform: scale(1.08);
        }

        .hero-right {
            flex: 1 1 45%;
            display: flex;
            justify-content: center;
            align-items: center;
            align-self: center;
            animation: fadeUp 0.7s 0.7s both;
            perspective: 1100px;
            min-width: 0;
        }

        .hero-marketing-img {
            display: block;
            width: min(700px, 56vw);
            max-width: 100%;
            max-height: min(820px, 84vh);
            height: auto;
            object-fit: contain;
            transform-origin: left center;
            animation: heroFloat 7.5s ease-in-out infinite;
            will-change: transform;
        }
        @keyframes heroFloat {
            0%, 100% {
                transform: translate3d(0, 12px, 0) rotateZ(-1.2deg) rotateY(2deg) scale(1);
            }
            50% {
                transform: translate3d(0, -28px, 40px) rotateZ(1.4deg) rotateY(-9deg) scale(1.045);
            }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeDown {
            from { opacity: 0; transform: translateX(-50%) translateY(-16px); }
            to   { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        @media (max-width: 768px) {
            .hero-title { font-size: clamp(3rem, 12vw, 5rem); }
            .immersive-hero { max-height: none; }
            .hero-right {
                width: 100%;
                justify-content: center;
            }
            .hero-marketing-img {
                width: min(520px, 94vw);
                max-height: none;
            }
        }

        /* ——— Indigo banner carousel ——— */
        .banner-strip {
            background: var(--indigo);
            color: var(--white);
            padding: 2.5rem 0 2rem;
            position: relative;
        }
        .banner-carousel {
            position: relative;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .banner-dots {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .banner-dots button {
            width: 8px; height: 8px;
            border-radius: 999px;
            border: none;
            background: rgba(255,255,255,0.35);
            cursor: pointer; padding: 0;
            transition: width 0.2s, background 0.2s;
        }
        .banner-dots button.is-active { width: 28px; background: var(--white); }
        .banner-slide { display: none; text-align: center; width: 100%; }
        .banner-slide.is-active { display: block; }
        .btn-pill-light {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--white);
            color: var(--indigo) !important;
            font-weight: 700;
            padding: 0.85rem 1.75rem;
            border-radius: 999px;
            font-size: 1rem;
            border: none; cursor: pointer;
            transition: transform 0.15s;
        }
        .btn-pill-light:hover { transform: scale(1.02); }
        .banner-arrows {
            position: absolute;
            top: 50%; transform: translateY(-50%);
            width: 100%; left: 0;
            pointer-events: none;
            display: flex;
            justify-content: space-between;
            padding: 0 0.25rem;
        }
        .banner-arrows button {
            pointer-events: auto;
            width: 40px; height: 40px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.2);
            color: var(--white);
            cursor: pointer;
            display: grid; place-items: center;
        }
        .banner-arrows button:hover { background: rgba(255,255,255,0.35); }
        .banner-scroll-hint { text-align: center; font-size: 0.75rem; opacity: 0.65; margin-top: 1rem; }

        /* ——— Section common ——— */
        .section { padding: clamp(3rem, 6vw, 5rem) 0; }
        .section--surface { background: var(--surface); }
        .tag {
            display: inline-block;
            font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: var(--purple-soft);
            color: var(--indigo);
            margin-bottom: 0.85rem;
        }
        .section h2 {
            font-family: var(--font-serif);
            font-size: clamp(1.75rem, 3.5vw, 2.35rem);
            color: var(--indigo); line-height: 1.2;
            margin-bottom: 0.75rem;
        }
        .section h2 em { font-style: italic; color: var(--purple); }

        /* ——— About ——— */
        .about-grid {
            display: flex; flex-wrap: wrap;
            gap: clamp(2rem, 4vw, 3.5rem);
            align-items: stretch;
        }
        .about-visual {
            flex: 1 1 380px;
            position: relative;
            display: flex;
            min-width: 0;
        }
        .about-notice-card {
            flex: 1;
            width: 100%;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(45, 35, 108, 0.08);
            padding: clamp(1.35rem, 2.5vw, 1.85rem);
            min-width: 300px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .about-notice-card__title {
            font-family: var(--font-serif);
            color: var(--indigo);
            font-size: clamp(1.5rem, 2.8vw, 1.85rem);
            font-weight: 700;
            margin: 0 0 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            line-height: 1.25;
        }
        .about-notice-card__title img {
            width: clamp(36px, 5vw, 44px);
            height: clamp(36px, 5vw, 44px);
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .about-notice-card__hint {
            font-family: var(--font-sans);
            color: #4b5563;
            font-size: clamp(0.95rem, 1.6vw, 1.1rem);
            margin: 0 0 1.15rem;
            font-weight: 500;
            line-height: 1.45;
        }
        .about-notice-video {
            flex: 1;
            min-height: clamp(260px, 42vw, 380px);
            background: #e8e9fc;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: stretch;
        }
        .about-notice-video video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
            display: block;
        }
        .about-copy {
            flex: 1 1 300px;
            min-width: 0;
        }
        .about-copy p {
            color: var(--muted);
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .about-vm {
            margin-top: 1.15rem;
            padding: 0;
        }
        .about-vm--mission { margin-top: 0.75rem; }
        .about-vm__label {
            display: block;
            font-family: var(--font-sans);
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            margin-bottom: 0.45rem;
        }
        .about-vm--vision .about-vm__label { color: #667eea; }
        .about-vm--mission .about-vm__label { color: #ff6b35; }
        .about-vm__text {
            margin: 0;
            font-family: var(--font-sans);
            font-size: 0.94rem;
            line-height: 1.6;
            font-weight: 500;
            color: var(--text);
        }
        .stats-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem; margin-top: 1.5rem;
        }
        .stat-card {
            background: var(--white); border-radius: var(--radius-sm);
            padding: 1rem; display: flex; gap: 0.75rem;
            align-items: flex-start;
            box-shadow: 0 4px 20px rgba(45, 35, 108, 0.06);
            border: 1px solid rgba(63, 65, 145, 0.06);
        }
        .stat-card__icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: var(--purple-soft); color: var(--purple);
            display: grid; place-items: center; flex-shrink: 0;
        }
        .stat-card__label { font-size: 0.78rem; color: var(--muted); }
        .stat-card__value { font-weight: 800; color: var(--indigo); font-size: 1.05rem; }
        .btn-services {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--purple); color: var(--white);
            font-weight: 700; padding: 0.85rem 1.75rem;
            border-radius: 999px; font-size: 1rem;
            margin-top: 1.75rem;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-services:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(93, 95, 239, 0.35); }

        /* ——— Feature Cards (Easy Applications / Quick Renewals / Track Progress) ——— */
        .features-section-white {
            background: #ffffff;
            padding: 4rem 2rem;
        }
        .features-container {
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        .feature-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.12);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-top: none;
            border-right: 2px solid rgba(102, 126, 234, 0.2);
            border-bottom: 2px solid rgba(102, 126, 234, 0.2);
            border-left: 2px solid rgba(102, 126, 234, 0.2);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #ff6b35;
            transition: background 0.4s ease;
        }
        .feature-card:hover {
            border-right-color: rgba(102, 126, 234, 0.4);
            border-bottom-color: rgba(102, 126, 234, 0.4);
            border-left-color: rgba(102, 126, 234, 0.4);
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.25);
            border-color: rgba(102, 126, 234, 0.3);
        }
        .feature-card:hover::before {
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        .feature-icon-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 1.5rem;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        .feature-card:hover .feature-icon-img {
            transform: scale(1.1) rotate(5deg);
        }
        .feature-card h3 {
            color: #2d3a4a;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.2px;
        }
        .feature-card p {
            color: #6b7a90;
            font-size: 1rem;
            line-height: 1.6;
            font-weight: 500;
        }
        @media (max-width: 576px) {
            .features-section-white {
                padding: 2rem 1rem;
            }
            .feature-card {
                padding: 1.5rem;
            }
            .feature-icon-img {
                width: 60px;
                height: 60px;
            }
            .feature-card h3 {
                font-size: 1.2rem;
            }
            .feature-card p {
                font-size: 0.9rem;
            }
        }

        /* ——— Announcements ——— */
        .announce-head {
            display: flex; flex-wrap: wrap; gap: 1.5rem 2rem;
            align-items: flex-end; justify-content: space-between;
            margin-bottom: 2rem;
        }
        .announce-head p { max-width: 28rem; color: var(--muted); font-size: 0.98rem; }
        .news-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem;
        }
        @media (max-width: 1024px) { .news-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .news-grid { grid-template-columns: 1fr; } }
        .news-card {
            background: var(--white); border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(45, 35, 108, 0.08);
            border: 1px solid rgba(63, 65, 145, 0.06);
            display: flex; flex-direction: column;
            transition: transform 0.2s;
        }
        .news-card:hover { transform: translateY(-4px); }
        .news-card__img { aspect-ratio: 16 / 10; object-fit: cover; object-position: center; width: 100%; background: #eef0f4; }
        .news-card__img--zoom {
            aspect-ratio: 16 / 10;
            object-fit: cover;
            object-position: center;
            width: 100%;
            background: #eef0f4;
        }
        .news-card__img-btn {
            position: relative;
            display: block;
            width: 100%;
            padding: 0;
            border: none;
            background: none;
            cursor: zoom-in;
            text-align: left;
        }
        .news-card__img-btn::after {
            content: '\f00e';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 0.65rem;
            bottom: 0.65rem;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(17, 24, 39, 0.72);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 0.82rem;
            opacity: 0;
            transform: scale(0.92);
            transition: opacity 0.2s, transform 0.2s;
            pointer-events: none;
        }
        .news-card__img-btn:hover::after,
        .news-card__img-btn:focus-visible::after {
            opacity: 1;
            transform: scale(1);
        }
        .news-card__img-btn:focus-visible {
            outline: 2px solid var(--purple);
            outline-offset: 2px;
        }
        .news-card__body { padding: 1.1rem 1.15rem 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .news-card__meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.65rem; font-size: 0.78rem; }
        .news-card__tag { font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .tag--imp { background: #fee2e2; color: #b91c1c; }
        .tag--notice { background: #dbeafe; color: #1d4ed8; }
        .tag--event { background: #dcfce7; color: #15803d; }
        .tag--update { background: #fef9c3; color: #a16207; }
        .news-card__date { color: #9ca3af; }
        .news-card h3 { font-size: 1.02rem; font-weight: 700; color: var(--indigo); margin-bottom: 0.5rem; line-height: 1.35; }
        .news-card p.desc { font-size: 0.88rem; color: var(--muted); flex: 1; margin-bottom: 0.85rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .read-more { font-weight: 600; font-size: 0.88rem; color: var(--purple); display: inline-flex; align-items: center; gap: 0.35rem; background: none; border: none; padding: 0; cursor: pointer; font-family: inherit; }
        .read-more:hover { text-decoration: underline; }

        /* News detail modal */
        .news-modal-overlay {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(17, 24, 39, 0.55);
            display: none; align-items: center; justify-content: center;
            padding: 1.25rem;
            backdrop-filter: blur(4px);
        }
        .news-modal-overlay.is-open { display: flex; }
        .news-modal {
            background: var(--white);
            border-radius: 16px;
            max-width: 560px;
            width: 100%;
            max-height: min(85vh, 720px);
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(45, 35, 108, 0.25);
            display: flex;
            flex-direction: column;
            animation: newsModalIn 0.22s ease;
        }
        @keyframes newsModalIn {
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .news-modal__head {
            padding: 1.1rem 1.25rem;
            background: linear-gradient(135deg, var(--purple) 0%, #4f52e0 100%);
            color: var(--white);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }
        .news-modal__head h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 0.35rem;
            line-height: 1.35;
            color: var(--white);
        }
        .news-modal__meta {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 0.78rem;
            opacity: 0.95;
        }
        .news-modal__close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: var(--white);
            width: 34px;
            height: 34px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.25rem;
            line-height: 1;
            flex-shrink: 0;
        }
        .news-modal__close:hover { background: rgba(255,255,255,0.32); }
        .news-modal__body {
            padding: 1.25rem 1.35rem 1.5rem;
            overflow-y: auto;
            color: var(--text);
            font-size: 0.95rem;
            line-height: 1.65;
        }
        .news-modal__body p { margin: 0 0 0.85rem; }
        .news-modal__body p:last-child { margin-bottom: 0; }
        .news-modal__photo {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 1rem;
            max-height: 320px;
            object-fit: contain;
            background: #f3f4f6;
            cursor: zoom-in;
        }

        /* News image lightbox */
        .news-image-overlay {
            position: fixed; inset: 0; z-index: 10000;
            background: rgba(17, 24, 39, 0.9);
            display: none; align-items: center; justify-content: center;
            padding: 1.5rem;
            backdrop-filter: blur(6px);
        }
        .news-image-overlay.is-open { display: flex; }
        .news-image-modal {
            position: relative;
            width: min(92vw, 920px);
            max-height: 92vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }
        .news-image-modal img {
            max-width: 100%;
            max-height: calc(92vh - 72px);
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            background: #fff;
        }
        .news-image-modal__caption {
            margin: 0;
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.92rem;
            text-align: center;
            max-width: 100%;
        }
        .news-image-modal__close {
            position: absolute;
            top: -2.75rem;
            right: 0;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
        }
        .news-image-modal__close:hover { background: rgba(255, 255, 255, 0.28); }

        /* ——— Services ——— */
        .services-band {
            background: linear-gradient(180deg, var(--purple) 0%, #4f52e0 100%);
            color: var(--white);
            padding: clamp(3rem, 6vw, 4.5rem) 0;
        }
        .services-head {
            display: flex; flex-wrap: wrap; gap: 1rem 2rem;
            align-items: flex-end; justify-content: space-between;
            margin-bottom: 2.25rem;
        }
        .services-head .tag { background: rgba(255,255,255,0.2); color: var(--white); }
        .services-head h2 { color: var(--white); font-size: clamp(1.85rem, 3.5vw, 2.5rem); }
        .services-head p { max-width: 22rem; opacity: 0.92; font-size: 0.95rem; }
        .services-row {
            display: flex; gap: 1rem;
            overflow-x: auto; padding-bottom: 0.5rem;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }
        .services-row::-webkit-scrollbar { height: 6px; }
        .services-row::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.35); border-radius: 999px; }
        .services-more {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        .btn-more-services {
            flex: 0 0 auto;
            width: auto;
            min-width: min(220px, 85vw);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--white);
            color: var(--indigo-deep) !important;
            font-family: var(--font-sans);
            font-weight: 800;
            padding: 0.95rem 2.25rem;
            border-radius: 999px;
            font-size: 1rem;
            white-space: nowrap;
            letter-spacing: 0.01em;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.12);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-more-services:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.25);
        }
        .service-card {
            flex: 0 0 min(220px, 85vw);
            scroll-snap-align: start;
            background: var(--white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(0,0,0,0.15);
            display: flex; flex-direction: column;
        }
        .service-card img { aspect-ratio: 16 / 10; object-fit: cover; width: 100%; }
        .service-card img.service-card__img--contain {
            aspect-ratio: auto;
            height: 118px;
            object-fit: contain;
            object-position: center;
            background: #f7f8fc;
            padding: 0.65rem 0.75rem;
            box-sizing: border-box;
        }
        .service-card img.service-card__img--banner {
            width: 100%;
            aspect-ratio: 16 / 10;
            object-fit: cover;
            object-position: center;
            display: block;
            flex-shrink: 0;
        }
        .service-card__body { padding: 1rem 1.1rem 1rem; color: var(--text); flex: 1; display: flex; flex-direction: column; }
        .service-card__icon { width: 38px; height: 38px; border-radius: 10px; background: var(--purple-soft); color: var(--purple); display: grid; place-items: center; margin-bottom: 0.65rem; font-size: 0.95rem; }
        .service-card h3 { font-size: 1rem; font-weight: 700; color: var(--indigo); margin-bottom: 0.35rem; }
        .service-card p { font-size: 0.82rem; color: var(--muted); line-height: 1.45; flex: 1; }
        .service-card__price { display: inline-block; margin-top: 0.85rem; font-size: 0.82rem; font-weight: 700; color: var(--purple); }

        /* ——— Contact ——— */
        .contact-grid { display: flex; flex-wrap: wrap; gap: clamp(2rem, 4vw, 3rem); }
        .contact-info { flex: 1 1 280px; }
        .contact-info > p { color: var(--muted); margin-bottom: 1.75rem; }
        .contact-item { display: flex; gap: 1rem; margin-bottom: 1.25rem; align-items: flex-start; }
        .contact-item__icon { width: 44px; height: 44px; border-radius: 50%; background: var(--purple-soft); color: var(--purple); display: grid; place-items: center; flex-shrink: 0; }
        .contact-item small { display: block; color: var(--muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .contact-item strong { color: var(--indigo); font-weight: 600; }
        .contact-form-wrap {
            flex: 1 1 320px;
            background: var(--white);
            border-radius: var(--radius);
            padding: clamp(1.5rem, 3vw, 2rem);
            box-shadow: var(--shadow);
            border: 1px solid rgba(63, 65, 145, 0.08);
        }
        .contact-form-wrap h3 { font-size: 1.2rem; color: var(--indigo); margin-bottom: 1.25rem; }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-group { flex: 1 1 140px; margin-bottom: 1rem; }
        .form-group--full { flex: 1 1 100%; }
        .form-group label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-bottom: 0.35rem; }
        .form-group input,
        .form-group textarea { width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #e5e7eb; border-radius: 10px; font-family: inherit; font-size: 0.95rem; }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group input:focus,
        .form-group textarea:focus { outline: none; border-color: var(--purple); box-shadow: 0 0 0 3px rgba(93, 95, 239, 0.15); }
        .btn-submit { width: 100%; padding: 0.9rem; background: var(--purple); color: var(--white); border: none; border-radius: 12px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 0.25rem; }
        .btn-submit:hover { filter: brightness(1.05); }
        .form-msg { margin-top: 0.75rem; font-size: 0.9rem; display: none; }
        .form-msg.is-show { display: block; }
        .form-msg.ok { color: #15803d; }
        .form-msg.err { color: #b91c1c; }

        /* ——— User portal ——— */
        .vendor-hero {
            position: relative;
            min-height: min(90vh, 720px);
            display: flex; align-items: center;
            padding: clamp(3rem, 8vw, 5rem) 0;
            color: var(--white);
        }
        .vendor-hero__bg {
            position: absolute; inset: 0;
            background-size: cover; background-position: center; background-repeat: no-repeat;
            z-index: -1;
        }
        .vendor-grid { display: flex; flex-wrap: nowrap; gap: 6rem; align-items: flex-start; justify-content: center; width: 100%; }
        .vendor-copy { flex: 1 1 280px; max-width: 440px; }
        .vendor-copy .brand__mark { width: 64px; height: 64px; font-size: 1.5rem; margin-bottom: 1.25rem; border: 2px solid rgba(255, 255, 255, 0.85); }
        .vendor-copy h2 { font-family: var(--font-serif); font-size: clamp(2.5rem, 5vw, 3.5rem); color: var(--white); line-height: 1.05; margin-bottom: 1rem; font-weight: 700; }
        .vendor-copy h2 .portal-line { display: block; color: #a5b4fc; }
        .vendor-copy > p { opacity: 0.95; margin-bottom: 1.5rem; line-height: 1.65; font-size: 0.95rem; }
        .feature-list { list-style: none; }
        .feature-list li { display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.85rem; font-size: 0.92rem; opacity: 0.95; }
        .feature-list i { width: 32px; height: 32px; border-radius: 8px; background: rgba(255,255,255,0.15); display: grid; place-items: center; flex-shrink: 0; font-size: 0.85rem; }
        .login-card {
            flex: 0 0 auto; width: min(360px, 100%);
            background: var(--white); border-radius: var(--radius);
            padding: 2rem 2rem 1.75rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.22);
            color: var(--text);
        }
        .login-card h3 { color: var(--indigo); font-size: 1.45rem; font-weight: 800; margin-bottom: 0.25rem; }
        .login-card .sub { color: var(--muted); font-size: 0.88rem; margin-bottom: 1.5rem; }
        .input-wrap { position: relative; margin-bottom: 1rem; }
        .input-wrap i.fa-user, .input-wrap i.fa-envelope, .input-wrap i.fa-lock { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.88rem; }
        .input-wrap input { width: 100%; padding: 0.78rem 0.85rem 0.78rem 2.4rem; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.93rem; font-family: inherit; color: var(--text); transition: border-color 0.15s; }
        .input-wrap input:focus { outline: none; border-color: var(--purple); box-shadow: 0 0 0 3px rgba(93, 95, 239, 0.12); }
        .input-wrap .toggle-pw { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0.25rem; }
        .login-card label.small { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text); margin-bottom: 0.4rem; }
        .login-options { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.1rem; font-size: 0.84rem; color: var(--text); }
        .login-options a { color: var(--purple); font-weight: 600; }
        .login-card .btn-submit { margin-top: 0; border-radius: 10px; font-size: 1rem; padding: 0.85rem; }
        .login-foot { margin-top: 1.1rem; text-align: center; font-size: 0.86rem; color: var(--muted); }
        .login-foot a { color: var(--purple); font-weight: 600; }

        /* ——— Footer ——— */
        .site-footer { background: var(--indigo-deep); color: rgba(255,255,255,0.88); padding: clamp(2.5rem, 5vw, 3.5rem) 0 1.5rem; }
        .footer-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255,255,255,0.12); }
        @media (max-width: 900px) { .footer-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .footer-grid { grid-template-columns: 1fr; } }
        .footer-brand .brand__mark { margin-bottom: 0.75rem; }
        .footer-brand h3 { font-family: var(--font-serif); color: var(--white); font-size: 1.15rem; margin-bottom: 0.65rem; }
        .footer-brand p { font-size: 0.88rem; opacity: 0.85; line-height: 1.55; margin-bottom: 1rem; }
        .social-row { display: flex; gap: 0.5rem; }
        .social-row a { width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,0.12); display: grid; place-items: center; color: var(--white); }
        .social-row a:hover { background: var(--purple); }
        .footer-col h4 { font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 1rem; color: var(--white); }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.5rem; font-size: 0.9rem; opacity: 0.85; }
        .footer-col a { font-size: 0.9rem; opacity: 0.85; }
        .footer-col a:hover { opacity: 1; color: var(--white); }
        .footer-contact li { display: flex; gap: 0.65rem; align-items: flex-start; margin-bottom: 0.65rem; font-size: 0.88rem; opacity: 0.9; }
        .footer-contact i { margin-top: 0.2rem; color: #a5b4fc; }
        .footer-bar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.75rem; padding-top: 1.25rem; font-size: 0.8rem; opacity: 0.75; }

        .vendor-hero .container { display: flex; justify-content: center; align-items: flex-start; padding-left: 4vw; padding-right: 4vw; max-width: 1200px; margin: 0 auto; }

        /* ——— Mobile: compact search bar + adaptive user login ——— */
        @media (max-width: 768px) {
            .immersive-hero {
                height: auto;
                min-height: 100vh;
                justify-content: flex-start;
                padding-top: clamp(9rem, 22vh, 12rem);
                padding-bottom: 2.5rem;
            }
            .hero-content { align-items: flex-start; }

            .hero-search {
                width: min(440px, 90vw);
                top: clamp(4.25rem, 9vh, 6rem);
            }
            .hero-search__inner {
                padding: 0.5rem 0.5rem 0.5rem 1.1rem;
                gap: 0.5rem;
            }
            .hero-search__inner input {
                font-size: 0.85rem;
                text-overflow: ellipsis;
            }
            .hero-search__btn {
                width: 36px;
                height: 36px;
                font-size: 0.82rem;
            }
            .hero-suggestion { padding: 0.7rem 1rem; font-size: 0.85rem; }
            .hero-suggestion__desc { font-size: 0.72rem; }

            /* User login — stack copy over the login card, full width */
            .vendor-hero { min-height: auto; }
            .vendor-grid {
                flex-wrap: wrap;
                gap: 2.25rem;
            }
            .vendor-copy {
                flex: 1 1 100%;
                max-width: 520px;
                width: 100%;
            }
            .login-card {
                width: 100%;
                max-width: 460px;
                margin: 0 auto;
            }
        }

        @media (max-width: 480px) {
            .hero-search { width: 92vw; }
            .hero-search__inner input { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <header class="site-nav" id="siteNav" data-lang-preserve="en">
        <div class="container site-nav__inner">
            <a href="#top" class="brand">
                <span class="brand__mark">
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Daet Public Market" width="44" height="44">
                </span>
                <span class="brand__stack">
                    <span class="brand__text">Daet Public Market</span>
                    <span class="brand__place">CAMARINES NORTE</span>
                </span>
            </a>
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Open menu"><i class="fas fa-bars"></i></button>
            <ul class="nav-links" id="navLinks">
                <li class="nav-close-wrap">
                    <button type="button" class="nav-close" id="navClose" aria-label="Close menu"><i class="fas fa-times"></i></button>
                </li>
                <li><a href="#about">About</a></li>
                <li><a href="#announcement">Announcement</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a class="btn-login" href="#user-portal"><i class="fas fa-right-to-bracket"></i> Login</a></li>
            </ul>
        </div>
    </header>

    <main id="top">

        
        <section class="immersive-hero" id="immersiveHero" aria-label="Welcome" data-lang-preserve="en">

            
            <div class="hero-bg" style="background-image: url('<?php echo $hero_bg; ?>')"></div>

            
            <div class="hero-overlay" aria-hidden="true"></div>

            <!-- Search bar -->
            <div class="hero-search" role="search">
                <div class="hero-search__inner">
                    <input type="text" id="heroSearchInput"
                           placeholder="Search: About, Announcement, Services, Contact…"
                           aria-label="Search sections"
                           autocomplete="off">
                    <button class="hero-search__btn" id="heroSearchBtn" aria-label="Search">
                        <i class="fas fa-magnifying-glass"></i>
                    </button>
                </div>
                <div class="hero-search__suggestions" id="heroSuggestions" role="listbox">
                    <div class="hero-suggestion" data-target="#about" role="option" tabindex="-1">
                        <span class="hero-suggestion__icon"><i class="fas fa-store"></i></span>
                        <span>
                            <div class="hero-suggestion__label">About</div>
                            <div class="hero-suggestion__desc">Learn about Daet Public Market</div>
                        </span>
                    </div>
                    <div class="hero-suggestion" data-target="#announcement" role="option" tabindex="-1">
                        <span class="hero-suggestion__icon"><i class="fas fa-bullhorn"></i></span>
                        <span>
                            <div class="hero-suggestion__label">Announcement</div>
                            <div class="hero-suggestion__desc">Latest news &amp; updates</div>
                        </span>
                    </div>
                    <div class="hero-suggestion" data-target="#services" role="option" tabindex="-1">
                        <span class="hero-suggestion__icon"><i class="fas fa-briefcase"></i></span>
                        <span>
                            <div class="hero-suggestion__label">Services</div>
                            <div class="hero-suggestion__desc">Stall rental, registration &amp; more</div>
                        </span>
                    </div>
                    <div class="hero-suggestion" data-target="#contact" role="option" tabindex="-1">
                        <span class="hero-suggestion__icon"><i class="fas fa-envelope"></i></span>
                        <span>
                            <div class="hero-suggestion__label">Contact</div>
                            <div class="hero-suggestion__desc">Get in touch with us</div>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="hero-content">
                <div class="hero-left">
                    <p class="hero-location">
                        <i class="fas fa-location-dot"></i>
                        Daet, Camarines Norte, Philippines
                    </p>
                    <h1 class="hero-title">WELCOME TO<br>DAET PUBLIC<br>MARKET</h1>
                    <p class="hero-desc">
                        Apply in a snap - Fast, Easy, Hassle free. Apply for stall slot now or renew in just a few clicks and documents.
                    </p>
                    <div class="hero-actions">
                        <a href="promotional-permit.php" class="btn-apply">
                            Explore More <i class="fas fa-arrow-right"></i>
                        </a>
                        <button class="btn-bookmark" aria-label="Save">
                            <i class="far fa-bookmark"></i>
                        </button>
                    </div>
                </div>

                <div class="hero-right">
                    <img src="<?php echo $hero_marketing; ?>"
                         alt="Daet Public Market promotional graphic"
                         class="hero-marketing-img">
                </div>
            </div>
        </section>

        <!--  carousel strip -->
        <section class="banner-strip" aria-label="Highlights">
            <div class="container banner-carousel" id="bannerCarousel">
                <div class="banner-dots" id="bannerDots"></div>
                <div class="banner-slide is-active" data-i="0">
                    <a href="#about" class="btn-pill-light">Explore Market <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="banner-slide" data-i="1">
                    <a href="#services" class="btn-pill-light">Our Services <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="banner-slide" data-i="2">
                    <a href="#user-portal" class="btn-pill-light">User Portal <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="banner-arrows">
                    <button type="button" id="bannerPrev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" id="bannerNext" aria-label="Next"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <p class="banner-scroll-hint">Scroll</p>
        </section>

        <!-- About -->
        <section class="section section--surface" id="about">
            <div class="container">
                <div class="about-grid">
                    <div class="about-visual">
                        <div class="about-notice-card">
                            <h3 class="about-notice-card__title">
                                <span data-translate="idx.notice">Notice</span>
                                <img src="<?php echo htmlspecialchars($pr); ?>assets/imgs/announcementicon.jpg" alt="">
                            </h3>
                            <p class="about-notice-card__hint">announcement, prices, birthdays, etc.</p>
                            <?php
                            
                            $homepage_announcement_video_path = null;
                            $homepage_announcement_media_id = null;
                            $conn_index = getDBConnection();
                            if ($conn_index) {
                                $mf_check = $conn_index->query("SHOW TABLES LIKE 'media_files'");
                                if ($mf_check && $mf_check->num_rows > 0) {
                                    $sec_col = $conn_index->query("SHOW COLUMNS FROM media_files LIKE 'section'");
                                    if ($sec_col && $sec_col->num_rows === 0) {
                                        $conn_index->query("ALTER TABLE media_files ADD COLUMN section VARCHAR(100) DEFAULT 'overview_video'");
                                    }
                                    $ha_stmt = $conn_index->prepare(
                                        "SELECT media_id, file_path FROM media_files
                                         WHERE file_type = 'video' AND section = 'homepage_announcement_video' AND (is_active = 1 OR is_active IS NULL)
                                         ORDER BY uploaded_at DESC, media_id DESC LIMIT 1"
                                    );
                                    if ($ha_stmt) {
                                        $ha_stmt->execute();
                                        $ha_res = $ha_stmt->get_result();
                                        if ($ha_row = $ha_res->fetch_assoc()) {
                                            $p = trim((string) ($ha_row['file_path'] ?? ''));
                                            $homepage_announcement_video_path = $p !== '' ? $p : null;
                                            $homepage_announcement_media_id = isset($ha_row['media_id']) ? (int) $ha_row['media_id'] : null;
                                        }
                                        $ha_stmt->close();
                                    }
                                }
                                $conn_index->close();
                            }
                            $homepage_announcement_video_ready = false;
                            $homepage_announcement_video_src = '';
                            $homepage_announcement_video_mime = 'video/mp4';
                            if (!empty($homepage_announcement_video_path)) {
                                $rel = str_replace('\\', '/', trim($homepage_announcement_video_path));
                                $rel = preg_replace('#/{2,}#', '/', $rel);
                                $rel = ltrim($rel, '/');
                                if ($rel !== '' && strpos($rel, '..') === false) {
                                    $abs = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                                    if (is_file($abs) && is_readable($abs)) {
                                        $homepage_announcement_video_ready = true;
                                        $parts = explode('/', $rel);
                                        $parts = array_map('rawurlencode', $parts);
                                        $homepage_announcement_video_src = implode('/', $parts);
                                        if ($homepage_announcement_media_id > 0) {
                                            $homepage_announcement_video_src .= '?v=' . $homepage_announcement_media_id;
                                        }
                                        if (function_exists('finfo_open')) {
                                            $fi = @finfo_open(FILEINFO_MIME_TYPE);
                                            if ($fi) {
                                                $detected = @finfo_file($fi, $abs);
                                                finfo_close($fi);
                                                if (is_string($detected) && strpos($detected, 'video/') === 0) {
                                                    $homepage_announcement_video_mime = $detected;
                                                }
                                            }
                                        }
                                        if (strpos($homepage_announcement_video_mime, 'video/') !== 0) {
                                            $ha_ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                                            $ext_mimes = [
                                                'mp4' => 'video/mp4',
                                                'webm' => 'video/webm',
                                                'ogg' => 'video/ogg',
                                                'ogv' => 'video/ogg',
                                                'mov' => 'video/quicktime',
                                                'qt' => 'video/quicktime',
                                                'avi' => 'video/x-msvideo',
                                                'wmv' => 'video/x-ms-wmv',
                                            ];
                                            if (isset($ext_mimes[$ha_ext])) {
                                                $homepage_announcement_video_mime = $ext_mimes[$ha_ext];
                                            }
                                        }
                                    }
                                }
                            }
                            if (!empty($homepage_announcement_video_ready) && $homepage_announcement_video_src !== ''): ?>
                                <div class="about-notice-video">
                                    <video id="aboutNoticeVideo" controls muted playsinline preload="metadata">
                                        <source src="<?php echo htmlspecialchars($homepage_announcement_video_src); ?>" type="<?php echo htmlspecialchars($homepage_announcement_video_mime); ?>">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                            <?php else: ?>
                                <span style="color:#6b7280;font-size:1rem;">No announcement video uploaded.</span>
                            <?php endif; ?>
                            <!-- --- END: Copy from index.php homepage announcement video logic --- -->
                        </div>
                    </div>
                    <div class="about-copy">
                        <span class="tag">About us</span>
                        <h2>Daet Public Market</h2>
                        <p>Market operation started way back 1980's and aftermath of the market total fire destruction, it was then rebuilt and established on 1997.</p>
                        <p>In 2002, Daet Public Market was developed under loan at Landbank of the Philippines in the amount of 51 million. It has (2) level structures with 566 stalls and was rebuilt after a fire incident in 2004.</p>
                        <p>As of today, Daet Public Market have undergone developments and innovations adaptive to stakeholders current needs and demands.</p>
                        <div class="about-vm about-vm--vision">
                            <span class="about-vm__label">Vision</span>
                            <p class="about-vm__text">Sustainable and Resilient Daet Public Market, Self-sufficient and Effective Local Economic Enterprise of the Local Government Unit of Daet.</p>
                        </div>
                        <div class="about-vm about-vm--mission">
                            <span class="about-vm__label">Mission</span>
                            <p class="about-vm__text">Inclusive and Progressive Daet Public Market with Sustainable Development and Disciplined Conduct of Businesses and Innovative Services and Operations for the Welfare of all Daetños.</p>
                        </div>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-location-dot"></i></div>
                                <div>
                                    <div class="stat-card__label">Location</div>
                                    <div class="stat-card__value">Daet, CN</div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-clock"></i></div>
                                <div>
                                    <div class="stat-card__label">Open hours</div>
                                    <div class="stat-card__value">8AM – 5PM</div>
                                </div>
                            </div>
                        </div>
                        <a class="btn-services" href="#services">Our Services <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Easy Applications / Quick Renewals / Track Progress -->
        <section class="features-section-white">
            <div class="features-container">
                <div class="feature-card">
                    <img src="<?php echo htmlspecialchars($pr); ?>assets/imgs/easy-application-icon.jpg" alt="Easy Applications" class="feature-icon-img">
                    <h3>Easy Applications</h3>
                    <p>Submit your market clearance applications quickly and efficiently through our streamlined process.</p>
                </div>
                <div class="feature-card">
                    <img src="<?php echo htmlspecialchars($pr); ?>assets/imgs/quick-renewals-icon.jpg" alt="Quick Renewals" class="feature-icon-img">
                    <h3>Quick Renewals</h3>
                    <p>Renew your existing clearances with just a few clicks and documents on it.</p>
                </div>
                <div class="feature-card">
                    <img src="<?php echo htmlspecialchars($pr); ?>assets/imgs/track-progress-icon.jpg" alt="Track Progress" class="feature-icon-img">
                    <h3>Track Progress</h3>
                    <p>Monitor the status of your applications in real-time from your profile.</p>
                </div>
            </div>
        </section>

        <!-- Announcements -->
        <section class="section" id="announcement">
            <div class="container">
                <div class="announce-head">
                    <div>
                        <span class="tag">Latest updates</span>
                        <h2>Announcements &amp; <em>News</em></h2>
                    </div>
                    <p>Stay updated with the latest news, events, and important notices from Daet Public Market.</p>
                </div>
                <div class="news-grid">
                    <?php foreach ($homepage_news_cards as $news_card): ?>
                    <?php
                        $nc_slot = (int) ($news_card['card_slot'] ?? 0);
                        $nc_tag_type = $news_card['tag_type'] ?? 'update';
                        $nc_tag_class = homepage_news_tag_class($nc_tag_type);
                        $nc_img = homepage_news_image_url($pr, $news_card['image_path'] ?? null, $homepage_news_fallback_img);
                        $nc_img_class = 'news-card__img';
                        if (in_array($nc_tag_type, ['notice', 'event', 'update'], true)) {
                            $nc_img_class .= ' news-card__img--zoom';
                        }
                    ?>
                    <article class="news-card">
                        <button type="button" class="news-card__img-btn" onclick="openNewsImageModal(<?php echo $nc_slot; ?>)" aria-label="View image for <?php echo dpm_promo_h($news_card['title'] ?? 'Announcement'); ?>">
                            <img class="<?php echo dpm_promo_h($nc_img_class); ?>" src="<?php echo htmlspecialchars($nc_img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo dpm_promo_h($news_card['title'] ?? 'Announcement'); ?>">
                        </button>
                        <div class="news-card__body">
                            <div class="news-card__meta">
                                <span class="news-card__tag <?php echo dpm_promo_h($nc_tag_class); ?>"><?php echo dpm_promo_h($news_card['tag_label'] ?? 'Update'); ?></span>
                                <span class="news-card__date"><?php echo dpm_promo_h($news_card['card_date'] ?? ''); ?></span>
                            </div>
                            <h3><?php echo dpm_promo_h($news_card['title'] ?? ''); ?></h3>
                            <p class="desc"><?php echo dpm_promo_h($news_card['summary'] ?? ''); ?></p>
                            <button type="button" class="read-more" onclick="openNewsDetailModal(<?php echo $nc_slot; ?>)">
                                Read More <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- News detail modal (Read More) -->
        <div class="news-modal-overlay" id="newsDetailModal" role="dialog" aria-modal="true" aria-labelledby="newsModalTitle" onclick="if(event.target===this)closeNewsDetailModal()">
            <div class="news-modal">
                <div class="news-modal__head">
                    <div>
                        <h3 id="newsModalTitle"></h3>
                        <div class="news-modal__meta">
                            <span class="news-card__tag" id="newsModalTag"></span>
                            <span id="newsModalDate"></span>
                        </div>
                    </div>
                    <button type="button" class="news-modal__close" onclick="closeNewsDetailModal()" aria-label="Close">&times;</button>
                </div>
                <div class="news-modal__body" id="newsModalBody"></div>
            </div>
        </div>

        <!-- News image lightbox -->
        <div class="news-image-overlay" id="newsImageModal" role="dialog" aria-modal="true" aria-labelledby="newsImageModalCaption" onclick="if(event.target===this)closeNewsImageModal()">
            <div class="news-image-modal">
                <button type="button" class="news-image-modal__close" onclick="closeNewsImageModal()" aria-label="Close">&times;</button>
                <img id="newsImageModalImg" src="" alt="">
                <p class="news-image-modal__caption" id="newsImageModalCaption"></p>
            </div>
        </div>

        <!-- Services -->
        <section class="services-band" id="services">
            <div class="container">
                <div class="services-head">
                    <div>
                        <span class="tag">What we offer</span>
                        <h2>Our Services</h2>
                    </div>
                    <p>Everything you need to start and grow your business at Daet Public Market.</p>
                </div>
                <div class="services-row">
                    <article class="service-card">
                        
                        <img class="service-card__img--banner" src="<?php echo htmlspecialchars($pr); ?>assets/imgs/stallrent-banner.png" alt="Stall Rental">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-house-chimney"></i></div>
                            <h3>Stall Rental</h3>
                            <p>Rent a market stall on our website with just several taps and documents -- quick and hassle free</p>
                            <span class="service-card__price">Rental</span>
                        </div>
                    </article>
                    <article class="service-card">
                        
                        <img class="service-card__img--banner" src="<?php echo htmlspecialchars($pr); ?>assets/imgs/register-banner.png?v=3" alt="Vendor Registration">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-file-signature"></i></div>
                            <h3>Vendor Registration</h3>
                            <p>Register as an official vendor at Daet Public Market. Get your documents now</p>
                            <span class="service-card__price">Register now!</span>
                        </div>
                    </article>
                    <article class="service-card">
                        <!-- CHANGED: replaced dynamic image with undermaintenance.jpg -->
                        <img class="service-card__img--banner" src="<?php echo htmlspecialchars($pr); ?>assets/imgs/renewal-banner.png" alt="Permit Renewal">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-arrows-rotate"></i></div>
                            <h3>Permit Renewal</h3>
                            <p>Renew your vendor permit in just few clicks and stall lease in an instant</p>
                            <span class="service-card__price">Renew</span>
                        </div>
                    </article>
                    <article class="service-card">
                        
                        <img class="service-card__img--banner" src="<?php echo htmlspecialchars($pr); ?>assets/imgs/inspection-banner.png" alt="Inspection Services">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-shield-halved"></i></div>
                            <h3>Inspection Services</h3>
                            <p>you can undergo in inspection after sending receipt</p>
                            <span class="service-card__price">Inspection</span>
                        </div>
                    </article>
                    <article class="service-card">
                       
                        <img class="service-card__img--banner" src="<?php echo htmlspecialchars($pr); ?>assets/imgs/customerservice-banner.png" alt="Vendor Support">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-headset"></i></div>
                            <h3>Vendor Support</h3>
                            <p>A widget that helps you and guide you with all your questions.</p>
                            <span class="service-card__price">Widget</span>
                        </div>
                    </article>
                </div>
                <div class="services-more">
                    <a href="promotional-permit.php" class="btn-more-services">More Services <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="section section--surface" id="contact">
            <div class="container">
                <div class="contact-grid">
                    <div class="contact-info">
                        <span class="tag">Get in touch</span>
                        <h2>Contact <em>Us</em></h2>
                        <p>Have questions about vendor registration, stall availability, or market services? Send messages or Reach out us through fb page or walk in on our office.</p>
                        <div class="contact-item">
                            <div class="contact-item__icon"><i class="fas fa-location-dot"></i></div>
                            <div>
                                <small>Address</small>
                                <strong>J. Lukban Street, Bagasbas Road, Brgy. VII, Daet, Camarines Norte</strong>
                            </div>
                        </div>
                        <div class="contact-item">
                            
                            <div class="contact-item__icon"><i class="fab fa-facebook-f"></i></div>
                            <div>
                                <small>Facebook Page</small>
                                <strong><a href="https://facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer">facebook.com/daetpublicmarket</a></strong>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-item__icon"><i class="fas fa-clock"></i></div>
                            <div>
                                <small>Office hours</small>
                                <strong>Mon–Fri: 8:00 AM – 5:00 PM</strong>
                            </div>
                        </div>
                    </div>
                    <div class="contact-form-wrap">
                        <h3>Send a Message</h3>
                        <form id="promoContactForm" novalidate>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="pf_name">Full Name</label>
                                    <input id="pf_name" name="full_name" type="text" placeholder="e.g Daniel Prado" autocomplete="name">
                                </div>
                                <div class="form-group">
                                    <label for="pf_email">Email</label>
                                    <input id="pf_email" name="email" type="email" placeholder="example@gmail.com" autocomplete="email">
                                </div>
                            </div>
                            <div class="form-group form-group--full">
                                <label for="pf_message">Message</label>
                                <textarea id="pf_message" name="message" placeholder="Write your message here..." required></textarea>
                            </div>
                            <button type="submit" class="btn-submit">Send Message</button>
                            <p class="form-msg" id="promoFormMsg" role="status"></p>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- User portal -->
        <section class="vendor-hero" id="user-portal">
            <div class="vendor-hero__bg" style="background-image: linear-gradient(105deg, rgba(46, 35, 108, 0.92) 0%, rgba(46, 35, 108, 0.75) 45%, rgba(93, 95, 239, 0.55) 100%), url('<?php echo htmlspecialchars($pr); ?>assets/imgs/palengke.jpg');" aria-hidden="true"></div>
            <div class="container vendor-grid">
                <div class="vendor-copy">
                    <div class="brand__mark">
                        <img src="<?php echo htmlspecialchars($pr); ?>assets/imgs/logo.jpeg" alt="" width="56" height="56"
                             onerror="var p=this.parentNode; this.remove(); var i=document.createElement('i'); i.className='fas fa-store'; i.setAttribute('aria-hidden','true'); p.appendChild(i);">
                    </div>
                    <h2>User<span class="portal-line">Portal</span></h2>
                    <p>Access your account to manage your stall, view announcements, renew permits, and track your market activities — all in one place.</p>
                    <ul class="feature-list">
                        <li><i class="fas fa-shield-halved"></i><span>Secure account management</span></li>
                        <li><i class="fas fa-file-lines"></i><span>Online permit renewal &amp; applications</span></li>
                        <li><i class="fas fa-bell"></i><span>Real-time announcements &amp; updates</span></li>
                    </ul>
                </div>
                <div class="login-card">
                    <h3>Welcome!</h3>
                    <p class="sub">Sign in to your account</p>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>#user-portal" autocomplete="on">
                        <input type="hidden" name="vendor_portal_login" value="1">
                        <label class="small" for="promo_email">Email</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input id="promo_email" type="email" name="email" placeholder="Enter your Email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <label class="small" for="promo_password">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input id="promo_password" type="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-pw" aria-label="Show password" data-target="promo_password"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="login-options">
                            <label><input type="checkbox" name="remember_me" value="1"> Remember me</label>
                            <a href="forgot_password.php">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn-submit">Sign In</button>
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-error show" style="background:#fee;color:#e74c3c;border:1px solid #fcc;padding:10px 16px;border-radius:8px;margin-bottom:18px;">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success show" style="background:#efe;color:#27ae60;border:1px solid #cfc;padding:10px 16px;border-radius:8px;margin-bottom:18px;">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                    </form>
                    <p class="login-foot">Don't have an account? <a href="registrationform.php">Sign up</a> </p>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="brand__mark" style="width:48px;height:48px;">
                        <img src="<?php echo htmlspecialchars($pr); ?>assets/imgs/logo.jpeg" alt="" width="48" height="48">
                    </div>
                    <h3>Daet Public Market</h3>
                    <p>if you have any questions or concerns feel free to message us on our facebook page</p>
                    <div class="social-row">
                        <!-- CHANGED: Facebook icon in footer now redirects to facebook.com/daetpublicmarket -->
                        <a href="https://facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#about">About</a></li>
                        <li><a href="#announcement">Announcements</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Services</h4>
                    <ul>
                        <li>Stall Rental</li>
                        <li>Vendor Registration</li>
                        <li>Permit Renewal</li>
                        <li>Inspection Services</li>
                        <li>Vendor Support</li>
                    </ul>
                </div>
                <div class="footer-col footer-contact">
                    <h4>Contact Info</h4>
                    <ul>
                        <li><i class="fas fa-location-dot"></i> Daet, Camarines Norte, Philippines</li>
                        
                        <li><i class="fab fa-facebook-f"></i> <a href="https://facebook.com/daetpublicmarket" target="_blank" rel="noopener noreferrer">facebook.com/daetpublicmarket</a></li>
                        <li><i class="fas fa-clock"></i> Open Mon-Fri: 8AM – 5PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bar">
                <span>&copy; <?php echo date('Y'); ?> Daet Public Market. All rights reserved.</span>
                <span>Local Government Unit of Daet, Camarines Norte</span>
            </div>
        </div>
    </footer>

    <?php
$cs_contact_href = '#contact';
$cs_announcement_href = !empty($homepage_announcement_video_ready) ? '#announcement' : '';
include __DIR__ . '/includes/mobile_enhancements.php';
include __DIR__ . '/includes/customer_support_widget.php';
?>

    <script>
    window.HOMEPAGE_NEWS_DETAILS = <?php
        $news_modal_data = [];
        foreach ($homepage_news_cards as $c) {
            $slot = (int) ($c['card_slot'] ?? 0);
            if ($slot < 1) continue;
            $news_modal_data[$slot] = [
                'title' => $c['title'] ?? '',
                'tag_label' => $c['tag_label'] ?? '',
                'tag_class' => homepage_news_tag_class($c['tag_type'] ?? 'update'),
                'card_date' => $c['card_date'] ?? '',
                'full_details' => $c['full_details'] ?? '',
                'image_url' => homepage_news_image_url($pr, $c['image_path'] ?? null, $homepage_news_fallback_img),
            ];
        }
        echo json_encode($news_modal_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    ?>;
    </script>
    <script>
    (function () {
        function openNewsImageModal(slot) {
            var data = window.HOMEPAGE_NEWS_DETAILS && window.HOMEPAGE_NEWS_DETAILS[slot];
            if (!data || !data.image_url) return;
            var overlay = document.getElementById('newsImageModal');
            var imgEl = document.getElementById('newsImageModalImg');
            var captionEl = document.getElementById('newsImageModalCaption');
            if (!overlay || !imgEl) return;
            imgEl.src = data.image_url;
            imgEl.alt = data.title || 'Announcement image';
            if (captionEl) captionEl.textContent = data.title || '';
            overlay.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
        function closeNewsImageModal() {
            var overlay = document.getElementById('newsImageModal');
            var imgEl = document.getElementById('newsImageModalImg');
            if (overlay) overlay.classList.remove('is-open');
            if (imgEl) imgEl.src = '';
            if (!document.getElementById('newsDetailModal') || !document.getElementById('newsDetailModal').classList.contains('is-open')) {
                document.body.style.overflow = '';
            }
        }
        function openNewsDetailModal(slot) {
            var data = window.HOMEPAGE_NEWS_DETAILS && window.HOMEPAGE_NEWS_DETAILS[slot];
            if (!data) return;
            var overlay = document.getElementById('newsDetailModal');
            var titleEl = document.getElementById('newsModalTitle');
            var tagEl = document.getElementById('newsModalTag');
            var dateEl = document.getElementById('newsModalDate');
            var bodyEl = document.getElementById('newsModalBody');
            if (!overlay || !titleEl || !bodyEl) return;
            titleEl.textContent = data.title || 'Announcement';
            if (tagEl) {
                tagEl.textContent = data.tag_label || '';
                tagEl.className = 'news-card__tag ' + (data.tag_class || 'tag--update');
            }
            if (dateEl) dateEl.textContent = data.card_date || '';
            var html = '';
            if (data.image_url) {
                html += '<img class="news-modal__photo" src="' + data.image_url.replace(/"/g, '&quot;') + '" alt="' + (data.title || 'Announcement').replace(/"/g, '&quot;') + '" onclick="openNewsImageModal(' + slot + ')">';
            }
            var text = (data.full_details || '').trim();
            if (!text) {
                html += '<p style="color:#6b7280;">No additional details available yet.</p>';
            } else {
                html += text.split(/\n\n+/).map(function (para) {
                    var safe = para
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/\n/g, '<br>');
                    return '<p>' + safe + '</p>';
                }).join('');
            }
            bodyEl.innerHTML = html;
            overlay.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
        function closeNewsDetailModal() {
            var overlay = document.getElementById('newsDetailModal');
            if (overlay) overlay.classList.remove('is-open');
            if (!document.getElementById('newsImageModal') || !document.getElementById('newsImageModal').classList.contains('is-open')) {
                document.body.style.overflow = '';
            }
        }
        window.openNewsImageModal = openNewsImageModal;
        window.closeNewsImageModal = closeNewsImageModal;
        window.openNewsDetailModal = openNewsDetailModal;
        window.closeNewsDetailModal = closeNewsDetailModal;
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (document.getElementById('newsImageModal') && document.getElementById('newsImageModal').classList.contains('is-open')) {
                closeNewsImageModal();
                return;
            }
            closeNewsDetailModal();
        });
    })();
    </script>
    <script>
    (function () {
        
        var siteNav = document.getElementById('siteNav');
        function onScroll() {
            siteNav.classList.toggle('scrolled', window.scrollY > 60);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        /* ── Mobile nav ── */
        var navToggle = document.getElementById('navToggle');
        var navLinks = document.getElementById('navLinks');
        var navBackdrop = document.getElementById('navBackdrop');
        var navScrollY = 0;
        function closeNav() {
            navLinks.classList.remove('is-open');
            navBackdrop.classList.remove('is-open');
            document.body.classList.remove('nav-open');
            document.body.style.top = '';
            if (siteNav) siteNav.classList.remove('menu-open');
            window.scrollTo(0, navScrollY);
        }
        function openNav() {
            navScrollY = window.scrollY || window.pageYOffset || 0;
            navLinks.classList.add('is-open');
            navBackdrop.classList.add('is-open');
            document.body.classList.add('nav-open');
            document.body.style.top = '-' + navScrollY + 'px';
            if (siteNav) siteNav.classList.add('menu-open');
        }
        var navClose = document.getElementById('navClose');
        if (navToggle && navLinks) {
            navToggle.addEventListener('click', function () {
                if (navLinks.classList.contains('is-open')) closeNav();
                else openNav();
            });
            if (navClose) navClose.addEventListener('click', closeNav);
            navBackdrop.addEventListener('click', closeNav);
            navLinks.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () {
                    if (a.getAttribute('href').charAt(0) === '#') closeNav();
                });
            });
        }

        /* ═══════════════════════════════════════════
           SMART SEARCH BAR
        ═══════════════════════════════════════════ */
        var searchInput  = document.getElementById('heroSearchInput');
        var searchBtn    = document.getElementById('heroSearchBtn');
        var suggestBox   = document.getElementById('heroSuggestions');
        var suggestions  = Array.from(document.querySelectorAll('.hero-suggestion'));

        // Shorter placeholder on mobile so the pill stays clean and readable
        (function () {
            if (!searchInput) return;
            var fullPh = searchInput.getAttribute('placeholder');
            function setPh() {
                searchInput.setAttribute('placeholder', window.innerWidth <= 480 ? 'Search…' : fullPh);
            }
            setPh();
            window.addEventListener('resize', setPh);
        })();

        var sectionMap = {
            'about':        '#about',
            'announcement': '#announcement',
            'announcements':'#announcement',
            'news':         '#announcement',
            'services':     '#services',
            'service':      '#services',
            'stall':        '#services',
            'rental':       '#services',
            'vendor':       '#services',
            'contact':      '#contact',
            'message':      '#contact',
            'reach':        '#contact',
            'portal':       '#user-portal',
            'login':        '#user-portal',
            'sign':         '#user-portal',
            'user':         '#user-portal',
            'apply':        '#user-portal',
        };

        var highlightIdx = -1;

        function getMatches(q) {
            if (!q) return suggestions; // show all if empty
            q = q.toLowerCase().trim();
            return suggestions.filter(function(s) {
                var label = s.querySelector('.hero-suggestion__label').textContent.toLowerCase();
                var desc  = s.querySelector('.hero-suggestion__desc').textContent.toLowerCase();
                return label.includes(q) || desc.includes(q);
            });
        }

        function showSuggestions(q) {
            var matches = getMatches(q);
            suggestions.forEach(function(s) {
                s.style.display = matches.includes(s) ? 'flex' : 'none';
                s.classList.remove('is-highlighted');
            });
            highlightIdx = -1;
            suggestBox.classList.toggle('is-open', matches.length > 0);
        }

        function hideSuggestions() {
            suggestBox.classList.remove('is-open');
            suggestions.forEach(function(s) { s.classList.remove('is-highlighted'); });
            highlightIdx = -1;
        }

        function doSearch(query) {
            hideSuggestions();
            if (!query) return;
            var q = query.toLowerCase().trim();
            var target = sectionMap[q];
            if (!target) {
                // fuzzy: pick first visible suggestion
                var visible = suggestions.filter(function(s) { return s.style.display !== 'none'; });
                if (visible.length) target = visible[0].getAttribute('data-target');
            }
            if (target) {
                var el = document.querySelector(target);
                if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                searchInput.value = '';
            }
        }

        function navigateSuggestion(dir) {
            var visible = suggestions.filter(function(s) { return s.style.display !== 'none'; });
            if (!visible.length) return;
            suggestions.forEach(function(s) { s.classList.remove('is-highlighted'); });
            highlightIdx = (highlightIdx + dir + visible.length) % visible.length;
            visible[highlightIdx].classList.add('is-highlighted');
            searchInput.value = visible[highlightIdx].querySelector('.hero-suggestion__label').textContent;
        }

        searchInput.addEventListener('input', function () { showSuggestions(this.value); });
        searchInput.addEventListener('focus', function () { showSuggestions(this.value); });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown')  { e.preventDefault(); navigateSuggestion(1); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); navigateSuggestion(-1); }
            else if (e.key === 'Enter') { e.preventDefault(); doSearch(this.value); }
            else if (e.key === 'Escape') hideSuggestions();
        });
        searchBtn.addEventListener('click', function () { doSearch(searchInput.value); });

        suggestions.forEach(function(s) {
            s.addEventListener('mousedown', function (e) {
                e.preventDefault(); // prevent blur first
                var target = s.getAttribute('data-target');
                var el = document.querySelector(target);
                if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                searchInput.value = '';
                hideSuggestions();
            });
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.hero-search')) hideSuggestions();
        });

        /* ── Banner carousel ── */
        var slides = document.querySelectorAll('.banner-slide');
        var dotsWrap = document.getElementById('bannerDots');
        var idx = 0;
        if (slides.length && dotsWrap) {
            slides.forEach(function (_, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('aria-label', 'Slide ' + (i + 1));
                if (i === 0) b.classList.add('is-active');
                b.addEventListener('click', function () { go(i); });
                dotsWrap.appendChild(b);
            });
            var dotBtns = dotsWrap.querySelectorAll('button');
            function go(n) {
                idx = (n + slides.length) % slides.length;
                slides.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
                dotBtns.forEach(function (d, i) { d.classList.toggle('is-active', i === idx); });
            }
            document.getElementById('bannerPrev').addEventListener('click', function () { go(idx - 1); });
            document.getElementById('bannerNext').addEventListener('click', function () { go(idx + 1); });
            setInterval(function () { go(idx + 1); }, 6000);
        }

        /* ── Password toggle ── */
        document.querySelectorAll('.toggle-pw').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-target');
                var inp = document.getElementById(id);
                if (!inp) return;
                var show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        });

        /* ── About notice video: play only while About section is in view ── */
        (function () {
            var aboutSection = document.getElementById('about');
            var noticeVideo = document.getElementById('aboutNoticeVideo');
            if (!aboutSection || !noticeVideo) return;

            noticeVideo.muted = true;
            noticeVideo.playsInline = true;

            function setNoticeVideoPlaying(shouldPlay) {
                if (shouldPlay) {
                    var playPromise = noticeVideo.play();
                    if (playPromise && typeof playPromise.catch === 'function') {
                        playPromise.catch(function () {});
                    }
                } else if (!noticeVideo.paused) {
                    noticeVideo.pause();
                }
            }

            if ('IntersectionObserver' in window) {
                var aboutVisible = false;
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        aboutVisible = entry.isIntersecting && entry.intersectionRatio >= 0.35;
                        setNoticeVideoPlaying(aboutVisible);
                    });
                }, {
                    threshold: [0, 0.2, 0.35, 0.5, 0.75, 1],
                    rootMargin: '-8% 0px -8% 0px'
                });
                observer.observe(aboutSection);
            } else {
                function syncNoticeVideoOnScroll() {
                    var rect = aboutSection.getBoundingClientRect();
                    var viewHeight = window.innerHeight || document.documentElement.clientHeight;
                    var visibleHeight = Math.min(rect.bottom, viewHeight) - Math.max(rect.top, 0);
                    var ratio = rect.height > 0 ? visibleHeight / rect.height : 0;
                    setNoticeVideoPlaying(ratio >= 0.35 && rect.bottom > 0 && rect.top < viewHeight);
                }
                window.addEventListener('scroll', syncNoticeVideoOnScroll, { passive: true });
                window.addEventListener('resize', syncNoticeVideoOnScroll);
                syncNoticeVideoOnScroll();
            }
        })();

        /* ── Contact form ── */
        var pf = document.getElementById('promoContactForm');
        var pmsg = document.getElementById('promoFormMsg');
        if (pf && pmsg) {
            pf.addEventListener('submit', function (e) {
                e.preventDefault();
                pmsg.className = 'form-msg';
                var msgEl = document.getElementById('pf_message');
                var msg = msgEl ? msgEl.value.trim() : '';
                if (!msg) {
                    pmsg.textContent = 'Please enter a message.';
                    pmsg.classList.add('is-show', 'err');
                    return;
                }
                var nameEl = document.getElementById('pf_name');
                var emailEl = document.getElementById('pf_email');
                var name = nameEl ? nameEl.value.trim() : '';
                var em = emailEl ? emailEl.value.trim() : '';
                var fd = new FormData();
                fd.append('message', msg);
                if (name) fd.append('full_name', name);
                if (em) fd.append('email', em);

                var btn = pf.querySelector('.btn-submit');
                if (btn) btn.disabled = true;
                pmsg.textContent = 'Sending…';
                pmsg.classList.add('is-show');

                fetch('submit_customer_concern.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (btn) btn.disabled = false;
                    pmsg.textContent = (data && data.message) ? data.message : 'Could not send. Please try again.';
                    pmsg.classList.add('is-show', (data && data.success) ? 'ok' : 'err');
                    if (data && data.success) {
                        pf.reset();
                    }
                })
                .catch(function () {
                    if (btn) btn.disabled = false;
                    pmsg.textContent = 'Could not send. Please try again later.';
                    pmsg.classList.add('is-show', 'err');
                });
            });
        }
    })();
    </script>
</body>
</html>