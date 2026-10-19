<?php
/**
 * Standalone promotional landing page — open as includes/promotionalwebsite_second.php
 * or require from root after defining PROMO_WEB_ROOT e.g. '' for same-dir assets.
 */
$pr = '../';
if (defined('PROMO_WEB_ROOT')) {
    $promo_web_root = constant('PROMO_WEB_ROOT');
    if (is_string($promo_web_root)) {
        $pr = $promo_web_root;
    }
}

/**
 * Collect photo paths under assets/imgs (relative to project root) for promotional sections.
 */
function dpm_promo_build_img_pool(string $projectRoot): array
{
    $pool = [];
    $dir = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'imgs';
    if (!is_dir($dir)) {
        return $pool;
    }
    $skip = ['logo.jpeg', 'calendar-icon.png', 'uk-flag.jpg', 'ph-flag.png'];
    $skipLower = array_map('strtolower', $skip);
    $items = @scandir($dir);
    if ($items === false) {
        return $pool;
    }
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $f;
        if (!is_file($full)) {
            continue;
        }
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) {
            continue;
        }
        if (in_array(strtolower($f), $skipLower, true)) {
            continue;
        }
        $pool[] = 'assets/imgs/' . $f;
    }
    $rootDir = $projectRoot;
    if (is_dir($rootDir)) {
        $rootItems = @scandir($rootDir);
        if ($rootItems !== false) {
            foreach ($rootItems as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $full = $rootDir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($full)) {
                    continue;
                }
                if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) {
                    continue;
                }
                if (in_array(strtolower($f), $skipLower, true)) {
                    continue;
                }
                $pool[] = $f;
            }
        }
    }
    return array_values(array_unique($pool));
}

