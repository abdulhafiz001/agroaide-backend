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
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #142018;
            --muted: #3d5244;
            --leaf: #2f7a3e;
            --leaf-deep: #1f5a2c;
            --sun: #e8b84a;
            --cream: #f3f7f1;
            --panel: rgba(255, 255, 255, 0.9);
            --line: rgba(20, 32, 24, 0.12);
            --shadow: 0 24px 60px rgba(20, 40, 28, 0.18);
            --font-display: "Fraunces", Georgia, serif;
            --font-body: "Source Sans 3", system-ui, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            color: var(--ink);
            background: var(--cream);
            line-height: 1.55;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; text-decoration: none; }

        .hero {
            min-height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
            color: #f7fff8;
            overflow: hidden;
            background:
                linear-gradient(115deg, rgba(12, 36, 20, 0.88) 0%, rgba(20, 60, 32, 0.72) 42%, rgba(40, 90, 48, 0.55) 100%),
                radial-gradient(circle at 80% 20%, rgba(232, 184, 74, 0.28), transparent 40%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"),
                linear-gradient(180deg, #163820 0%, #2a6b38 55%, #1a4024 100%);
        }
        .hero::after {
            content: "";
            position: absolute;
            inset: auto 0 0 0;
            height: 140px;
            background: linear-gradient(to top, var(--cream), transparent);
            pointer-events: none;
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 6vw;
            position: relative;
            z-index: 2;
            animation: rise 0.8s ease both;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: var(--font-display);
            font-size: 1.55rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .brand img {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255,255,255,0.12);
            object-fit: contain;
        }
        .nav a.ghost {
            color: #e8f5ea;
            font-weight: 600;
            opacity: 0.9;
        }

        .hero-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem 6vw 5rem;
            max-width: 920px;
            position: relative;
            z-index: 2;
        }
        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(3rem, 9vw, 5.6rem);
            line-height: 0.95;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
            animation: rise 0.9s 0.1s ease both;
        }
        .hero .lede {
            font-size: clamp(1.05rem, 2.4vw, 1.3rem);
            max-width: 36rem;
            color: #dceee0;
            margin-bottom: 1.75rem;
            animation: rise 0.9s 0.2s ease both;
        }
        .cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            align-items: center;
            animation: rise 0.9s 0.3s ease both;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 52px;
            padding: 0.85rem 1.35rem;
            border-radius: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary {
            background: var(--sun);
            color: #1d2a14;
            box-shadow: 0 12px 28px rgba(232, 184, 74, 0.35);
        }
        .btn-primary:hover { background: #f0c45c; }
        .btn-primary.is-disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.28);
            backdrop-filter: blur(6px);
        }
        .ios-note {
            width: 100%;
            font-size: 0.92rem;
            color: #c5dfca;
            animation: rise 0.9s 0.4s ease both;
        }

        section {
            padding: 4.5rem 6vw;
        }
        .section-title {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 4vw, 2.6rem);
            letter-spacing: -0.02em;
            margin-bottom: 0.6rem;
        }
        .section-sub {
            color: var(--muted);
            max-width: 40rem;
            margin-bottom: 2rem;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .feature {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1.35rem 1.3rem 1.45rem;
            box-shadow: 0 10px 30px rgba(20, 40, 28, 0.05);
            transition: transform 0.25s ease;
        }
        .feature:hover { transform: translateY(-4px); }
        .feature h3 {
            font-family: var(--font-display);
            font-size: 1.25rem;
            margin-bottom: 0.45rem;
            color: var(--leaf-deep);
        }
        .feature p { color: var(--muted); font-size: 0.98rem; }
        .feature .tag {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--leaf);
            margin-bottom: 0.55rem;
        }

        .process {
            background: linear-gradient(180deg, #e8f1e6 0%, var(--cream) 100%);
        }
        .steps {
            display: grid;
            gap: 1rem;
            counter-reset: step;
        }
        .step {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
            align-items: start;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 1.15rem 1.25rem;
        }
        .step::before {
            counter-increment: step;
            content: counter(step);
            width: 2.2rem;
            height: 2.2rem;
            border-radius: 999px;
            background: var(--leaf);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
        }
        .step h3 {
            font-family: var(--font-display);
            font-size: 1.15rem;
            margin-bottom: 0.25rem;
        }
        .step p { color: var(--muted); }

        .market {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 2rem;
            align-items: center;
        }
        .market-panel {
            background: #163820;
            color: #eaf6ec;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: var(--shadow);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(232,184,74,0.25), transparent 35%),
                linear-gradient(145deg, #163820, #245c32 60%, #1a4024);
        }
        .market-panel strong {
            font-family: var(--font-display);
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .download-band {
            background: #142018;
            color: #f2faf3;
            text-align: center;
        }
        .download-band .brand-lockup {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            font-family: var(--font-display);
            font-size: 1.6rem;
            font-weight: 700;
        }
        .download-band img {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: rgba(255,255,255,0.08);
        }
        .download-band p {
            max-width: 34rem;
            margin: 0 auto 1.4rem;
            color: #c9decf;
        }

        footer {
            padding: 2rem 6vw 2.5rem;
            color: var(--muted);
            font-size: 0.92rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            border-top: 1px solid var(--line);
            background: #fff;
        }
        footer a { color: var(--leaf-deep); font-weight: 600; }

        @keyframes rise {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 800px) {
            .market { grid-template-columns: 1fr; }
            .nav { padding-inline: 1.25rem; }
            .hero-main, section, footer { padding-inline: 1.25rem; }
        }
    </style>
</head>
<body>
    @php
        $apkUrl = trim((string) config('app.android_apk_url'));
        $hasApk = $apkUrl !== '';
    @endphp

    <header class="hero">
        <nav class="nav">
            <div class="brand">
                <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide logo">
                <span>AgroAide</span>
            </div>
            <a class="ghost" href="#features">Features</a>
        </nav>

        <div class="hero-main">
            <h1>AgroAide</h1>
            <p class="lede">
                Crop health scans, weather-aware advice, market prices, and nearby disease warnings —
                built for Nigerian smallholders who need clear next steps on the phone.
            </p>
            <div class="cta-row">
                @if ($hasApk)
                    <a class="btn btn-primary" href="{{ $apkUrl }}" id="download-android">
                        Download for Android
                    </a>
                @else
                    <span class="btn btn-primary is-disabled" title="Set ANDROID_APK_URL on the server">
                        Download for Android
                    </span>
                @endif
                <a class="btn btn-secondary" href="#how-it-works">See how it works</a>
            </div>
            <p class="ios-note">Android only for now — iOS is paused because of Apple’s developer payment requirements.</p>
        </div>
    </header>

    <section id="features">
        <h2 class="section-title">Everything in the app</h2>
        <p class="section-sub">Real tools farmers use daily — from the first scan to market timing and outbreak alerts.</p>
        <div class="features">
            <article class="feature">
                <div class="tag">Scan</div>
                <h3>Crop disease scanning</h3>
                <p>Photograph a leaf or plant. Kindwise research ID plus a clear farmer write-up with what to do next.</p>
            </article>
            <article class="feature">
                <div class="tag">Advisor</div>
                <h3>AI farm advisor</h3>
                <p>Ask in English, Hausa, Yoruba, or Pidgin — type or speak. Advice uses your crops, fields, and weather.</p>
            </article>
            <article class="feature">
                <div class="tag">Weather</div>
                <h3>Local weather & soil</h3>
                <p>7-day forecast, rain chances, and soil moisture cues tied to your farm GPS — not a random city.</p>
            </article>
            <article class="feature">
                <div class="tag">Calendar</div>
                <h3>Farming calendar</h3>
                <p>Tasks, planting reminders, and crop watches so today’s work stays visible on one screen.</p>
            </article>
            <article class="feature">
                <div class="tag">Market</div>
                <h3>Crop market intel</h3>
                <p>Crowd-verified prices from Market Eye for the crops you grow, so you sell with better timing.</p>
            </article>
            <article class="feature">
                <div class="tag">Community</div>
                <h3>Disease outbreak map</h3>
                <p>When nearby farmers report the same disease on the same crop, you get a warning or outbreak alert.</p>
            </article>
            <article class="feature">
                <div class="tag">Fields</div>
                <h3>Fields, journal & money</h3>
                <p>Walk boundaries, log observations, track expenses/income, and estimate seed or fertilizer needs.</p>
            </article>
            <article class="feature">
                <div class="tag">Alerts</div>
                <h3>Push notifications</h3>
                <p>Severe weather, task reminders, daily AI tips, and disease alerts delivered to your Android phone.</p>
            </article>
        </div>
    </section>

    <section class="process" id="how-it-works">
        <h2 class="section-title">How farmers use AgroAide</h2>
        <p class="section-sub">A simple loop from setup to action — designed for low friction in the field.</p>
        <div class="steps">
            <div class="step">
                <div>
                    <h3>Create your farm profile</h3>
                    <p>Add crops as cards, set soil and irrigation, then pin your farm with GPS or LocationIQ search.</p>
                </div>
            </div>
            <div class="step">
                <div>
                    <h3>Scan when plants look wrong</h3>
                    <p>Upload a photo. Get condition, disease (if any), and practical next steps — up to four scans a day.</p>
                </div>
            </div>
            <div class="step">
                <div>
                    <h3>Check today’s insight & weather</h3>
                    <p>Dashboard tips refresh each day from live rain outlook, soil signals, tasks, and recent scans.</p>
                </div>
            </div>
            <div class="step">
                <div>
                    <h3>Ask the advisor or watch the market</h3>
                    <p>Chat for treatment or planting questions. Open market prices before you harvest or haul to market.</p>
                </div>
            </div>
            <div class="step">
                <div>
                    <h3>Stay ahead of outbreaks</h3>
                    <p>If enough nearby growers of your crop report the same disease, AgroAide warns you early.</p>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="market">
            <div>
                <h2 class="section-title">Crop market, not guesswork</h2>
                <p class="section-sub">
                    AgroAide pulls crowd-verified market intel for your crops so you can compare local price signals
                    before you sell. Pair it with weather and calendar tasks to plan harvest and transport days.
                </p>
                <ul style="color: var(--muted); padding-left: 1.1rem; display: grid; gap: 0.55rem;">
                    <li>Prices matched to the crops on your profile</li>
                    <li>History trends when available from Market Eye</li>
                    <li>Works alongside field finance tracking in the app</li>
                </ul>
            </div>
            <div class="market-panel">
                <strong>Know when to sell</strong>
                <p>Open Market in the app after you set crops — then use daily weather and advisor tips to time the trip.</p>
            </div>
        </div>
    </section>

    <section class="download-band" id="download">
        <div class="brand-lockup">
            <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide logo">
            <span>AgroAide</span>
        </div>
        <h2 class="section-title" style="color:#fff;">Get the Android app</h2>
        <p>
            Install AgroAide on your phone, create an account, and start with a farm location + your primary crops.
            iOS is not available yet due to Apple developer payment limits.
        </p>
        @if ($hasApk)
            <a class="btn btn-primary" href="{{ $apkUrl }}">Download for Android</a>
        @else
            <span class="btn btn-primary is-disabled">Download for Android</span>
            <p style="margin-top:0.85rem;font-size:0.9rem;">Download link will appear here once the APK URL is configured.</p>
        @endif
    </section>

    <footer>
        <div>© {{ date('Y') }} AgroAide</div>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <a href="{{ url('/legal/terms') }}">Terms</a>
            <a href="{{ url('/legal/privacy') }}">Privacy</a>
            <a href="{{ url('/api/health') }}">API status</a>
        </div>
    </footer>
</body>
</html>
