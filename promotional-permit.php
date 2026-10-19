<?php
$pr = '';
$logo = $pr . 'assets/imgs/logo.jpeg';
$hero_video = $pr . 'assets/imgs/promotional-permit.mp4';
$hero_poster = $pr . 'assets/imgs/palengke.jpg';

function promo_h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$fb = 'https://facebook.com/daetpublicmarket';

$ticker = [
    'Promotional permits processed in as little as 12 minutes',
    '108 CCTV units keeping the market area safe',
    'One-stop market office for vendors and clients',
    'Timbangan ng Bayan for fair and accurate weighing',
    'LED wall and banner ads for in-market promotions',
    'Stall registration and permit renewal in a few steps',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services — Daet Public Market</title>
    <link rel="icon" type="image/jpeg" href="<?php echo promo_h($logo); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --orange: #ff6b1a;
            --orange-dark: #e85d0f;
            --orange-soft: #fff1e6;
            --indigo: #667eea;
            --indigo-deep: #2e236c;
            --indigo-soft: #ecebff;
            --ink: #1c1733;
            --muted: #5c5672;
            --line: #eceaf6;
            --cream: #f6f4fb;
            --white: #fff;
            --serif: 'DM Serif Display', Georgia, serif;
            --sans: 'Inter', system-ui, sans-serif;
            --radius: 22px;
            --shadow: 0 18px 50px rgba(46, 35, 108, 0.08);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--sans); color: var(--ink); background: var(--cream); line-height: 1.6; }
        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }
        .wrap {
            width: min(1160px, calc(100% - clamp(1.25rem, 4vw, 2.4rem)));
            margin-inline: auto;
        }

        /* Nav (matches index.php: transparent → white on scroll) */
        .site-nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            background: transparent;
            transition: background 0.3s, box-shadow 0.3s;
        }
        .site-nav.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 4px 24px rgba(45, 35, 108, 0.08);
        }
        .site-nav__inner {
            display: flex; align-items: center; justify-content: space-between;
            gap: clamp(0.5rem, 1.5vw, 1rem);
            padding: clamp(0.75rem, 2vw, 1.1rem) 0;
            min-width: 0;
        }
        .brand {
            display: flex; align-items: center; gap: 0.65rem; transition: color 0.3s;
            min-width: 0; flex: 1 1 auto;
        }
        .brand__stack { display: flex; flex-direction: column; gap: 0.1rem; }
        .brand__mark {
            width: 44px; height: 44px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.85);
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .site-nav.scrolled .brand__mark { border-color: #efeefe; }
        .brand__mark img { width: 100%; height: 100%; object-fit: cover; }
        .brand__text {
            font-family: var(--serif);
            font-weight: 400;
            font-size: clamp(0.88rem, 1.55vw, 1.08rem);
            line-height: 1.15;
            letter-spacing: 0;
            color: #fff;
            transition: color 0.3s;
        }
        .site-nav.scrolled .brand__text { color: var(--indigo-deep); }
        .brand__place {
            display: block; font-size: 0.62rem; letter-spacing: 0.14em; font-weight: 700;
            color: rgba(255, 255, 255, 0.78); margin-top: 0.15rem; transition: color 0.3s;
        }
        .site-nav.scrolled .brand__place { color: var(--muted); }
        .nav-links {
            display: flex; align-items: center; flex-wrap: nowrap;
            gap: clamp(0.55rem, 1.35vw, 1.35rem);
            list-style: none;
            font-size: clamp(0.78rem, 1.05vw, 0.9rem);
            font-weight: 600;
            color: rgba(255, 255, 255, 0.92); transition: color 0.3s;
            flex: 0 1 auto; min-width: 0;
        }
        .nav-links a { white-space: nowrap; }
        .site-nav.scrolled .nav-links { color: var(--indigo-deep); }
        .nav-links a:hover { color: var(--orange); }
        .btn-inquire {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: var(--orange); color: #fff !important;
            padding: clamp(0.55rem, 1.2vw, 0.72rem) clamp(0.75rem, 1.8vw, 1.15rem);
            border-radius: 999px; font-weight: 700;
            font-size: clamp(0.78rem, 1.05vw, 0.9rem);
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 18px rgba(255,107,26,0.28);
        }
        .btn-inquire:hover {
            background: var(--orange-dark);
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.5), 0 0 15px rgba(255, 107, 53, 0.3);
        }
        .nav-toggle {
            display: none; width: 44px; height: 44px; border: none; border-radius: 10px;
            background: rgba(255, 255, 255, 0.15); color: #fff; font-size: 1.15rem; cursor: pointer;
            transition: background 0.3s, color 0.3s;
        }
        .site-nav.scrolled .nav-toggle {
            background: var(--indigo-soft); color: var(--indigo-deep);
        }
        body.nav-open {
            position: fixed;
            width: 100%;
            overflow: hidden;
        }
        body.nav-open .site-nav { z-index: 230; }
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
        .nav-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(30, 27, 75, 0.35);
            z-index: 210;
        }
        .nav-backdrop.is-open { display: block; }

        /* Hero */
        .hero { position: relative; min-height: 100vh; display: flex; align-items: stretch; color: #fff; overflow: hidden; background: #120e28; }
        .hero__media { position: absolute; inset: 0; z-index: 0; overflow: hidden; }
        .hero__media video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
        }
        .hero__shade {
            position: absolute; inset: 0; z-index: 1;
            background:
                linear-gradient(90deg, rgba(12,8,32,0.82) 0%, rgba(12,8,32,0.55) 46%, rgba(12,8,32,0.22) 100%),
                linear-gradient(to top, rgba(12,8,32,0.78) 0%, rgba(12,8,32,0.15) 46%, transparent 70%);
        }
        /* Mobile: crop baked-in “Palengke Tour” title out of the hero video */
        @media (max-width: 900px) {
            .hero__media video {
                height: 118%;
                width: 108%;
                max-width: none;
                object-fit: cover;
                object-position: 50% 78%;
                transform: translate(-4%, -10%) scale(1.06);
                transform-origin: 50% 70%;
            }
            .hero__shade {
                background:
                    linear-gradient(to bottom, rgba(12, 8, 32, 0.97) 0%, rgba(12, 8, 32, 0.88) 14%, rgba(12, 8, 32, 0.45) 38%, transparent 68%),
                    linear-gradient(90deg, rgba(12,8,32,0.88) 0%, rgba(12,8,32,0.55) 50%, rgba(12,8,32,0.28) 100%),
                    linear-gradient(to top, rgba(12,8,32,0.82) 0%, rgba(12,8,32,0.2) 42%, transparent 72%);
            }
        }
        @media (max-width: 480px) {
            .hero__media video {
                height: 125%;
                width: 112%;
                object-position: 50% 82%;
                transform: translate(-6%, -14%) scale(1.1);
            }
        }
        .hero__inner {
            position: relative; z-index: 2;
            width: min(1160px, calc(100% - clamp(1.25rem, 4vw, 2.4rem)));
            margin: 0 auto;
            padding: clamp(4.75rem, 11vh, 6.25rem) 0 clamp(1rem, 2.5vh, 1.15rem);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .hero__badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            width: max-content; max-width: 100%; align-self: flex-start;
            border: 1px solid rgba(255,255,255,0.28); background: rgba(255,255,255,0.08);
            border-radius: 999px; padding: 0.5rem 1rem; font-size: clamp(0.85rem, 1.5vw, 0.95rem); font-weight: 600;
            letter-spacing: 0.03em; text-transform: none; white-space: nowrap;
            margin-bottom: 1.25rem;
        }
        .hero__badge i { color: var(--orange); font-size: 0.5rem; }
        .hero h1 {
            font-family: var(--serif); font-weight: 400;
            font-size: clamp(1.95rem, 3.2vw + 0.85rem, 4.35rem);
            line-height: 1.08;
            max-width: min(13em, 100%);
            text-wrap: balance;
        }
        .hero__word {
            display: block;
            color: var(--orange);
            font-style: italic;
            min-height: 1.15em;
            margin-top: 0.12em;
        }
        .hero__word span { display: inline-block; animation: wordIn 0.55s ease; }
        @keyframes wordIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .hero p {
            max-width: min(38rem, 100%);
            margin: clamp(0.85rem, 2vh, 1.1rem) 0 clamp(1.1rem, 2.5vh, 1.6rem);
            color: rgba(255,255,255,0.88);
            font-size: clamp(0.92rem, 1.1vw + 0.65rem, 1.02rem);
        }
        .hero p strong { color: #fff; }
        .hero__actions {
            display: flex; flex-wrap: wrap; gap: 0.75rem;
            margin-bottom: clamp(1.35rem, 3vh, 2.4rem);
        }
        .btn-solid, .btn-ghost {
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 999px;
            padding: clamp(0.72rem, 1.5vw, 0.9rem) clamp(0.95rem, 2vw, 1.25rem);
            font-weight: 700;
            font-size: clamp(0.82rem, 1vw + 0.55rem, 0.92rem);
        }
        .btn-solid { background: var(--orange); color: #fff; }
        .btn-solid:hover { background: var(--orange-dark); }
        .btn-ghost { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.35); }
        .btn-ghost:hover { background: rgba(255,255,255,0.16); }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: clamp(0.45rem, 1.2vw, 0.75rem);
            background: rgba(18, 14, 40, 0.55); border: 1px solid rgba(255,255,255,0.16);
            backdrop-filter: blur(10px); border-radius: 18px;
            padding: clamp(0.65rem, 1.5vw, 0.85rem);
            width: 100%;
        }
        .stat {
            display: flex; align-items: center; gap: clamp(0.45rem, 1vw, 0.75rem);
            padding: 0.45rem 0.55rem; min-width: 0;
        }
        .stat > div { min-width: 0; }
        .stat__icon {
            width: clamp(34px, 4vw, 42px); height: clamp(34px, 4vw, 42px);
            border-radius: 50%; flex-shrink: 0;
            display: grid; place-items: center; background: rgba(255,107,26,0.16); color: var(--orange);
            font-size: clamp(0.78rem, 1.2vw, 0.95rem);
        }
        .stat strong {
            display: block;
            font-size: clamp(0.92rem, 1.35vw + 0.35rem, 1.15rem);
            line-height: 1.15;
            overflow-wrap: anywhere;
        }
        .stat > div > span.hero-stat-detail {
            display: block;
            font-size: clamp(0.65rem, 0.85vw + 0.35rem, 0.75rem);
            color: rgba(255,255,255,0.72);
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .scroll-cue { text-align: center; margin-top: 1.1rem; font-size: 0.68rem; letter-spacing: 0.22em; opacity: 0.75; }
        .scroll-cue i { display: block; margin-top: 0.35rem; animation: bob 1.6s ease-in-out infinite; }
        @keyframes bob { 50% { transform: translateY(5px); } }

        /* Ticker */
        .ticker { background: var(--indigo-deep); color: #fff; overflow: hidden; border-top: 1px solid rgba(255,255,255,0.08); }
        .ticker__track { display: flex; width: max-content; animation: tickerMove 36s linear infinite; }
        .ticker:hover .ticker__track { animation-play-state: paused; }
        .ticker__group { display: flex; }
        .ticker__item { display: inline-flex; align-items: center; gap: 0.55rem; padding: 0.72rem 1.35rem; white-space: nowrap; font-size: 0.86rem; font-weight: 600; }
        .ticker__item i { color: #ffb98a; font-size: 0.7rem; }
        @keyframes tickerMove { from { transform: translateX(0); } to { transform: translateX(-50%); } }

        /* Sections */
        .section { padding: clamp(3.5rem, 7vw, 5.25rem) 0; }
        .kicker {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: var(--orange-soft); color: var(--orange-dark);
            font-size: 0.68rem; font-weight: 800; letter-spacing: 0.14em;
            padding: 0.35rem 0.75rem; border-radius: 999px; margin-bottom: 0.9rem;
        }
        .kicker i { font-size: 0.45rem; }
        .section-title { font-family: var(--serif); font-weight: 400; font-size: clamp(2.1rem, 4vw, 3.3rem); line-height: 1.08; color: var(--indigo-deep); }
        .section-title em { color: var(--orange); font-style: italic; }
        .section-lead { max-width: 40rem; margin: 0.85rem auto 0; color: var(--muted); }
        .center { text-align: center; }

        .cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 2.2rem; }
        .card {
            background: #fff; border: 1px solid var(--line); border-radius: var(--radius);
            padding: 1.35rem 1.3rem 1.2rem; box-shadow: var(--shadow);
            display: flex; flex-direction: column; min-height: 230px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover { transform: translateY(-4px); box-shadow: 0 22px 40px rgba(46,35,108,0.12); }
        .card__top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.3rem; }
        .card__icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: var(--indigo-soft); color: var(--indigo); }
        .pill { font-size: 0.62rem; font-weight: 800; letter-spacing: 0.08em; color: #9a6b7a; background: #f8eef1; padding: 0.28rem 0.55rem; border-radius: 999px; }
        .card h3 { font-size: 1.15rem; margin-bottom: 0.4rem; color: var(--indigo-deep); }
        .card p { color: var(--muted); font-size: 0.92rem; flex: 1; }
        .card__link {
            margin-top: auto; padding-top: 1rem; color: var(--indigo); font-weight: 700; font-size: 0.9rem;
            background: none; border: none; cursor: pointer; font-family: inherit; text-align: left;
            display: inline-flex; align-items: center; gap: 0.35rem;
        }
        .card__link:hover { color: var(--orange); }
        .card__more {
            display: none; margin-top: 0.85rem; padding-top: 0.85rem; border-top: 1px solid var(--line);
            font-size: 0.88rem; color: var(--muted);
        }
        .card.is-expanded .card__more { display: block; }
        .card__more ul { list-style: none; margin-top: 0.55rem; display: grid; gap: 0.4rem; }
        .card__more li { display: flex; gap: 0.5rem; align-items: flex-start; }
        .card__more li i { color: var(--orange); margin-top: 0.2rem; font-size: 0.75rem; }
        .card__more img { width: 100%; max-height: 140px; object-fit: contain; margin-top: 0.75rem; border-radius: 12px; background: var(--cream); }
        .card.is-expanded .card__link i.fa-arrow-right { transform: rotate(90deg); }
        .card__link i.fa-arrow-right { transition: transform 0.2s ease; }

        .service-modal[hidden] { display: none !important; }
        .service-modal {
            position: fixed; inset: 0; z-index: 200;
            display: flex; align-items: center; justify-content: center;
            padding: 1.25rem;
            background: rgba(18, 14, 40, 0.55);
            opacity: 0; visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .service-modal.is-open { opacity: 1; visibility: visible; }
        .service-modal__panel {
            position: relative;
            width: min(520px, 100%);
            max-height: min(90vh, 640px);
            overflow-y: auto;
            background: #fff;
            border-radius: var(--radius);
            padding: 1.75rem 1.6rem 1.5rem;
            box-shadow: 0 24px 60px rgba(46, 35, 108, 0.28);
            border: 1px solid var(--line);
            transform: scale(0.92) translateY(12px);
            transition: transform 0.25s ease;
        }
        .service-modal.is-open .service-modal__panel { transform: scale(1) translateY(0); }
        .service-modal__close {
            position: absolute; top: 1rem; right: 1rem;
            width: 40px; height: 40px; border: none; border-radius: 50%;
            background: linear-gradient(135deg, var(--indigo) 0%, var(--indigo-deep) 100%);
            color: #fff; font-size: 1.35rem; line-height: 1; cursor: pointer;
            display: grid; place-items: center;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .service-modal__close:hover {
            transform: scale(1.08) rotate(90deg);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.45);
        }
        .service-modal__head { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; padding-right: 2.5rem; }
        .service-modal__head .card__icon { margin: 0; }
        .service-modal__head h3 { font-size: 1.35rem; color: var(--indigo-deep); margin: 0; }
        .service-modal__body { color: var(--muted); font-size: 0.95rem; }
        .service-modal__body ul { list-style: none; margin-top: 0.75rem; display: grid; gap: 0.5rem; }
        .service-modal__body li { display: flex; gap: 0.55rem; align-items: flex-start; }
        .service-modal__body li i { color: var(--orange); margin-top: 0.22rem; font-size: 0.78rem; }
        .service-modal__body img {
            width: 100%; max-height: 220px; object-fit: contain;
            margin-top: 1rem; border-radius: 14px; background: var(--cream); padding: 0.5rem;
        }
        .service-modal__cta {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--indigo);
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: 0.75rem;
        }
        .service-modal__cta:hover { color: var(--orange); }
        body.modal-open { overflow: hidden; }

        .fee-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 2rem; }
        .fee {
            background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 1.4rem;
            box-shadow: var(--shadow);
        }
        .fee__top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; color: var(--orange); }
        .fee small { letter-spacing: 0.1em; font-size: 0.62rem; font-weight: 800; color: var(--muted); }
        .fee h3 { font-size: 1.15rem; color: var(--indigo-deep); margin-bottom: 0.45rem; }
        .fee p { color: var(--muted); font-size: 0.9rem; min-height: 3.2em; }
        .fee__price { margin-top: 1rem; color: var(--indigo); font-weight: 800; font-size: 1.35rem; }
        .fee__price span { font-weight: 600; font-size: 0.85rem; color: var(--muted); }
        .fee a { display: inline-flex; margin-top: 0.9rem; color: var(--indigo); font-weight: 700; }
        .note {
            margin-top: 1.1rem; background: #fff; border: 1px solid var(--line); border-radius: 999px;
            padding: 0.7rem 0.8rem 0.7rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            box-shadow: var(--shadow);
        }
        .note p { color: var(--muted); font-size: 0.88rem; }
        .note i { color: var(--orange); margin-right: 0.4rem; }
        .note a { white-space: nowrap; background: var(--indigo-deep); color: #fff; border-radius: 999px; padding: 0.7rem 1rem; font-weight: 700; font-size: 0.88rem; }

        .two { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 1rem; margin-top: 1.6rem; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 1.5rem; box-shadow: var(--shadow); }
        .panel h3 { font-family: var(--serif); font-weight: 400; font-size: 1.7rem; color: var(--indigo-deep); margin-bottom: 0.9rem; }
        .checks { list-style: none; display: grid; gap: 0.6rem; }
        .checks li { display: flex; gap: 0.6rem; color: var(--muted); }
        .checks i { color: var(--orange); margin-top: 0.2rem; }
        .tags { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.8rem; }
        .tag { background: var(--indigo-soft); color: var(--indigo-deep); border-radius: 999px; padding: 0.28rem 0.65rem; font-size: 0.75rem; font-weight: 700; }
        .dark-panel { background: linear-gradient(160deg, var(--indigo-deep), #4d46b5); color: #fff; border-radius: var(--radius); padding: 1.5rem; }
        .dark-panel h3 { font-family: var(--serif); font-weight: 400; font-size: 1.6rem; margin-bottom: 0.8rem; }
        .row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.14); font-size: 0.9rem; }
        .row:last-child { border-bottom: none; }
        .row b { text-align: right; }
        .avail { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1rem; }
        .avail article { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 1rem; display: flex; gap: 0.75rem; }
        .avail b { width: 32px; height: 32px; border-radius: 10px; background: var(--indigo); color: #fff; display: grid; place-items: center; flex-shrink: 0; }
        .avail h4 { font-size: 0.95rem; color: var(--indigo-deep); }
        .avail p { color: var(--muted); font-size: 0.85rem; }

        .steps { display: grid; gap: 0.8rem; margin-top: 1.5rem; }
        .step { display: grid; grid-template-columns: 52px 1fr; gap: 0.9rem; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1rem 1.1rem; }
        .step__n { width: 52px; height: 52px; border-radius: 14px; background: var(--orange); color: #fff; display: grid; place-items: center; font-family: var(--serif); font-size: 1.4rem; }
        .step h4 { color: var(--indigo-deep); }
        .step p { color: var(--muted); font-size: 0.9rem; }
        .meta { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.55rem; }
        .meta span { background: var(--indigo-soft); color: var(--indigo-deep); border-radius: 999px; padding: 0.25rem 0.6rem; font-size: 0.75rem; font-weight: 700; }
        .total { margin-top: 1rem; color: var(--muted); }

        .gallery { max-width: 760px; margin: 1.6rem auto 0; }
        .gallery-viewport { position: relative; overflow: hidden; border-radius: var(--radius); background: #fff; border: 1px solid var(--line); box-shadow: var(--shadow); }
        .gallery-track { display: flex; transition: transform 0.45s ease; }
        .gallery-slide { flex: 0 0 100%; }
        .gallery-slide img { width: 100%; aspect-ratio: 16/9; object-fit: contain; background: #fff; }
        .gallery-arrow { position: absolute; top: 50%; transform: translateY(-50%); width: 42px; height: 42px; border: none; border-radius: 50%; background: #fff; color: var(--indigo-deep); box-shadow: 0 6px 16px rgba(46,35,108,0.18); cursor: pointer; }
        .gallery-arrow.prev { left: 0.7rem; }
        .gallery-arrow.next { right: 0.7rem; }
        .gallery-caption { text-align: center; margin-top: 0.8rem; font-weight: 700; color: var(--indigo-deep); }
        .gallery-dots { display: flex; justify-content: center; gap: 0.4rem; margin-top: 0.7rem; }
        .gallery-dots button { width: 8px; height: 8px; border: none; border-radius: 99px; background: #d9d6ea; cursor: pointer; }
        .gallery-dots button.is-active { width: 22px; background: var(--orange); }

        .faq { display: grid; grid-template-columns: 0.8fr 1.2fr; gap: 2rem; align-items: start; }
        .faq__side p { color: var(--muted); margin: 0.7rem 0 1rem; }
        .help { background: #eefaf3; border-radius: 18px; padding: 1rem; }
        .help strong { display: block; color: var(--indigo-deep); }
        .help p { margin: 0.2rem 0 0.5rem; }
        .help a { color: #1f8a4c; font-weight: 700; }
        .faq__item { background: #fff; border-radius: 16px; margin-bottom: 0.65rem; border: 1px solid var(--line); overflow: hidden; }
        .faq__item button { width: 100%; text-align: left; background: none; border: none; padding: 1rem 1.1rem; font: inherit; font-weight: 700; color: var(--indigo-deep); cursor: pointer; display: flex; justify-content: space-between; gap: 1rem; }
        .faq__item button i { color: var(--muted); transition: transform 0.2s; }
        .faq__item.is-open button i { transform: rotate(180deg); }
        .faq__item div { display: none; padding: 0 1.1rem 1rem; color: var(--muted); }
        .faq__item.is-open div { display: block; }

        /* Footer (matches index.php) */
        .site-footer > .wrap {
            width: min(1180px, 100% - 2.5rem);
            margin-inline: auto;
        }
        .site-footer { background: var(--indigo-deep); color: rgba(255,255,255,0.88); padding: clamp(2.5rem, 5vw, 3.5rem) 0 1.5rem; }
        .footer-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255,255,255,0.12); }
        @media (max-width: 900px) { .footer-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .footer-grid { grid-template-columns: 1fr; } }
        .footer-brand .brand__mark {
            width: 48px;
            height: 48px;
            margin-bottom: 0.75rem;
            border-color: rgba(255, 255, 255, 0.28);
        }
        .footer-brand h3 { font-family: var(--serif); color: var(--white); font-size: 1.15rem; margin-bottom: 0.65rem; font-weight: 400; }
        .footer-brand p { font-size: 0.88rem; opacity: 0.85; line-height: 1.55; margin-bottom: 1rem; }
        .social-row { display: flex; gap: 0.5rem; }
        .social-row a { width: 38px; height: 38px; border-radius: 10px; background: rgba(255,255,255,0.12); display: grid; place-items: center; color: var(--white); }
        .social-row a:hover { background: var(--indigo); }
        .footer-col h4 { font-size: 0.72rem; letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 1rem; color: var(--white); }
        .footer-col ul { list-style: none; }
        .footer-col li { margin-bottom: 0.5rem; font-size: 0.9rem; opacity: 0.85; }
        .footer-col a { font-size: 0.9rem; opacity: 0.85; }
        .footer-col a:hover { opacity: 1; color: var(--white); }
        .footer-bar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.75rem; padding-top: 1.25rem; font-size: 0.8rem; opacity: 0.75; }

        @media (max-width: 1240px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 980px) {
            .cards, .fee-grid, .faq, .two, .avail { grid-template-columns: 1fr; }
            .hero__badge { white-space: normal; }
            .note { border-radius: 18px; flex-direction: column; align-items: flex-start; }
            .hero h1 { max-width: none; }
        }
        @media (max-width: 1280px) {
            .nav-toggle { display: grid; place-items: center; }
            .site-nav.scrolled .nav-toggle {
                background: rgba(46, 35, 108, 0.08);
                color: var(--indigo-deep);
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
            .nav-links .btn-inquire,
            .nav-close {
                pointer-events: auto;
                position: relative;
                z-index: 1;
            }
            .nav-links .btn-inquire { width: 100%; justify-content: center; }
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
            .nav-links {
                font-size: 0.95rem;
                gap: 1.25rem;
            }
        }
        @media (max-width: 768px) {
            .wrap,
            .hero__inner {
                width: min(1160px, calc(100% - clamp(1rem, 4vw, 1.35rem)));
            }
            .hero__inner {
                padding-bottom: 1.5rem;
                min-height: 100svh;
            }
            .hero h1 {
                font-size: clamp(1.75rem, 6.8vw, 2.55rem);
                line-height: 1.18;
            }
            .hero p { font-size: 0.95rem; margin: 0.95rem 0 1.35rem; }
            .hero__actions {
                flex-direction: column;
                align-items: stretch;
                gap: 0.65rem;
                margin-bottom: 1.75rem;
            }
            .hero__actions .btn-solid,
            .hero__actions .btn-ghost {
                width: 100%;
                justify-content: center;
                padding: 0.85rem 1.1rem;
            }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.55rem; padding: 0.65rem; }
            .stat { padding: 0.35rem 0.4rem; gap: 0.55rem; }
            .stat__icon { width: 36px; height: 36px; font-size: 0.85rem; }
            .stat strong { font-size: 1rem; }
            .scroll-cue { margin-top: 0.85rem; font-size: 0.62rem; }
            .site-nav__inner { padding: 0.85rem 0; }
            .brand__mark { width: 40px; height: 40px; }
            .section { padding: clamp(2.75rem, 8vw, 3.5rem) 0; }
            .section-title { font-size: clamp(1.75rem, 6.5vw, 2.35rem); }
            .footer-bar { flex-direction: column; align-items: flex-start; gap: 0.35rem; }
        }
        @media (max-width: 480px) {
            .wrap,
            .hero__inner {
                width: min(1160px, calc(100% - 1rem));
            }
            .stats { grid-template-columns: 1fr; }
            .hero__badge {
                font-size: 0.8rem;
                padding: 0.45rem 0.85rem;
            }
            .site-footer li,
            .site-footer a { word-break: break-word; }
        }
    </style>
</head>
<body>
    <div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

    <header class="site-nav" id="siteNav" data-lang-preserve="en">
        <div class="wrap site-nav__inner">
            <a href="index.php" class="brand">
                <span class="brand__mark"><img src="<?php echo promo_h($logo); ?>" alt="Daet Public Market"></span>
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
                <li><a href="#value">Value</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#fees">Permits &amp; Fees</a></li>
                <li><a href="#process">Process</a></li>
                <li><a href="#charter">Charter</a></li>
                <li><a href="#faq">FAQ</a></li>
                <li><a class="btn-inquire" href="index.php"><i class="fas fa-arrow-left"></i> Back</a></li>
            </ul>
        </div>
    </header>

    <section class="hero" aria-label="Stall Slots">
        <div class="hero__media" aria-hidden="true">
            <video autoplay muted loop playsinline poster="<?php echo promo_h($hero_poster); ?>">
                <source src="<?php echo promo_h($hero_video); ?>" type="video/mp4">
            </video>
        </div>
        <div class="hero__shade"></div>
        <div class="hero__inner">
            <div class="hero__badge"><i class="fas fa-circle"></i> Daet, Camarines Norte</div>
            <h1>Everything you need at the Daet Public Market <span class="hero__word" id="heroWord"><span>Permits</span></span></h1>
            <p>Clear requirements, transparent fees, and a guided step-by-step process — for vendors, business owners, and shoppers alike. Compliant, quick, and processed in as little as <strong>12 minutes</strong>.</p>
            <div class="hero__actions">
                <a class="btn-solid" href="#services">Explore Market Services <i class="fas fa-arrow-right"></i></a>
                <a class="btn-ghost" href="#fees"><i class="far fa-file-lines"></i> Permits &amp; Fees</a>
            </div>
            <div class="stats">
                <div class="stat"><span class="stat__icon"><i class="fas fa-store"></i></span><div><strong>Market</strong><span class="hero-stat-detail">Office services</span></div></div>
                <div class="stat"><span class="stat__icon"><i class="fas fa-clock"></i></span><div><strong>12 min</strong><span class="hero-stat-detail">Average processing</span></div></div>
                <div class="stat"><span class="stat__icon"><i class="fas fa-video"></i></span><div><strong>108</strong><span class="hero-stat-detail">CCTV units active</span></div></div>
                <div class="stat"><span class="stat__icon"><i class="fas fa-peso-sign"></i></span><div><strong>Php 0</strong><span class="hero-stat-detail">Permit processing fee</span></div></div>
            </div>
            <div class="scroll-cue">SCROLL <i class="fas fa-chevron-down"></i></div>
        </div>
    </section>

    <div class="ticker" aria-hidden="true">
        <div class="ticker__track">
            <?php for ($copy = 0; $copy < 2; $copy++): ?>
            <div class="ticker__group">
                <?php foreach ($ticker as $line): ?>
                <span class="ticker__item"><i class="fas fa-star"></i> <?php echo promo_h($line); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <main>
        <section class="section" id="services">
            <div class="wrap">
                <div class="center">
                    <span class="kicker"><i class="fas fa-circle"></i> Market services</span>
                    <h2 class="section-title">What you can do at the <em>Daet Market</em></h2>
                    <p class="section-lead">From promotional permits to stall registration and market-wide safety, these are the services our office handles for vendors and clients every day.</p>
                </div>
                <div class="cards">
                    <article class="card">
                        <div class="card__top"><span class="card__icon"><i class="fas fa-bullhorn"></i></span><span class="pill">PERMITS</span></div>
                        <h3>Promotional Permit</h3>
                        <p>Apply to conduct promotional activity within the market’s scope of management. Compliant and processed in as little as 12 minutes.</p>
                        <button type="button" class="card__link js-service-modal-open" data-src="modal-src-promo" data-title="Promotional Permit" data-icon="fa-bullhorn" aria-haspopup="dialog">Learn more <i class="fas fa-arrow-right"></i></button>
                    </article>
                    <article class="card">
                        <div class="card__top"><span class="card__icon"><i class="fas fa-tv"></i></span><span class="pill">ADVERTISING</span></div>
                        <h3>Billboards &amp; Streamers</h3>
                        <p>Commercial LED walls, banners, and streamers used for business advertising, with clear monthly fees and display rules.</p>
                        <button type="button" class="card__link js-service-modal-open" data-src="modal-src-ads" data-title="Billboards & Streamers" data-icon="fa-tv" aria-haspopup="dialog">Learn more <i class="fas fa-arrow-right"></i></button>
                    </article>
                    <article class="card">
                        <div class="card__top"><span class="card__icon"><i class="fas fa-store"></i></span><span class="pill">VENDORS</span></div>
                        <h3>Stall Registration</h3>
                        <p>New and renewing vendors can register stalls, settle rental dues, and get assigned a space in the trading area.</p>
                        <button type="button" class="card__link js-service-modal-open" data-src="modal-src-stall" data-title="Stall Registration" data-icon="fa-store" aria-haspopup="dialog">Learn more <i class="fas fa-arrow-right"></i></button>
                    </article>
                    <article class="card" id="cctv">
                        <div class="card__top"><span class="card__icon"><i class="fas fa-video"></i></span><span class="pill">SECURITY</span></div>
                        <h3>CCTV Monitoring</h3>
                        <p>108 camera units cover stalls, walkways, entrances, and common areas to keep vendors and shoppers safe.</p>
                        <button type="button" class="card__link js-service-modal-open" data-src="modal-src-cctv" data-title="CCTV Monitoring" data-icon="fa-video" aria-haspopup="dialog">Learn more <i class="fas fa-arrow-right"></i></button>
                    </article>
                    <article class="card" id="timbangan">
                        <div class="card__top"><span class="card__icon"><i class="fas fa-weight-scale"></i></span><span class="pill">FAIR TRADE</span></div>
                        <h3>Timbangan ng Bayan</h3>
                        <p>A public weighing station for produce, meat, and fish so buyers and vendors can check weight before a sale.</p>
                        <button type="button" class="card__link js-service-modal-open" data-src="modal-src-timbangan" data-title="Timbangan ng Bayan" data-icon="fa-weight-scale" aria-haspopup="dialog">Learn more <i class="fas fa-arrow-right"></i></button>
                    </article>
                    <article class="card">
                        <div class="card__top"><span class="card__icon"><i class="fas fa-headset"></i></span><span class="pill">ASSISTANCE</span></div>
                        <h3>Client Assistance Desk</h3>
                        <p>Market office staff help you applicants and resolve questions on permits, receipts, and requirements.</p>
                        <button type="button" class="card__link js-service-modal-open" data-src="modal-src-assist" data-title="Client Assistance Desk" data-icon="fa-headset" aria-haspopup="dialog">Learn more <i class="fas fa-arrow-right"></i></button>
                    </article>
                </div>
            </div>
        </section>

        <section class="section" id="fees" style="padding-top:0;">
            <div class="wrap">
                <div class="center">
                    <span class="kicker"><i class="fas fa-circle"></i> Permits &amp; fees</span>
                    <h2 class="section-title">Clear fees for ads <em>inside the market</em></h2>
                    <p class="section-lead">Commercial billboards and streamers used for business advertisements within the market’s scope of management.</p>
                </div>
                <div class="fee-grid">
                    <article class="fee">
                        <div class="fee__top"><i class="fas fa-tv"></i><small>LED WALL AD. DISPLAY</small></div>
                        <h3>LED Wall Advertisement</h3>
                        <p>Additional requirement: electronic copy of the advertisement for display.</p>
                        <div class="fee__price">Php 5,000.00 <span>/ month</span></div>
                        <p>For 2 hours of playback per day.</p>
                        <a href="<?php echo promo_h($fb); ?>" target="_blank" rel="noopener noreferrer">Apply for this ad <i class="fas fa-arrow-right"></i></a>
                    </article>
                    <article class="fee">
                        <div class="fee__top"><i class="fas fa-align-left"></i><small>LED SCROLLER / BANNER ADS</small></div>
                        <h3>LED Scroller / Banner Advertisement</h3>
                        <p>Additional requirement: copy of the information for display.</p>
                        <div class="fee__price">Php 5,000.00 <span>/ month</span></div>
                        <p>At least thirty (30) characters, 2-hour scroll per day.</p>
                        <a href="<?php echo promo_h($fb); ?>" target="_blank" rel="noopener noreferrer">Apply for this ad <i class="fas fa-arrow-right"></i></a>
                    </article>
                    <article class="fee">
                        <div class="fee__top"><i class="fas fa-receipt"></i><small>PROMOTIONAL OFFICIAL RECEIPT</small></div>
                        <h3>Promotional Official Receipt</h3>
                        <p>Additional requirement: 1 original and 1 photocopy of the official receipt.</p>
                        <div class="fee__price">Php 1,000.00 <span>one-time</span></div>
                        <p>Payable to the Municipal Treasury Office.</p>
                        <a href="#requirements">See requirements <i class="fas fa-arrow-right"></i></a>
                    </article>
                </div>
                <div class="note">
                    <p><i class="fas fa-circle-info"></i> Fees are payable to the Municipal Treasury Office. Present your official receipt to the Market Office when filing and releasing your permit.</p>
                    <a href="#process">See the full process <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </section>

        <section class="section" id="value">
            <div class="wrap">
                <span class="kicker"><i class="fas fa-circle"></i> What is it</span>
                <h2 class="section-title">A prerequisite for <em>promotional activity</em></h2>
                <p class="section-lead" style="margin-left:0;">The Market Promotional Permit is required before any promotion inside the Daet Public Market scope of management. It confirms the applicant’s details and compliance with the Revised Market Code of 2017.</p>
                <div class="two" id="requirements">
                    <div class="panel">
                        <h3>Checklist of requirements</h3>
                        <ul class="checks">
                            <li><i class="fas fa-id-card"></i> Government-issued ID, for presentation</li>
                            <li><i class="fas fa-file-lines"></i> Letter of Intent with the applicant’s profile</li>
                            <li><i class="fas fa-box"></i> Product or goods to be promoted</li>
                            <li><i class="fas fa-calendar"></i> Covered dates and time frame</li>
                        </ul>
                        <p style="margin-top:1rem;font-size:0.78rem;font-weight:800;letter-spacing:0.08em;color:var(--indigo);">WHERE TO SECURE A VALID ID</p>
                        <div class="tags">
                            <span class="tag">BIR</span><span class="tag">Post Office</span><span class="tag">DFA</span><span class="tag">PSA</span><span class="tag">SSS</span><span class="tag">GSIS</span><span class="tag">Pag-IBIG</span><span class="tag">PhilID</span><span class="tag">LTO</span>
                        </div>
                    </div>
                    <div class="dark-panel">
                        <h3>Where to process</h3>
                        <div class="row"><span>Received &amp; approved Letter of Intent</span><b>Mayor’s Office</b></div>
                        <div class="row"><span>Promotional Official Receipt<br><small>1 original, 1 photocopy</small></span><b>Municipal Treasury<br>Php 1,000.00</b></div>
                        <div class="row"><span>Office / Division</span><b>Market Division</b></div>
                        <div class="row"><span>Classification</span><b>Complex · G2B &amp; G2C</b></div>
                    </div>
                </div>
                <div class="avail">
                    <article><b>A</b><div><h4>Daet Public Market — Barangay VI</h4><p>Including BOT scheme structures surrounding it.</p></div></article>
                    <article><b>B</b><div><h4>Daet Elevated Town Plaza</h4><p>Build-Operate-Transfer scheme stalls.</p></div></article>
                    <article><b>C</b><div><h4>Satellite Market — Barangay Lag-on</h4><p>Including BOT structures within the area.</p></div></article>
                    <article><b>D</b><div><h4>Satellite Market — Camambugan</h4><p>Any person or group promoting inside market premises.</p></div></article>
                </div>
            </div>
        </section>

        <section class="section" id="process" style="padding-top:0;">
            <div class="wrap">
                <div class="center">
                    <span class="kicker"><i class="fas fa-circle"></i> Step by step</span>
                    <h2 class="section-title">Client process in <em>12 minutes</em></h2>
                    <p class="section-lead">Simple and free of processing charges. Advertising fees, when required, are paid separately at the treasury.</p>
                </div>
                <div class="steps">
                    <article class="step"><div class="step__n">1</div><div><h4>Sign in the Client Log Book</h4><p>Sign in at the office entrance and follow the office’s public health standards. Staff provides the log book.</p><div class="meta"><span>Fee: None</span><span>3 minutes</span><span>Market Office Staff</span></div></div></article>
                    <article class="step"><div class="step__n">2</div><div><h4>Submit the required documents</h4><p>Staff checks completeness, files photocopies, and collects extra requirements for commercial billboards and streamers.</p><div class="meta"><span>Fee: None</span><span>3 minutes</span><span>Market Supervisor II &amp; Staff</span></div></div></article>
                    <article class="step"><div class="step__n">3</div><div><h4>Scheduling of approved activity</h4><p>The approved promotion is placed on the Calendar of Promotional Activities.</p><div class="meta"><span>Fee: None</span><span>1 minute</span><span>Market Supervisor / Staff</span></div></div></article>
                    <article class="step"><div class="step__n">4</div><div><h4>Conduct the promotional activity</h4><p>A market enforcer assists and checks that the activity follows the approved permit and stated conditions.</p><div class="meta"><span>Fee: None</span><span>5 minutes</span><span>Market Enforcer</span></div></div></article>
                </div>
                <p class="total">Total processing fee: <strong>Php 0.00</strong> · Total time: <strong>12 minutes</strong></p>
            </div>
        </section>

        <section class="section" id="charter">
            <div class="wrap">
                <div class="center">
                    <span class="kicker"><i class="fas fa-circle"></i> Official reference</span>
                    <h2 class="section-title">Citizen’s <em>Charter</em> slides</h2>
                    <p class="section-lead">Swipe or click through the official market promotional permit information.</p>
                </div>
                <?php
                $slides = [
                    'promo-01-about.png' => 'What is the Market Promotional Permit',
                    'promo-02-overview.png' => 'Office, classification & who may avail',
                    'promo-03-checklist.png' => 'Checklist of requirements',
                    'promo-04-secure.png' => 'Where to process & fees',
                    'promo-05-ledwall.png' => 'LED Wall Ads display',
                    'promo-06-ledscroller.png' => 'LED Scroller / Banner Ads',
                    'promo-07-process1.png' => 'Client process — steps 1 & 2',
                    'promo-08-process2.png' => 'Client process — steps 3 & 4',
                ];
                ?>
                <div class="gallery" id="galleryCarousel">
                    <div class="gallery-viewport">
                        <button type="button" class="gallery-arrow prev" id="galleryPrev" aria-label="Previous slide"><i class="fas fa-chevron-left"></i></button>
                        <div class="gallery-track" id="galleryTrack">
                            <?php foreach ($slides as $file => $caption): ?>
                            <div class="gallery-slide">
                                <img src="<?php echo promo_h($pr . 'assets/imgs/' . $file); ?>" alt="<?php echo promo_h($caption); ?>" data-caption="<?php echo promo_h($caption); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="gallery-arrow next" id="galleryNext" aria-label="Next slide"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <p class="gallery-caption" id="galleryCaption"><?php echo promo_h(reset($slides)); ?></p>
                    <div class="gallery-dots" id="galleryDots"></div>
                </div>
            </div>
        </section>

        <section class="section" id="faq" style="padding-top:0;">
            <div class="wrap faq">
                <div class="faq__side">
                    <span class="kicker"><i class="fas fa-circle"></i> Good to know</span>
                    <h2 class="section-title">Frequently asked <em>questions</em></h2>
                    <p>Quick answers to the questions we hear most from vendors and business applicants.</p>
                    <div class="help">
                        <strong>Still have a question?</strong>
                        <p>Our client assistance desk is happy to help during office hours.</p>
                        <a href="<?php echo promo_h($fb); ?>" target="_blank" rel="noopener noreferrer">Send an inquiry <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <div>
                    <article class="faq__item"><button type="button">What is a Market Promotional Permit? <i class="fas fa-chevron-down"></i></button><div>It is the document you need before conducting any promotional activity inside the Daet Public Market scope of management.</div></article>
                    <article class="faq__item"><button type="button">How long does the process take? <i class="fas fa-chevron-down"></i></button><div>The client steps take about 12 minutes in total: sign-in, document check, scheduling, and the activity inspection.</div></article>
                    <article class="faq__item"><button type="button">Where do I pay the fees? <i class="fas fa-chevron-down"></i></button><div>Pay at the Municipal Treasury Office, then present the official receipt to the Market Office. The permit process itself has no processing fee.</div></article>
                    <article class="faq__item"><button type="button">Do I need a permit for LED walls and streamers? <i class="fas fa-chevron-down"></i></button><div>Yes. LED wall and LED scroller or banner ads have extra requirements and a Php 5,000.00 monthly display fee.</div></article>
                    <article class="faq__item"><button type="button">Is the market under CCTV surveillance? <i class="fas fa-chevron-down"></i></button><div>Yes. 108 CCTV units cover stalls, walkways, entrances, and common areas.</div></article>
                </div>
            </div>
        </section>
    </main>

    <div class="service-modal" id="serviceModal" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle" hidden>
        <div class="service-modal__panel">
            <button type="button" class="service-modal__close" id="closeServiceModal" aria-label="Close">&times;</button>
            <div class="service-modal__head">
                <span class="card__icon" id="serviceModalIcon"><i class="fas fa-store"></i></span>
                <h3 id="serviceModalTitle">Service</h3>
            </div>
            <div class="service-modal__body" id="serviceModalBody"></div>
        </div>
    </div>

    <div hidden class="service-modal-source" id="modal-src-promo" aria-hidden="true">
        <p>The <strong style="color:var(--indigo-deep);">Market Promotional Permit</strong> is required before any promotion inside the Daet Public Market scope of management. Processing is free and can take as little as <strong>12 minutes</strong>.</p>
        <ul>
            <li><i class="fas fa-check"></i> Government-issued ID for presentation</li>
            <li><i class="fas fa-check"></i> Letter of Intent with the applicant’s profile</li>
            <li><i class="fas fa-check"></i> Product or goods to be promoted, plus covered dates and time frame</li>
            <li><i class="fas fa-check"></i> Promotional Official Receipt from the Municipal Treasury when applicable</li>
        </ul>
        <p><a href="#process" class="service-modal__cta">See the full process <i class="fas fa-arrow-right"></i></a></p>
    </div>
    <div hidden class="service-modal-source" id="modal-src-ads" aria-hidden="true">
        <p>Commercial <strong style="color:var(--indigo-deep);">LED walls, scrollers, banners, and streamers</strong> inside the market require approval and monthly display fees.</p>
        <ul>
            <li><i class="fas fa-check"></i> LED Wall Advertisement — Php 5,000.00 / month (2 hours playback per day)</li>
            <li><i class="fas fa-check"></i> LED Scroller / Banner — Php 5,000.00 / month (30+ characters, 2-hour scroll per day)</li>
            <li><i class="fas fa-check"></i> Submit electronic or printed copy of the ad content for display</li>
        </ul>
        <p><a href="#fees" class="service-modal__cta">View fees &amp; requirements <i class="fas fa-arrow-right"></i></a></p>
    </div>
    <div hidden class="service-modal-source" id="modal-src-stall" aria-hidden="true">
        <p><strong style="color:var(--indigo-deep);">New and renewing vendors</strong> can register stalls, settle rental dues, and get assigned a space in the trading area through the vendor portal.</p>
        <ul>
            <li><i class="fas fa-check"></i> Stall assignment across Daet Public Market and satellite trading areas</li>
            <li><i class="fas fa-check"></i> Rental and clearance workflows in one place</li>
            <li><i class="fas fa-check"></i> Support from the Market Division for Youli>
        </ul>
        <p><a href="registrationform.php" class="service-modal__cta">Go to registration form <i class="fas fa-arrow-right"></i></a></p>
    </div>
    <div hidden class="service-modal-source" id="modal-src-cctv" aria-hidden="true">
        <p><strong style="color:var(--indigo-deep);">108 camera units</strong> watch stalls, walkways, entrances, and common areas. Recordings help staff respond to incidents and settle reported concerns.</p>
        <ul>
            <li><i class="fas fa-check"></i> Coverage across the trading area and key entrances</li>
            <li><i class="fas fa-check"></i> Supports crowd management and after-hours security</li>
            <li><i class="fas fa-check"></i> Helps enforce market rules and public welfare</li>
        </ul>
        <img src="<?php echo promo_h($pr . 'assets/imgs/cctv.gif'); ?>" alt="CCTV monitoring at Daet Public Market">
    </div>
    <div hidden class="service-modal-source" id="modal-src-timbangan" aria-hidden="true">
        <p>The market’s public scale gives vendors and buyers a neutral weigh-in for fresh goods before purchase or sale.</p>
        <ul>
            <li><i class="fas fa-check"></i> For produce, meat, fish, and other market items</li>
            <li><i class="fas fa-check"></i> Supports honest pricing and consumer protection</li>
            <li><i class="fas fa-check"></i> Coordinated with the Market Division</li>
        </ul>
        <p><strong style="color:var(--indigo-deep);">Tip:</strong> Look for the Timbangan ng Bayan station when you want goods weighed before purchase or sale.</p>
    </div>
    <div hidden class="service-modal-source" id="modal-src-assist" aria-hidden="true">
        <p>Market office staff help <strong style="color:var(--indigo-deep);">you</strong> and answer questions on permits, official receipts, stall rules, and requirements.</p>
        <ul>
            <li><i class="fas fa-check"></i> Walk-through of promotional permit and advertising requirements</li>
            <li><i class="fas fa-check"></i> Help with treasury receipts and document checklists</li>
            <li><i class="fas fa-check"></i> Mon–Fri, 8:00 AM – 5:00 PM at the Daet Public Market</li>
        </ul>
        <p><a href="#faq" class="service-modal__cta">Read FAQs <i class="fas fa-arrow-right"></i></a>
        · <a href="<?php echo promo_h($fb); ?>" target="_blank" rel="noopener noreferrer" class="service-modal__cta">Message on Facebook <i class="fas fa-arrow-right"></i></a></p>
    </div>

    <footer class="site-footer">
        <div class="wrap">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="brand__mark">
                        <img src="<?php echo promo_h($logo); ?>" alt="Daet Public Market" width="48" height="48">
                    </div>
                    <h3>Daet Public Market</h3>
                    <p>Questions about permits, stalls, or market services? Message the official Facebook page.</p>
                    <div class="social-row">
                        <a href="<?php echo promo_h($fb); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>On this page</h4>
                    <ul>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#fees">Permits &amp; Fees</a></li>
                        <li><a href="#process">Process</a></li>
                        <li><a href="#charter">Charter</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Shortcut links</h4>
                    <ul>
                        <li><a href="index.php">Market home</a></li>
                        <li><a href="index.php#about">About</a></li>
                        <li><a href="index.php#announcement">Announcements</a></li>
                        <li><a href="index.php#services">Services</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                        <li><a href="registrationform.php">Vendor portal</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <ul>
                        <li>Daet, Camarines Norte</li>
                        <li><a href="<?php echo promo_h($fb); ?>" target="_blank" rel="noopener noreferrer">facebook.com/daetpublicmarket</a></li>
                        <li>Mon–Fri, 8:00 AM – 5:00 PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bar">
                <span>&copy; <?php echo date('Y'); ?> Daet Public Market. All rights reserved.</span>
                <span>Local Government Unit of Daet, Camarines Norte</span>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            var siteNav = document.getElementById('siteNav');
            function onScroll() {
                if (siteNav) siteNav.classList.toggle('scrolled', window.scrollY > 60);
            }
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            var navToggle = document.getElementById('navToggle');
            var navLinks = document.getElementById('navLinks');
            var navBackdrop = document.getElementById('navBackdrop');
            var navScrollY = 0;
            function closeNav() {
                if (!navLinks) return;
                navLinks.classList.remove('is-open');
                if (navBackdrop) navBackdrop.classList.remove('is-open');
                document.body.classList.remove('nav-open');
                document.body.style.top = '';
                if (siteNav) siteNav.classList.remove('menu-open');
                window.scrollTo(0, navScrollY);
            }
            function openNav() {
                if (!navLinks) return;
                navScrollY = window.scrollY || window.pageYOffset || 0;
                navLinks.classList.add('is-open');
                if (navBackdrop) navBackdrop.classList.add('is-open');
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
                if (navBackdrop) navBackdrop.addEventListener('click', closeNav);
                navLinks.querySelectorAll('a').forEach(function (a) {
                    a.addEventListener('click', closeNav);
                });
            }

            var word = document.getElementById('heroWord');
            var heroWordIndex = 0;
            window.promoApplyHeroWordLang = function (lang) {
                if (!word) return;
                var labels = lang === 'ph'
                    ? ['Mga Permit', 'Mga Serbisyo', 'Mga Stall Slot']
                    : ['Permits', 'Services', 'Stall Slots'];
                word.innerHTML = '<span>' + labels[heroWordIndex % labels.length] + '</span>';
            };
            if (word) {
                setInterval(function () {
                    heroWordIndex = (heroWordIndex + 1) % 3;
                    var lang = 'en';
                    try { lang = localStorage.getItem('preferredLanguage') || 'en'; } catch (e) {}
                    window.promoApplyHeroWordLang(lang);
                }, 2600);
            }

            var serviceModal = document.getElementById('serviceModal');
            var closeServiceBtn = document.getElementById('closeServiceModal');
            var serviceModalTitle = document.getElementById('serviceModalTitle');
            var serviceModalIcon = document.getElementById('serviceModalIcon');
            var serviceModalBody = document.getElementById('serviceModalBody');
            function closeServiceModalFn() {
                if (!serviceModal) return;
                serviceModal.classList.remove('is-open');
                document.body.classList.remove('modal-open');
                setTimeout(function () { serviceModal.hidden = true; }, 250);
            }
            function openServiceModalFn(srcId, title, iconClass) {
                if (!serviceModal || !serviceModalBody) return;
                var src = document.getElementById(srcId);
                if (!src) return;
                if (serviceModalTitle) {
                    var lang = 'en';
                    try { lang = localStorage.getItem('preferredLanguage') || 'en'; } catch (e) {}
                    var modalTitle = title || 'Service';
                    if (lang === 'ph' && window.PROMO_SERVICE_TITLES_PH && window.PROMO_SERVICE_TITLES_PH[modalTitle]) {
                        modalTitle = window.PROMO_SERVICE_TITLES_PH[modalTitle];
                    }
                    serviceModalTitle.textContent = modalTitle;
                }
                if (serviceModalIcon) {
                    serviceModalIcon.innerHTML = '<i class="fas ' + (iconClass || 'fa-circle-info') + '" aria-hidden="true"></i>';
                }
                serviceModalBody.innerHTML = src.innerHTML;
                serviceModal.hidden = false;
                serviceModal.classList.add('is-open');
                document.body.classList.add('modal-open');
                if (closeServiceBtn) closeServiceBtn.focus();
            }
            document.querySelectorAll('.js-service-modal-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openServiceModalFn(
                        btn.getAttribute('data-src'),
                        btn.getAttribute('data-title'),
                        btn.getAttribute('data-icon')
                    );
                });
            });
            if (closeServiceBtn) closeServiceBtn.addEventListener('click', closeServiceModalFn);
            if (serviceModal) {
                serviceModal.addEventListener('click', function (e) {
                    if (e.target === serviceModal) closeServiceModalFn();
                });
            }
            if (serviceModalBody) {
                serviceModalBody.addEventListener('click', function (e) {
                    var link = e.target.closest('a[href^="#"]');
                    if (link) closeServiceModalFn();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && serviceModal && serviceModal.classList.contains('is-open')) closeServiceModalFn();
            });

            document.querySelectorAll('.faq__item button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var item = btn.parentElement;
                    var open = item.classList.contains('is-open');
                    document.querySelectorAll('.faq__item').forEach(function (el) { el.classList.remove('is-open'); });
                    if (!open) item.classList.add('is-open');
                });
            });

            var track = document.getElementById('galleryTrack');
            if (!track) return;
            var slides = Array.prototype.slice.call(track.children);
            var total = slides.length;
            var index = 0;
            var timer = null;
            var dotsWrap = document.getElementById('galleryDots');
            var caption = document.getElementById('galleryCaption');
            var dots = [];
            slides.forEach(function (_, n) {
                var b = document.createElement('button');
                b.type = 'button';
                b.addEventListener('click', function () { go(n); restart(); });
                dotsWrap.appendChild(b);
                dots.push(b);
            });
            function render() {
                track.style.transform = 'translateX(' + (-index * 100) + '%)';
                var img = slides[index].querySelector('img');
                if (caption && img) caption.textContent = img.getAttribute('data-caption') || '';
                dots.forEach(function (d, n) { d.classList.toggle('is-active', n === index); });
            }
            function go(n) { index = (n + total) % total; render(); }
            function next() { go(index + 1); }
            function prev() { go(index - 1); }
            function start() { timer = setInterval(next, 5000); }
            function stop() { if (timer) { clearInterval(timer); timer = null; } }
            function restart() { stop(); start(); }
            document.getElementById('galleryNext').addEventListener('click', function () { next(); restart(); });
            document.getElementById('galleryPrev').addEventListener('click', function () { prev(); restart(); });
            var box = document.getElementById('galleryCarousel');
            box.addEventListener('mouseenter', stop);
            box.addEventListener('mouseleave', start);
            render();
            start();
        })();
    </script>

    <?php
    $cs_contact_href = 'index.php#contact';
    $cs_announcement_href = 'index.php#announcement';
    include __DIR__ . '/includes/mobile_enhancements.php';
    include __DIR__ . '/includes/customer_support_widget.php';
    ?>
</body>
</html>
