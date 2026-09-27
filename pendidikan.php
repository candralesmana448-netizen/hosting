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
  <title>Program Pendidikan - Yayasan Ar-Rahmah</title>
  
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
      --shadow: 0 14px 34px -18px rgba(43,33,24,0.35);
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
      letter-spacing: 0.2px;
    }

    a { text-decoration: none; color: inherit; }
    img { max-width: 100%; display: block; }

    .container {
      max-width: 1160px;
      margin: 0 auto;
      padding: 0 28px;
    }

    .eyebrow {
      text-transform: uppercase;
      letter-spacing: 2.2px;
      font-size: 13px;
      font-weight: 700;
      color: var(--orange-deep);
      display: inline-flex;
      align-items: center;
      gap: 10px;
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

    header .brand-text .brand-name { color: var(--white); }
    header .brand-text .brand-sub   { color: rgba(255,255,255,0.72); }
    header .brand-mark              { background: rgba(255,255,255,0.14); }

    header .menu-toggle {
      border-color: rgba(255,255,255,0.3);
      background: rgba(255,255,255,0.1);
    }
    header .menu-toggle span,
    header .menu-toggle span::before,
    header .menu-toggle span::after {
      background: var(--white);
    }

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

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-mark {
      width: 45px; 
      height: 45px;
      display: flex; 
      align-items: center; 
      justify-content: center;
      background: transparent;
      border-radius: 8px;
      overflow: hidden;
      transition: background 0.4s ease;
    }

    .brand-mark img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .brand-text .brand-sub {
      font-size: 11.5px;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      transition: color 0.4s ease;
    }

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

    .menu-toggle {
      display: none;
      width: 40px; 
      height: 40px;
      border: 1px solid var(--border);
      background: var(--white);
      border-radius: 10px;
      align-items: center; 
      justify-content: center;
      cursor: pointer;
      transition: border-color 0.3s ease, background 0.3s ease;
    }

    .menu-toggle span, 
    .menu-toggle span::before, 
    .menu-toggle span::after {
      content: ''; 
      display: block; 
      width: 18px; 
      height: 2px; 
      background: var(--ink); 
      position: relative; 
      transition: .2s, background 0.3s ease;
    }

    .menu-toggle span::before { position: absolute; top: -6px; }
    .menu-toggle span::after { position: absolute; top: 6px; }

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

    /* ---------- Section shared ---------- */
    section { padding: 80px 0; }

    .section-head {
      text-align: center;
      max-width: 680px;
      margin: 0 auto 36px;
    }

    .section-head h1 {
      font-size: 42px;
      color: var(--green);
      margin: 14px 0 12px;
    }

    .section-head p { color: var(--ink-soft); font-size: 16.5px; }

    .action-bar {
      margin-bottom: 24px;
      display: flex;
      justify-content: flex-start;
      gap: 14px;
      flex-wrap: wrap;
    }

    /* ---------- School Cards Grid & Accordion Animation ---------- */
    .school-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
      align-items: start;
    }

    .school-card {
      background: var(--white);
      border-radius: 18px;
      border: 1px solid var(--border);
      overflow: hidden;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .school-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px -18px rgba(43,33,24,0.4);
    }

    .school-img {
      height: 200px;
      background: #ffffff;
      overflow: hidden;
      box-sizing: border-box;
      padding: 16px;
      border-bottom: 1px solid var(--border);
    }

    .school-img img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .school-body {
      padding: 26px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .school-body h3 {
      font-family: 'Amiri', serif;
      color: var(--green);
      font-size: 26px;
      margin: 0 0 10px 0;
    }

    .school-body p {
      color: var(--ink-soft);
      font-size: 14.5px;
      line-height: 1.6;
      margin: 0;
    }

    /* Animation Smooth Accordion Expand */
    .school-detail {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), 
                  opacity 0.4s ease, 
                  margin-top 0.3s ease, 
                  padding-top 0.3s ease;
    }

    .school-card.active .school-detail {
      max-height: 600px;
      opacity: 1;
      margin-top: 18px;
      padding-top: 18px;
      border-top: 1px dashed var(--border);
    }

    .detail-section-title {
      font-size: 12.5px;
      font-weight: 700;
      color: var(--orange-deep);
      text-transform: uppercase;
      letter-spacing: 0.6px;
      margin-top: 12px;
      margin-bottom: 6px;
    }

    .detail-list {
      margin: 0 0 12px 0;
      padding-left: 18px;
      color: var(--ink-soft);
      font-size: 13.5px;
      line-height: 1.5;
    }

    .detail-list li { margin-bottom: 4px; }

    .btn-detail {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      background: var(--orange);
      color: var(--white);
      padding: 12px 20px;
      border-radius: 100px;
      font-weight: 700;
      font-size: 14.5px;
      margin-top: 22px;
      border: none;
      cursor: pointer;
      font-family: inherit;
      box-shadow: 0 8px 18px -8px rgba(217,113,31,0.5);
      transition: background 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }

    .btn-detail:hover {
      background: var(--orange-deep);
      transform: translateY(-2px);
    }

    .btn-detail:active {
      transform: translateY(0);
    }

    .btn-detail svg {
      transition: transform 0.3s ease;
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

    /* Section-head slide-down */
    .page-loaded .section-head {
      animation: fadeDownSection 0.8s ease both;
    }
    @keyframes fadeDownSection {
      0% { opacity: 0; transform: translateY(-24px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Action bar fade in */
    .page-loaded .action-bar {
      animation: fadeInAction 0.7s ease 0.15s both;
    }
    @keyframes fadeInAction {
      0% { opacity: 0; transform: translateY(10px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    /* Staggered card entrance */
    .page-loaded .school-grid .school-card {
      animation: cardEntrance 0.75s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    .page-loaded .school-grid .school-card:nth-child(1) { animation-delay: 0.30s; }
    .page-loaded .school-grid .school-card:nth-child(2) { animation-delay: 0.45s; }
    .page-loaded .school-grid .school-card:nth-child(3) { animation-delay: 0.60s; }
    .page-loaded .school-grid .school-card:nth-child(4) { animation-delay: 0.75s; }
    .page-loaded .school-grid .school-card:nth-child(5) { animation-delay: 0.90s; }
    .page-loaded .school-grid .school-card:nth-child(6) { animation-delay: 1.05s; }

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
      .page-loaded .section-head, .page-loaded .action-bar,
      .page-loaded .school-grid .school-card {
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
      .school-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 720px) {
      .menu-toggle { display: flex; }
      .section-head h1 { font-size: 32px; }
      .school-grid { grid-template-columns: 1fr; }
      section { padding: 50px 0; }
      .action-bar { justify-content: center; }
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
      <button class="menu-toggle" id="menuToggle" aria-label="Buka menu"><span></span></button>
    </div>
  </header>

  <!-- ===== PROGRAM PENDIDIKAN SECTION ===== -->
  <section>
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Lembaga Pendidikan</span>
        <h1 data-field="pdTitle">Pendidikan Yayasan Ar-Rahmah</h1>
        <p data-field="pdSubtitle">Membina generasi Islam yang cerdas, berakhlak mulia, dan siap menghadapi masa depan melalui pendidikan holistik berkarakter.</p>
      </div>

      <!-- Tombol Kembali ke Beranda & tombol admin -->
      <div class="action-bar reveal">
        <a href="index.php#beranda" class="nav-cta">Kembali ke Beranda</a>
        <?php if ($isAdmin): ?>
          <a href="index.php#beranda" class="nav-cta nav-cta-green">Mode Admin Aktif</a>
          <button type="button" id="btnToggleEdit" class="nav-cta">Edit Konten Halaman</button>
          <button type="button" id="btnSaveContent" class="nav-cta nav-cta-green" style="display:none;">Simpan Perubahan</button>
        <?php endif; ?>
      </div>

      <div class="school-grid">
        <!-- 1. TK Ar-Rahmah -->
        <div class="school-card reveal-scale" data-school="TK">
          <div class="school-img">
            <img src="tk.png" alt="Logo TK Ar-Rahmah">
          </div>
          <div class="school-body">
            <h3 data-field="tkTitle">TK Ar-Rahmah</h3>
            <p data-field="tkDesc">Membentuk karakter awal anak melalui pendidikan usia dini berbasis keislaman, kreativitas, dan pembelajaran yang menyenangkan.</p>
            
            <div class="school-detail">
              <div class="detail-section-title" data-field="tkProgramLabel">Program Unggulan:</div>
              <ul class="detail-list" data-field="tkProgram">
                <li>Tahfidz Cilik (Hafalan Juz Amma)</li>
                <li>Pembentukan Karakter &amp; Adab Islami</li>
                <li>Fun Learning &amp; Cooking Class</li>
              </ul>
              <div class="detail-section-title" data-field="tkFasilitasLabel">Fasilitas &amp; Layanan:</div>
              <ul class="detail-list" data-field="tkFasilitas">
                <li>Ruang Kelas Nyaman &amp; Ber-AC</li>
                <li>Taman Bermain Outdoor &amp; Indoor</li>
                <li>Area Media Kreatif &amp; Seni</li>
              </ul>
            </div>

            <button class="btn-detail" onclick="toggleDetail(this, 'TK')">
              <span>Lihat Profil TK</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
          </div>
        </div>

        <!-- 2. SMP Ar-Rahmah -->
        <div class="school-card reveal-scale" data-school="SMP">
          <div class="school-img">
            <img src="smp.png" alt="Logo SMP Ar-Rahmah">
          </div>
          <div class="school-body">
            <h3 data-field="smpTitle">SMP Ar-Rahmah</h3>
            <p data-field="smpDesc">Pendidikan tingkat pertama yang memadukan kurikulum nasional dan tahfidz Al-Qur'an serta pengembangan potensi akademik dan ekstrakurikuler.</p>
            
            <div class="school-detail">
              <div class="detail-section-title" data-field="smpProgramLabel">Program Unggulan:</div>
              <ul class="detail-list" data-field="smpProgram">
                <li>Target Hafalan 3 - 5 Juz Al-Qur'an</li>
                <li>Kelas Ekstrakurikuler IT &amp; Coding</li>
                <li>Bimbingan Intensive Sains &amp; Matematika</li>
              </ul>
              <div class="detail-section-title" data-field="smpFasilitasLabel">Fasilitas Utama:</div>
              <ul class="detail-list" data-field="smpFasilitas">
                <li>Laboratorium Komputer &amp; IPA</li>
                <li>Perpustakaan Digital</li>
                <li>Lapangan Olahraga Serbaguna</li>
              </ul>
            </div>

            <button class="btn-detail" onclick="toggleDetail(this, 'SMP')">
              <span>Lihat Profil SMP</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
          </div>
        </div>

        <!-- 3. SMK Ar-Rahmah -->
        <div class="school-card reveal-scale" data-school="SMK">
          <div class="school-img">
            <img src="smk.png" alt="Logo SMK Ar-Rahmah">
          </div>
          <div class="school-body">
            <h3 data-field="smkTitle">SMK Ar-Rahmah</h3>
            <p data-field="smkDesc">Pendidikan kejuruan menengah yang membekali siswa dengan keahlian praktis, siap kerja, dan berjiwa kewirausahaan yang berlandaskan imtaq.</p>
            
            <div class="school-detail">
              <div class="detail-section-title" data-field="smkJurusanLabel">Jurusan / Keahlian:</div>
              <ul class="detail-list" data-field="smkJurusan">
                <li>Teknik Komputer &amp; Jaringan (TKJ)</li>
                <li>Rekayasa Perangkat Lunak (RPL)</li>
              </ul>
              <div class="detail-section-title" data-field="smkProgramLabel">Program &amp; Fasilitas:</div>
              <ul class="detail-list" data-field="smkProgram">
                <li>Praktek Kerja Lapangan (PKL) Industri</li>
                <li>Uji Kompetensi &amp; Sertifikasi Keahlian</li>
                <li>Lab Bengkel Komputer &amp; Software</li>
              </ul>
            </div>

            <button class="btn-detail" onclick="toggleDetail(this, 'SMK')">
              <span>Lihat Profil SMK</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
          </div>
        </div>

      </div>
    </div>
  </section>

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
      if (window.scrollY > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });

    // ============================================================
    // 5. Animasi Masuk Saat Halaman Selesai Dimuat
    //    + Reveal Animation (Intersection Observer)
    // ============================================================
    window.addEventListener('load', function() {
      document.body.classList.add('page-loaded');
      checkAdminStatus();

      // Reveal animation on scroll
      const revealEls = document.querySelectorAll('.reveal, .reveal-scale');
      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            e.target.classList.add('in');
          }
        });
      }, { threshold: 0.12 });
      revealEls.forEach(el => io.observe(el));
    });

    // ============================================================
    // 6. Accordion Melebar dan Menutup Detail Sekolah
    // ============================================================
    function toggleDetail(btn, unit) {
      const card = btn.closest('.school-card');
      const isExpanded = card.classList.contains('active');
      const textSpan = btn.querySelector('span');
      const iconSvg = btn.querySelector('svg');
      
      if (isExpanded) {
        card.classList.remove('active');
        textSpan.innerText = `Lihat Profil ${unit}`;
        if (iconSvg) iconSvg.style.transform = 'rotate(0deg)';
      } else {
        document.querySelectorAll('.school-card.active').forEach(c => {
          if (c !== card) {
            c.classList.remove('active');
            const otherBtn = c.querySelector('.btn-detail');
            if (otherBtn) {
              const otherSpan = otherBtn.querySelector('span');
              const otherIcon = otherBtn.querySelector('svg');
              if (otherSpan) {
                const schoolName = c.dataset.school || 'Sekolah';
                otherSpan.innerText = `Lihat Profil ${schoolName}`;
              }
              if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
            }
          }
        });
        
        card.classList.add('active');
        textSpan.innerText = 'Tutup Profil';
        if (iconSvg) iconSvg.style.transform = 'rotate(180deg)';
      }
    }

    // ============================================================
    // 7. Edit Konten Halaman (Admin) - tersimpan di localStorage
    // ============================================================
    const PD_STORAGE_KEY = 'ar_pendidikan';
    const editableFields = document.querySelectorAll('[data-field]');

    function loadPendidikanContent() {
      let saved = null;
      try {
        saved = JSON.parse(localStorage.getItem(PD_STORAGE_KEY));
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

    function collectPendidikanContent() {
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
          loadPendidikanContent();
        }
      });

      btnSaveContent.addEventListener('click', function() {
        const data = collectPendidikanContent();
        try {
          localStorage.setItem(PD_STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
          console.error('Gagal menyimpan konten:', e);
        }
        editMode = false;
        setEditMode(false);
        btnToggleEdit.textContent = 'Edit Konten Halaman';
        btnSaveContent.style.display = 'none';
        alert('Perubahan konten pendidikan berhasil disimpan.');
      });
    }

    loadPendidikanContent();

    // ============================================================
    // 8. Mobile Menu Toggle
    // ============================================================
    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
      menuToggle.addEventListener('click', function() {
        const nav = document.querySelector('nav');
        if (nav) {
          nav.classList.toggle('open');
        }
      });
    }
  </script>
</body>
</html>