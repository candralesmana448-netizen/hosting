<?php
session_start();

// Cek status admin dari session
$isAdmin = isset($_SESSION['ar_is_admin']) && $_SESSION['ar_is_admin'] === true;

// Handle logout via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Handle cek status admin
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_admin'])) {
    echo json_encode(['isAdmin' => $isAdmin]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sosial Media Ar-Rahmah - Yayasan Ar-Rahmah</title>
  
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --orange: #D9711F;
      --orange-deep: #B85A12;
      --orange-light: #F3A15C;
      --green: #1F5C3F;
      --green-light: #7FA65C;
      --green-pale: #E7EFDD;
      --cream: #FDF9F3;
      --white: #FFFFFF;
      --ink: #2B2118;
      --ink-soft: #6B5F52;
      --border: #EAE1D3;
      --radius: 14px;
      --shadow: 0 12px 30px -15px rgba(43,33,24,0.25);
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    
    body {
      margin: 0;
      font-family: 'Plus Jakarta Sans', sans-serif;
      color: var(--ink);
      background: var(--cream);
      line-height: 1.6;
    }

    h1, h2, h3, .brand-name {
      font-family: 'Amiri', serif;
      font-weight: 700;
      margin: 0;
    }

    a { text-decoration: none; color: inherit; }
    img { max-width: 100%; display: block; }

    .container {
      max-width: 1160px;
      margin: 0 auto;
      padding: 0 28px;
    }

    /* ---------- Header: HIJAU SOLID (sama seperti index.php) ---------- */
    header {
      position: sticky;
      top: 0;
      z-index: 100;
      background: var(--green);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      box-shadow: 0 2px 14px rgba(0,0,0,0.06);
      transition:
        background 0.55s cubic-bezier(0.4, 0, 0.2, 1),
        box-shadow 0.55s cubic-bezier(0.4, 0, 0.2, 1),
        border-color 0.55s ease,
        padding 0.3s ease;
    }

    header.scrolled {
      background: var(--green);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(255,255,255,0.15);
      box-shadow: 0 6px 30px rgba(0,0,0,0.18);
    }

    header .brand-name { color: var(--white); }
    header .brand-sub  { color: rgba(255,255,255,0.72); }

    .nav-wrap {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 28px;
      max-width: 1160px;
      margin: 0 auto;
      transition: padding 0.3s ease;
    }

    header.scrolled .nav-wrap { padding: 12px 28px; }

    .brand { display: flex; align-items: center; gap: 12px; }
    .brand-mark { 
      width: 42px; 
      height: 42px; 
      border-radius: 8px; 
      overflow: hidden;
      transition: background 0.3s ease;
    }
    .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
    .brand-sub { 
      font-size: 11px; 
      text-transform: uppercase; 
      letter-spacing: 0.5px;
      transition: color 0.3s ease;
    }
    .brand-name { transition: color 0.3s ease; }

    /* ============================================================
       EXPANDING FILL ANIMATION (sama seperti .nav-cta di index.php)
       ============================================================ */
    .nav-cta {
      background: var(--orange);
      color: var(--white) !important;
      padding: 11px 22px;
      border-radius: 100px;
      font-weight: 700;
      font-size: 14.5px;
      box-shadow: 0 8px 18px -8px rgba(217,113,31,0.6);
      cursor: pointer;
      border: none;
      display: inline-block;
      position: relative;
      overflow: hidden;
      isolation: isolate;
      z-index: 1;
      transition: color 0.5s ease, transform 0.3s ease, box-shadow 0.3s ease;
    }

    .nav-cta::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 0;
      height: 100%;
      background: var(--orange-deep);
      z-index: -1;
      transition: width 0.55s cubic-bezier(0.4, 0, 0.2, 1);
      pointer-events: none;
    }

    .nav-cta:hover::before,
    .nav-cta:focus-visible::before {
      width: 100%;
    }

    .nav-cta:hover {
      background: var(--orange);
      transform: translateY(-1px);
    }

    /* Varian warna hijau untuk tombol "Mode Admin Aktif" & "Simpan Perubahan" */
    .nav-cta.nav-cta-green {
      background: var(--green);
      box-shadow: 0 8px 18px -8px rgba(31,92,63,0.6);
    }
    .nav-cta.nav-cta-green::before {
      background: #14402C;
    }

    /* ---------- Admin Panel Floating ---------- */
    .admin-panel {
      position: fixed; 
      bottom: 24px; 
      right: 24px; 
      z-index: 999;
      background: var(--white); 
      border: 2px solid var(--orange);
      border-radius: var(--radius); 
      padding: 12px 18px; 
      box-shadow: var(--shadow);
      display: none; 
      gap: 12px; 
      align-items: center;
    }

    .admin-panel.active { display: flex; }

    .admin-panel .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 100px;
      font-weight: 700;
      font-size: 13px;
      cursor: pointer;
      border: none;
      transition: transform .2s, box-shadow .2s, background .2s;
      font-family: inherit;
      position: relative;
      overflow: hidden;
      isolation: isolate;
      z-index: 1;
    }

    .admin-panel .btn-primary {
      background: var(--orange);
      color: var(--white);
      box-shadow: 0 8px 18px -8px rgba(217,113,31,0.5);
    }

    .admin-panel .btn-primary::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 0;
      height: 100%;
      background: var(--orange-deep);
      z-index: -1;
      transition: width 0.55s cubic-bezier(0.4, 0, 0.2, 1);
      pointer-events: none;
    }

    .admin-panel .btn-primary:hover::before,
    .admin-panel .btn-primary:focus-visible::before {
      width: 100%;
    }

    .admin-panel .btn-primary:hover { 
      background: var(--orange); 
      transform: translateY(-2px); 
    }

    .admin-panel .btn-ghost {
      background: var(--white);
      color: var(--green);
      border: 1.5px solid var(--green);
    }

    .admin-panel .btn-ghost::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 0;
      height: 100%;
      background: var(--green-pale);
      z-index: -1;
      transition: width 0.55s cubic-bezier(0.4, 0, 0.2, 1);
      pointer-events: none;
    }

    .admin-panel .btn-ghost:hover::before,
    .admin-panel .btn-ghost:focus-visible::before {
      width: 100%;
    }

    .admin-panel .btn-ghost:hover { background: var(--white); }

    /* ---------- Hero Banner Sosial Media ---------- */
    .socmed-hero {
      background: linear-gradient(135deg, var(--green) 0%, #123825 100%);
      color: var(--white);
      padding: 60px 0 80px;
      border-bottom-left-radius: 40px;
      border-bottom-right-radius: 40px;
      margin-bottom: 40px;
      box-shadow: 0 15px 30px -10px rgba(31, 92, 63, 0.3);
    }

    .hero-wrap {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 30px;
      flex-wrap: wrap;
    }

    .hero-content h1 {
      font-size: 42px;
      color: var(--white);
      margin-top: 10px;
    }

    .hero-content p {
      color: rgba(255,255,255,0.85);
      font-size: 16px;
      max-width: 540px;
    }

    /* ---------- Filter Bar ---------- */
    .filter-bar {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-bottom: 40px;
      flex-wrap: wrap;
    }

    .filter-btn {
      background: var(--white);
      border: 1px solid var(--border);
      padding: 10px 22px;
      border-radius: 100px;
      font-size: 14px;
      font-weight: 600;
      color: var(--ink-soft);
      cursor: pointer;
      transition: all 0.25s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      font-family: inherit;
    }

    .filter-btn:hover, .filter-btn.active {
      background: var(--green);
      color: var(--white);
      border-color: var(--green);
      box-shadow: 0 6px 15px -5px rgba(31, 92, 63, 0.4);
    }

    /* ---------- Social Media Feed Grid ---------- */
    .feed-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
    }

    .feed-card {
      background: var(--white);
      border-radius: 20px;
      border: 1px solid var(--border);
      overflow: hidden;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .feed-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 18px 35px -15px rgba(43,33,24,0.3);
    }

    /* Card Header */
    .card-header {
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #F5EFE6;
    }

    .profile-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .profile-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid var(--orange-light);
    }

    .profile-name {
      font-weight: 700;
      font-size: 14px;
      color: var(--ink);
    }

    .post-date {
      font-size: 11px;
      color: var(--ink-soft);
    }

    .platform-badge {
      padding: 4px 10px;
      border-radius: 50px;
      font-size: 11px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .badge-ig { background: #FFEBF4; color: #D62976; }
    .badge-yt { background: #FFEEEE; color: #FF0000; }
    .badge-fb { background: #EBF3FF; color: #1877F2; }

    /* Media Tampilan Hanya Logo */
    .feed-media {
      position: relative;
      height: 220px;
      background: var(--green-pale);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .feed-media:hover {
      background: #d5e5c8;
    }

    .feed-media img.logo-only {
      width: 110px;
      height: 110px;
      object-fit: contain;
      transition: transform 0.3s ease;
    }

    .feed-card:hover .feed-media img.logo-only {
      transform: scale(1.08);
    }

    .feed-content {
      padding: 20px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .feed-caption {
      font-size: 14px;
      color: var(--ink);
      line-height: 1.5;
      margin-bottom: 16px;
      flex-grow: 1;
    }

    .hashtags {
      color: var(--orange-deep);
      font-weight: 600;
      font-size: 13px;
    }

    /* Card Footer Link Direct */
    .card-footer {
      padding: 14px 20px;
      border-top: 1px solid #F5EFE6;
      background: #FAF6F0;
      display: flex;
      align-items: center;
      justify-content: flex-end;
    }

    .btn-direct {
      background: var(--orange);
      color: var(--white);
      padding: 8px 18px;
      border-radius: 50px;
      font-size: 13px;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background 0.2s, transform 0.2s;
      border: none;
      cursor: pointer;
    }

    .btn-direct:hover {
      background: var(--orange-deep);
      transform: translateY(-1px);
    }

    /* ---------- Action Back Button ---------- */
    .action-top {
      margin-bottom: 20px;
      display: flex;
      justify-content: flex-start;
      gap: 14px;
      flex-wrap: wrap;
    }

    /* ---------- Footer ---------- */
    footer {
      background: #163F2C;
      color: rgba(255,255,255,0.82);
      padding: 48px 0 26px;
      margin-top: 60px;
    }

    .footer-content {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      text-align: left;
      padding-bottom: 28px;
      border-bottom: 1px solid rgba(255,255,255,0.12);
    }

    .footer-brand { 
      display: flex; 
      gap: 12px; 
      align-items: center; 
      justify-content: flex-start;
      margin-bottom: 12px; 
    }
    .footer-brand .brand-name { color: var(--white); font-size: 22px; }

    footer .desc { 
      font-size: 14.5px; 
      opacity: 0.8; 
      max-width: 520px; 
      line-height: 1.7; 
      margin: 0;
    }

    .footer-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 24px;
      font-size: 13px;
      opacity: 0.65;
      flex-wrap: wrap;
      gap: 12px;
    }

    /* ============================================================
       REVEAL ANIMATION — SAMA SEPERTI STAFARRAHMAH.PHP
       ============================================================ */
    .reveal {
      opacity: 0;
      transform: translateY(18px);
      transition: opacity 0.7s ease, transform 0.7s ease;
    }

    .reveal.in { opacity: 1; transform: translateY(0); }

    .reveal-scale {
      opacity: 0;
      transform: scale(0.9);
      transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .reveal-scale.in { opacity: 1; transform: scale(1); }

    /* Header slide-down saat halaman load */
    .page-loaded header .nav-wrap { animation: slideDown 0.6s ease forwards; }

    @keyframes slideDown {
      0% { opacity: 0; transform: translateY(-20px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Hero slide-down saat load */
    .page-loaded .socmed-hero {
      animation: fadeDownHero 0.8s ease both;
    }
    @keyframes fadeDownHero {
      0% { opacity: 0; transform: translateY(-28px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Action bar fade in */
    .page-loaded .action-top {
      animation: fadeInAction 0.7s ease 0.15s both;
    }
    @keyframes fadeInAction {
      0% { opacity: 0; transform: translateY(10px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Filter bar fade in */
    .page-loaded .filter-bar {
      animation: fadeInFilter 0.7s ease 0.3s both;
    }
    @keyframes fadeInFilter {
      0% { opacity: 0; transform: translateY(10px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Staggered feed card entrance */
    .page-loaded .feed-grid .feed-card {
      animation: cardEntrance 0.75s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    .page-loaded .feed-grid .feed-card:nth-child(1) { animation-delay: 0.45s; }
    .page-loaded .feed-grid .feed-card:nth-child(2) { animation-delay: 0.60s; }
    .page-loaded .feed-grid .feed-card:nth-child(3) { animation-delay: 0.75s; }
    .page-loaded .feed-grid .feed-card:nth-child(4) { animation-delay: 0.90s; }
    .page-loaded .feed-grid .feed-card:nth-child(5) { animation-delay: 1.05s; }
    .page-loaded .feed-grid .feed-card:nth-child(6) { animation-delay: 1.20s; }

    @keyframes cardEntrance {
      0% {
        opacity: 0;
        transform: translateY(40px) scale(0.94);
      }
      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .reveal, .reveal-scale, .page-loaded header .nav-wrap,
      .page-loaded .socmed-hero, .page-loaded .action-top,
      .page-loaded .filter-bar, .page-loaded .feed-grid .feed-card {
        opacity: 1; 
        transform: none; 
        animation: none; 
        transition: none;
      }
      html { scroll-behavior: auto; }
      .nav-cta::before,
      .admin-panel .btn-primary::before,
      .admin-panel .btn-ghost::before { display: none; }
    }

    /* ---------- Editable Content (Admin) ---------- */
    [data-field][contenteditable="true"] {
      outline: 2px dashed var(--orange);
      outline-offset: 4px;
      border-radius: 6px;
      background: rgba(217,113,31,0.06);
      cursor: text;
    }
    [data-field][contenteditable="true"]:focus {
      outline: 2px solid var(--orange-deep);
      background: rgba(217,113,31,0.1);
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 980px) {
      .feed-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 680px) {
      .feed-grid { grid-template-columns: 1fr; }
      .hero-wrap { flex-direction: column; text-align: center; }
      .hero-content h1 { font-size: 32px; }
      .action-top { justify-content: center; }
      .admin-panel {
        bottom: 12px;
        right: 12px;
        padding: 10px 14px;
        flex-wrap: wrap;
        gap: 8px;
      }
      .admin-panel .btn { font-size: 12px; padding: 6px 12px; }
    }
  </style>
</head>
<body>

  <!-- ===== HEADER ===== -->
  <header id="mainHeader">
    <div class="nav-wrap">
      <a href="index.php#beranda" class="brand">
        <div class="brand-mark">
          <img src="logoyayasan.png" alt="Logo Yayasan Abdul Rahman Musafi">
        </div>
        <div class="brand-text">
          <div class="brand-name">Ar-Rahmah</div>
          <div class="brand-sub">Yayasan Sosial &amp; Pendidikan</div>
        </div>
      </a>
      <a href="index.php#kontak" class="nav-cta">Kontak Kami</a>
    </div>
  </header>

  <!-- ===== HERO BANNER SOSMED ===== -->
  <section class="socmed-hero">
    <div class="container">
      <div class="hero-wrap">
        <div class="hero-content">
          <span style="background: rgba(255,255,255,0.15); padding: 6px 16px; border-radius: 50px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Komunitas Digital</span>
          <h1 data-field="smTitle">Sosial Media Ar-Rahmah</h1>
          <p data-field="smSubtitle">Kunjungi dan ikuti akun resmi sosial media Yayasan Ar-Rahmah untuk mendapatkan pembaruan informasi, dokumentasi kegiatan, serta kajian keagamaan secara langsung.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== MAIN CONTENT ===== -->
  <main class="container">
    
    <!-- Tombol Kembali -->
    <div class="action-top">
      <a href="index.php#beranda" class="nav-cta">Kembali ke Beranda</a>
      <?php if ($isAdmin): ?>
        <a href="index.php#beranda" class="nav-cta nav-cta-green">Mode Admin Aktif</a>
        <button type="button" id="btnToggleEdit" class="nav-cta">Edit Konten Halaman</button>
        <button type="button" id="btnSaveContent" class="nav-cta nav-cta-green" style="display:none;">Simpan Perubahan</button>
      <?php endif; ?>
    </div>

    <!-- Filter Platform -->
    <div class="filter-bar">
      <button class="filter-btn active" onclick="filterFeed('all', this)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Semua Kanal
      </button>
      <button class="filter-btn" onclick="filterFeed('instagram', this)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
        Instagram
      </button>
      <button class="filter-btn" onclick="filterFeed('youtube', this)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/></svg>
        YouTube
      </button>
      <button class="filter-btn" onclick="filterFeed('facebook', this)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        Facebook
      </button>
    </div>

    <!-- Feed Cards Grid -->
    <div class="feed-grid" id="feedGrid">

      <!-- Post 1: Instagram Official (Klik Langsung ke IG) -->
      <div class="feed-card" data-platform="instagram">
        <div class="card-header">
          <div class="profile-info">
            <img src="instagram.png" class="profile-avatar" alt="Avatar">
            <div>
              <div class="profile-name" data-field="ig1Name">Instagram Official</div>
              <div class="post-date" data-field="ig1Handle">@arrahmah.official</div>
            </div>
          </div>
          <span class="platform-badge badge-ig">Instagram</span>
        </div>

        <!-- Klik Gambar Langsung ke Instagram Official -->
        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="feed-media">
          <img src="instagram.png" class="logo-only" alt="Logo instagram">
        </a>

        <div class="feed-content">
          <p class="feed-caption" data-field="ig1Caption">
            Ikuti akun Instagram resmi Yayasan Ar-Rahmah untuk melihat update harian, galeri santunan, dan dokumentasi kegiatan sekolah secara langsung.
            <br><span class="hashtags">#InstagramOfficial #YayasanArRahmah</span>
          </p>
        </div>

        <div class="card-footer">
          <!-- Tombol Buka Instagram -->
          <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="btn-direct">
            <span>Buka Instagram</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          </a>
        </div>
      </div>

      <!-- Post 2: YouTube Official -->
      <div class="feed-card" data-platform="youtube">
        <div class="card-header">
          <div class="profile-info">
            <img src="youtube.png" class="profile-avatar" alt="Avatar">
            <div>
              <div class="profile-name" data-field="yt1Name">YouTube Channel</div>
              <div class="post-date" data-field="yt1Handle">Ar-Rahmah TV</div>
            </div>
          </div>
          <span class="platform-badge badge-yt">YouTube</span>
        </div>

        <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer" class="feed-media">
          <img src="youtube.png" class="logo-only" alt="Logo YouTube">
        </a>

        <div class="feed-content">
          <p class="feed-caption" data-field="yt1Caption">
            Saksikan tayangan kajian rutin, profil lembaga, serta video dokumentasi lengkap acara Yayasan Ar-Rahmah melalui kanal YouTube resmi kami.
            <br><span class="hashtags">#YouTubeChannel #ArRahmahTV</span>
          </p>
        </div>

        <div class="card-footer">
          <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer" class="btn-direct">
            <span>Buka YouTube</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          </a>
        </div>
      </div>

      <!-- Post 3: Facebook Official -->
      <div class="feed-card" data-platform="facebook">
        <div class="card-header">
          <div class="profile-info">
            <img src="facebook.png" class="profile-avatar" alt="Avatar">
            <div>
              <div class="profile-name" data-field="fb1Name">Facebook Fanpage</div>
              <div class="post-date" data-field="fb1Handle">Yayasan Ar-Rahmah</div>
            </div>
          </div>
          <span class="platform-badge badge-fb">Facebook</span>
        </div>

        <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" class="feed-media">
          <img src="facebook.png" class="logo-only" alt="Logo Facebook">
        </a>

        <div class="feed-content">
          <p class="feed-caption" data-field="fb1Caption">
            Dapatkan pengumuman resmi, informasi penerimaan siswa baru, serta kabar berita terbaru mengenai Yayasan Ar-Rahmah via halaman Facebook.
            <br><span class="hashtags">#FacebookPage #InfoArRahmah</span>
          </p>
        </div>

        <div class="card-footer">
          <a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" class="btn-direct">
            <span>Buka Facebook</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          </a>
        </div>
      </div>

    </div>
  </main>

  <!-- ===== FLOATING ADMIN PANEL ===== -->
  <div class="admin-panel" id="adminPanel">
    <span style="font-size: 13px;"><strong>Mode Admin Active</strong></span>
    <button class="btn btn-ghost" id="logoutBtn">Logout</button>
  </div>

  <!-- ===== FOOTER ===== -->
  <footer>
    <div class="container">
      <div class="footer-content">
        <div class="footer-brand">
          <div class="brand-mark">
            <img src="logoyayasan.png" alt="Logo Yayasan Abdul Rahman Musafi">
          </div>
          <div class="brand-name">Ar-Rahmah</div>
        </div>
        <p class="desc">Lembaga pendidikan yang berkomitmen mencerdaskan generasi Desa Banjarsari melalui pendidikan yang berkualitas, terjangkau, dan berkelanjutan.</p>
      </div>
      <div class="footer-bottom">
        <span>© 2026 Yayasan Ar-Rahmah.</span>
        <span>Dibangun dengan ikhlas untuk kebaikan bersama.</span>
      </div>
    </div>
  </footer>

  <!-- ===== JAVASCRIPT ===== -->
  <script>
    // ============================================================
    // 1. Cek Status Admin dari Session PHP via AJAX
    // ============================================================
    let isAdmin = false;

    async function checkAdminStatus() {
      try {
        const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?check_admin=1');
        const data = await response.json();
        isAdmin = data.isAdmin;
        updateAdminUI();
      } catch (err) {
        console.error('Error checking admin status:', err);
        <?php if ($isAdmin): ?>
          isAdmin = true;
        <?php else: ?>
          isAdmin = false;
        <?php endif; ?>
        updateAdminUI();
      }
    }

    // ============================================================
    // 2. Update Admin UI
    // ============================================================
    function updateAdminUI() {
      const adminPanel = document.getElementById('adminPanel');
      if (adminPanel) {
        if (isAdmin) {
          adminPanel.classList.add('active');
        } else {
          adminPanel.classList.remove('active');
        }
      }
    }

    // ============================================================
    // 3. Logout via AJAX
    // ============================================================
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        try {
          const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=logout'
          });
          const result = await response.json();
          if (result.success) {
            isAdmin = false;
            updateAdminUI();
            window.location.href = 'index.php';
          }
        } catch (err) {
          console.error('Logout error:', err);
          window.location.href = 'index.php';
        }
      });
    }

    // ============================================================
    // 4. Header Scroll Effect
    // ============================================================
    const header = document.getElementById('mainHeader');
    window.addEventListener('scroll', function() {
      if (window.scrollY > 40) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });

    // ============================================================
    // 5. Animasi Masuk Saat Halaman Selesai Dimuat
    // ============================================================
    window.addEventListener('load', function() {
      document.body.classList.add('page-loaded');
      checkAdminStatus();
    });

    // ============================================================
    // 6. Filter Platform Feed
    // ============================================================
    function filterFeed(platform, btn) {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const cards = document.querySelectorAll('.feed-card');
      cards.forEach(card => {
        if (platform === 'all' || card.getAttribute('data-platform') === platform) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // ============================================================
    // 7. Edit Konten Halaman (Admin) - tersimpan di localStorage
    // ============================================================
    const SM_STORAGE_KEY = 'ar_sosmed';
    const editableFields = document.querySelectorAll('[data-field]');

    function loadSosmedContent() {
      let saved = null;
      try {
        saved = JSON.parse(localStorage.getItem(SM_STORAGE_KEY));
      } catch (e) {
        saved = null;
      }
      if (!saved) return;

      editableFields.forEach(el => {
        const key = el.dataset.field;
        if (Object.prototype.hasOwnProperty.call(saved, key)) {
          el.innerHTML = saved[key];
        }
      });
    }

    function collectSosmedContent() {
      const data = {};
      editableFields.forEach(el => {
        data[el.dataset.field] = el.innerHTML.trim();
      });
      return data;
    }

    function setEditMode(active) {
      editableFields.forEach(el => {
        el.setAttribute('contenteditable', active ? 'true' : 'false');
      });
    }

    const btnToggleEdit = document.getElementById('btnToggleEdit');
    const btnSaveContent = document.getElementById('btnSaveContent');
    let editMode = false;

    if (btnToggleEdit && btnSaveContent) {
      btnToggleEdit.addEventListener('click', function() {
        editMode = !editMode;
        setEditMode(editMode);
        btnToggleEdit.textContent = editMode ? 'Batalkan Edit' : 'Edit Konten Halaman';
        btnSaveContent.style.display = editMode ? 'inline-block' : 'none';
        if (!editMode) {
          loadSosmedContent();
        }
      });

      btnSaveContent.addEventListener('click', function() {
        const data = collectSosmedContent();
        try {
          localStorage.setItem(SM_STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
          console.error('Gagal menyimpan konten:', e);
        }
        editMode = false;
        setEditMode(false);
        btnToggleEdit.textContent = 'Edit Konten Halaman';
        btnSaveContent.style.display = 'none';
        alert('Perubahan konten sosial media berhasil disimpan.');
      });
    }

    loadSosmedContent();
  </script>
</body>
</html>