/**
 * Public URL prefix + random local image, or external fallback if folder is empty.
 */
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
    $pr,
    $dpm_promo_pool,
    'https://images.unsplash.com/photo-1567449303078-57ad995bd329?w=1600&q=80'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daet Public Market — Vendor Portal &amp; Community</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --font-sans: 'Inter', system-ui, sans-serif;
            --font-serif: 'DM Serif Display', Georgia, serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-sans);
            color: var(--text);
            background: var(--white);
            line-height: 1.6;
        }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .container {
            width: min(1180px, 100% - 2.5rem);
            margin-inline: auto;
        }

        /* ——— Nav ——— */
        .site-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--white);
            border-bottom: 1px solid rgba(63, 65, 145, 0.08);
            box-shadow: 0 4px 24px rgba(45, 35, 108, 0.06);
        }
        .site-nav__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.85rem 0;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--indigo);
            letter-spacing: -0.02em;
        }
        .brand__mark {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple), var(--indigo));
            display: grid;
            place-items: center;
            color: var(--white);
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .brand__mark img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            list-style: none;
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--indigo);
        }
        .nav-links a:hover { color: var(--purple); }
        .btn-login {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: var(--purple);
            color: var(--white) !important;
            padding: 0.55rem 1.15rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(93, 95, 239, 0.35);
        }
        .nav-toggle {
            display: none;
            width: 44px;
            height: 44px;
            border: none;
            background: var(--purple-soft);
            border-radius: 10px;
            color: var(--indigo);
            font-size: 1.25rem;
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .nav-toggle { display: grid; place-items: center; }
            .nav-links {
                position: fixed;
                inset: 0 0 0 auto;
                width: min(280px, 85vw);
                background: var(--white);
                flex-direction: column;
                align-items: flex-start;
                padding: 5rem 1.5rem 2rem;
                gap: 1.25rem;
                box-shadow: -12px 0 40px rgba(0,0,0,0.12);
                transform: translateX(100%);
                transition: transform 0.25s ease;
            }
            .nav-links.is-open { transform: translateX(0); }
            .nav-links .btn-login { width: 100%; justify-content: center; }
        }
        .nav-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 27, 75, 0.35);
            z-index: 99;
        }
        .nav-backdrop.is-open { display: block; }

        /* ——— Hero dark ——— */
        .hero-dark {
            background: linear-gradient(165deg, var(--hero-dark) 0%, #1a1545 100%);
            color: var(--white);
            padding: clamp(2.5rem, 6vw, 4.5rem) 0 clamp(3rem, 8vw, 5rem);
            overflow: hidden;
        }
        .hero-dark__kicker {
            text-align: center;
            font-size: 0.72rem;
            letter-spacing: 0.2em;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 1rem;
        }
        .hero-dark__title {
            text-align: center;
            font-family: var(--font-serif);
            font-size: clamp(2rem, 5vw, 3.25rem);
            font-weight: 400;
            line-height: 1.15;
            max-width: 18ch;
            margin: 0 auto 1rem;
        }
        .hero-dark__sub {
            text-align: center;
            max-width: 36rem;
            margin: 0 auto 2.5rem;
            opacity: 0.92;
            font-size: 1rem;
        }
        .hero-trio {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: clamp(0.75rem, 2vw, 1.5rem);
            flex-wrap: nowrap;
        }
        .hero-trio__side {
            flex: 0 0 auto;
            width: clamp(100px, 22vw, 200px);
            aspect-ratio: 3 / 4;
            border-radius: var(--radius);
            object-fit: cover;
            box-shadow: 0 16px 40px rgba(0,0,0,0.35);
        }
        .hero-trio__center {
            flex: 0 0 auto;
            width: min(52vw, 420px);
            max-width: 100%;
            aspect-ratio: 4 / 3;
            border-radius: calc(var(--radius) + 4px);
            object-fit: cover;
            box-shadow: 0 24px 60px rgba(0,0,0,0.45);
            z-index: 1;
        }
        @media (max-width: 576px) {
            .hero-trio { flex-wrap: wrap; }
            .hero-trio__side { width: calc(50% - 0.5rem); aspect-ratio: 1; max-width: 160px; }
            .hero-trio__center { width: 100%; order: -1; max-height: 220px; }
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
            width: 8px;
            height: 8px;
            border-radius: 999px;
            border: none;
            background: rgba(255,255,255,0.35);
            cursor: pointer;
            padding: 0;
            transition: width 0.2s, background 0.2s;
        }
        .banner-dots button.is-active {
            width: 28px;
            background: var(--white);
        }
        .banner-slide {
            display: none;
            text-align: center;
            width: 100%;
        }
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
            border: none;
            cursor: pointer;
            transition: transform 0.15s;
        }
        .btn-pill-light:hover { transform: scale(1.02); }
        .banner-arrows {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            left: 0;
            pointer-events: none;
            display: flex;
            justify-content: space-between;
            padding: 0 0.25rem;
        }
        .banner-arrows button {
            pointer-events: auto;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.2);
            color: var(--white);
            cursor: pointer;
            display: grid;
            place-items: center;
        }
        .banner-arrows button:hover { background: rgba(255,255,255,0.35); }
        .banner-scroll-hint {
            text-align: center;
            font-size: 0.75rem;
            opacity: 0.65;
            margin-top: 1rem;
        }

        /* ——— Section common ——— */
        .section { padding: clamp(3rem, 6vw, 5rem) 0; }
        .section--surface { background: var(--surface); }
        .tag {
            display: inline-block;
            font-size: 0.68rem;
            font-weight: 700;
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
            color: var(--indigo);
            line-height: 1.2;
            margin-bottom: 0.75rem;
        }
        .section h2 em {
            font-style: italic;
            color: var(--purple);
        }

        /* ——— About ——— */
        .about-grid {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(2rem, 4vw, 3.5rem);
            align-items: center;
        }
        .about-visual {
            flex: 1 1 320px;
            position: relative;
        }
        .about-visual img {
            width: 100%;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .float-card {
            position: absolute;
            background: var(--white);
            padding: 0.85rem 1.1rem;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow);
            font-size: 0.85rem;
        }
        .float-card--tl { top: -0.5rem; left: -0.5rem; }
        .float-card--br { bottom: 1rem; right: -0.5rem; background: var(--purple); color: var(--white); }
        .float-card strong { display: block; font-size: 1.5rem; color: var(--purple); line-height: 1.1; }
        .float-card--br strong { color: var(--white); }
        .float-card span { color: var(--muted); font-size: 0.8rem; }
        .float-card--br span { color: rgba(255,255,255,0.9); }
        .about-copy {
            flex: 1 1 300px;
        }
        .about-copy p {
            color: var(--muted);
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
            margin-top: 1.5rem;
        }
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-sm);
            padding: 1rem;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            box-shadow: 0 4px 20px rgba(45, 35, 108, 0.06);
            border: 1px solid rgba(63, 65, 145, 0.06);
        }
        .stat-card__icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--purple-soft);
            color: var(--purple);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }
        .stat-card__label { font-size: 0.78rem; color: var(--muted); }
        .stat-card__value { font-weight: 800; color: var(--indigo); font-size: 1.05rem; }

        /* ——— Announcements ——— */
        .announce-head {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem 2rem;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .announce-head p {
            max-width: 28rem;
            color: var(--muted);
            font-size: 0.98rem;
        }
        .news-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
        }
        @media (max-width: 1024px) {
            .news-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 560px) {
            .news-grid { grid-template-columns: 1fr; }
        }
        .news-card {
            background: var(--white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(45, 35, 108, 0.08);
            border: 1px solid rgba(63, 65, 145, 0.06);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .news-card:hover { transform: translateY(-4px); }
        .news-card__img {
            aspect-ratio: 16 / 11;
            object-fit: cover;
            width: 100%;
        }
        .news-card__body { padding: 1.1rem 1.15rem 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .news-card__meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.65rem;
            font-size: 0.78rem;
        }
        .news-card__tag {
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .tag--imp { background: #fee2e2; color: #b91c1c; }
        .tag--notice { background: #dbeafe; color: #1d4ed8; }
        .tag--event { background: #dcfce7; color: #15803d; }
        .tag--update { background: #fef9c3; color: #a16207; }
        .news-card__date { color: #9ca3af; }
        .news-card h3 {
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--indigo);
            margin-bottom: 0.5rem;
            line-height: 1.35;
        }
        .news-card p.desc {
            font-size: 0.88rem;
            color: var(--muted);
            flex: 1;
            margin-bottom: 0.85rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .read-more {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--purple);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .read-more:hover { text-decoration: underline; }

        /* ——— Services purple ——— */
        .services-band {
            background: linear-gradient(180deg, var(--purple) 0%, #4f52e0 100%);
            color: var(--white);
            padding: clamp(3rem, 6vw, 4.5rem) 0;
        }
        .services-head {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem 2rem;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 2.25rem;
        }
        .services-head .tag {
            background: rgba(255,255,255,0.2);
            color: var(--white);
        }
        .services-head h2 {
            color: var(--white);
            font-size: clamp(1.85rem, 3.5vw, 2.5rem);
        }
        .services-head p {
            max-width: 22rem;
            opacity: 0.92;
            font-size: 0.95rem;
        }
        .services-row {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }
        .services-row::-webkit-scrollbar { height: 6px; }
        .services-row::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.35);
            border-radius: 999px;
        }
        .service-card {
            flex: 0 0 min(220px, 85vw);
            scroll-snap-align: start;
            background: var(--white);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(0,0,0,0.15);
        }
        .service-card img { aspect-ratio: 16 / 10; object-fit: cover; width: 100%; }
        .service-card__body { padding: 1rem 1.1rem 1.2rem; color: var(--text); }
        .service-card__icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--purple-soft);
            color: var(--purple);
            display: grid;
            place-items: center;
            margin-bottom: 0.65rem;
            font-size: 0.95rem;
        }
        .service-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--indigo);
            margin-bottom: 0.35rem;
        }
        .service-card p {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.45;
        }

        /* ——— Contact ——— */
        .contact-grid {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(2rem, 4vw, 3rem);
        }
        .contact-info { flex: 1 1 280px; }
        .contact-info > p { color: var(--muted); margin-bottom: 1.75rem; }
        .contact-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            align-items: flex-start;
        }
        .contact-item__icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--purple-soft);
            color: var(--purple);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }
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
        .contact-form-wrap h3 {
            font-size: 1.2rem;
            color: var(--indigo);
            margin-bottom: 1.25rem;
        }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-group { flex: 1 1 140px; margin-bottom: 1rem; }
        .form-group--full { flex: 1 1 100%; }
        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(93, 95, 239, 0.15);
        }
        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            background: var(--purple);
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 0.25rem;
        }
        .btn-submit:hover { filter: brightness(1.05); }
        .form-msg { margin-top: 0.75rem; font-size: 0.9rem; display: none; }
        .form-msg.is-show { display: block; }
        .form-msg.ok { color: #15803d; }
        .form-msg.err { color: #b91c1c; }

        /* ——— Vendor portal ——— */
        .vendor-hero {
            position: relative;
            min-height: min(90vh, 720px);
            display: flex;
            align-items: center;
            padding: clamp(3rem, 8vw, 5rem) 0;
            color: var(--white);
        }
        .vendor-hero__bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: -1;
        }
        .vendor-grid {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(2rem, 5vw, 4rem);
            align-items: center;
            width: 100%;
        }
        .vendor-copy { flex: 1 1 300px; max-width: 520px; }
        .vendor-copy .brand__mark {
            width: 56px;
            height: 56px;
            font-size: 1.35rem;
            margin-bottom: 1.25rem;
        }
        .vendor-copy h2 {
            font-family: var(--font-serif);
            font-size: clamp(2rem, 4vw, 2.85rem);
            color: var(--white);
            margin-bottom: 1rem;
        }
        .vendor-copy > p { opacity: 0.95; margin-bottom: 1.5rem; line-height: 1.65; }
        .feature-list { list-style: none; }
        .feature-list li {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            margin-bottom: 0.9rem;
            font-size: 0.95rem;
        }
        .feature-list i {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255,255,255,0.15);
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }
        .login-card {
            flex: 1 1 300px;
            max-width: 420px;
            margin-left: auto;
            background: var(--white);
            border-radius: var(--radius);
            padding: clamp(1.5rem, 3vw, 2rem);
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            color: var(--text);
        }
        .login-card h3 { color: var(--indigo); font-size: 1.35rem; margin-bottom: 0.25rem; }
        .login-card .sub { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.25rem; }
        .input-wrap {
            position: relative;
            margin-bottom: 1rem;
        }
        .input-wrap i.fa-envelope,
        .input-wrap i.fa-lock {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
        }
        .input-wrap input {
            width: 100%;
            padding: 0.75rem 0.85rem 0.75rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .input-wrap .toggle-pw {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 0.25rem;
        }
        .login-card label.small {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 0.35rem;
        }
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        .login-options a { color: var(--purple); font-weight: 600; }
        .login-card .btn-submit { margin-top: 0; }
        .login-foot {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.88rem;
            color: var(--muted);
        }
        .login-foot a { color: var(--purple); font-weight: 600; }

        /* ——— Footer ——— */
        .site-footer {
            background: var(--indigo-deep);
            color: rgba(255,255,255,0.88);
            padding: clamp(2.5rem, 5vw, 3.5rem) 0 1.5rem;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }
        @media (max-width: 900px) {
            .footer-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 520px) {
            .footer-grid { grid-template-columns: 1fr; }
        }
        .footer-brand .brand__mark {
            margin-bottom: 0.75rem;
        }
        .footer-brand h3 {
            font-family: var(--font-serif);
            color: var(--white);
            font-size: 1.15rem;
            margin-bottom: 0.65rem;
        }
        .footer-brand p {
            font-size: 0.88rem;
            opacity: 0.85;
            line-height: 1.55;
            margin-bottom: 1rem;
        }
        .social-row { display: flex; gap: 0.5rem; }
        .social-row a {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255,255,255,0.12);
            display: grid;
            place-items: center;
            color: var(--white);
        }
        .social-row a:hover { background: var(--purple); }
        .footer-col h4 {
            font-size: 0.72rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 1rem;
            color: var(--white);
        }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.5rem; }
        .footer-col a {
            font-size: 0.9rem;
            opacity: 0.85;
        }
        .footer-col a:hover { opacity: 1; color: var(--white); }
        .footer-contact li {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
            margin-bottom: 0.65rem;
            font-size: 0.88rem;
            opacity: 0.9;
        }
        .footer-contact i {
            margin-top: 0.2rem;
            color: #a5b4fc;
        }
        .footer-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 0.75rem;
            padding-top: 1.25rem;
            font-size: 0.8rem;
            opacity: 0.75;
        }
    </style>
