<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="AgroAide — AI crop scanning, disease outbreak heatmap radar, farm GPS boundary mapping, weather intelligence, and market prices for Nigerian growers.">
<title>AgroAide — Precision Farming & AI Crop Protection for Nigerian Growers</title>
<link rel="icon" href="{{ asset('images/agroaideLogo.png') }}" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,700;12..96,800&family=Inter:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
  :root {
    --bg-main: #0c120e;
    --bg-surface: #141f19;
    --bg-card: #1a2820;
    --bg-card-hover: #22342b;
    --bg-light: #f7faf8;
    --bg-light-surface: #ffffff;
    --bg-light-card: #f0f5f2;
    
    --primary: #10b981;
    --primary-light: #34d399;
    --primary-deep: #059669;
    --primary-glow: rgba(16, 185, 129, 0.25);
    
    --gold: #f59e0b;
    --gold-light: #fbbf24;
    --gold-glow: rgba(245, 158, 11, 0.25);
    
    --rust: #ef4444;
    --rust-light: #f87171;
    --rust-glow: rgba(239, 68, 68, 0.25);
    
    --cyan: #06b6d4;
    --cyan-light: #38bdf8;
    --cyan-glow: rgba(6, 182, 212, 0.25);
    
    --text-pure: #ffffff;
    --text-primary: #f3f7f4;
    --text-secondary: #9cb3a4;
    --text-muted: #6b8274;
    
    --text-light-primary: #111a14;
    --text-light-secondary: #4a5c51;
    --text-light-muted: #788d80;
    
    --border-dark: rgba(255, 255, 255, 0.08);
    --border-dark-highlight: rgba(16, 185, 129, 0.3);
    --border-light: rgba(16, 185, 129, 0.15);
    
    --font-heading: "Bricolage Grotesque", system-ui, -apple-system, sans-serif;
    --font-body: "Inter", system-ui, -apple-system, sans-serif;
    --font-mono: "IBM Plex Mono", monospace;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  
  body {
    font-family: var(--font-body);
    background-color: var(--bg-main);
    color: var(--text-primary);
    line-height: 1.6;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
  }

  a { color: inherit; text-decoration: none; }
  img, svg { display: block; max-width: 100%; }

  .container {
    max-width: 1240px;
    margin-inline: auto;
    padding-inline: clamp(1.25rem, 4vw, 2.5rem);
  }

  /* ------------------- TOP BANNER ------------------- */
  .top-notice {
    background: linear-gradient(90deg, #064e3b, #047857, #065f46);
    color: #ecfdf5;
    font-size: 0.84rem;
    padding: 0.5rem 1rem;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-weight: 500;
  }
  .top-notice-badge {
    background: var(--gold);
    color: #000;
    font-family: var(--font-mono);
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  /* ------------------- NAVIGATION ------------------- */
  .navbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(12, 18, 14, 0.85);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border-dark);
    transition: all 0.3s ease;
  }
  .navbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 72px;
  }
  .brand-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-family: var(--font-heading);
    font-weight: 800;
    font-size: 1.35rem;
    letter-spacing: -0.02em;
    color: var(--text-pure);
  }
  .brand-logo .logo-icon {
    width: 40px;
    height: 40px;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.3);
    border-radius: 10px;
    display: grid;
    place-items: center;
    box-shadow: 0 0 16px var(--primary-glow);
    flex-shrink: 0;
    overflow: hidden;
    padding: 3px;
  }
  .brand-logo .logo-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }
  .brand-badge {
    font-family: var(--font-mono);
    font-size: 0.65rem;
    background: rgba(16, 185, 129, 0.15);
    color: var(--primary-light);
    border: 1px solid rgba(16, 185, 129, 0.3);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
  }

  .nav-menu {
    display: flex;
    align-items: center;
    gap: 1.75rem;
    list-style: none;
  }
  .nav-menu a {
    font-size: 0.92rem;
    font-weight: 500;
    color: var(--text-secondary);
    transition: color 0.2s ease;
  }
  .nav-menu a:hover {
    color: var(--primary-light);
  }

  .nav-actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
  }

  /* ------------------- BUTTONS ------------------- */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    padding: 0.7rem 1.35rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.92rem;
    border: none;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
  }
  .btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-deep));
    color: #ffffff;
    box-shadow: 0 4px 18px var(--primary-glow);
  }
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(16, 185, 129, 0.4);
    background: linear-gradient(135deg, #10b981, #047857);
  }
  .btn-gold {
    background: linear-gradient(135deg, var(--gold), #d97706);
    color: #1a0f00;
    box-shadow: 0 4px 18px var(--gold-glow);
    font-weight: 700;
  }
  .btn-gold:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 24px rgba(245, 158, 11, 0.45);
  }
  .btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border: 1px solid var(--border-dark);
  }
  .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
  }
  .btn-sm {
    padding: 0.45rem 0.95rem;
    font-size: 0.85rem;
  }

  /* ------------------- HERO SECTION ------------------- */
  .hero-section {
    position: relative;
    padding-top: clamp(3rem, 6vw, 5rem);
    padding-bottom: clamp(3.5rem, 7vw, 6rem);
    overflow: hidden;
  }
  
  .hero-ambient-glow {
    position: absolute;
    top: -20%;
    right: -10%;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.14) 0%, rgba(6, 182, 212, 0.05) 50%, transparent 70%);
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
  }
  .hero-ambient-glow-2 {
    position: absolute;
    bottom: -10%;
    left: -10%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.1) 0%, transparent 65%);
    filter: blur(80px);
    pointer-events: none;
    z-index: 0;
  }

  .hero-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1.05fr 0.95fr;
    gap: 3.5rem;
    align-items: center;
  }

  .hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.35rem 0.85rem;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.25);
    border-radius: 999px;
    font-family: var(--font-mono);
    font-size: 0.76rem;
    color: var(--primary-light);
    margin-bottom: 1.4rem;
  }
  .hero-pill-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--primary);
    box-shadow: 0 0 8px var(--primary);
    animation: pulseGlow 2s infinite;
  }

  @keyframes pulseGlow {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.4); opacity: 0.6; }
  }

  .hero-title {
    font-family: var(--font-heading);
    font-size: clamp(2.4rem, 4.4vw, 3.8rem);
    font-weight: 800;
    line-height: 1.08;
    letter-spacing: -0.03em;
    color: var(--text-pure);
    margin-bottom: 1.35rem;
  }
  .hero-title .gradient-text {
    background: linear-gradient(120deg, #34d399 10%, #fbbf24 60%, #38bdf8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .hero-desc {
    font-size: clamp(1.02rem, 1.4vw, 1.16rem);
    color: var(--text-secondary);
    line-height: 1.65;
    margin-bottom: 2.2rem;
    max-width: 36rem;
  }

  .hero-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .hero-badges-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    padding-top: 1.75rem;
    border-top: 1px solid var(--border-dark);
  }
  .hero-stat-item {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
  }
  .hero-stat-val {
    font-family: var(--font-heading);
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-pure);
    display: flex;
    align-items: center;
    gap: 0.35rem;
  }
  .hero-stat-val .accent { color: var(--primary-light); }
  .hero-stat-lbl {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-family: var(--font-mono);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  /* ------------------- HERO INTERACTIVE PREVIEW CARD ------------------- */
  .hero-preview-frame {
    position: relative;
    background: linear-gradient(160deg, #16241c 0%, #101c15 100%);
    border: 1px solid rgba(52, 211, 153, 0.25);
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.75), 0 0 30px rgba(16, 185, 129, 0.1);
  }
  .preview-hud-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-dark);
    margin-bottom: 1.25rem;
  }
  .preview-hud-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: var(--primary-light);
  }
  .preview-hud-status::before {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary);
    box-shadow: 0 0 8px var(--primary);
  }
  .preview-hud-chip {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid var(--border-dark);
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    color: var(--text-secondary);
  }

  /* Interactive Crop Scan Leaf SVG & Laser */
  .scan-view-box {
    position: relative;
    width: 100%;
    height: 260px;
    background: radial-gradient(circle at 50% 50%, #1e3327 0%, #0d1912 100%);
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.06);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
  }
  .scan-laser-line {
    position: absolute;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, var(--primary-light) 50%, transparent 100%);
    box-shadow: 0 0 12px 3px var(--primary);
    z-index: 10;
    animation: scanLaser 3s ease-in-out infinite;
  }
  @keyframes scanLaser {
    0% { top: 5%; opacity: 0.2; }
    30% { opacity: 1; }
    70% { opacity: 1; }
    100% { top: 95%; opacity: 0.2; }
  }

  .scan-target-box {
    position: absolute;
    width: 120px;
    height: 90px;
    border: 1.5px dashed var(--gold);
    background: rgba(245, 158, 11, 0.08);
    border-radius: 8px;
    top: 32%;
    left: 36%;
    z-index: 5;
    animation: targetPulse 2.5s infinite;
  }
  @keyframes targetPulse {
    0%, 100% { border-color: var(--gold); box-shadow: 0 0 10px var(--gold-glow); }
    50% { border-color: var(--rust); box-shadow: 0 0 15px var(--rust-glow); }
  }
  .target-tag {
    position: absolute;
    top: -22px;
    left: 0;
    font-family: var(--font-mono);
    font-size: 0.65rem;
    font-weight: 700;
    background: var(--gold);
    color: #000;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
  }

  .scan-hud-diagnosis {
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid var(--border-dark);
    border-radius: 14px;
    padding: 1.1rem;
  }
  .diag-row-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.4rem;
  }
  .diag-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--text-pure);
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .diag-confidence {
    font-family: var(--font-mono);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--primary-light);
    background: rgba(16, 185, 129, 0.15);
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
  }
  .diag-desc {
    font-size: 0.84rem;
    color: var(--text-secondary);
    line-height: 1.45;
    margin-bottom: 0.75rem;
  }
  .diag-footer-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .mini-pill {
    font-family: var(--font-mono);
    font-size: 0.7rem;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    border: 1px solid var(--border-dark);
  }
  .mini-pill.alert {
    background: rgba(239, 68, 68, 0.12);
    color: var(--rust-light);
    border-color: rgba(239, 68, 68, 0.3);
  }

  /* ------------------- LIVE TICKER ------------------- */
  .ticker-section {
    background: #080c0a;
    border-block: 1px solid var(--border-dark);
    padding: 0.85rem 0;
    overflow: hidden;
  }
  .ticker-strip {
    display: flex;
    width: max-content;
    gap: 2.5rem;
    animation: scrollTicker 38s linear infinite;
  }
  @keyframes scrollTicker {
    from { transform: translateX(0); }
    to { transform: translateX(-50%); }
  }
  .ticker-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-family: var(--font-mono);
    font-size: 0.82rem;
    color: var(--text-secondary);
    white-space: nowrap;
  }
  .ticker-crop { font-weight: 600; color: var(--text-pure); }
  .ticker-price { color: var(--gold-light); font-weight: 600; }
  .ticker-delta.up { color: var(--primary-light); }
  .ticker-delta.down { color: var(--rust-light); }
  .ticker-tag {
    font-size: 0.68rem;
    background: rgba(255, 255, 255, 0.06);
    padding: 0.15rem 0.4rem;
    border-radius: 4px;
    color: var(--text-muted);
  }

  /* ------------------- SECTION SHARED ------------------- */
  section {
    padding: clamp(4.5rem, 8vw, 7rem) 0;
    position: relative;
  }
  .section-header {
    text-align: center;
    max-width: 44rem;
    margin-inline: auto;
    margin-bottom: clamp(2.5rem, 5vw, 4rem);
  }
  .section-kicker {
    display: inline-block;
    font-family: var(--font-mono);
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--primary-light);
    margin-bottom: 0.75rem;
  }
  .section-heading {
    font-family: var(--font-heading);
    font-size: clamp(2rem, 3.4vw, 2.75rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    color: var(--text-pure);
    line-height: 1.15;
    margin-bottom: 0.9rem;
  }
  .section-subtext {
    font-size: 1.05rem;
    color: var(--text-secondary);
    line-height: 1.6;
  }

  /* ------------------- 3-PILLAR INTERACTIVE SHOWCASE ------------------- */
  .showcase-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.75rem;
  }
  .showcase-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-dark);
    border-radius: 20px;
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.28s ease;
    box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
    position: relative;
    overflow: hidden;
  }
  .showcase-card:hover {
    border-color: var(--border-dark-highlight);
    transform: translateY(-4px);
    box-shadow: 0 18px 40px -15px var(--primary-glow);
  }
  .showcase-top { margin-bottom: 1.5rem; }
  .showcase-icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    margin-bottom: 1.1rem;
  }
  .badge-scan { background: rgba(16, 185, 129, 0.12); color: var(--primary-light); }
  .badge-radar { background: rgba(239, 68, 68, 0.12); color: var(--rust-light); }
  .badge-bound { background: rgba(6, 182, 212, 0.12); color: var(--cyan-light); }
  
  .showcase-card h3 {
    font-family: var(--font-heading);
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--text-pure);
    margin-bottom: 0.5rem;
  }
  .showcase-card p {
    font-size: 0.92rem;
    color: var(--text-secondary);
    line-height: 1.55;
  }

  .showcase-graphic-wrap {
    width: 100%;
    height: 220px;
    border-radius: 14px;
    background: #0e1611;
    border: 1px solid rgba(255,255,255,0.06);
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Radar sweep animation */
  .radar-sweep-line {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: conic-gradient(from 0deg, rgba(239, 68, 68, 0.35) 0deg, transparent 60deg, transparent 360deg);
    animation: rotateRadar 5s linear infinite;
    pointer-events: none;
  }
  @keyframes rotateRadar {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  /* ------------------- FEATURES GRID ------------------- */
  .features-grid-8 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.25rem;
  }
  .feat-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-dark);
    border-radius: 16px;
    padding: 1.5rem 1.35rem;
    transition: all 0.22s ease;
  }
  .feat-card:hover {
    background: var(--bg-card);
    border-color: rgba(16, 185, 129, 0.25);
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -10px rgba(0,0,0,0.4);
  }
  .feat-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    margin-bottom: 1rem;
  }
  .feat-card h4 {
    font-family: var(--font-heading);
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--text-pure);
    margin-bottom: 0.45rem;
  }
  .feat-card p {
    font-size: 0.88rem;
    color: var(--text-secondary);
    line-height: 1.5;
  }

  /* ------------------- AGRO ZONES SECTION ------------------- */
  .zones-box {
    background: linear-gradient(160deg, #132219 0%, #0d1711 100%);
    border: 1px solid var(--border-dark-highlight);
    border-radius: 22px;
    padding: clamp(2rem, 4vw, 3rem);
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 3rem;
    align-items: center;
  }
  .zone-tag-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  .zone-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--border-dark);
    padding: 1rem 1.25rem;
    border-radius: 12px;
  }
  .zone-color-bar {
    width: 4px;
    height: 100%;
    border-radius: 2px;
    flex-shrink: 0;
  }
  .bar-sahel { background: var(--gold); }
  .bar-guinea { background: var(--primary); }
  .bar-forest { background: var(--cyan); }
  .zone-info h5 {
    font-weight: 700;
    font-size: 0.98rem;
    color: var(--text-pure);
    margin-bottom: 0.2rem;
  }
  .zone-info p {
    font-size: 0.84rem;
    color: var(--text-secondary);
  }

  /* ------------------- HOW IT WORKS TIMELINE ------------------- */
  .timeline-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.25rem;
    position: relative;
  }
  .timeline-step {
    background: var(--bg-surface);
    border: 1px solid var(--border-dark);
    border-radius: 16px;
    padding: 1.5rem 1.25rem;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    transition: all 0.22s ease;
  }
  .timeline-step:hover {
    border-color: var(--border-dark-highlight);
    transform: translateY(-3px);
  }
  .step-num {
    font-family: var(--font-mono);
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary-light);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .step-num-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.15);
    display: grid;
    place-items: center;
    font-size: 0.75rem;
  }
  .timeline-step h4 {
    font-family: var(--font-heading);
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-pure);
    margin-bottom: 0.45rem;
  }
  .timeline-step p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.45;
  }

  /* ------------------- MARKET SECTION ------------------- */
  .market-split-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3.5rem;
    align-items: center;
  }
  .market-board-wrap {
    background: #111a14;
    border: 1px solid var(--border-dark);
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 20px 50px -15px rgba(0,0,0,0.6);
  }
  .market-board-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--border-dark);
    margin-bottom: 0.75rem;
  }
  .market-board-header span {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }
  .market-table-row {
    display: grid;
    grid-template-columns: 1.3fr 0.9fr 0.8fr 0.7fr;
    align-items: center;
    padding: 0.75rem 0.2rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-family: var(--font-mono);
    font-size: 0.86rem;
  }
  .market-table-row:last-child { border-bottom: none; }
  .market-table-row .crop-name { font-weight: 600; color: var(--text-pure); }
  .market-table-row .market-loc { font-size: 0.72rem; color: var(--text-muted); }
  .market-table-row .price-val { font-weight: 700; color: var(--gold-light); }
  .market-table-row .delta.up { color: var(--primary-light); text-align: right; }
  .market-table-row .delta.down { color: var(--rust-light); text-align: right; }

  /* ------------------- DOWNLOAD CALL TO ACTION ------------------- */
  .download-hero-card {
    background: radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.18) 0%, transparent 60%),
                linear-gradient(160deg, #15251d 0%, #0c1510 100%);
    border: 1px solid rgba(52, 211, 153, 0.3);
    border-radius: 28px;
    padding: clamp(2.5rem, 5vw, 4.5rem) clamp(1.5rem, 4vw, 3.5rem);
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 30px 70px -20px rgba(0,0,0,0.8), 0 0 40px var(--primary-glow);
  }
  .download-hero-card h2 {
    font-family: var(--font-heading);
    font-size: clamp(2.2rem, 4vw, 3.2rem);
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 1rem;
    letter-spacing: -0.025em;
  }
  .download-hero-card p {
    font-size: 1.1rem;
    color: var(--text-secondary);
    max-width: 38rem;
    margin-inline: auto;
    margin-bottom: 2.2rem;
    line-height: 1.6;
  }
  .download-buttons-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
  }
  .download-subtext {
    font-size: 0.85rem;
    color: var(--text-muted);
  }

  /* ------------------- FOOTER ------------------- */
  .site-footer {
    background: #080c09;
    border-top: 1px solid var(--border-dark);
    padding: 3.5rem 0 2rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
  }
  .footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr 1fr;
    gap: 3rem;
    margin-bottom: 3rem;
  }
  .footer-brand h4 {
    font-family: var(--font-heading);
    font-size: 1.3rem;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
  }
  .footer-brand p {
    font-size: 0.88rem;
    color: var(--text-muted);
    line-height: 1.6;
    max-width: 22rem;
  }
  .footer-col h5 {
    font-family: var(--font-mono);
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-pure);
    margin-bottom: 1rem;
  }
  .footer-links {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
  }
  .footer-links a {
    color: var(--text-secondary);
    transition: color 0.2s ease;
  }
  .footer-links a:hover {
    color: var(--primary-light);
  }
  .footer-bottom {
    padding-top: 1.75rem;
    border-top: 1px solid var(--border-dark);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.82rem;
    color: var(--text-muted);
  }

  /* ------------------- RESPONSIVE QUERIES ------------------- */
  @media (max-width: 1024px) {
    .hero-grid { grid-template-columns: 1fr; gap: 2.5rem; }
    .showcase-container { grid-template-columns: 1fr; gap: 1.5rem; }
    .features-grid-8 { grid-template-columns: repeat(2, 1fr); }
    .timeline-grid { grid-template-columns: repeat(2, 1fr); }
    .market-split-grid { grid-template-columns: 1fr; }
    .zones-box { grid-template-columns: 1fr; }
    .footer-grid { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 640px) {
    .nav-menu { display: none; }
    .features-grid-8 { grid-template-columns: 1fr; }
    .timeline-grid { grid-template-columns: 1fr; }
    .footer-grid { grid-template-columns: 1fr; }
    .market-table-row { grid-template-columns: 1.2fr 0.9fr 0.8fr; }
    .market-table-row .market-loc { display: none; }
  }
</style>
</head>
<body>

@php
    $apkUrl = trim((string) config('app.android_apk_url'));
    $hasApk = $apkUrl !== '';
@endphp

<!-- Global Notice Banner -->
<div class="top-notice">
  <span class="top-notice-badge">AgroAide v2.4</span>
  <span>Built for Nigerian farmers — AI Crop Doctor, Outbreak Heatmap Radar &amp; GPS Boundary Mapping.</span>
</div>

<!-- Header Navigation -->
<nav class="navbar">
  <div class="container navbar-inner">
    <a href="{{ url('/') }}" class="brand-logo">
      <div class="logo-icon">
        <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide Logo">
      </div>
      <span>AgroAide</span>
      <span class="brand-badge">NG</span>
    </a>

    <ul class="nav-menu">
      <li><a href="#features">Features</a></li>
      <li><a href="#interactive-showcase">Scanner &amp; Radar</a></li>
      <li><a href="#boundaries">Farm GPS</a></li>
      <li><a href="#how-it-works">How It Works</a></li>
      <li><a href="#market">Market Prices</a></li>
    </ul>

    <div class="nav-actions">
      @if ($hasApk)
        <a href="{{ $apkUrl }}" class="btn btn-primary btn-sm" id="nav-download-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v13m0 0-4-4m4 4 4-4M5 21h14"/></svg>
          Get App
        </a>
      @else
        <a href="#download" class="btn btn-primary btn-sm">Download App</a>
      @endif
    </div>
  </div>
</nav>

<!-- ==================== HERO SECTION ==================== -->
<header class="hero-section">
  <div class="hero-ambient-glow"></div>
  <div class="hero-ambient-glow-2"></div>

  <div class="container hero-grid">
    <div>
      <div class="hero-pill">
        <span class="hero-pill-dot"></span>
        <span>AI Computer Vision + Real-Time Outbreak Radar</span>
      </div>

      <h1 class="hero-title">
        Protect your crops. <br>
        <span class="gradient-text">Diagnose leaves. Map boundaries.</span>
      </h1>

      <p class="hero-desc">
        Instant photo-based crop disease diagnosis, live 30km outbreak radar alerts, GPS field boundary mapping, and weather intelligence tailored directly to your farm coordinates in Nigeria.
      </p>

      <div class="hero-actions">
        @if ($hasApk)
          <a href="{{ $apkUrl }}" class="btn btn-gold" id="hero-download-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v13m0 0-4-4m4 4 4-4M5 21h14"/></svg>
            Download Android App (.APK)
          </a>
        @else
          <a href="#download" class="btn btn-gold">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v13m0 0-4-4m4 4 4-4M5 21h14"/></svg>
            Get AgroAide APK
          </a>
        @endif
        <a href="#interactive-showcase" class="btn btn-secondary">
          Explore Live Visuals
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
        </a>
      </div>

      <div class="hero-badges-row">
        <div class="hero-stat-item">
          <div class="hero-stat-val">94.8<span class="accent">%</span></div>
          <div class="hero-stat-lbl">Scan Accuracy</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-val">30<span class="accent">km</span></div>
          <div class="hero-stat-lbl">Outbreak Radius</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-val">12+</div>
          <div class="hero-stat-lbl">Nigerian Crops</div>
        </div>
        <div class="hero-stat-item">
          <div class="hero-stat-val">4 <span class="accent">Langs</span></div>
          <div class="hero-stat-lbl">Eng · Hau · Yor · Pid</div>
        </div>
      </div>
    </div>

    <!-- Live Scanner HUD Frame -->
    <div class="hero-preview-frame">
      <div class="preview-hud-header">
        <div class="preview-hud-status">AI Vision Scanner Active</div>
        <div class="preview-hud-chip">GPS: 9.057°N, 7.495°E (Abuja)</div>
      </div>

      <!-- Animated Leaf Scan SVG -->
      <div class="scan-view-box">
        <div class="scan-laser-line"></div>
        <div class="scan-target-box">
          <span class="target-tag">Target: Lesion Focus</span>
        </div>

        <!-- High-fidelity Vector Leaf Illustration with Infection Patches -->
        <svg viewBox="0 0 320 220" width="280" height="190" style="filter: drop-shadow(0 10px 15px rgba(0,0,0,0.6));">
          <!-- Main Stem -->
          <path d="M160 210 Q 158 120 160 30" stroke="#2d5a3c" stroke-width="5" stroke-linecap="round" fill="none" />
          
          <!-- Large Left Leaf -->
          <path d="M160 140 C 90 150 40 100 60 40 C 100 40 150 90 160 140 Z" fill="url(#leafGradLeft)" stroke="#3e7a52" stroke-width="1.5" />
          <!-- Left Veins -->
          <path d="M160 140 Q 110 95 60 40 M130 115 Q 100 80 85 90 M145 130 Q 115 110 90 120" stroke="#285035" stroke-width="1.2" fill="none" opacity="0.8"/>

          <!-- Large Right Leaf (Infected) -->
          <path d="M160 120 C 230 130 280 80 260 20 C 220 20 170 70 160 120 Z" fill="url(#leafGradRight)" stroke="#3e7a52" stroke-width="1.5" />
          <!-- Right Veins -->
          <path d="M160 120 Q 210 75 260 20 M185 95 Q 220 60 235 70 M172 110 Q 205 90 230 100" stroke="#285035" stroke-width="1.2" fill="none" opacity="0.8"/>

          <!-- Disease Lesion Spots (Early Blight Concentric Rings) -->
          <g transform="translate(190, 60)">
            <circle cx="15" cy="15" r="14" fill="#6d4c28" opacity="0.85" />
            <circle cx="15" cy="15" r="10" fill="#442a12" />
            <circle cx="15" cy="15" r="6" fill="#1c0f05" />
            <circle cx="15" cy="15" r="2" fill="#ef4444" />
          </g>
          <g transform="translate(215, 45)">
            <circle cx="10" cy="10" r="9" fill="#6d4c28" opacity="0.8" />
            <circle cx="10" cy="10" r="6" fill="#442a12" />
            <circle cx="10" cy="10" r="2" fill="#ef4444" />
          </g>
          <g transform="translate(175, 80)">
            <circle cx="8" cy="8" r="7" fill="#6d4c28" opacity="0.8" />
            <circle cx="8" cy="8" r="4" fill="#442a12" />
          </g>

          <!-- Top Sprout Leaves -->
          <path d="M160 60 C 140 30 145 5 160 2 C 175 5 180 30 160 60 Z" fill="#4ade80" />

          <!-- Gradients -->
          <defs>
            <linearGradient id="leafGradLeft" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#15803d" />
              <stop offset="100%" stop-color="#22c55e" />
            </linearGradient>
            <linearGradient id="leafGradRight" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#84cc16" />
              <stop offset="60%" stop-color="#15803d" />
              <stop offset="100%" stop-color="#ca8a04" />
            </linearGradient>
          </defs>
        </svg>
      </div>

      <!-- Live Diagnosis Output -->
      <div class="scan-hud-diagnosis">
        <div class="diag-row-top">
          <div class="diag-name">
            <span style="color:var(--rust); font-size:1.1rem;">●</span>
            Tomato: Early Blight (Alternaria solani)
          </div>
          <div class="diag-confidence">94.8% Match</div>
        </div>
        <p class="diag-desc">
          Concentric brown lesions identified on lower leaf canopy. Weather radar detects 85% rain probability this evening — delay fungicide spray until dry morning.
        </p>
        <div class="diag-footer-pills">
          <span class="mini-pill alert">🚨 4 Nearby Outbreaks (4.2km)</span>
          <span class="mini-pill">🧪 Copper Oxychloride Suggested</span>
          <span class="mini-pill">✂️ Prune Lower Leaves</span>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- ==================== LIVE MARKET PRICE TICKER ==================== -->
<section class="ticker-section">
  <div class="ticker-strip">
    <div class="ticker-item">
      <span class="ticker-tag">Kano (Dawanau)</span>
      <span class="ticker-crop">White Maize (100kg)</span>
      <span class="ticker-price">₦62,500</span>
      <span class="ticker-delta up">▲ +4.2%</span>
    </div>
    <div class="ticker-item">
      <span class="ticker-tag">Ibadan (Bodija)</span>
      <span class="ticker-crop">Cassava Tubers (Pickup)</span>
      <span class="ticker-price">₦185,000</span>
      <span class="ticker-delta up">▲ +1.8%</span>
    </div>
    <div class="ticker-item">
      <span class="ticker-tag">Lagos (Mile 12)</span>
      <span class="ticker-crop">Fresh Tomato (Big Basket)</span>
      <span class="ticker-price">₦32,000</span>
      <span class="ticker-delta down">▼ -3.5%</span>
    </div>
    <div class="ticker-item">
      <span class="ticker-tag">Abuja (Wuse)</span>
      <span class="ticker-crop">Soybeans (100kg Bag)</span>
      <span class="ticker-price">₦78,000</span>
      <span class="ticker-delta up">▲ +6.1%</span>
    </div>
    <div class="ticker-item">
      <span class="ticker-tag">Benue (Gboko)</span>
      <span class="ticker-crop">Yam (100 Medium Tubers)</span>
      <span class="ticker-price">₦145,000</span>
      <span class="ticker-delta up">▲ +2.4%</span>
    </div>
    <div class="ticker-item">
      <span class="ticker-tag">Kaduna (Central)</span>
      <span class="ticker-crop">Cowpea/Beans (100kg)</span>
      <span class="ticker-price">₦94,000</span>
      <span class="ticker-delta down">▼ -1.2%</span>
    </div>

    <!-- Duplicate for seamless loop -->
    <div class="ticker-item">
      <span class="ticker-tag">Kano (Dawanau)</span>
      <span class="ticker-crop">White Maize (100kg)</span>
      <span class="ticker-price">₦62,500</span>
      <span class="ticker-delta up">▲ +4.2%</span>
    </div>
    <div class="ticker-item">
      <span class="ticker-tag">Ibadan (Bodija)</span>
      <span class="ticker-crop">Cassava Tubers (Pickup)</span>
      <span class="ticker-price">₦185,000</span>
      <span class="ticker-delta up">▲ +1.8%</span>
    </div>
    <div class="ticker-item">
      <span class="ticker-tag">Lagos (Mile 12)</span>
      <span class="ticker-crop">Fresh Tomato (Big Basket)</span>
      <span class="ticker-price">₦32,000</span>
      <span class="ticker-delta down">▼ -3.5%</span>
    </div>
  </div>
</section>

<!-- ==================== INTERACTIVE VISUAL SHOWCASE ==================== -->
<section id="interactive-showcase" style="background: var(--bg-main);">
  <div class="container">
    <div class="section-header">
      <span class="section-kicker">Core Visual Technology</span>
      <h2 class="section-heading">Three intelligent layers protecting your harvest</h2>
      <p class="section-subtext">
        From leaf-level computer vision to regional pathogen radar and GPS field boundaries, explore how AgroAide safeguards your investment.
      </p>
    </div>

    <div class="showcase-container">
      <!-- Card 1: Crop Scanner -->
      <div class="showcase-card">
        <div class="showcase-top">
          <div class="showcase-icon-badge badge-scan">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
          <h3>AI Crop Disease Scanner</h3>
          <p>Multi-modal vision identifies foliar diseases, nutrient deficiencies, and pest bites within seconds with immediate, local treatment guidance.</p>
        </div>

        <div class="showcase-graphic-wrap">
          <!-- Animated Scanner Reticle Graphic -->
          <svg viewBox="0 0 240 180" width="100%" height="100%">
            <!-- Grid Background -->
            <defs>
              <pattern id="gridPattern" width="20" height="20" patternUnits="userSpaceOnUse">
                <path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
              </pattern>
            </defs>
            <rect width="240" height="180" fill="url(#gridPattern)" />

            <!-- Maize/Corn Leaf Vector -->
            <path d="M30 150 C 70 80 150 40 220 30 C 180 70 110 130 30 150 Z" fill="#15803d" stroke="#22c55e" stroke-width="1.5" />
            <path d="M30 150 Q 125 90 220 30" stroke="#4ade80" stroke-width="1.5" fill="none" />
            
            <!-- Streak Disease Spots -->
            <line x1="110" y1="95" x2="135" y2="80" stroke="#facc15" stroke-width="3" stroke-linecap="round" />
            <line x1="140" y1="78" x2="165" y2="65" stroke="#facc15" stroke-width="3" stroke-linecap="round" />
            <line x1="90" y1="110" x2="115" y2="95" stroke="#f87171" stroke-width="3" stroke-linecap="round" />

            <!-- Scan Corner Reticles -->
            <path d="M 70 60 L 60 60 L 60 70 M 170 60 L 180 60 L 180 70 M 60 130 L 60 140 L 70 140 M 180 130 L 180 140 L 170 140" stroke="#34d399" stroke-width="2.5" fill="none" />
            
            <!-- Target Center Crosshair -->
            <circle cx="120" cy="100" r="18" stroke="rgba(52, 211, 153, 0.6)" stroke-width="1.5" stroke-dasharray="4 2" fill="none" />
            <line x1="120" y1="78" x2="120" y2="122" stroke="#34d399" stroke-width="1" />
            <line x1="98" y1="100" x2="142" y2="100" stroke="#34d399" stroke-width="1" />

            <!-- Scan Tag -->
            <rect x="75" y="145" width="90" height="20" rx="4" fill="#064e3b" stroke="#10b981" stroke-width="1"/>
            <text x="120" y="159" font-family="IBM Plex Mono" font-size="9" fill="#a7f3d0" text-anchor="middle" font-weight="600">CONFIDENCE: 96%</text>
          </svg>
        </div>
      </div>

      <!-- Card 2: Disease Outbreak Heatmap Radar -->
      <div class="showcase-card" id="outbreaks">
        <div class="showcase-top">
          <div class="showcase-icon-badge badge-radar">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg>
          </div>
          <h3>Disease Outbreak Heatmap</h3>
          <p>Real-time regional surveillance map. When neighboring farms flag contagious blights or rust, your 30km perimeter automatically triggers early warnings.</p>
        </div>

        <div class="showcase-graphic-wrap">
          <!-- Animated Radar Sweep -->
          <div class="radar-sweep-line"></div>

          <!-- Heatmap Topographic Map SVG -->
          <svg viewBox="0 0 240 180" width="100%" height="100%">
            <!-- Concentric Radius Rings -->
            <circle cx="120" cy="90" r="25" stroke="rgba(255,255,255,0.08)" stroke-width="1" fill="none"/>
            <circle cx="120" cy="90" r="50" stroke="rgba(255,255,255,0.08)" stroke-width="1" fill="none"/>
            <circle cx="120" cy="90" r="75" stroke="rgba(255,255,255,0.08)" stroke-width="1" fill="none"/>

            <!-- Heatmap Gradient Blobs (Cluster Outbreaks) -->
            <defs>
              <radialGradient id="heatCritical" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#ef4444" stop-opacity="0.8" />
                <stop offset="50%" stop-color="#f97316" stop-opacity="0.4" />
                <stop offset="100%" stop-color="#ef4444" stop-opacity="0" />
              </radialGradient>
              <radialGradient id="heatWarning" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#eab308" stop-opacity="0.7" />
                <stop offset="100%" stop-color="#eab308" stop-opacity="0" />
              </radialGradient>
            </defs>

            <!-- Outbreak Hotspot 1 (Red Critical) -->
            <circle cx="165" cy="65" r="32" fill="url(#heatCritical)" />
            <circle cx="165" cy="65" r="5" fill="#ef4444" />
            <text x="165" y="55" font-family="IBM Plex Mono" font-size="7" fill="#fca5a5" text-anchor="middle">MAIZE RUST (8 SCANS)</text>

            <!-- Outbreak Hotspot 2 (Yellow Warning) -->
            <circle cx="75" cy="120" r="28" fill="url(#heatWarning)" />
            <circle cx="75" cy="120" r="4" fill="#eab308" />

            <!-- User Farm Pin Center -->
            <circle cx="120" cy="90" r="7" fill="#10b981" stroke="#ffffff" stroke-width="2" />
            <circle cx="120" cy="90" r="14" stroke="#10b981" stroke-width="1.5" stroke-dasharray="3 3" fill="none" />
            
            <!-- Distance Indicator Line -->
            <line x1="120" y1="90" x2="165" y2="65" stroke="#ef4444" stroke-width="1.2" stroke-dasharray="3 3" />
            <rect x="135" y="72" width="38" height="14" rx="3" fill="#1f2937" stroke="#ef4444" stroke-width="0.8"/>
            <text x="154" y="82" font-family="IBM Plex Mono" font-size="6.5" fill="#fca5a5" text-anchor="middle">4.2 KM</text>

            <!-- Status Pill -->
            <rect x="10" y="10" width="85" height="18" rx="4" fill="rgba(239,68,68,0.2)" stroke="rgba(239,68,68,0.4)" stroke-width="1"/>
            <text x="52" y="22" font-family="IBM Plex Mono" font-size="7.5" fill="#fca5a5" text-anchor="middle" font-weight="600">⚡ PERIMETER ALERT</text>
          </svg>
        </div>
      </div>

      <!-- Card 3: Farm GPS Boundaries -->
      <div class="showcase-card" id="boundaries">
        <div class="showcase-top">
          <div class="showcase-icon-badge badge-bound">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/></svg>
          </div>
          <h3>GPS Farm Boundary Polygon</h3>
          <p>Walk your perimeter to capture exact GPS coordinates. Calculate area in square meters and hectares for precise input budgeting and yield planning.</p>
        </div>

        <div class="showcase-graphic-wrap">
          <!-- GPS Polygon Vector Map -->
          <svg viewBox="0 0 240 180" width="100%" height="100%">
            <!-- Satellite Grid Lines -->
            <line x1="0" y1="45" x2="240" y2="45" stroke="rgba(255,255,255,0.04)" />
            <line x1="0" y1="90" x2="240" y2="90" stroke="rgba(255,255,255,0.04)" />
            <line x1="0" y1="135" x2="240" y2="135" stroke="rgba(255,255,255,0.04)" />
            <line x1="60" y1="0" x2="60" y2="180" stroke="rgba(255,255,255,0.04)" />
            <line x1="120" y1="0" x2="120" y2="180" stroke="rgba(255,255,255,0.04)" />
            <line x1="180" y1="0" x2="180" y2="180" stroke="rgba(255,255,255,0.04)" />

            <!-- Field Polygon Shape (Parcel 1) -->
            <polygon points="45,40 185,30 205,140 75,155 35,100" fill="rgba(6, 182, 212, 0.15)" stroke="#06b6d4" stroke-width="2" />
            
            <!-- Secondary Internal Crop Split Line -->
            <line x1="115" y1="35" x2="135" y2="148" stroke="#06b6d4" stroke-width="1.2" stroke-dasharray="4 2" />

            <!-- GPS Vertex Node Pins -->
            <circle cx="45" cy="40" r="4.5" fill="#38bdf8" stroke="#ffffff" stroke-width="1.5"/>
            <circle cx="185" cy="30" r="4.5" fill="#38bdf8" stroke="#ffffff" stroke-width="1.5"/>
            <circle cx="205" cy="140" r="4.5" fill="#38bdf8" stroke="#ffffff" stroke-width="1.5"/>
            <circle cx="75" cy="155" r="4.5" fill="#38bdf8" stroke="#ffffff" stroke-width="1.5"/>
            <circle cx="35" cy="100" r="4.5" fill="#38bdf8" stroke="#ffffff" stroke-width="1.5"/>

            <!-- Area Badge Overlay -->
            <g transform="translate(70, 75)">
              <rect width="98" height="34" rx="6" fill="#082f49" stroke="#0ea5e9" stroke-width="1"/>
              <text x="49" y="15" font-family="IBM Plex Mono" font-size="8" fill="#e0f2fe" text-anchor="middle" font-weight="600">PLOT #1: CASSAVA</text>
              <text x="49" y="27" font-family="IBM Plex Mono" font-size="9" fill="#38bdf8" text-anchor="middle" font-weight="700">12,450 m² (1.25 Ha)</text>
            </g>

            <!-- Coordinate Tag -->
            <text x="45" y="30" font-family="IBM Plex Mono" font-size="6.5" fill="#94a3b8">9.057°N, 7.495°E</text>
          </svg>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== 8 COMPREHENSIVE FEATURES ==================== -->
<section id="features" style="background: var(--bg-surface); border-top: 1px solid var(--border-dark);">
  <div class="container">
    <div class="section-header">
      <span class="section-kicker">Everything Built In</span>
      <h2 class="section-heading">The full operational suite for modern Nigerian farming</h2>
      <p class="section-subtext">Designed for low-bandwidth rural connectivity, multilingual voice prompts, and maximum field resilience.</p>
    </div>

    <div class="features-grid-8">
      <!-- 1. AI Disease Diagnosis -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(16, 185, 129, 0.12); color: var(--primary-light);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <h4>Crop Health Diagnosis</h4>
        <p>Take a leaf photo to identify 40+ blights, mosaic viruses, and nutrient lacks with step-by-step chemical and organic remedies.</p>
      </div>

      <!-- 2. Disease Outbreak Heatmap -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(239, 68, 68, 0.12); color: var(--rust-light);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/></svg>
        </div>
        <h4>Outbreak Radar &amp; Heatmap</h4>
        <p>Crowdsourced disease clusters automatically calculate pathogen distance and notify you before infections enter your boundary.</p>
      </div>

      <!-- 3. GPS Farm Boundaries -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(6, 182, 212, 0.12); color: var(--cyan-light);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/></svg>
        </div>
        <h4>GPS Perimeter Walk</h4>
        <p>Walk your boundary to record precise GeoJSON polygons, field acreage, and soil profiles for clean seed &amp; fertilizer estimates.</p>
      </div>

      <!-- 4. Multilingual AI Advisor -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(245, 158, 11, 0.12); color: var(--gold-light);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h4>Multilingual AI Advisor</h4>
        <p>Chat or speak in English, Hausa, Yoruba, or Nigerian Pidgin. Advisor answers using your exact field crops and weather context.</p>
      </div>

      <!-- 5. Hyperlocal Weather & Soil -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(56, 189, 248, 0.12); color: #38bdf8;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19a4.5 4.5 0 0 0 0-9 6 6 0 0 0-11.6 1.7A4 4 0 0 0 6 19z"/></svg>
        </div>
        <h4>Hyperlocal Weather &amp; Soil</h4>
        <p>7-day forecast, rain probabilities, soil temperature, and moisture index tied directly to your exact farm GPS pin.</p>
      </div>

      <!-- 6. Full Crop Cycle Lifecycle -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(16, 185, 129, 0.12); color: var(--primary-light);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <h4>Full Crop Cycle &amp; Harvest</h4>
        <p>Zone-specific planting windows, harvest countdowns, yield reviews, and fallow field next-crop rotation planners.</p>
      </div>

      <!-- 7. Market Price Intelligence -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(245, 158, 11, 0.12); color: var(--gold-light);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h4>Market Price Intel</h4>
        <p>Real-time commodity prices from Dawanau, Bodija, Mile 12, and Wuse markets so you negotiate sales from a position of strength.</p>
      </div>

      <!-- 8. Push Alerts & Notifications -->
      <div class="feat-card">
        <div class="feat-icon-box" style="background: rgba(239, 68, 68, 0.12); color: var(--rust-light);">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        </div>
        <h4>Instant Push Alerts</h4>
        <p>Receive push notifications for heavy storm advisories, task schedules, crop watch windows, and nearby outbreaks.</p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== AGRO-ECOLOGICAL ZONES OF NIGERIA ==================== -->
<section style="background: var(--bg-main);">
  <div class="container">
    <div class="zones-box">
      <div>
        <span class="section-kicker">Customized For Nigeria</span>
        <h3 class="section-heading" style="font-size: clamp(1.8rem, 3vw, 2.3rem); margin-bottom: 0.75rem;">
          Calibrated across 3 Agro-Ecological Zones
        </h3>
        <p class="section-subtext" style="font-size: 0.95rem;">
          Farming in Sokoto is fundamentally different from farming in Ondo. AgroAide automatically resolves your farm's ecological zone to deliver precise planting windows and rainfall milestones.
        </p>

        <div class="zone-tag-list">
          <div class="zone-item">
            <div class="zone-color-bar bar-sahel"></div>
            <div class="zone-info">
              <h5>Sudan &amp; Sahel Savanna (Kano, Sokoto, Katsina, Borno)</h5>
              <p>Short-season drought-tolerant crops: Millet, Sorghum, Cowpea, Sesame, and Groundnut.</p>
            </div>
          </div>
          <div class="zone-item">
            <div class="zone-color-bar bar-guinea"></div>
            <div class="zone-info">
              <h5>Guinea Savanna (Kaduna, Abuja, Benue, Niger, Plateau)</h5>
              <p>Bimodal &amp; monomodal grain hubs: Maize, Soybeans, Yam, Rice, and Vegetables.</p>
            </div>
          </div>
          <div class="zone-item">
            <div class="zone-color-bar bar-forest"></div>
            <div class="zone-info">
              <h5>Humid Forest (Oyo, Ogun, Edo, Enugu, Rivers, Cross River)</h5>
              <p>High precipitation tree &amp; root crops: Cassava, Plantain, Cocoa, Palm Oil, and Cocoyam.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Agro Map Vector Diagram -->
      <div style="background: #0b1510; border: 1px solid var(--border-dark); border-radius: 16px; padding: 1.5rem; text-align: center;">
        <svg viewBox="0 0 280 220" width="100%" height="200">
          <!-- Nigeria Simplified Geographic Shape -->
          <path d="M30 60 Q 90 20 180 25 Q 260 30 260 90 Q 250 160 210 190 Q 140 210 80 190 Q 30 140 20 90 Z" fill="#14241b" stroke="#22543d" stroke-width="2"/>
          
          <!-- Zone 1 (Sahel - North) -->
          <path d="M30 60 Q 90 20 180 25 Q 260 30 255 75 Q 160 70 30 60 Z" fill="rgba(245, 158, 11, 0.25)" stroke="#f59e0b" stroke-width="1.2" stroke-dasharray="3 2" />
          <text x="140" y="48" font-family="IBM Plex Mono" font-size="8" fill="#fde68a" font-weight="700">SAHEL &amp; SUDAN SAVANNA</text>

          <!-- Zone 2 (Guinea - Middle Belt) -->
          <path d="M30 60 Q 160 70 255 75 Q 250 125 240 135 Q 140 130 25 115 Z" fill="rgba(16, 185, 129, 0.25)" stroke="#10b981" stroke-width="1.2" stroke-dasharray="3 2" />
          <text x="140" y="105" font-family="IBM Plex Mono" font-size="8" fill="#a7f3d0" font-weight="700">GUINEA SAVANNA (MIDDLE BELT)</text>

          <!-- Zone 3 (Forest - South) -->
          <path d="M25 115 Q 140 130 240 135 Q 210 190 80 190 Q 30 140 25 115 Z" fill="rgba(6, 182, 212, 0.25)" stroke="#06b6d4" stroke-width="1.2" stroke-dasharray="3 2" />
          <text x="140" y="165" font-family="IBM Plex Mono" font-size="8" fill="#bae6fd" font-weight="700">HUMID RAINFOREST</text>

          <!-- Capital City Node -->
          <circle cx="135" cy="100" r="5" fill="#f59e0b" stroke="#ffffff" stroke-width="1.5" />
          <text x="135" y="115" font-family="IBM Plex Mono" font-size="7" fill="#ffffff">Abuja (HQ)</text>
        </svg>
      </div>
    </div>
  </div>
</section>

<!-- ==================== HOW IT WORKS TIMELINE ==================== -->
<section id="how-it-works" style="background: var(--bg-surface); border-block: 1px solid var(--border-dark);">
  <div class="container">
    <div class="section-header">
      <span class="section-kicker">Simple 5-Step Process</span>
      <h2 class="section-heading">From planting to harvest with AgroAide</h2>
      <p class="section-subtext">No complex setups. Works seamlessly in low connectivity environments across Nigeria.</p>
    </div>

    <div class="timeline-grid">
      <!-- Step 1 -->
      <div class="timeline-step">
        <div class="step-num">
          <span>01</span>
          <div class="step-num-circle">📍</div>
        </div>
        <h4>Map Your Farm</h4>
        <p>Walk your boundary with GPS or pin your location. AgroAide automatically determines your soil type and agro-zone.</p>
      </div>

      <!-- Step 2 -->
      <div class="timeline-step">
        <div class="step-num">
          <span>02</span>
          <div class="step-num-circle">📸</div>
        </div>
        <h4>Scan Sick Leaves</h4>
        <p>Photograph suspicious leaves or symptoms. Get instant disease verification with dosage recommendations.</p>
      </div>

      <!-- Step 3 -->
      <div class="timeline-step">
        <div class="step-num">
          <span>03</span>
          <div class="step-num-circle">📡</div>
        </div>
        <h4>Track Outbreaks</h4>
        <p>Keep an eye on the 30km outbreak radar. Receive notifications before contagious pathogens migrate to your parcel.</p>
      </div>

      <!-- Step 4 -->
      <div class="timeline-step">
        <div class="step-num">
          <span>04</span>
          <div class="step-num-circle">🌦️</div>
        </div>
        <h4>Check Weather &amp; AI</h4>
        <p>Receive daily farming tips and rain alerts. Chat with your voice AI agronomist in English, Hausa, Yoruba, or Pidgin.</p>
      </div>

      <!-- Step 5 -->
      <div class="timeline-step">
        <div class="step-num">
          <span>05</span>
          <div class="step-num-circle">💰</div>
        </div>
        <h4>Harvest &amp; Sell</h4>
        <p>Log your successful harvest, check crowd-verified wholesale market prices, and plan your next crop rotation.</p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== MARKET INTEL SECTION ==================== -->
<section id="market" style="background: var(--bg-main);">
  <div class="container market-split-grid">
    <div>
      <span class="section-kicker">Market Intelligence</span>
      <h2 class="section-heading">Sell on real market data, not middleman rumors</h2>
      <p class="section-subtext" style="margin-bottom: 1.5rem;">
        AgroAide aggregates verified wholesale prices from major trading hubs across Nigeria. Know exactly what your maize, cassava, yam, and tomatoes are fetching before you hire transport.
      </p>

      <div style="display:flex; flex-direction:column; gap:0.9rem;">
        <div style="display:flex; gap:0.75rem; align-items:flex-start;">
          <div style="color:var(--primary-light); margin-top:2px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div style="font-size:0.95rem; color:var(--text-secondary);">
            <strong style="color:var(--text-pure);">Direct Hub Tracking:</strong> Updated commodity data from Dawanau, Bodija, Mile 12, and Wuse markets.
          </div>
        </div>

        <div style="display:flex; gap:0.75rem; align-items:flex-start;">
          <div style="color:var(--primary-light); margin-top:2px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div style="font-size:0.95rem; color:var(--text-secondary);">
            <strong style="color:var(--text-pure);">Harvest Timing Optimization:</strong> Pair market price surges with your field's harvest window for maximum profit.
          </div>
        </div>

        <div style="display:flex; gap:0.75rem; align-items:flex-start;">
          <div style="color:var(--primary-light); margin-top:2px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <div style="font-size:0.95rem; color:var(--text-secondary);">
            <strong style="color:var(--text-pure);">Expense &amp; Profit Ledger:</strong> Integrated field finance tracker to calculate net margin per hectare.
          </div>
        </div>
      </div>
    </div>

    <!-- Live Market Board Mockup -->
    <div class="market-board-wrap">
      <div class="market-board-header">
        <span>Verified Market Feed · Nigeria</span>
        <span style="display:inline-flex; align-items:center; gap:5px; color:var(--primary-light); font-size:0.72rem;">
          <span style="width:6px; height:6px; border-radius:50%; background:var(--primary);"></span> LIVE
        </span>
      </div>

      <div class="market-table-row">
        <div>
          <div class="crop-name">White Maize</div>
          <div class="market-loc">Dawanau, Kano</div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted);">100kg Bag</div>
        <div class="price-val">₦62,500</div>
        <div class="delta up">▲ +4.2%</div>
      </div>

      <div class="market-table-row">
        <div>
          <div class="crop-name">Cassava Tubers</div>
          <div class="market-loc">Bodija, Ibadan</div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted);">Pickup Load</div>
        <div class="price-val">₦185,000</div>
        <div class="delta up">▲ +1.8%</div>
      </div>

      <div class="market-table-row">
        <div>
          <div class="crop-name">Fresh Tomatoes</div>
          <div class="market-loc">Mile 12, Lagos</div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted);">Big Basket</div>
        <div class="price-val">₦32,000</div>
        <div class="delta down">▼ -3.5%</div>
      </div>

      <div class="market-table-row">
        <div>
          <div class="crop-name">Soybeans</div>
          <div class="market-loc">Wuse, Abuja</div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted);">100kg Bag</div>
        <div class="price-val">₦78,000</div>
        <div class="delta up">▲ +6.1%</div>
      </div>

      <div class="market-table-row">
        <div>
          <div class="crop-name">Yam Tubers</div>
          <div class="market-loc">Gboko, Benue</div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted);">100 Tubers</div>
        <div class="price-val">₦145,000</div>
        <div class="delta up">▲ +2.4%</div>
      </div>

      <div class="market-table-row">
        <div>
          <div class="crop-name">Cowpea (Beans)</div>
          <div class="market-loc">Kaduna Central</div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted);">100kg Bag</div>
        <div class="price-val">₦94,000</div>
        <div class="delta down">▼ -1.2%</div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== DOWNLOAD CTA BAND ==================== -->
