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
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#1c140c;
    --muted:#5a4c3c;
    --bg:#faf5ea;
    --bg-deep:#20160e;
    --bg-deep-2:#2b1d12;
    --card:#fffdf8;
    --clay:#b65c1f;
    --clay-deep:#8f4415;
    --leaf:#3c6e47;
    --leaf-deep:#264d30;
    --gold:#e0a53a;
    --rust:#a6402d;
    --line:rgba(28,20,12,0.13);
    --line-dark:rgba(255,246,230,0.14);
    --font-display:"Bricolage Grotesque", system-ui, sans-serif;
    --font-body:"Inter", system-ui, sans-serif;
    --font-mono:"IBM Plex Mono", ui-monospace, monospace;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  html{scroll-behavior:smooth}
  body{
    font-family:var(--font-body);
    color:var(--ink);
    background:var(--bg);
    line-height:1.6;
    -webkit-font-smoothing:antialiased;
  }
  img{max-width:100%;display:block}
  a{color:inherit;text-decoration:none}
  :focus-visible{outline:2px solid var(--clay);outline-offset:3px}
  .wrap{max-width:1180px;margin-inline:auto;padding-inline:clamp(1.25rem,4vw,3rem)}

  /* ---------- NAV ---------- */
  .nav{
    position:relative;z-index:5;
    display:flex;align-items:center;justify-content:space-between;
    padding:1.4rem clamp(1.25rem,4vw,3rem);
    color:#f7f1e6;
  }
  .brand{display:flex;align-items:center;gap:0.65rem;font-family:var(--font-display);font-weight:700;font-size:1.3rem;letter-spacing:-0.01em}
  .brand .mark{width:38px;height:38px;border-radius:10px;background:var(--gold);display:grid;place-items:center;color:var(--bg-deep);flex-shrink:0;overflow:hidden}
  .brand .mark img{width:100%;height:100%;object-fit:contain;padding:4px}
  .nav-link{font-size:0.92rem;font-weight:600;color:#e9dfcd;opacity:0.85}
  .nav-link:hover{opacity:1}

  /* ---------- HERO ---------- */
  .hero{
    position:relative;
    background:
      radial-gradient(ellipse 60% 50% at 88% 8%, rgba(224,165,58,0.16), transparent 60%),
      linear-gradient(180deg,var(--bg-deep) 0%, var(--bg-deep-2) 100%);
    overflow:hidden;
    padding-bottom:0;
  }
  .hero-grid{
    display:grid;
    grid-template-columns:1.05fr 0.95fr;
    gap:2.5rem;
    align-items:center;
    padding-top:2.5rem;
    padding-bottom:4rem;
    color:#f7f1e6;
  }
  .eyebrow{
    display:inline-flex;align-items:center;gap:0.5rem;
    font-family:var(--font-mono);font-size:0.76rem;letter-spacing:0.06em;
    color:var(--gold);text-transform:uppercase;
    margin-bottom:1.1rem;
  }
  .eyebrow::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--gold)}
  .hero h1{
    font-family:var(--font-display);
    font-weight:700;
    font-size:clamp(2.5rem,4.6vw,3.6rem);
    line-height:1.06;
    letter-spacing:-0.02em;
    max-width:14ch;
    margin-bottom:1.15rem;
  }
  .hero h1 em{
    font-style:normal;
    color:var(--gold);
  }
  .hero .lede{
    font-size:clamp(1rem,1.4vw,1.12rem);
    max-width:34rem;
    color:#d8ccb6;
    margin-bottom:2rem;
  }
  .cta-row{display:flex;flex-wrap:wrap;gap:0.85rem;align-items:center;margin-bottom:1rem}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:0.55rem;
    min-height:50px;padding:0.8rem 1.4rem;border-radius:11px;
    font-weight:600;font-size:0.96rem;border:none;cursor:pointer;
    transition:transform 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
  }
  .btn:hover{transform:translateY(-2px)}
  .btn-primary{background:var(--gold);color:#241704;box-shadow:0 14px 30px -10px rgba(224,165,58,0.55)}
  .btn-primary:hover{background:#eab652}
  .btn-primary.is-disabled{opacity:0.55;cursor:not-allowed;transform:none;box-shadow:none}
  .btn-secondary{background:rgba(255,255,255,0.06);color:#f7f1e6;border:1px solid var(--line-dark)}
  .btn-secondary:hover{background:rgba(255,255,255,0.11)}
  .btn svg{width:18px;height:18px}
  .ios-note{font-size:0.85rem;color:#a0937c}

  /* signature: field card */
  .field-card{
    position:relative;
    background:linear-gradient(165deg,#2c4a34,#1c3323);
    border-radius:22px;
    border:1px solid rgba(255,255,255,0.08);
    padding:1.5rem 1.5rem 1.7rem;
    box-shadow:0 30px 60px -20px rgba(0,0,0,0.55);
  }
  .field-card-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.1rem}
  .field-card-head .label{font-family:var(--font-mono);font-size:0.72rem;letter-spacing:0.05em;color:#bcd6c2;text-transform:uppercase}
  .field-card-head .crop{font-family:var(--font-display);font-size:1.2rem;font-weight:600;color:#fff;margin-top:0.2rem}
  .scan-badge{
    font-family:var(--font-mono);font-size:0.72rem;font-weight:600;
    background:rgba(224,165,58,0.16);color:var(--gold);
    border:1px solid rgba(224,165,58,0.35);
    padding:0.3rem 0.6rem;border-radius:999px;
  }
  .field-card-body{background:rgba(0,0,0,0.18);border-radius:14px;padding:1rem 1.1rem;margin-bottom:1rem}
  .field-card-body .diag{color:#fff;font-weight:600;font-size:0.98rem;margin-bottom:0.3rem}
  .field-card-body .diag .dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--rust);margin-right:0.5rem}
  .field-card-body p{color:#c9d8cb;font-size:0.87rem;line-height:1.55}
  .field-card-foot{display:flex;gap:0.6rem}
  .pill{font-family:var(--font-mono);font-size:0.72rem;color:#dfe9df;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);padding:0.35rem 0.6rem;border-radius:8px}

  /* ticker */
  .ticker-wrap{
    position:relative;border-top:1px solid var(--line-dark);
    background:#180f08;
    overflow:hidden;
    padding:0.85rem 0;
  }
  .ticker-track{
    display:flex;width:max-content;gap:2.2rem;
    animation:ticker 32s linear infinite;
  }
  @media (prefers-reduced-motion: reduce){ .ticker-track{animation:none} }
  @keyframes ticker{ from{transform:translateX(0)} to{transform:translateX(-50%)} }
  .tick{
    display:flex;align-items:center;gap:0.5rem;
    font-family:var(--font-mono);font-size:0.82rem;color:#cbb995;
    white-space:nowrap;
  }
  .tick b{color:#f2e6cf;font-weight:600}
  .tick .up{color:#7fbf8b}
  .tick .down{color:#d97a6c}

  /* ---------- SECTIONS ---------- */
  section{padding:5rem 0}
  .section-head{max-width:38rem;margin-bottom:2.75rem}
  .kicker{
    font-family:var(--font-mono);font-size:0.76rem;letter-spacing:0.06em;text-transform:uppercase;
    color:var(--clay);margin-bottom:0.7rem;display:block;
  }
  .section-title{
    font-family:var(--font-display);font-weight:700;
    font-size:clamp(1.7rem,3vw,2.3rem);letter-spacing:-0.02em;margin-bottom:0.6rem;
  }
  .section-sub{color:var(--muted);font-size:1.02rem}

  /* features */
  .features{display:grid;grid-template-columns:repeat(4,1fr);gap:1.1rem}
  .feature{
    background:var(--card);border:1px solid var(--line);border-radius:16px;
    padding:1.4rem 1.3rem;
    transition:transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }
  .feature:hover{transform:translateY(-3px);box-shadow:0 16px 30px -14px rgba(28,20,12,0.18);border-color:transparent}
  .feature .icon{
    width:38px;height:38px;border-radius:10px;
    display:grid;place-items:center;margin-bottom:0.9rem;
  }
  .feature .icon svg{width:20px;height:20px}
  .feature h3{font-family:var(--font-display);font-weight:600;font-size:1.04rem;margin-bottom:0.4rem}
  .feature p{color:var(--muted);font-size:0.9rem;line-height:1.55}
  .f-scan .icon{background:rgba(182,92,31,0.12);color:var(--clay)}
  .f-advisor .icon{background:rgba(60,110,71,0.12);color:var(--leaf)}
  .f-weather .icon{background:rgba(224,165,58,0.16);color:#a97a1f}
  .f-calendar .icon{background:rgba(60,110,71,0.12);color:var(--leaf)}
  .f-market .icon{background:rgba(182,92,31,0.12);color:var(--clay)}
  .f-community .icon{background:rgba(166,64,45,0.12);color:var(--rust)}
  .f-fields .icon{background:rgba(60,110,71,0.12);color:var(--leaf)}
  .f-alerts .icon{background:rgba(224,165,58,0.16);color:#a97a1f}

  /* process */
  .process{background:linear-gradient(180deg,#f1e8d4,var(--bg))}
  .steps{max-width:44rem}
  .step{
    display:grid;grid-template-columns:3.4rem 1fr;gap:1.1rem;
    padding:1.4rem 0;border-bottom:1px solid var(--line);
    position:relative;
  }
  .step:last-child{border-bottom:none}
  .step .num{font-family:var(--font-mono);font-weight:600;font-size:0.95rem;color:var(--clay);padding-top:0.15rem}
  .step h3{font-family:var(--font-display);font-weight:600;font-size:1.08rem;margin-bottom:0.3rem}
  .step p{color:var(--muted);font-size:0.93rem}

  /* market */
  .market{display:grid;grid-template-columns:1.05fr 0.95fr;gap:3rem;align-items:center}
  .market ul{list-style:none;display:grid;gap:0.65rem;margin-top:1.4rem}
  .market li{display:flex;align-items:flex-start;gap:0.65rem;color:var(--muted);font-size:0.95rem}
  .market li svg{width:17px;height:17px;flex-shrink:0;margin-top:0.2rem;color:var(--leaf)}
  .board{
    background:var(--bg-deep);border-radius:20px;padding:1.4rem;
    box-shadow:0 24px 50px -20px rgba(28,20,12,0.35);
  }
  .board-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:0.9rem}
  .board-head span:first-child{font-family:var(--font-mono);font-size:0.75rem;color:#cbb995;letter-spacing:0.05em;text-transform:uppercase}
  .board-head span:last-child{width:8px;height:8px;border-radius:50%;background:#7fbf8b}
  .board-row{
    display:flex;justify-content:space-between;align-items:center;
    padding:0.65rem 0.2rem;border-bottom:1px solid rgba(255,255,255,0.07);
    font-family:var(--font-mono);font-size:0.87rem;color:#f2e6cf;
  }
  .board-row:last-child{border-bottom:none}
  .board-row .name{color:#e9dfcd}
  .board-row .delta.up{color:#7fbf8b}
  .board-row .delta.down{color:#d97a6c}

  /* download band */
  .download-band{
    background:linear-gradient(180deg,var(--bg-deep-2),var(--bg-deep));
    color:#f7f1e6;text-align:center;
  }
  .download-band .brand-lockup{display:inline-flex;align-items:center;gap:0.7rem;margin-bottom:1rem;font-family:var(--font-display);font-weight:700;font-size:1.4rem}
  .download-band .brand-lockup .mark{width:40px;height:40px;border-radius:10px;background:var(--gold);display:grid;place-items:center;color:var(--bg-deep);overflow:hidden}
  .download-band .brand-lockup .mark img{width:100%;height:100%;object-fit:contain;padding:4px}
  .download-band p{max-width:32rem;margin:0 auto 1.6rem;color:#c9bfa9;font-size:0.98rem}
  .download-band .btn-primary{margin-inline:auto}

  footer{
    padding:2rem clamp(1.25rem,4vw,3rem);
    color:var(--muted);font-size:0.88rem;
    display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;
    border-top:1px solid var(--line);background:var(--card);
  }
  footer a{color:var(--clay);font-weight:600}
  footer a:hover{color:var(--clay-deep)}

  @media (max-width:980px){
    .hero-grid{grid-template-columns:1fr}
    .features{grid-template-columns:repeat(2,1fr)}
    .market{grid-template-columns:1fr}
  }
  @media (max-width:560px){
    .features{grid-template-columns:1fr}
    .hero h1{max-width:none}
  }
</style>
</head>
<body>
    @php
        $apkUrl = trim((string) config('app.android_apk_url'));
        $hasApk = $apkUrl !== '';
    @endphp

<header class="hero">
  <nav class="nav wrap" style="padding-inline:0">
    <div class="brand">
      <span class="mark"><img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide logo"></span>
      <span>AgroAide</span>
    </div>
    <a class="nav-link" href="#features">Features</a>
  </nav>

  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow">Built for Nigerian farms</span>
      <h1>Know your field <em>before</em> it costs you the season.</h1>
      <p class="lede">Scan a sick leaf, check the week's rain, and see what your crop is fetching nearby, all from one app that speaks your language on the ground.</p>
      <div class="cta-row">
        @if ($hasApk)
            <a class="btn btn-primary" href="{{ $apkUrl }}" id="download-android">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13m0 0-4-4m4 4 4-4M5 21h14"/></svg>
                Download for Android
            </a>
        @else
            <span class="btn btn-primary is-disabled" title="Set ANDROID_APK_URL on the server">
                Download for Android
            </span>
        @endif
        <a class="btn btn-secondary" href="#how-it-works">See how it works</a>
      </div>
      <p class="ios-note">iOS is on hold for now, Apple's developer fee is next on the list.</p>
    </div>

    <div class="field-card">
      <div class="field-card-head">
        <div>
          <span class="label">Scan result</span>
          <div class="crop">Cassava · Plot 2</div>
        </div>
        <span class="scan-badge">Just now</span>
      </div>
      <div class="field-card-body">
        <div class="diag"><span class="dot"></span>Cassava mosaic disease</div>
        <p>Leaf mottling matches early-stage infection. Remove affected stems and space new cuttings further apart to slow spread.</p>
      </div>
      <div class="field-card-foot">
        <span class="pill">3 nearby growers reported this</span>
        <span class="pill">Warning issued</span>
      </div>
    </div>
  </div>

  
</header>

<section id="features">
  <div class="wrap">
    <div class="section-head">
      <span class="kicker">In the app</span>
      <h2 class="section-title">Everything you need between planting and sale</h2>
      <p class="section-sub">Eight tools that work off your farm's actual location, crops, and history, not generic advice.</p>
    </div>
    <div class="features">
      <article class="feature f-scan">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/></svg></div>
        <h3>Crop disease scanning</h3>
        <p>Photograph a leaf. Get an ID and a plain write-up of what's wrong and what to do next, up to four scans a day.</p>
      </article>
      <article class="feature f-advisor">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
        <h3>AI farm advisor</h3>
        <p>Ask by typing or speaking, in English, Hausa, Yoruba, or Pidgin. Answers use your crops, fields, and the weather.</p>
      </article>
      <article class="feature f-weather">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19a4.5 4.5 0 0 0 0-9 6 6 0 0 0-11.6 1.7A4 4 0 0 0 6 19z"/></svg></div>
        <h3>Local weather &amp; soil</h3>
        <p>A 7-day forecast and soil moisture cues tied to your farm's GPS pin, not the nearest big city.</p>
      </article>
      <article class="feature f-calendar">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
        <h3>Farming calendar</h3>
        <p>Tasks, planting reminders, and crop watches, so the day's work is one screen instead of a memory test.</p>
      </article>
      <article class="feature f-market">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <h3>Crop market intel</h3>
        <p>Crowd-verified prices from Market Eye for the crops you actually grow, so you sell on your terms, not a rumor.</p>
      </article>
      <article class="feature f-community">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg></div>
        <h3>Disease outbreak map</h3>
        <p>When nearby growers of the same crop report the same disease, you're warned before it reaches your field.</p>
      </article>
      <article class="feature f-fields">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21 21 3M3 3l18 18"/></svg></div>
        <h3>Fields, journal &amp; money</h3>
        <p>Walk your boundaries, log what you observe, and track expenses and income against each field.</p>
      </article>
      <article class="feature f-alerts">
        <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></div>
        <h3>Push notifications</h3>
        <p>Severe weather, task reminders, daily tips, and disease alerts, sent straight to your phone.</p>
      </article>
    </div>
  </div>
</section>

<section class="process" id="how-it-works">
  <div class="wrap">
    <div class="section-head">
      <span class="kicker">How it works</span>
      <h2 class="section-title">From setup to sale, in five steps</h2>
      <p class="section-sub">Built to stay out of your way in the field, with as few taps as possible.</p>
    </div>
    <div class="steps">
      <div class="step">
        <span class="num">01</span>
        <div><h3>Create your farm profile</h3><p>Add your crops as cards, set soil and irrigation, then pin your farm by GPS or search.</p></div>
      </div>
      <div class="step">
        <span class="num">02</span>
        <div><h3>Scan when a plant looks wrong</h3><p>Upload a photo and get a condition, a likely disease, and next steps in minutes.</p></div>
      </div>
      <div class="step">
        <span class="num">03</span>
        <div><h3>Check today's dashboard</h3><p>Fresh tips each day, built from live rain outlook, soil signals, tasks, and recent scans.</p></div>
      </div>
      <div class="step">
        <span class="num">04</span>
        <div><h3>Ask the advisor or check the market</h3><p>Chat about treatment or planting. Open market prices before you harvest or haul out.</p></div>
      </div>
      <div class="step">
        <span class="num">05</span>
        <div><h3>Stay ahead of outbreaks</h3><p>Enough nearby reports of the same disease on your crop, and AgroAide warns you early.</p></div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap market">
    <div>
      <span class="kicker">Market</span>
      <h2 class="section-title">Sell on price, not guesswork</h2>
      <p class="section-sub">AgroAide pulls crowd-verified market intel for your crops, so you can weigh local price signals before you sell. Pair it with weather and calendar tasks to plan the harvest and the trip to market.</p>
      <ul>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Prices matched to the crops on your farm profile</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>History and trend lines where Market Eye has them</li>
        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Works alongside the field finance tracker already in the app</li>
      </ul>
    </div>
    <div class="board">
      <div class="board-head"><span>Nearby market, today</span><span></span></div>
      <div class="board-row"><span class="name">Maize (paint)</span><span>₦610</span><span class="delta up">▲ 4%</span></div>
      <div class="board-row"><span class="name">Cassava (tuber)</span><span>₦340</span><span class="delta down">▼ 2%</span></div>
      <div class="board-row"><span class="name">Tomato (basket)</span><span>₦1,250</span><span class="delta up">▲ 9%</span></div>
      <div class="board-row"><span class="name">Yam (tuber)</span><span>₦980</span><span class="delta up">▲ 1%</span></div>
      <div class="board-row"><span class="name">Pepper (basket)</span><span>₦2,100</span><span class="delta up">▲ 6%</span></div>
    </div>
  </div>
</section>

<section class="download-band" id="download">
  <div class="wrap">
    <div class="brand-lockup">
      <span class="mark"><img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide logo"></span>
      <span>AgroAide</span>
    </div>
    <h2 class="section-title" style="color:#fff">Get it on your phone</h2>
    <p>Install AgroAide, create an account, and start with your farm's location and your main crops. iOS is not available yet due to Apple's developer payment requirements.</p>
    @if ($hasApk)
        <a class="btn btn-primary" href="{{ $apkUrl }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13m0 0-4-4m4 4 4-4M5 21h14"/></svg>
            Download for Android
        </a>
    @else
        <span class="btn btn-primary is-disabled">Download for Android</span>
        <p style="margin-top:0.85rem;font-size:0.9rem;">Download link will appear here once the APK URL is configured.</p>
    @endif
  </div>
</section>

<footer>
  <div>© {{ date('Y') }} AgroAide</div>
  <div style="display:flex;gap:1rem;flex-wrap:wrap">
    <a href="{{ url('/legal/terms') }}">Terms</a>
    <a href="{{ url('/legal/privacy') }}">Privacy</a>
    <a href="{{ url('/api/health') }}">API status</a>
  </div>
</footer>

</body>
</html>