</head>
<body>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <header class="site-nav">
        <div class="container site-nav__inner">
            <a href="#top" class="brand">
                <span class="brand__mark">
                    <img src="<?php echo htmlspecialchars($pr); ?>assets/imgs/logo.jpeg" alt="" width="44" height="44"
                         onerror="var p=this.parentNode; this.remove(); var i=document.createElement('i'); i.className='fas fa-store'; i.setAttribute('aria-hidden','true'); p.appendChild(i);">
                </span>
                <span class="brand__text">Daet Public Market</span>
            </a>
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Open menu"><i class="fas fa-bars"></i></button>
            <ul class="nav-links" id="navLinks">
                <li><a href="#about">About</a></li>
                <li><a href="#announcement">Announcement</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a class="btn-login" href="<?php echo htmlspecialchars($pr); ?>userlogin.php"><i class="fas fa-right-to-bracket"></i> Login</a></li>
            </ul>
        </div>
    </header>

    <main id="top">
        <!-- Hero: dark gateway -->
        <section class="hero-dark" aria-label="Welcome">
            <div class="container">
                <p class="hero-dark__kicker">Your gateway to Daet Public Market</p>
                <h1 class="hero-dark__title">Apply for Your Market Slot Today</h1>
                <p class="hero-dark__sub">Join Daet Public Market as a vendor! Apply for new market slots or renew your existing vendor registration.</p>
                <div class="hero-trio">
                    <img class="hero-trio__side" src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1555529908-865ab0d9f8e4?w=500&q=80')); ?>" alt="Market building exterior">
                    <img class="hero-trio__center" src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1488459716781-31db52582fe9?w=900&q=80')); ?>" alt="Fresh produce at market stall">
                    <img class="hero-trio__side" src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1604719312566-8912e9227c6a?w=500&q=80')); ?>" alt="Market interior">
                </div>
            </div>
        </section>

        <!-- Indigo carousel strip -->
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
                    <a href="#vendor-portal" class="btn-pill-light">Vendor Portal <i class="fas fa-arrow-right"></i></a>
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
                        <img src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1567449303078-57ad995bd329?w=960&q=80')); ?>" alt="Daet Public Market building">
                        <div class="float-card float-card--tl">
                            <strong>500+</strong>
                            <span>Active Vendors</span>
                        </div>
                        <div class="float-card float-card--br">
                            <strong style="font-size:1rem;">50+ Years</strong>
                            <span>of Service</span>
                        </div>
                    </div>
                    <div class="about-copy">
                        <span class="tag">About us</span>
                        <h2>The Heart of Daet's Vibrant Community</h2>
                        <p>Daet Public Market has been the cornerstone of commerce and community in Daet, Camarines Norte for over five decades. We provide a safe, organized, and thriving marketplace for hundreds of local vendors and thousands of daily shoppers.</p>
                        <p>Whether you are applying for a new stall, renewing your permit, or visiting for fresh goods, our team is here to support you.</p>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-store"></i></div>
                                <div>
                                    <div class="stat-card__label">Market stalls</div>
                                    <div class="stat-card__value">500+</div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-card__icon"><i class="fas fa-users"></i></div>
                                <div>
                                    <div class="stat-card__label">Daily visitors</div>
                                    <div class="stat-card__value">5,000+</div>
                                </div>
                            </div>
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
                                    <div class="stat-card__label">Open daily</div>
                                    <div class="stat-card__value">5AM – 6PM</div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                    <article class="news-card">
                        <img class="news-card__img" src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1542838132-29c444cd35c3?w=640&q=80')); ?>" alt="">
                        <div class="news-card__body">
                            <div class="news-card__meta">
                                <span class="news-card__tag tag--imp">Important</span>
                                <span class="news-card__date">April 10, 2026</span>
                            </div>
                            <h3>Market Slot Application Now Open</h3>
                            <p class="desc">New vendor applications are being accepted for the upcoming quarter. Submit requirements early to secure your preferred section.</p>
                            <a class="read-more" href="<?php echo htmlspecialchars($pr); ?>application.php">Read more <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>
                    <article class="news-card">
                        <img class="news-card__img" src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1488459716781-31db52582fe9?w=640&q=80')); ?>" alt="">
                        <div class="news-card__body">
                            <div class="news-card__meta">
                                <span class="news-card__tag tag--notice">Notice</span>
                                <span class="news-card__date">April 5, 2026</span>
                            </div>
                            <h3>Market Clearance Schedule — Q2 2026</h3>
                            <p class="desc">Review posted schedules for stall inspections, renewals, and compliance deadlines for the second quarter.</p>
                            <a class="read-more" href="<?php echo htmlspecialchars($pr); ?>index.php">Read more <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>
                    <article class="news-card">
                        <img class="news-card__img" src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=640&q=80')); ?>" alt="">
                        <div class="news-card__body">
                            <div class="news-card__meta">
                                <span class="news-card__tag tag--event">Event</span>
                                <span class="news-card__date">March 28, 2026</span>
                            </div>
                            <h3>Daet Market Day Festival 2026</h3>
                            <p class="desc">Join us for special activities, local products, and community programs celebrating our vendors and shoppers.</p>
                            <a class="read-more" href="#announcement">Read more <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>
                    <article class="news-card">
                        <img class="news-card__img" src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=640&q=80')); ?>" alt="">
                        <div class="news-card__body">
                            <div class="news-card__meta">
                                <span class="news-card__tag tag--update">Update</span>
                                <span class="news-card__date">March 15, 2026</span>
                            </div>
                            <h3>New Vendor Guidelines Released</h3>
                            <p class="desc">Updated policies on stall assignments, sanitation, and documentation are now available at the market office.</p>
                            <a class="read-more" href="#contact">Read more <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </article>
                </div>
            </div>
        </section>

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
                        <img src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1604719312566-8912e9227c6a?w=480&q=80')); ?>" alt="">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-house-chimney"></i></div>
                            <h3>Stall rental</h3>
                            <p>Rent a market stall in prime locations with clear guidelines and fair processes.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <img src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=480&q=80')); ?>" alt="">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-file-signature"></i></div>
                            <h3>Vendor registration</h3>
                            <p>Register as an official vendor and access the online clearance system.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <img src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=480&q=80')); ?>" alt="">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-arrows-rotate"></i></div>
                            <h3>Permit renewal</h3>
                            <p>Renew your vendor permit and stall assignment through a streamlined process.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <img src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=480&q=80')); ?>" alt="">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-shield-halved"></i></div>
                            <h3>Sanitation services</h3>
                            <p>Regular sanitation and waste management keep the market safe for everyone.</p>
                        </div>
                    </article>
                    <article class="service-card">
                        <img src="<?php echo dpm_promo_h(dpm_promo_img_src($pr, $dpm_promo_pool, 'https://images.unsplash.com/photo-1521791136064-7986c2920216?w=480&q=80')); ?>" alt="">
                        <div class="service-card__body">
                            <div class="service-card__icon"><i class="fas fa-headset"></i></div>
                            <h3>Vendor support</h3>
                            <p>Dedicated support to help you with applications, renewals, and concerns.</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="section section--surface" id="contact">
            <div class="container">
                <div class="contact-grid">
                    <div class="contact-info">
                        <span class="tag">Get in touch</span>
                        <h2>Contact Us</h2>
                        <p>Have questions about vendor registration, stall availability, or market services? Reach out to us and our team will get back to you as soon as possible.</p>
                        <div class="contact-item">
                            <div class="contact-item__icon"><i class="fas fa-location-dot"></i></div>
                            <div>
                                <small>Address</small>
                                <strong>Daet Public Market, Daet, Camarines Norte, Philippines</strong>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-item__icon"><i class="fas fa-phone"></i></div>
                            <div>
                                <small>Phone</small>
                                <strong>+63 (054) 571-0000</strong>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-item__icon"><i class="fas fa-envelope"></i></div>
                            <div>
                                <small>Email</small>
                                <strong>info@daetpublicmarket.gov.ph</strong>
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
                                    <label for="pf_name">Full name</label>
                                    <input id="pf_name" name="full_name" type="text" placeholder="Juan dela Cruz" autocomplete="name">
                                </div>
                                <div class="form-group">
                                    <label for="pf_email">Email</label>
                                    <input id="pf_email" name="email" type="email" placeholder="juan@email.com" autocomplete="email">
                                </div>
                            </div>
                            <div class="form-group form-group--full">
                                <label for="pf_subject">Subject</label>
                                <input id="pf_subject" name="subject" type="text" placeholder="Stall inquiry / vendor registration">
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

        <!-- Vendor portal -->
        <section class="vendor-hero" id="vendor-portal">
            <div class="vendor-hero__bg" style="background-image: linear-gradient(105deg, rgba(46, 35, 108, 0.92) 0%, rgba(46, 35, 108, 0.75) 45%, rgba(93, 95, 239, 0.55) 100%), url('<?php echo dpm_promo_h($dpm_vendor_bg); ?>');" aria-hidden="true"></div>
            <div class="container vendor-grid">
                <div class="vendor-copy">
                    <div class="brand__mark"><i class="fas fa-store"></i></div>
                    <h2>Vendor Portal</h2>
                    <p>Access your vendor account to manage your stall, view announcements, renew permits, and track your market activities — all in one place.</p>
                    <ul class="feature-list">
                        <li><i class="fas fa-shield-halved"></i><span>Secure vendor account management</span></li>
                        <li><i class="fas fa-file-lines"></i><span>Online permit renewal &amp; applications</span></li>
                        <li><i class="fas fa-bell"></i><span>Real-time announcements &amp; updates</span></li>
                    </ul>
                </div>
                <div class="login-card">
                    <h3>Welcome Back</h3>
                    <p class="sub">Sign in to your vendor account</p>
                    <form method="post" action="<?php echo htmlspecialchars($pr); ?>userlogin.php" autocomplete="on">
                        <label class="small" for="promo_email">Email address</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input id="promo_email" type="email" name="email" placeholder="Enter your email" required>
                        </div>
                        <label class="small" for="promo_password">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input id="promo_password" type="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="toggle-pw" aria-label="Show password" data-target="promo_password"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="login-options">
                            <label><input type="checkbox" name="remember_me" value="1"> Remember me</label>
                            <a href="<?php echo htmlspecialchars($pr); ?>forgot_password.php">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn-submit">Sign In</button>
                    </form>
                    <p class="login-foot">Don't have an account? <a href="<?php echo htmlspecialchars($pr); ?>registrationform.php">Sign up</a> or contact the Market Office.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="brand__mark" style="width:48px;height:48px;"><i class="fas fa-store"></i></div>
                    <h3>Daet Public Market</h3>
                    <p>Serving the community of Daet, Camarines Norte since the 1970s. Your trusted marketplace for fresh produce and local goods.</p>
                    <div class="social-row">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="X"><i class="fab fa-x-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Quick links</h4>
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
                        <li><a href="#services">Stall rental</a></li>
                        <li><a href="<?php echo htmlspecialchars($pr); ?>registrationform.php">Vendor registration</a></li>
                        <li><a href="<?php echo htmlspecialchars($pr); ?>renewal.php">Permit renewal</a></li>
                        <li><a href="#services">Sanitation services</a></li>
                        <li><a href="#contact">Vendor support</a></li>
                    </ul>
                </div>
                <div class="footer-col footer-contact">
                    <h4>Contact info</h4>
                    <ul>
                        <li><i class="fas fa-location-dot"></i> Daet, Camarines Norte, Philippines</li>
                        <li><i class="fas fa-phone"></i> +63 (054) 571-0000</li>
                        <li><i class="fas fa-envelope"></i> info@daetpublicmarket.gov.ph</li>
                        <li><i class="fas fa-clock"></i> Open daily: 5AM – 6PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bar">
                <span>&copy; 2026 Daet Public Market. All rights reserved.</span>
                <span>Local Government Unit of Daet, Camarines Norte</span>
            </div>
        </div>
    </footer>

    <script>
    (function () {
        var navToggle = document.getElementById('navToggle');
        var navLinks = document.getElementById('navLinks');
        var navBackdrop = document.getElementById('navBackdrop');
        function closeNav() {
            navLinks.classList.remove('is-open');
            navBackdrop.classList.remove('is-open');
        }
        if (navToggle && navLinks) {
            navToggle.addEventListener('click', function () {
                navLinks.classList.toggle('is-open');
                navBackdrop.classList.toggle('is-open');
            });
            navBackdrop.addEventListener('click', closeNav);
            navLinks.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    if (a.getAttribute('href').charAt(0) === '#') closeNav();
                });
            });
        }

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

        var pf = document.getElementById('promoContactForm');
        var pmsg = document.getElementById('promoFormMsg');
        if (pf && pmsg) {
            pf.addEventListener('submit', function (e) {
                e.preventDefault();
                pmsg.className = 'form-msg';
                var msg = document.getElementById('pf_message').value.trim();
                if (!msg) {
                    pmsg.textContent = 'Please enter a message.';
                    pmsg.classList.add('is-show', 'err');
                    return;
                }
                var subj = document.getElementById('pf_subject').value.trim();
                var name = document.getElementById('pf_name').value.trim();
                var em = document.getElementById('pf_email').value.trim();
                var body = (subj ? 'Subject: ' + subj + '\n\n' : '') + msg;
                if (name) body = 'From: ' + name + (em ? ' <' + em + '>\n\n' : '\n\n') + body;
                else if (em) body = 'From: ' + em + '\n\n' + body;
                window.location.href = 'mailto:info@daetpublicmarket.gov.ph?subject=' +
                    encodeURIComponent(subj || 'Daet Public Market inquiry') +
                    '&body=' + encodeURIComponent(body);
                pmsg.textContent = 'Your email app should open. If it does not, email info@daetpublicmarket.gov.ph';
                pmsg.classList.add('is-show', 'ok');
            });
        }
    })();
    </script>
    <?php
    $dpm_web_root = '../';
    $cs_contact_href = '../index.php#contact';
    $cs_announcement_href = '../index.php#announcement';
    include __DIR__ . '/customer_support_widget.php';
    ?>
</body>
</html>