<section id="download" style="background: var(--bg-surface); padding-block: 5rem;">
  <div class="container">
    <div class="download-hero-card">
      <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(255,255,255,0.08); padding:0.35rem 0.85rem; border-radius:999px; margin-bottom:1.5rem;">
        <span style="color:var(--gold-light);">★</span>
        <span style="font-family:var(--font-mono); font-size:0.75rem; color:#f3f7f4;">Ready for Android Devices</span>
      </div>

      <h2>Download AgroAide for Android</h2>
      <p>
        Install the application, select your crops, and walk your farm boundaries. Start diagnosing plant health and receiving outbreak radar alerts right on your phone.
      </p>

      <div class="download-buttons-wrap">
        @if ($hasApk)
          <a href="{{ $apkUrl }}" class="btn btn-gold" style="padding:0.95rem 2rem; font-size:1.05rem;" id="footer-download-btn">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 3v13m0 0-4-4m4 4 4-4M5 21h14"/></svg>
            Download Android APK (Direct)
          </a>
        @else
          <a href="#" class="btn btn-gold" style="padding:0.95rem 2rem; font-size:1.05rem; opacity:0.65; cursor:not-allowed;">
            Download Android APK
          </a>
        @endif
        <a href="#interactive-showcase" class="btn btn-secondary" style="padding:0.95rem 1.6rem; font-size:1.05rem;">
          Explore Visual Features
        </a>
      </div>

      <div class="download-subtext">
        <span>Requires Android 8.0+ · Instant Install · Free to Use for Nigerian Farmers</span>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <!-- Brand column -->
      <div class="footer-brand">
        <h4>
          <div style="width:34px; height:34px; background:rgba(16, 185, 129, 0.12); border:1px solid rgba(16, 185, 129, 0.3); border-radius:9px; display:grid; place-items:center; overflow:hidden; padding:3px; flex-shrink:0;">
            <img src="{{ asset('images/agroaideLogo.png') }}" alt="AgroAide Logo" style="width:100%; height:100%; object-fit:contain; display:block;">
          </div>
          AgroAide
        </h4>
        <p>
          AI-powered agricultural platform designed specifically for Nigerian growers. Real-time disease diagnosis, radar surveillance, weather analytics, and market pricing.
        </p>
      </div>

      <!-- Quick Links -->
      <div class="footer-col">
        <h5>Features</h5>
        <ul class="footer-links">
          <li><a href="#interactive-showcase">Crop Disease Scanner</a></li>
          <li><a href="#outbreaks">Outbreak Radar</a></li>
          <li><a href="#boundaries">Farm GPS Boundaries</a></li>
          <li><a href="#market">Market Prices</a></li>
          <li><a href="#how-it-works">5-Step Farming Cycle</a></li>
        </ul>
      </div>

      <!-- Languages -->
      <div class="footer-col">
        <h5>Supported Languages</h5>
        <ul class="footer-links">
          <li><span>English (Default)</span></li>
          <li><span>Hausa (Harshen Hausa)</span></li>
          <li><span>Yoruba (Èdè Yorùbá)</span></li>
          <li><span>Nigerian Pidgin</span></li>
          <li><span>Igbo (Asụsụ Igbo)</span></li>
        </ul>
      </div>

      <!-- Legal & Access -->
      <div class="footer-col">
        <h5>Platform &amp; Legal</h5>
        <ul class="footer-links">
          <li><a href="{{ url('/legal/terms') }}">Terms of Service</a></li>
          <li><a href="{{ url('/legal/privacy') }}">Privacy Policy</a></li>
          <li><a href="{{ url('/api/health') }}" target="_blank">API Status Check</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div>© {{ date('Y') }} AgroAide Nigeria. Precision agricultural intelligence for every grower.</div>
      <div style="display:flex; gap:1.25rem;">
        <a href="{{ url('/legal/terms') }}">Terms</a>
        <a href="{{ url('/legal/privacy') }}">Privacy</a>
      </div>
    </div>
  </div>
</footer>

</body>
</html>
