<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AgroAide — crop scanning, AI farm advisor, weather, market prices, and disease outbreak alerts for Nigerian farmers.">
    <title>AgroAide — Smart farming for Nigerian growers</title>
    <link rel="icon" href="{{ asset('images/agroaideLogo.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cabinet+Grotesk:wght@700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #09130c;
            --bg-card: #112216;
            --primary: #10b981;
            --primary-hover: #059669;
            --accent-gold: #f59e0b;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --border-glow: rgba(16, 185, 129, 0.15);
            --glass-bg: rgba(17, 34, 22, 0.65);
            --font-head: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            color: var(--text-main);
            background-color: var(--bg-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }

        /* --- Ambient Glow Backgrounds --- */
        .glow-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(120px);
            pointer-events: none;
            z-index: 0;
        }
        .glow-1 {
            width: 400px;
            height: 400px;
            background: rgba(16, 185, 129, 0.18);
            top: -100px;
            right: -100px;
        }
        .glow-2 {
            width: 350px;
            height: 350px;
            background: rgba(245, 158, 11, 0.12);
            top: 40%;
            left: -100px;
        }

        /* --- Header / Nav --- */
        header {
            position: relative;
            background: radial-gradient(100% 100% at 50% 0%, #152e1e 0%, #09130c 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 5rem;
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem 6vw;
            max-width: 1280px;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .brand img {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .nav-link {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: color 0.2s;
        }
        .nav-link:hover { color: var(--primary); }

        /* --- Hero Section --- */
        .hero {
            max-width: 1280px;
            margin: 0 auto;
            padding: 4rem 6vw 2rem;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .badge-pill {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            border-radius: 99px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(180deg, #ffffff 0%, #d1d5db 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p.lede {
            font-size: 1.15rem;
            color: var(--text-muted);
            max-width: 540px;
        }

        .cta-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-top: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            padding: 0.9rem 1.8rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: #042010;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.35);
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(16, 185, 129, 0.45);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-main);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn.is-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .ios-note {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Mock Phone / Visual Element */
        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
        }
        .hero-card {
            width: 100%;
            max-width: 360px;
            background: var(--glass-bg);
            border: 1px solid var(--border-glow);
            border-radius: 24px;
            padding: 1.5rem;
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .hero-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        /* --- Sections Layout --- */
        section {
            max-width: 1280px;
            margin: 0 auto;
            padding: 6rem 6vw;
            position: relative;
            z-index: 2;
        }

        .section-header {
            margin-bottom: 3.5rem;
            text-align: center;
        }
        .section-title {
            font-size: clamp(2rem, 3.5vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.75rem;
        }
        .section-sub {
            color: var(--text-muted);
            font-size: 1.1rem;
            max-width: 580px;
            margin: 0 auto;
        }

        /* --- Grid Features --- */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 20px;
            padding: 2rem 1.75rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-glow);
            box-shadow: 0 12px 30px rgba(0,0,0,0.3);
        }
        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }
        .feature-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .feature-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* --- How It Works / Steps --- */
        .steps-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            position: relative;
        }

        .step-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 2rem 1.5rem;
            position: relative;
        }
        .step-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: rgba(16, 185, 129, 0.2);
            line-height: 1;
            margin-bottom: 1rem;
        }
        .step-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .step-card p {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* --- Market Feature Highlight --- */
        .market-banner {
            background: linear-gradient(135deg, rgba(17, 34, 22, 0.8) 0%, rgba(9, 19, 12, 0.9) 100%);
            border: 1px solid var(--border-glow);
            border-radius: 28px;
            padding: 3.5rem 3vw;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }
        .market-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .market-list li {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .market-list li::before {
            content: "✓";
            color: var(--primary);
            font-weight: bold;
        }

        .market-card-preview {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }

        /* --- CTA Download Band --- */
        .download-wrapper {
            text-align: center;
            background: radial-gradient(circle at center, rgba(16, 185, 129, 0.12) 0%, transparent 70%);
            padding: 5rem 2rem;
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 4rem;
        }
        .download-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }
        .download-brand img {
            width: 48px;
            height: 48px;
            border-radius: 12px;
        }

        /* --- Footer --- */
        footer {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding: 2.5rem 6vw;
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .footer-links {
            display: flex;
            gap: 1.5rem;
        }
        .footer-links a:hover {
            color: var(--primary);
        }

        /* Responsive Breakpoints */
        @media (max-width: 968px) {
            .hero {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2.5rem;
            }
            .badge-pill, .hero p.lede {
                margin-left: auto;
                margin-right: auto;
            }
            .cta-group {
                justify-content: center;
            }
            .market-banner {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    @php
        $apkUrl = trim((string) config('app.android_apk_url'));
        $hasApk = $apkUrl !== '';
    @endphp

    <div class="glow-orb glow-1"></div>
    <div class="glow-orb glow-2"></div>

    <!-- Header Section -->
    <header>
        <nav class="nav">
            <div class="brand">
                <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide logo">
                <span>AgroAide</span>
            </div>
            <a class="nav-link" href="#features">Features</a>
        </nav>

        <div class="hero">
            <div class="hero-content">
                <div class="badge-pill">
                    <span>🌱</span> Built for Nigerian Smallholders
                </div>
                <h1>Smart farming tools right on your phone</h1>
                <p class="lede">
                    Crop health scans, weather-aware advice, market prices, and nearby disease warnings — helping you manage your farm with confidence.
                </p>

                <div class="cta-group">
                    @if ($hasApk)
                        <a class="btn btn-primary" href="{{ $apkUrl }}" id="download-android">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.52 0c-.39 0-.74.19-.96.49l-1.92 2.58a10.05 10.05 0 0 0-5.28 0L7.44.49A1.2 1.2 0 0 0 6.48 0c-.8 0-1.34.78-1.02 1.51l1.32 2.97C3.15 6.27 1 9.87 1 14h22c0-4.13-2.15-7.73-5.78-9.52l1.32-2.97C18.86.78 18.32 0 17.52 0zM7 10.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5.67-1.5 1.5-1.5zm10 0c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5.67-1.5 1.5-1.5zM1 15v6c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-6H1zm20 0v6c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-6h-3z"/></svg>
                            Download for Android
                        </a>
                    @else
                        <span class="btn btn-primary is-disabled" title="Set ANDROID_APK_URL on the server">
                            Download for Android
                        </span>
                    @endif
                    <a class="btn btn-secondary" href="#how-it-works">See how it works</a>
                </div>

                <p class="ios-note">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Android only — iOS paused due to Apple developer payment limits.
                </p>
            </div>

            <!-- UI Mockup Graphic -->
            <div class="hero-visual">
                <div class="hero-card">
                    <div class="hero-card-header">
                        <span style="font-size: 0.85rem; font-weight:700; color:var(--text-muted)">DAILY DASHBOARD</span>
                        <span style="font-size: 0.75rem; color: var(--primary); background:rgba(16,185,129,0.15); padding:2px 8px; border-radius:10px;">Live Insights</span>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <h4 style="font-size: 1.1rem; font-weight:700;">Rainfall Expected Today 🌦️</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top:0.2rem;">Hold off on applying fertilizer until tomorrow afternoon to avoid runoff.</p>
                    </div>
                    <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="display:block; font-size:0.75rem; color:var(--text-muted)">Cassava Market (Kano)</span>
                            <span style="font-weight:700; color:#fff;">₦180,000 / ton</span>
                        </div>
                        <span style="color:var(--primary); font-size:0.85rem; font-weight:700;">+3.2%</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Features Section -->
    <section id="features">
        <div class="section-header">
            <h2 class="section-title">Everything Built for Your Field</h2>
            <p class="section-sub">Essential tools designed for practical, daily decision-making in agriculture.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <h3>Crop Disease Scanning</h3>
                <p>Upload a photo of damaged leaves. Get instant diagnosis alongside treatment steps tailored for local application.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                </div>
                <h3>Multilingual AI Advisor</h3>
                <p>Chat or speak in English, Hausa, Yoruba, or Pidgin. Get contextual advice based on your crops, land, and climate.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 001.09-9.88A5.5 5.5 0 008.06 6.02A4.5 4.5 0 003 15z"></path></svg>
                </div>
                <h3>Localized Weather & Soil</h3>
                <p>7-day forecasts and soil moisture indicators calibrated directly to your farm’s exact GPS positioning.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <h3>Farming Calendar</h3>
                <p>Stay updated on key tasks, planting timelines, and maintenance routines with simple visual tracking.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <h3>Crop Market Intel</h3>
                <p>Track crowd-verified pricing data from Market Eye so you pick the optimal time and venue to sell crops.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </div>
                <h3>Outbreak Alerts</h3>
                <p>Receive immediate area-wide push notifications whenever neighboring farms report spreading crop diseases.</p>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works">
        <div class="section-header">
            <h2 class="section-title">Simple Steps to Better Yields</h2>
            <p class="section-sub">Designed for straightforward usability out in the field.</p>
        </div>

        <div class="steps-wrapper">
            <div class="step-card">
                <div class="step-number">01</div>
                <h3>Set Up Farm Profile</h3>
                <p>Select your primary crops, outline soil conditions, and pin your field using integrated location features.</p>
            </div>

            <div class="step-card">
                <div class="step-number">02</div>
                <h3>Scan Crop Issues</h3>
                <p>Capture leaves or stems showing distress to immediately receive verified identification and treatment steps.</p>
            </div>

            <div class="step-card">
                <div class="step-number">03</div>
                <h3>Review Daily Guidance</h3>
                <p>Check customized dashboard updates that cross-reference incoming weather with your crop calendar.</p>
            </div>

            <div class="step-card">
                <div class="step-number">04</div>
                <h3>Time the Market</h3>
                <p>Consult local crop prices and use voice chat queries before committing to sale or logistics.</p>
            </div>
        </div>
    </section>

    <!-- Market Highlights -->
    <section>
        <div class="market-banner">
            <div>
                <h2 class="section-title" style="text-align: left;">Smarter Market Timing</h2>
                <p style="color: var(--text-muted); font-size: 1.05rem;">
                    Eliminate guesswork during harvest season. AgroAide compiles real market values for your configured crops so you maximize your returns.
                </p>
                <ul class="market-list">
                    <li>Matched automatically with your registered crops</li>
                    <li>Historical pricing trends via Market Eye</li>
                    <li>Integrated with expense & income logs</li>
                </ul>
            </div>

            <div class="market-card-preview">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Market Insight</span>
                        <h4 style="font-size: 1.25rem; font-weight: 700;">Optimal Selling Window</h4>
                    </div>
                    <span style="color: var(--accent-gold); font-size: 1.5rem;">📈</span>
                </div>
                <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.5;">
                    Current demand trends suggest holding Maize yields for 4 days due to predicted price bumps in regional distribution centers.
                </p>
            </div>
        </div>
    </section>

    <!-- Download CTA Band -->
    <section id="download">
        <div class="download-wrapper">
            <div class="download-brand">
                <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide logo">
                <span>AgroAide</span>
            </div>
            <h2 class="section-title">Get Started Today</h2>
            <p class="section-sub" style="margin-bottom: 2rem;">
                Download the app for Android to manage crops, inspect health, and monitor market updates.
            </p>

            @if ($hasApk)
                <a class="btn btn-primary" href="{{ $apkUrl }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.52 0c-.39 0-.74.19-.96.49l-1.92 2.58a10.05 10.05 0 0 0-5.28 0L7.44.49A1.2 1.2 0 0 0 6.48 0c-.8 0-1.34.78-1.02 1.51l1.32 2.97C3.15 6.27 1 9.87 1 14h22c0-4.13-2.15-7.73-5.78-9.52l1.32-2.97C18.86.78 18.32 0 17.52 0zM7 10.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5.67-1.5 1.5-1.5zm10 0c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5-1.5-.67-1.5-1.5.67-1.5 1.5-1.5zM1 15v6c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-6H1zm20 0v6c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-6h-3z"/></svg>
                    Download APK Directly
                </a>
            @else
                <span class="btn btn-primary is-disabled">Download for Android</span>
                <p style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                    Download link will become active as soon as configured.
                </p>
            @endif
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div>© {{ date('Y') }} AgroAide. All rights reserved.</div>
        <div class="footer-links">
            <a href="{{ url('/legal/terms') }}">Terms</a>
            <a href="{{ url('/legal/privacy') }}">Privacy</a>
            <a href="{{ url('/api/health') }}">API Status</a>
        </div>
    </footer>

</body>
</html>