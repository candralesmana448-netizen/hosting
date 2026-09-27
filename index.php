<?php
session_start();

// Default data
$defaultStats = ['siswa' => 135, 'tk' => 70, 'smp' => 65];
$defaultNews = [
    [
        'tag' => 'Sosial',
        'date' => '12 Juli 2026',
        'title' => 'Pembagian 500 Paket Sembako untuk Warga Terdampak',
        'desc' => 'Yayasan menyalurkan bantuan pangan kepada keluarga prasejahtera di lima desa binaan.',
        'img' => 'https://images.unsplash.com/photo-1593113598332-cd288d649433?q=80&w=700&auto=format&fit=crop'
    ],
    [
        'tag' => 'Pendidikan',
        'date' => '28 Juni 2026',
        'title' => 'Penyerahan Beasiswa kepada 86 Pelajar Berprestasi',
        'desc' => 'Program beasiswa tahun ini menjangkau lebih banyak pelajar dari keluarga kurang mampu.',
        'img' => 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?q=80&w=700&auto=format&fit=crop'
    ],
    [
        'tag' => 'Kesehatan',
        'date' => '15 Juni 2026',
        'title' => 'Layanan Kesehatan Gratis untuk Lansia & Anak',
        'desc' => 'Bekerja sama dengan tenaga medis relawan, kami membuka layanan pemeriksaan gratis.',
        'img' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=700&auto=format&fit=crop'
    ]
];

// Inisialisasi session untuk admin
$isAdmin = isset($_SESSION['ar_is_admin']) && $_SESSION['ar_is_admin'] === true;

require 'config.php';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['ar_is_admin'] = true;
            $_SESSION['ar_admin_id'] = $admin['id'];
            $isAdmin = true;

            $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")
                ->execute([$admin['id']]);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Username atau password salah!']);
        }
        exit;
    }

    if ($_POST['action'] === 'logout') {
        session_destroy();
        $isAdmin = false;
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'upload_image') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'error' => 'Anda harus login sebagai admin.']);
            exit;
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Tidak ada file yang terunggah.']);
            exit;
        }

        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Format file harus JPG, PNG, WEBP, atau GIF.']);
            exit;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Ukuran gambar maksimal 5MB.']);
            exit;
        }

        $uploadDir = __DIR__ . '/uploads/berita/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'berita_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $publicUrl = 'uploads/berita/' . $filename;
            echo json_encode(['success' => true, 'url' => $publicUrl]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Gagal menyimpan gambar ke server.']);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_image') {
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'error' => 'Anda harus login sebagai admin.']);
            exit;
        }

        $imageUrl = trim($_POST['image_url'] ?? '');
        if ($imageUrl === '') {
            echo json_encode(['success' => false, 'error' => 'URL gambar kosong.']);
            exit;
        }

        $prefix = 'uploads/berita/';
        if (strpos($imageUrl, $prefix) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Hanya gambar di folder uploads/berita/ yang dapat dihapus.']);
            exit;
        }

        $relative = substr($imageUrl, strlen($prefix));

        if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $relative)) {
            echo json_encode(['success' => false, 'error' => 'Nama file tidak valid.']);
            exit;
        }

        $relative = basename($relative);
        $fullPath = __DIR__ . '/' . $prefix . $relative;

        if (!file_exists($fullPath)) {
            echo json_encode(['success' => true, 'message' => 'File sudah tidak ada.']);
            exit;
        }

        if (!is_file($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'Bukan file.']);
            exit;
        }

        if (@unlink($fullPath)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Gagal menghapus file. Cek permission folder uploads/berita/.']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Yayasan Ar-Rahmah</title>
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
      position: relative;
      overflow-x: hidden;
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
      position: relative;
      z-index: 2;
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

    /* ---------- Particle Background + Parallax ---------- */
    #particleCanvas {
      position: fixed;
      inset: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
      display: block;
      background: transparent;
      will-change: transform;
    }

    header, section, .stats, footer, .admin-panel, .drawer, .modal-overlay {
      position: relative;
      z-index: 1;
    }

    #profil, #berita, #kontak {
      background: rgba(255,255,255,0.88);
      backdrop-filter: blur(2px);
    }

    #berita { background: rgba(253,249,243,0.9); position: relative; overflow: hidden; }

    .hero { background: transparent; }

    .stats { background: var(--green); }

    /* ============================================================
       BACKGROUND DECOR UNTUK SECTION BERITA — LEBIH RAME
       ============================================================ */
    .news-bg-decor {
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
      opacity: 0.75;
    }

    /* --- Blob gradient besar (6 buah) --- */
    .news-bg-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(70px);
      will-change: transform, opacity;
    }

    .news-bg-blob.blob-1 {
      width: 480px; height: 480px;
      top: -160px; left: -140px;
      background: radial-gradient(circle at 30% 30%, rgba(217,113,31,0.55), rgba(217,113,31,0) 70%);
      animation: blobFloat1 22s ease-in-out infinite;
    }

    .news-bg-blob.blob-2 {
      width: 520px; height: 520px;
      bottom: -200px; right: -160px;
      background: radial-gradient(circle at 60% 40%, rgba(31,92,63,0.5), rgba(31,92,63,0) 70%);
      animation: blobFloat2 26s ease-in-out infinite;
    }

    .news-bg-blob.blob-3 {
      width: 360px; height: 360px;
      top: 40%; left: 45%;
      background: radial-gradient(circle at 50% 50%, rgba(243,161,92,0.4), rgba(243,161,92,0) 70%);
      animation: blobFloat3 30s ease-in-out infinite;
    }

    .news-bg-blob.blob-4 {
      width: 300px; height: 300px;
      top: 8%; right: 8%;
      background: radial-gradient(circle at 50% 50%, rgba(127,166,92,0.45), rgba(127,166,92,0) 70%);
      animation: blobOrbit 24s linear infinite;
    }

    .news-bg-blob.blob-5 {
      width: 260px; height: 260px;
      bottom: 12%; left: 6%;
      background: radial-gradient(circle at 50% 50%, rgba(184,90,18,0.4), rgba(184,90,18,0) 70%);
      animation: blobOrbit 32s linear infinite reverse;
    }

    .news-bg-blob.blob-6 {
      width: 340px; height: 340px;
      top: 20%; left: 30%;
      background: radial-gradient(circle at 40% 60%, rgba(217,113,31,0.35), rgba(217,113,31,0) 70%);
      animation: blobFloat1 28s ease-in-out infinite reverse;
    }

    @keyframes blobFloat1 {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33%      { transform: translate(60px, 40px) scale(1.08); }
      66%      { transform: translate(20px, -30px) scale(0.95); }
    }

    @keyframes blobFloat2 {
      0%, 100% { transform: translate(0, 0) scale(1); }
      50%      { transform: translate(-70px, -50px) scale(1.1); }
    }

    @keyframes blobFloat3 {
      0%, 100% { transform: translate(0, 0) scale(1); }
      25%      { transform: translate(-40px, 30px) scale(1.06); }
      75%      { transform: translate(50px, -20px) scale(0.94); }
    }

    @keyframes blobOrbit {
      0%   { transform: rotate(0deg) translateX(40px) rotate(0deg); }
      100% { transform: rotate(360deg) translateX(40px) rotate(-360deg); }
    }

    /* --- Partikel titik kecil (12 buah) --- */
    .news-bg-particle {
      position: absolute;
      border-radius: 50%;
      opacity: 0.8;
      will-change: transform, opacity;
    }

    .news-bg-particle.p1 {
      width: 8px; height: 8px;
      top: 18%; left: 8%;
      background: var(--orange);
      animation: particleFloat 9s ease-in-out infinite;
    }
    .news-bg-particle.p2 {
      width: 6px; height: 6px;
      top: 62%; left: 14%;
      background: var(--green);
      animation: particleFloat 11s ease-in-out infinite;
      animation-delay: -3s;
    }
    .news-bg-particle.p3 {
      width: 10px; height: 10px;
      top: 30%; right: 10%;
      background: var(--orange-light);
      animation: particleFloat 10s ease-in-out infinite;
      animation-delay: -5s;
    }
    .news-bg-particle.p4 {
      width: 7px; height: 7px;
      bottom: 22%; right: 18%;
      background: var(--green-light);
      animation: particleFloat 12s ease-in-out infinite;
      animation-delay: -7s;
    }
    .news-bg-particle.p5 {
      width: 5px; height: 5px;
      top: 50%; left: 48%;
      background: var(--orange-deep);
      animation: particleFloat 8s ease-in-out infinite;
      animation-delay: -2s;
    }
    .news-bg-particle.p6 {
      width: 9px; height: 9px;
      top: 78%; left: 60%;
      background: var(--green);
      animation: particleFloat 13s ease-in-out infinite;
      animation-delay: -9s;
    }
    .news-bg-particle.p7 {
      width: 6px; height: 6px;
      top: 12%; left: 40%;
      background: var(--orange-deep);
      animation: particleFloat 10.5s ease-in-out infinite;
      animation-delay: -1.5s;
    }
    .news-bg-particle.p8 {
      width: 8px; height: 8px;
      bottom: 14%; left: 32%;
      background: var(--orange-light);
      animation: particleFloat 9.5s ease-in-out infinite;
      animation-delay: -4s;
    }
    .news-bg-particle.p9 {
      width: 5px; height: 5px;
      top: 40%; left: 22%;
      background: var(--green-light);
      animation: particleFloat 11.5s ease-in-out infinite;
      animation-delay: -6s;
    }
    .news-bg-particle.p10 {
      width: 7px; height: 7px;
      top: 70%; right: 30%;
      background: var(--orange);
      animation: particleFloat 12.5s ease-in-out infinite;
      animation-delay: -8s;
    }
    .news-bg-particle.p11 {
      width: 6px; height: 6px;
      top: 8%; right: 26%;
      background: var(--green);
      animation: particleFloat 10s ease-in-out infinite;
      animation-delay: -11s;
    }
    .news-bg-particle.p12 {
      width: 4px; height: 4px;
      bottom: 40%; right: 6%;
      background: var(--orange-deep);
      animation: particleFloat 9s ease-in-out infinite;
      animation-delay: -3.5s;
    }

    @keyframes particleFloat {
      0%, 100% { transform: translate(0, 0) scale(1);     opacity: 0.55; }
      25%      { transform: translate(18px, -24px) scale(1.2); opacity: 0.9; }
      50%      { transform: translate(-12px, 20px) scale(0.85); opacity: 0.4; }
      75%      { transform: translate(22px, 12px) scale(1.05); opacity: 0.8; }
    }

    /* --- Partikel ring (4 buah) — outline bulat yang berputar --- */
    .news-bg-ring {
      position: absolute;
      border-radius: 50%;
      border: 2px solid;
      opacity: 0.35;
      will-change: transform, opacity;
    }

    .news-bg-ring.r1 {
      width: 26px; height: 26px;
      top: 22%; left: 18%;
      border-color: var(--orange);
      animation: ringPulse 7s ease-in-out infinite;
    }
    .news-bg-ring.r2 {
      width: 18px; height: 18px;
      top: 60%; right: 24%;
      border-color: var(--green);
      animation: ringPulse 8s ease-in-out infinite;
      animation-delay: -2s;
    }
    .news-bg-ring.r3 {
      width: 34px; height: 34px;
      bottom: 18%; left: 44%;
      border-color: var(--orange-light);
      animation: ringPulse 9s ease-in-out infinite;
      animation-delay: -4s;
    }
    .news-bg-ring.r4 {
      width: 22px; height: 22px;
      top: 36%; right: 40%;
      border-color: var(--green-light);
      animation: ringPulse 6.5s ease-in-out infinite;
      animation-delay: -5s;
    }

    @keyframes ringPulse {
      0%, 100% { transform: scale(1) rotate(0deg); opacity: 0.35; }
      50%      { transform: scale(1.3) rotate(180deg); opacity: 0.7; }
    }

    /* --- Partikel cross (2 buah) --- */
    .news-bg-cross {
      position: absolute;
      width: 16px; height: 16px;
      opacity: 0.6;
      will-change: transform, opacity;
    }

    .news-bg-cross::before,
    .news-bg-cross::after {
      content: '';
      position: absolute;
      background: var(--orange);
      border-radius: 2px;
    }
    .news-bg-cross::before {
      width: 100%; height: 2px;
      top: 50%; left: 0;
      transform: translateY(-50%);
    }
    .news-bg-cross::after {
      width: 2px; height: 100%;
      left: 50%; top: 0;
      transform: translateX(-50%);
    }

    .news-bg-cross.c1 {
      top: 46%; left: 12%;
      animation: crossSpin 14s linear infinite;
    }
    .news-bg-cross.c2 {
      bottom: 26%; right: 12%;
      animation: crossSpin 18s linear infinite reverse;
    }
    .news-bg-cross.c2::before,
    .news-bg-cross.c2::after {
      background: var(--green);
    }

    @keyframes crossSpin {
      0%   { transform: rotate(0deg) scale(1); opacity: 0.5; }
      50%  { transform: rotate(180deg) scale(1.2); opacity: 0.9; }
      100% { transform: rotate(360deg) scale(1); opacity: 0.5; }
    }

    /* --- Garis diagonal dekoratif (2 buah) --- */
    .news-bg-line {
      position: absolute;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--orange), transparent);
      opacity: 0.35;
      will-change: transform, opacity;
    }

    .news-bg-line.l1 {
      width: 180px;
      top: 14%; left: 20%;
      transform: rotate(-20deg);
      animation: lineSlide 12s ease-in-out infinite;
    }
    .news-bg-line.l2 {
      width: 140px;
      bottom: 30%; right: 15%;
      transform: rotate(15deg);
      background: linear-gradient(90deg, transparent, var(--green), transparent);
      animation: lineSlide 15s ease-in-out infinite reverse;
    }

    @keyframes lineSlide {
      0%, 100% { transform: translateX(0) rotate(var(--r, -20deg)); opacity: 0.2; }
      50%      { transform: translateX(40px) rotate(var(--r, -20deg)); opacity: 0.6; }
    }

    .news-bg-line.l1 { --r: -20deg; }
    .news-bg-line.l2 { --r: 15deg; }

    /* Pastikan container & carousel tetap di atas dekor */
    #berita .container { position: relative; z-index: 2; }

    /* ---------- Header: hijau SOLID ---------- */
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

    header nav a {
      color: rgba(255,255,255,0.85);
    }
    header nav a:hover,
    header nav a.active {
      background: rgba(255,255,255,0.18);
      color: var(--white);
    }

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

    .brand { display: flex; align-items: center; gap: 12px; }

    .brand-mark {
      width: 45px; height: 45px;
      display: flex; align-items: center; justify-content: center;
      background: transparent;
      border-radius: 8px;
      overflow: hidden;
      transition: background 0.4s ease;
    }

    .brand-mark img { width: 100%; height: 100%; object-fit: contain; }

    .brand-text .brand-sub {
      font-size: 11.5px;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      transition: color 0.4s ease;
    }

    nav ul { list-style: none; display: flex; gap: 6px; margin: 0; padding: 0; }

    nav a {
      padding: 10px 16px;
      font-weight: 600;
      font-size: 15px;
      border-radius: 100px;
      transition: background .25s ease, color .25s ease;
      position: relative;
    }

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

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 15px 28px;
      border-radius: 100px;
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      border: none;
      transition: transform .2s, box-shadow .2s, background .2s, color .2s;
    }

    .btn-primary {
      background: var(--orange);
      color: var(--white);
      box-shadow: 0 12px 24px -10px rgba(217,113,31,0.55);
      position: relative;
      overflow: hidden;
      isolation: isolate;
      z-index: 1;
    }

    .btn-primary::before {
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

    .btn-primary:hover::before,
    .btn-primary:focus-visible::before {
      width: 100%;
    }

    .btn-primary:hover {
      background: var(--orange);
      transform: translateY(-2px);
    }

    .btn-ghost {
      background: var(--white);
      color: var(--green);
      border: 1.5px solid var(--green);
      position: relative;
      overflow: hidden;
      isolation: isolate;
      z-index: 1;
    }

    .btn-ghost::before {
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

    .btn-ghost:hover::before,
    .btn-ghost:focus-visible::before {
      width: 100%;
    }

    .btn-ghost:hover {
      background: var(--white);
    }

    .menu-toggle {
      display: none;
      width: 40px; height: 40px;
      border: 1px solid var(--border);
      background: var(--white);
      border-radius: 10px;
      align-items: center; justify-content: center;
      cursor: pointer;
      transition: border-color 0.3s ease, background 0.3s ease;
    }

    .menu-toggle span, .menu-toggle span::before, .menu-toggle span::after {
      content: '';
      display: block;
      width: 18px; height: 2px;
      position: relative;
      transition: .2s, background 0.3s ease;
    }

    .menu-toggle span::before { position: absolute; top: -6px; }
    .menu-toggle span::after { position: absolute; top: 6px; }

    .hero { position: relative; overflow: hidden; padding: 96px 0 110px; }

    .hero-grid {
      display: grid;
      grid-template-columns: 1.05fr 0.95fr;
      gap: 56px;
      align-items: center;
      position: relative;
    }

    .hero h1 { font-size: 52px; line-height: 1.16; color: var(--green); margin: 18px 0 22px; }
    .hero h1 em { font-style: normal; color: var(--orange); }
    .hero p { font-size: 17px; color: var(--ink-soft); max-width: 480px; margin-bottom: 34px; }

    .btn-row { display: flex; gap: 14px; flex-wrap: wrap; }

    .hero-art { position: relative; aspect-ratio: 1/1; display: flex; align-items: center; justify-content: center; }

    .hero-art .ring {
      position: absolute;
      inset: 0;
      border-radius: 50%;
      background: conic-gradient(from 90deg, var(--green), var(--green-light), var(--orange-light), var(--orange), var(--green));
      opacity: 0.14;
      animation: spin 40s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    .hero-art .photo {
      position: relative;
      width: 78%;
      aspect-ratio: 1/1;
      border-radius: 50%;
      overflow: hidden;
      border: 6px solid var(--white);
      box-shadow: var(--shadow);
    }

    .hero-art .photo img { width: 100%; height: 100%; object-fit: cover; }

    .hero-art .badge {
      position: absolute;
      bottom: 6%;
      left: 0;
      background: var(--white);
      border-radius: 16px;
      padding: 14px 18px;
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .hero-art .badge .num {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 30px;
      font-weight: 800;
      color: var(--orange);
      letter-spacing: -1px;
      line-height: 1;
    }
    .hero-art .badge .lbl { font-size: 12px; color: var(--ink-soft); line-height: 1.3; }

    .stats { background: var(--green); padding: 6px 0; position: relative; z-index: 2; }

    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); padding: 38px 0; }

    .stat {
      text-align: center;
      color: var(--white);
      border-left: 1px solid rgba(255,255,255,0.15);
      padding: 0 12px;
    }

    .stat:first-child { border-left: none; }

    .stat .n {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 42px;
      font-weight: 800;
      color: var(--orange-light);
      display: inline-block;
      letter-spacing: -1.5px;
      line-height: 1;
      transition: transform 0.3s ease, opacity 0.3s ease;
      opacity: 1;
    }

    .stat .n.pop { animation: popNumber 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
    @keyframes popNumber { 0% { transform: scale(0.5); opacity: 0.3; } 100% { transform: scale(1); opacity: 1; } }

    .stat .n.pulse { animation: pulseNumber 0.8s ease forwards; }
    @keyframes pulseNumber {
      0% { transform: scale(1); opacity: 1; }
      30% { transform: scale(1.15); opacity: 1; color: #fff; }
      60% { transform: scale(0.95); }
      100% { transform: scale(1); opacity: 1; }
    }

    .stat .l { font-size: 13px; opacity: 0.85; margin-top: 6px; font-weight: 500; }

    section { padding: 96px 0; }

    .section-head { text-align: center; max-width: 620px; margin: 0 auto 56px; }
    .section-head h2 { font-size: 36px; color: var(--green); margin: 14px 0 12px; }
    .section-head p { color: var(--ink-soft); font-size: 16px; }

    .profil-grid { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 64px; align-items: center; }

    .profil-art {
      position: relative;
      border-radius: 22px;
      overflow: hidden;
      box-shadow: var(--shadow);
    }

    .profil-art img { width: 100%; height: 420px; object-fit: cover; }

    .profil-art::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, transparent 50%, rgba(31,92,63,0.55));
    }

    .profil-text h3 { font-size: 26px; color: var(--green); margin-bottom: 14px; }
    .profil-text p { color: var(--ink-soft); margin-bottom: 16px; }

    .vm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 28px; }

    .vm-card {
      background: var(--green-pale);
      border-radius: 16px;
      padding: 24px;
      border: 1px solid #DCE7CC;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .vm-card:hover { transform: translateY(-4px); box-shadow: var(--shadow); }

    .vm-card h4 { font-family: 'Amiri', serif; font-size: 18px; color: var(--green); margin-bottom: 8px; }
    .vm-card p { font-size: 14.5px; color: var(--ink-soft); margin: 0; }

    .pillars { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 64px; scroll-margin-top: 90px; }

    .pillar {
      background: var(--cream);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 28px 22px;
      text-align: center;
      transition: transform .3s ease, box-shadow .3s ease;
    }

    a.pillar { text-decoration: none; color: inherit; display: block; }
    .pillar:hover { transform: translateY(-6px); box-shadow: var(--shadow); }

    .pillar .icon {
      width: 52px; height: 52px;
      border-radius: 14px;
      background: var(--orange);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px;
      transition: transform 0.3s ease;
    }

    .pillar:hover .icon { transform: scale(1.1) rotate(-5deg); }
    .pillar .icon svg { width: 26px; height: 26px; }
    .pillar .icon svg path, .pillar .icon svg circle, .pillar .icon svg rect { stroke: var(--white); }

    .pillar h4 { font-family: 'Amiri', serif; font-size: 17px; color: var(--green); margin-bottom: 8px; }
    .pillar p { font-size: 14px; color: var(--ink-soft); margin: 0; }

    .nav-hint {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-top: 16px;
      padding: 8px 18px;
      background: linear-gradient(135deg, rgba(217,113,31,0.1), rgba(31,92,63,0.08));
      border: 1px dashed var(--orange-light);
      border-radius: 100px;
      font-size: 13px;
      font-weight: 600;
      color: var(--orange-deep);
      animation: hintPulse 2.5s ease-in-out infinite;
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
      transition: background 0.3s ease, border-color 0.3s ease, transform 0.2s ease;
    }

    .nav-hint:hover {
      background: linear-gradient(135deg, rgba(217,113,31,0.18), rgba(31,92,63,0.14));
      border-color: var(--orange);
    }

    .nav-hint:active {
      transform: scale(0.97);
    }

    .nav-hint:focus-visible {
      outline: 3px solid var(--orange);
      outline-offset: 4px;
    }

    .nav-hint svg {
      width: 16px;
      height: 16px;
      animation: hintBounce 1.5s ease-in-out infinite;
    }

    @keyframes hintPulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(217,113,31,0.25); }
      50% { box-shadow: 0 0 0 8px rgba(217,113,31,0); }
    }

    @keyframes hintBounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(3px); }
    }

    .pillars.flash .pillar {
      animation: pillarFlash 1.2s ease;
    }

    @keyframes pillarFlash {
      0%, 100% { box-shadow: 0 0 0 0 rgba(217,113,31,0); }
      30% { box-shadow: 0 0 0 6px rgba(217,113,31,0.25); }
    }

    a.pillar {
      position: relative;
      cursor: pointer;
      overflow: visible;
    }

    a.pillar::before {
      content: '';
      position: absolute;
      inset: -2px;
      border-radius: 18px;
      background: linear-gradient(135deg, var(--orange), var(--green-light), var(--orange));
      background-size: 200% 200%;
      opacity: 0;
      z-index: -1;
      transition: opacity 0.35s ease;
      animation: gradientShift 3s ease infinite;
    }

    a.pillar:hover::before {
      opacity: 1;
    }

    @keyframes gradientShift {
      0%, 100% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
    }

    .pillar-badge {
      position: absolute;
      top: 12px;
      right: 12px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 5px 10px;
      background: var(--orange);
      color: var(--white);
      font-size: 10.5px;
      font-weight: 700;
      letter-spacing: 0.3px;
      border-radius: 100px;
      text-transform: uppercase;
      box-shadow: 0 4px 12px -4px rgba(217,113,31,0.6);
      opacity: 0;
      transform: translateY(-6px) scale(0.9);
      transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      pointer-events: none;
      z-index: 3;
    }

    .pillar-badge svg {
      width: 11px;
      height: 11px;
      stroke: var(--white);
    }

    a.pillar:hover .pillar-badge,
    a.pillar:focus-visible .pillar-badge {
      opacity: 1;
      transform: translateY(0) scale(1);
    }

    .pillar-arrow {
      position: absolute;
      bottom: 14px;
      left: 50%;
      transform: translateX(-50%) translateY(6px);
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 700;
      color: var(--orange-deep);
      opacity: 0;
      transition: opacity 0.3s ease, transform 0.3s ease;
      pointer-events: none;
      white-space: nowrap;
    }

    .pillar-arrow svg {
      width: 14px;
      height: 14px;
      transition: transform 0.3s ease;
    }

    a.pillar:hover .pillar-arrow {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }

    a.pillar:hover .pillar-arrow svg {
      transform: translateX(4px);
    }

    a.pillar {
      animation: cardBreathe 4s ease-in-out infinite;
      animation-delay: var(--delay, 0s);
    }

    a.pillar:nth-child(1) { --delay: 0s; }
    a.pillar:nth-child(2) { --delay: 1s; }
    a.pillar:nth-child(3) { --delay: 2s; }
    a.pillar:nth-child(4) { --delay: 3s; }

    @keyframes cardBreathe {
      0%, 100% { transform: translateY(0); box-shadow: 0 0 0 0 rgba(217,113,31,0); }
      50% { transform: translateY(-4px); box-shadow: 0 12px 24px -12px rgba(217,113,31,0.25); }
    }

    a.pillar:hover {
      animation-play-state: paused;
    }

    a.pillar:focus-visible {
      outline: 3px solid var(--orange);
      outline-offset: 4px;
    }

    a.pillar * {
      cursor: pointer;
    }

    @media (prefers-reduced-motion: reduce) {
      .nav-hint, a.pillar, .pillar-badge, .pillar-arrow,
      .nav-hint svg, .pillars.flash .pillar,
      .news-bg-blob, .news-bg-particle, .news-bg-ring,
      .news-bg-cross, .news-bg-line {
        animation: none !important;
        transition: none !important;
      }
      a.pillar::before { display: none; }
    }

    .news-carousel { position: relative; margin: 0 -8px; z-index: 2; }

    .news-grid {
      display: flex;
      gap: 26px;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scroll-behavior: smooth;
      padding: 8px 8px 22px;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
      align-items: stretch;
    }

    .news-grid::-webkit-scrollbar { height: 8px; }
    .news-grid::-webkit-scrollbar-track { background: var(--cream); border-radius: 10px; }
    .news-grid::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
    .news-grid::-webkit-scrollbar-thumb:hover { background: var(--orange-light); }

    .news-card {
      background: var(--white);
      border-radius: 18px;
      overflow: hidden;
      border: 1px solid var(--border);
      transition: transform .3s ease, box-shadow .3s ease;
      display: flex;
      flex-direction: column;
      flex: 0 0 calc((100% - 78px) / 4);
      scroll-snap-align: start;
      min-width: 0;
      min-height: 480px;
    }

    .news-card:hover { transform: translateY(-6px); box-shadow: var(--shadow); }

    .news-img { height: 160px; overflow: hidden; position: relative; flex-shrink: 0; }
    .news-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
    .news-card:hover .news-img img { transform: scale(1.06); }

    .news-tag {
      position: absolute;
      top: 14px;
      left: 14px;
      background: var(--orange);
      color: var(--white);
      font-size: 11.5px;
      font-weight: 700;
      padding: 6px 12px;
      border-radius: 100px;
      letter-spacing: 0.3px;
      z-index: 2;
    }

    .news-body {
      padding: 20px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .news-date {
      font-size: 12px;
      color: var(--green);
      font-weight: 700;
      letter-spacing: 0.4px;
      text-transform: uppercase;
      flex-shrink: 0;
    }

    .news-body h4 {
      font-family: 'Amiri', serif;
      font-size: 17px;
      margin: 8px 0 10px;
      color: var(--ink);
      line-height: 1.3;
    }

    .news-desc-wrap {
      flex-grow: 1;
    }

    .news-desc {
      font-size: 13.5px;
      color: var(--ink-soft);
      margin: 0;
      line-height: 1.55;
    }

    .news-nav { display: flex; justify-content: center; gap: 12px; margin-top: 8px; align-items: center; }

    .news-nav-btn {
      width: 42px; height: 42px;
      border-radius: 50%;
      border: 1.5px solid var(--border);
      background: var(--white);
      color: var(--green);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background .2s, color .2s, border-color .2s, transform .2s;
      box-shadow: 0 4px 12px -6px rgba(43,33,24,0.25);
    }

    .news-nav-btn:hover:not(:disabled) { background: var(--orange); color: var(--white); border-color: var(--orange); transform: translateY(-2px); }
    .news-nav-btn:disabled { opacity: 0.35; cursor: not-allowed; }
    .news-nav-btn svg { width: 18px; height: 18px; }

    .news-dots { display: flex; gap: 8px; align-items: center; padding: 0 8px; }

    .news-dot {
      width: 9px; height: 9px;
      border-radius: 50%;
      border: none;
      background: var(--border);
      cursor: pointer;
      padding: 0;
      transition: background .25s ease, transform .25s ease, width .25s ease;
    }

    .news-dot.active { background: var(--orange); width: 26px; border-radius: 6px; }

    .kontak-grid { display: grid; grid-template-columns: 0.85fr 1.15fr; gap: 50px; }

    .kontak-info {
      background: var(--green);
      border-radius: 22px;
      padding: 40px 34px;
      color: var(--white);
      position: relative;
      overflow: hidden;
    }

    .kontak-info h3 { font-size: 24px; margin-bottom: 10px; }
    .kontak-info > p { opacity: 0.85; font-size: 14.5px; margin-bottom: 30px; }

    .kontak-item {
      display: flex;
      gap: 14px;
      align-items: flex-start;
      margin-bottom: 22px;
      transition: transform 0.2s ease;
    }

    .kontak-item:hover { transform: translateX(4px); }

    .kontak-item .ico {
      width: 38px; height: 38px;
      background: rgba(255,255,255,0.14);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      transition: background 0.3s ease;
    }

    .kontak-item:hover .ico { background: rgba(255,255,255,0.25); }
    .kontak-item .ico svg { width: 18px; height: 18px; }
    .kontak-item .ico svg path, .kontak-item .ico svg circle, .kontak-item .ico svg rect { stroke: var(--orange-light); }

    .kontak-item .t { font-size: 13px; opacity: 0.75; margin-bottom: 2px; }
    .kontak-item .v { font-size: 14.5px; font-weight: 600; line-height: 1.5; }

    .kontak-map-container {
      background: var(--cream);
      border: 1px solid var(--border);
      border-radius: 22px;
      padding: 8px;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .kontak-map-container:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -18px rgba(43,33,24,0.4); }

    .kontak-map-container iframe { width: 100%; height: 320px; border-radius: 16px; border: 0; display: block; }

    .map-details { padding: 20px 16px 8px 16px; display: flex; flex-direction: column; gap: 6px; }
    .map-details .map-address { font-weight: 600; color: var(--green); display: flex; align-items: center; gap: 8px; }
    .map-details .map-address svg { width: 18px; height: 18px; stroke: var(--orange); flex-shrink: 0; }
    .map-details .map-hours { font-size: 14px; color: var(--ink-soft); display: flex; align-items: center; gap: 8px; }
    .map-details .map-hours svg { width: 16px; height: 16px; stroke: var(--ink-soft); flex-shrink: 0; }

    footer {
      background: #163F2C;
      color: rgba(255,255,255,0.82);
      padding: 56px 0 26px;
      position: relative;
      z-index: 2;
    }

    .footer-grid {
      display: grid;
      grid-template-columns: 1.3fr 1fr 1fr;
      gap: 40px;
      padding-bottom: 36px;
      border-bottom: 1px solid rgba(255,255,255,0.12);
    }

    .footer-brand { display: flex; gap: 12px; align-items: center; margin-bottom: 14px; }
    .footer-brand .brand-name { color: var(--white); font-size: 19px; }

    footer .desc { font-size: 14px; opacity: 0.75; max-width: 300px; line-height: 1.7; }
    footer h5 { color: var(--white); font-size: 14px; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 16px; }

    footer ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 11px; }
    footer ul a {
      font-size: 14.5px;
      opacity: 0.8;
      transition: opacity .2s ease, color .2s ease, transform .2s ease;
      display: inline-block;
    }

    footer ul a:hover { opacity: 1; color: var(--orange-light); transform: translateX(4px); }

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

    .reveal { opacity: 0; transform: translateY(18px); transition: opacity 0.7s ease, transform 0.7s ease; }
    .reveal.in { opacity: 1; transform: translateY(0); }

    .reveal-scale { opacity: 0; transform: scale(0.9); transition: opacity 0.6s ease, transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1); }
    .reveal-scale.in { opacity: 1; transform: scale(1); }

    .page-loaded .hero .hero-grid > * { animation: fadeInUp 0.8s ease forwards; }
    .page-loaded .hero .hero-grid > *:nth-child(2) { animation-delay: 0.3s; }

    @keyframes fadeInUp { 0% { opacity: 0; transform: translateY(40px); } 100% { opacity: 1; transform: translateY(0); } }

    .page-loaded header .nav-wrap { animation: slideDown 0.6s ease forwards; }
    @keyframes slideDown { 0% { opacity: 0; transform: translateY(-20px); } 100% { opacity: 1; transform: translateY(0); } }

    .hero-art .badge { animation: floatBadge 3s ease-in-out infinite; }
    @keyframes floatBadge { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

    .hero-art .ring { animation: spin 40s linear infinite, shimmerRing 4s ease-in-out infinite; }
    @keyframes shimmerRing { 0%, 100% { opacity: 0.14; } 50% { opacity: 0.25; } }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.65);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 2000;
      backdrop-filter: blur(5px);
    }

    .modal-overlay.active { display: flex; }

    .modal-box {
      background: var(--white);
      border-radius: var(--radius);
      padding: 32px;
      width: 90%;
      max-width: 400px;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
    }

    .modal-box h3 { margin-bottom: 20px; color: var(--green); text-align: center; font-size: 24px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--ink); }

    .form-group input, .form-group textarea {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s;
    }

    .form-group input:focus, .form-group textarea:focus { border-color: var(--orange); }

    .login-box { max-width: 380px; text-align: center; transform: translateY(12px); opacity: 0; transition: transform 0.25s ease, opacity 0.25s ease; }
    .modal-overlay.active .login-box { transform: translateY(0); opacity: 1; }

    .login-icon {
      width: 54px; height: 54px;
      margin: 0 auto 16px;
      display: flex; align-items: center; justify-content: center;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--green), var(--green-light));
      box-shadow: 0 10px 22px -10px rgba(31,92,63,0.55);
    }

    .login-box h3 { margin-bottom: 4px; }
    .login-sub { color: var(--ink-soft); font-size: 13px; margin-bottom: 22px; }
    .login-box .form-group { text-align: left; }
    .login-box form { margin-top: 4px; }

    .password-field { position: relative; }
    .password-field input { padding-right: 42px; }
    .password-toggle {
      position: absolute;
      right: 4px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      padding: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--ink-soft);
      cursor: pointer;
      border-radius: 6px;
      transition: color 0.2s, background 0.2s;
    }
    .password-toggle:hover { color: var(--orange); background: var(--cream); }

    .login-actions { display: flex; gap: 10px; margin-top: 4px; }
    .login-actions .btn { flex: 1; justify-content: center; padding: 11px 0; }

    [data-field][contenteditable="true"] {
      outline: 2px dashed var(--orange);
      outline-offset: 4px;
      border-radius: 6px;
      background: rgba(217,113,31,0.06);
      cursor: text;
    }
    [data-field][contenteditable="true"]:focus { outline: 2px solid var(--orange-deep); background: rgba(217,113,31,0.1); }

    .img-edit-wrap { position: relative; }
    .img-edit-btn {
      display: none;
      position: absolute;
      right: 10px;
      bottom: 10px;
      z-index: 5;
      background: var(--orange);
      color: var(--white);
      border: none;
      border-radius: 100px;
      padding: 8px 14px;
      font-size: 12.5px;
      font-weight: 700;
      font-family: 'Plus Jakarta Sans', sans-serif;
      cursor: pointer;
      box-shadow: 0 8px 18px -8px rgba(217,113,31,0.6);
      align-items: center;
      gap: 6px;
    }
    .img-edit-wrap.edit-active .img-edit-btn { display: inline-flex; }
    .img-edit-btn:hover { background: var(--orange-deep); }
    .img-edit-input { display: none; }

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

    .drawer {
      position: fixed;
      right: -420px;
      top: 0;
      bottom: 0;
      width: 380px;
      max-width: 90vw;
      background: var(--white);
      box-shadow: -10px 0 30px rgba(0,0,0,0.15);
      z-index: 1001;
      padding: 24px;
      transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow-y: auto;
    }

    .drawer.open { right: 0; }

    .drawer-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      border-bottom: 1px solid var(--border);
      padding-bottom: 12px;
    }

    .btn-danger {
      background: #d9534f;
      color: white;
      border: none;
      padding: 4px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 12px;
      margin-top: 8px;
    }
    .btn-danger:hover { background: #c9302c; }
    .btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }

    @media (prefers-reduced-motion: reduce) {
      .reveal, .reveal-scale, .page-loaded .hero .hero-grid > *,
      .page-loaded header .nav-wrap, .hero-art .badge,
      .hero-art .ring, .stat .n.pulse,
      .nav-cta::before, .btn-primary::before, .btn-ghost::before,
      .news-bg-blob, .news-bg-particle, .news-bg-ring,
      .news-bg-cross, .news-bg-line {
        opacity: 1; transform: none; animation: none; transition: none;
      }
      html { scroll-behavior: auto; }
    }

    @media (max-width: 980px) {
      .hero-grid { grid-template-columns: 1fr; }
      .hero-art { max-width: 380px; margin: 0 auto; }
      .profil-grid { grid-template-columns: 1fr; }
      .pillars { grid-template-columns: repeat(2, 1fr); }
      .news-card { flex: 0 0 calc((100% - 26px) / 2); }
      .kontak-grid { grid-template-columns: 1fr; }
      .footer-grid { grid-template-columns: 1fr 1fr; }
      .news-bg-blob.blob-1 { width: 320px; height: 320px; }
      .news-bg-blob.blob-2 { width: 340px; height: 340px; }
      .news-bg-blob.blob-3 { width: 260px; height: 260px; }
      .news-bg-blob.blob-4 { width: 220px; height: 220px; }
      .news-bg-blob.blob-5 { width: 200px; height: 200px; }
      .news-bg-blob.blob-6 { width: 240px; height: 240px; }
    }

    @media (max-width: 720px) {
      nav {
        position: fixed;
        top: 73px;
        left: 0;
        right: 0;
        background: var(--green);
        border-bottom: 1px solid rgba(255,255,255,0.15);
        flex-direction: column;
        padding: 12px 20px;
        box-shadow: 0 16px 24px -18px rgba(0,0,0,0.35);
        transform: translateY(-130%);
        opacity: 0;
        transition: .25s;
      }
      nav.open { transform: translateY(0); opacity: 1; }
      nav ul { flex-direction: column; width: 100%; }
      nav a { display: block; padding: 14px 12px; border-radius: 10px; color: rgba(255,255,255,0.85); }
      .nav-cta { display: inline-block; margin-top: 6px; text-align: center; width: 100%; }
      .menu-toggle { display: flex; }
      .hero h1 { font-size: 36px; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 24px 0; }
      .stat { border-left: none; }
      .stat .n { font-size: 36px; }
      .pillars { grid-template-columns: 1fr; }
      .news-card { flex: 0 0 100%; min-height: 440px; }
      .footer-grid { grid-template-columns: 1fr; }
      section { padding: 64px 0; }
      .hero { padding: 56px 0 70px; }
      .news-bg-decor { opacity: 0.4; }
    }
  </style>
</head>
<body>

  <canvas id="particleCanvas" aria-hidden="true"></canvas>

  <header id="mainHeader">
    <div class="nav-wrap">
      <a href="#beranda" class="brand">
        <div class="brand-mark img-edit-wrap" data-img-key="logoImg">
          <img src="logoyayasan.png" alt="Logo Yayasan Abdul Rahman Musafi">
          <?php if ($isAdmin): ?>
          <button type="button" class="img-edit-btn" style="right:-6px; bottom:-6px; padding:4px 8px; font-size:10px;" onclick="this.nextElementSibling.click()">Ganti</button>
          <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
          <?php endif; ?>
        </div>
        <div class="brand-text">
          <div class="brand-name">Ar-Rahmah</div>
          <div class="brand-sub">Yayasan Sosial &amp; Pendidikan</div>
        </div>
      </a>

      <nav id="mainNav">
        <ul>
          <li><a href="#beranda" class="active">Beranda</a></li>
          <li><a href="#profil">Profil</a></li>
          <li><a href="#berita">Berita</a></li>
          <li><a href="#kontak">Hubungi Kami</a></li>
        </ul>
      </nav>

      <button class="nav-cta" id="loginBtn">Login</button>
      <button class="menu-toggle" id="menuToggle" aria-label="Buka menu"><span></span></button>
    </div>
  </header>

  <section id="beranda" class="hero">
    <div class="container">
      <div class="hero-grid">
        <div class="reveal in">
          <span class="eyebrow" data-field="heroEyebrow">Yayasan Ar-Rahmah</span>
          <h1 data-field="heroTitle">Menebar <em>Kasih</em>,<br>Membangun Harapan</h1>
          <p data-field="heroDesc">Kami hadir untuk semua kalangan masyarakat, dan siap mendidik siswa sampai menjadi penerus bangsa yang bermartabat dan berbudiman.</p>
          <div class="btn-row">
            <a href="#profil" class="btn btn-primary">
              Kenali Kami
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
            <a href="#kontak" class="btn btn-ghost">Contact Us</a>
          </div>
        </div>
        <div class="hero-art reveal in">
          <div class="ring"></div>
          <div class="photo img-edit-wrap" data-img-key="heroPhoto">
            <img src="logoyayasan.png" alt="Kegiatan sosial Yayasan Ar-Rahmah bersama anak-anak">
            <?php if ($isAdmin): ?>
            <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
            <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
            <?php endif; ?>
          </div>
          <div class="badge">
            <span class="num" data-field="heroBadgeNum">9</span>
            <span class="lbl" data-field="heroBadgeLbl">Tahun<br><b>Berdiri</b></span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div class="stats">
    <div class="container stats-grid" id="statsContainer">
      <div class="stat reveal">
        <div class="n" id="stat-siswa" data-target="135">0</div>
        <div class="l" data-field="statSiswaLbl">Seluruh Siswa</div>
      </div>
      <div class="stat reveal">
        <div class="n" id="stat-tk" data-target="70">0</div>
        <div class="l" data-field="statTkLbl">Siswa TK</div>
      </div>
      <div class="stat reveal">
        <div class="n" id="stat-smp" data-target="65">0</div>
        <div class="l" data-field="statSmpLbl">Siswa SMP &amp; SMK</div>
      </div>
      <div class="stat reveal" id="statPersen">
        <div class="n" id="stat-persen" data-target="100">0</div>
        <div class="l" data-field="statPersenLbl">Transparan &amp; Amanah</div>
      </div>
    </div>
  </div>

  <section id="profil">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow" data-field="profilEyebrow">Profil Kami</span>
        <h2 data-field="profilTitle">Tentang Yayasan Ar-Rahmah</h2>
        <p data-field="profilSubtitle">Berdiri sejak 2017, kami berkomitmen menjadi jembatan kebaikan antara para dermawan dan mereka yang membutuhkan.</p>

        <div class="nav-hint" id="pillarHint" role="button" tabindex="0" aria-label="Gulir ke navigasi program">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6"/>
          </svg>
          <span>Klik kartu di bawah untuk menjelajah program kami</span>
        </div>
      </div>

      <div class="profil-grid">
        <div class="profil-art reveal img-edit-wrap" data-img-key="profilPhoto">
          <img src="logosmp.jpeg" alt="Kantor dan kegiatan Yayasan Ar-Rahmah">
          <?php if ($isAdmin): ?>
          <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
          <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
          <?php endif; ?>
        </div>
        <div class="profil-text reveal">
          <h3 data-field="profilHeading">Amanah dalam Setiap Langkah</h3>
          <p data-field="profilPara1">Yayasan Ar-Rahmah adalah lembaga sosial dan pendidikan nirlaba yang berfokus pada pemberdayaan anak yatim, dhuafa, dan masyarakat prasejahtera melalui program pendidikan, kesehatan, dan bantuan sosial.</p>
          <p data-field="profilPara2">Setiap donasi yang kami kelola disalurkan secara transparan dan dilaporkan secara berkala kepada para donatur dan masyarakat luas.</p>

          <div class="vm-grid">
            <div class="vm-card reveal-scale">
              <h4 data-field="visiTitle">Visi</h4>
              <p data-field="visiDesc">Menjadi yayasan terpercaya yang menghadirkan kesejahteraan dan pendidikan berkualitas bagi anak bangsa.</p>
            </div>
            <div class="vm-card reveal-scale">
              <h4 data-field="misiTitle">Misi</h4>
              <p data-field="misiDesc">Menyalurkan bantuan tepat sasaran, mendampingi pendidikan anak, dan membangun kemandirian masyarakat.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="pillars" id="pillarsNav">
        <a href="pendidikan.php" class="pillar reveal-scale" aria-label="Buka halaman Pendidikan">
          <span class="pillar-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 6l6 6-6 6"/>
            </svg>
            Klik
          </span>
          <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 3 3 6 3s6-2 6-3v-5"/></svg>
          </div>
          <h4 data-field="p1Title">Pendidikan</h4>
          <p data-field="p1Desc">Seluruh Pendidikan yang ada di Yayasan Ar-Rahmah &amp; Program Unggulan</p>
          <span class="pillar-arrow">
            Buka halaman
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </span>
        </a>

        <a href="prestasi.php" class="pillar reveal-scale" aria-label="Buka halaman Prestasi Siswa">
          <span class="pillar-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 6l6 6-6 6"/>
            </svg>
            Klik
          </span>
          <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.45 1-1 1H7v3h10v-3h-2c-.55 0-1-.45-1-1v-2.34"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/></svg>
          </div>
          <h4 data-field="p2Title">Prestasi Siswa</h4>
          <p data-field="p2Desc">Capaian dan penghargaan siswa-siswi dalam bidang akademik maupun non-akademik.</p>
          <span class="pillar-arrow">
            Buka halaman
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </span>
        </a>

        <a href="sosial-media.php" class="pillar reveal-scale" aria-label="Buka halaman Sosial Media">
          <span class="pillar-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 6l6 6-6 6"/>
            </svg>
            Klik
          </span>
          <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          </div>
          <h4 data-field="p3Title">Sosial Media Ar-Rahmah</h4>
          <p data-field="p3Desc">Ikuti dokumentasi kegiatan, informasi, dan pembaruan terkini yayasan kami.</p>
          <span class="pillar-arrow">
            Buka halaman
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </span>
        </a>

        <a href="stafarrahmah.php" class="pillar reveal-scale" aria-label="Buka halaman Staf &amp; Sosial">
          <span class="pillar-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 6l6 6-6 6"/>
            </svg>
            Klik
          </span>
          <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.5-9.5-9C.5 8 3 4 7 4c2 0 3.6 1.2 5 3 1.4-1.8 3-3 5-3 4 0 6.5 4 4.5 8-2.5 4.5-9.5 9-9.5 9z"/></svg>
          </div>
          <h4 data-field="p4Title">Seluruh Staf &amp; Sosial</h4>
          <p data-field="p4Desc">Informasi tentang seluruh staf yayasan Ar-Rahmah.</p>
          <span class="pillar-arrow">
            Buka halaman
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6"/>
            </svg>
          </span>
        </a>
      </div>
    </div>
  </section>

  <!-- ===== BERITA ===== -->
  <section id="berita">
    <!-- ===== BACKGROUND DECOR (rame: 6 blob + 12 particle + 4 ring + 2 cross + 2 line) ===== -->
    <div class="news-bg-decor" aria-hidden="true">
      <!-- Blob gradient -->
      <span class="news-bg-blob blob-1"></span>
      <span class="news-bg-blob blob-2"></span>
      <span class="news-bg-blob blob-3"></span>
      <span class="news-bg-blob blob-4"></span>
      <span class="news-bg-blob blob-5"></span>
      <span class="news-bg-blob blob-6"></span>

      <!-- Partikel titik -->
      <span class="news-bg-particle p1"></span>
      <span class="news-bg-particle p2"></span>
      <span class="news-bg-particle p3"></span>
      <span class="news-bg-particle p4"></span>
      <span class="news-bg-particle p5"></span>
      <span class="news-bg-particle p6"></span>
      <span class="news-bg-particle p7"></span>
      <span class="news-bg-particle p8"></span>
      <span class="news-bg-particle p9"></span>
      <span class="news-bg-particle p10"></span>
      <span class="news-bg-particle p11"></span>
      <span class="news-bg-particle p12"></span>

      <!-- Partikel ring -->
      <span class="news-bg-ring r1"></span>
      <span class="news-bg-ring r2"></span>
      <span class="news-bg-ring r3"></span>
      <span class="news-bg-ring r4"></span>

      <!-- Partikel cross -->
      <span class="news-bg-cross c1"></span>
      <span class="news-bg-cross c2"></span>

      <!-- Garis diagonal -->
      <span class="news-bg-line l1"></span>
      <span class="news-bg-line l2"></span>
    </div>

    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow" data-field="beritaEyebrow">Berita &amp; Kegiatan</span>
        <h2 data-field="beritaTitle">Kabar Terbaru dari Ar-Rahmah</h2>
        <p data-field="beritaSubtitle">Dokumentasi program dan kegiatan yang telah kami jalankan.</p>
      </div>

      <div class="news-carousel">
        <div class="news-grid" id="newsGrid"></div>
        <div class="news-nav" id="newsNav">
          <button type="button" class="news-nav-btn" id="newsPrev" aria-label="Berita sebelumnya">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <div class="news-dots" id="newsDots"></div>
          <button type="button" class="news-nav-btn" id="newsNext" aria-label="Berita berikutnya">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
          </button>
        </div>
      </div>
    </div>
  </section>

  <section id="kontak">
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow" data-field="kontakEyebrow">Hubungi Kami</span>
        <h2 data-field="kontakTitle">Mari Berkolaborasi dalam Kebaikan</h2>
        <p data-field="kontakSubtitle">Punya pertanyaan seputar donasi atau program kami? Temukan lokasi dan jam operasional kami di bawah ini.</p>
      </div>

      <div class="kontak-grid">
        <div class="kontak-info reveal">
          <h3 data-field="kontakHeading">Kontak Yayasan</h3>
          <p data-field="kontakDesc">Kami siap melayani Anda pada hari &amp; jam kerja.</p>

          <div class="kontak-item">
            <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 1118 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
            <div>
              <div class="t">Alamat</div>
              <div class="v" data-field="kontakAlamat">Jl. Beringin No. 0, Desa Banjarsari,<br> Kec. Bangsalsari, Kab. Jember,<br> Jawa Timur, Indonesia</div>
            </div>
          </div>

          <div class="kontak-item">
            <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.7A2 2 0 014.1 2h3a2 2 0 012 1.7c.1 1 .3 2 .6 3a2 2 0 01-.5 2L8 10a16 16 0 006 6l1.3-1.3a2 2 0 012-.5c1 .3 2 .5 3 .6a2 2 0 011.7 2z"/></svg></div>
            <div>
              <div class="t">Telepon</div>
              <div class="v" data-field="kontakTelepon">(031) 123-4567</div>
            </div>
          </div>

          <div class="kontak-item">
            <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M22 6l-10 7L2 6"/></svg></div>
            <div>
              <div class="t">Email</div>
              <div class="v" data-field="kontakEmail">info@ar-rahmah.or.id</div>
            </div>
          </div>

          <div class="kontak-item">
            <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
            <div>
              <div class="t">Jam Layanan</div>
              <div class="v" data-field="kontakJam">Senin – Sabtu, 07.00 – 12.00 WIB</div>
            </div>
          </div>
        </div>

        <div class="kontak-map-container reveal">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d15792.932916206086!2d113.5617032!3d-8.1563286!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd68d0018206365%3A0xe067f7b4765892d3!2sSMP%20Ar-Rahmah!5e0!3m2!1sid!2sid!4v1721212121212!5m2!1sid!2sid"
            width="600"
            height="320"
            style="border:0;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Lokasi Yayasan Ar-Rahmah di Google Maps">
          </iframe>
        <div class="map-details">
          <div class="map-address">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 1118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span data-field="mapAddress">Yayasan Ar-Rahmah, Banjarsari, Jember</span>
          </div>
          <div class="map-hours">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <span data-field="mapHours">Buka: Senin–Sabtu, 07.00–12.00 WIB</span>
          </div>
        </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer>
    <div class="container">
      <div class="footer-grid">
        <div>
          <div class="footer-brand">
            <div class="brand-mark img-edit-wrap" data-img-key="logoImg">
              <img src="logoyayasan.png" alt="Logo Yayasan Abdul Rahman Musafi">
            </div>
            <div class="brand-name">Ar-Rahmah</div>
          </div>
          <p class="desc" data-field="footerDesc">Yayasan sosial dan pendidikan yang berkomitmen membantu anak yatim, dhuafa, dan masyarakat prasejahtera secara amanah dan berkelanjutan.</p>
        </div>
        <div>
          <h5>Navigasi</h5>
          <ul>
            <li><a href="index.php#beranda">Beranda</a></li>
            <li><a href="index.php#profil">Profil</a></li>
            <li><a href="index.php#berita">Berita</a></li>
            <li><a href="index.php#kontak">Hubungi Kami</a></li>
          </ul>
        </div>
        <div>
          <h5>Program</h5>
          <ul>
            <li><a href="pendidikan.php">Pendidikan</a></li>
            <li><a href="sosial-media.php">Sosial Media</a></li>
            <li><a href="prestasi.php">Prestasi</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© 2026 Yayasan Ar-Rahmah.</span>
        <span>Dibangun dengan ikhlas untuk kebaikan bersama.</span>
      </div>
    </div>
  </footer>

  <div class="admin-panel" id="adminPanel">
    <span style="font-size: 13px;"><strong>Mode Admin Active</strong></span>
    <button class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;" onclick="toggleDrawer(true)">Kelola Statistik & Berita</button>
    <button type="button" class="btn btn-primary" id="btnToggleEdit" style="padding: 8px 16px; font-size: 13px; background: var(--orange);">Edit Konten Halaman</button>
    <button type="button" class="btn btn-primary" id="btnSaveContent" style="padding: 8px 16px; font-size: 13px; background: var(--green); display:none;">Simpan Perubahan</button>
    <button class="btn btn-ghost" style="padding: 8px 16px; font-size: 13px;" id="logoutBtn">Logout</button>
  </div>

  <div class="drawer" id="adminDrawer">
    <div class="drawer-header">
      <h3 style="font-family:'Amiri',serif; font-size:22px; color:var(--green); margin:0;">Dashboard Admin</h3>
      <button onclick="toggleDrawer(false)" style="border:none; background:none; cursor:pointer; font-size:22px; color:var(--ink-soft);">✕</button>
    </div>

    <h4 style="color:var(--green); margin-bottom:12px;">Edit Statistik Siswa</h4>
    <form id="formStats">
      <div class="form-group"><label>Seluruh Siswa</label><input type="number" id="inputSiswa" required></div>
      <div class="form-group"><label>Siswa TK</label><input type="number" id="inputTK" required></div>
      <div class="form-group"><label>Siswa SMP &amp; SMK</label><input type="number" id="inputSMP" required></div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:10px;">Simpan Statistik</button>
    </form>

    <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--border);">

    <h4 style="color:var(--green); margin-bottom:12px;">Tambah Berita Baru</h4>
    <form id="formNews">
      <div class="form-group"><label>Kategori (Tag)</label><input type="text" id="newsTag" placeholder="Contoh: Pendidikan" required></div>
      <div class="form-group"><label>Judul Berita</label><input type="text" id="newsTitle" placeholder="Judul berita..." required></div>
      <div class="form-group"><label>Tanggal</label><input type="text" id="newsDate" placeholder="Contoh: 12 Juli 2026" required></div>
      <div class="form-group"><label>Ringkasan Berita</label><textarea id="newsDesc" rows="3" placeholder="Isi ringkasan..." required></textarea></div>
      <div class="form-group">
        <label>Gambar Berita</label>
        <input type="file" id="newsImg" accept="image/jpeg,image/png,image/webp,image/gif" required>
        <img id="newsImgPreview" style="display:none; margin-top:8px; max-height:140px; border-radius:8px; border:1px solid var(--border);">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:10px;" id="btnSubmitNews">Tambah Berita</button>
    </form>
  </div>

  <div class="modal-overlay" id="loginModal">
    <div class="modal-box login-box">
      <div class="login-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 15.5C13.3807 15.5 14.5 14.3807 14.5 13C14.5 11.6193 13.3807 10.5 12 10.5C10.6193 10.5 9.5 11.6193 9.5 13C9.5 14.3807 10.6193 15.5 12 15.5Z" stroke="white" stroke-width="1.6"/>
          <path d="M12 15.5V18" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
          <path d="M6 10V8C6 4.68629 8.68629 2 12 2C15.3137 2 18 4.68629 18 8V10" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
          <rect x="4" y="10" width="16" height="12" rx="3" stroke="white" stroke-width="1.6"/>
        </svg>
      </div>
      <h3>Masuk sebagai Admin</h3>
      <p class="login-sub">Khusus pengelola resmi Yayasan Ar-Rahmah</p>
      <form id="loginForm">
        <div class="form-group">
          <label>Username</label>
          <input type="text" id="username" autocomplete="username" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="password-field">
            <input type="password" id="password" autocomplete="current-password" required>
            <button type="button" class="password-toggle" id="togglePassword" aria-label="Lihat password">
              <svg id="eyeIcon" width="19" height="19" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 12C2 12 5.5 5.5 12 5.5C18.5 5.5 22 12 22 12C22 12 18.5 18.5 12 18.5C5.5 18.5 2 12 2 12Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
              </svg>
            </button>
          </div>
        </div>
        <p id="loginError" style="color:red; font-size:12px; display:none; margin-bottom:10px;">Username atau password salah!</p>
        <div class="login-actions">
          <button type="button" class="btn btn-ghost" id="closeLogin">Batal</button>
          <button type="submit" class="btn btn-primary">Masuk</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let isAdmin = false;

    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
      method: 'GET',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(response => response.text())
    .then(data => {
      <?php if ($isAdmin): ?>
        isAdmin = true;
      <?php endif; ?>
      updateAdminUI();
    }).catch(() => {
      <?php if ($isAdmin): ?>
        isAdmin = true;
      <?php endif; ?>
      updateAdminUI();
    });

    (function initParticleBackground() {
      const canvas = document.getElementById('particleCanvas');
      if (!canvas) return;

      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/particlesjs/2.2.3/particles.min.js';
      script.onload = function () {
        if (typeof Particles === 'undefined') return;

        Particles.init({
          selector: '#particleCanvas',
          maxParticles: 70,
          sizeVariations: 3,
          speed: 0.6,
          color: ['#D9711F', '#1F5C3F', '#F3A15C', '#7FA65C'],
          minDistance: 130,
          connectParticles: true,
          responsive: [
            { breakpoint: 992, options: { maxParticles: 50, minDistance: 110 } },
            { breakpoint: 720, options: { maxParticles: 30, minDistance: 90 } },
            { breakpoint: 480, options: { maxParticles: 18, minDistance: 70, connectParticles: false } }
          ]
        });

        let currentOffset = 0;
        let targetOffset = 0;
        let rafId = null;

        const PARALLAX_FACTOR = 0.35;
        const SMOOTHING = 0.08;

        function animateParallax() {
          currentOffset += (targetOffset - currentOffset) * SMOOTHING;
          canvas.style.transform = 'translate3d(0, ' + (-currentOffset) + 'px, 0)';

          if (Math.abs(targetOffset - currentOffset) > 0.1) {
            rafId = requestAnimationFrame(animateParallax);
          } else {
            rafId = null;
          }
        }

        function onScroll() {
          const scrollY = window.scrollY || window.pageYOffset || 0;
          targetOffset = scrollY * PARALLAX_FACTOR;
          if (rafId === null) {
            rafId = requestAnimationFrame(animateParallax);
          }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
      };
      script.onerror = function () {
        console.warn('particles.js gagal dimuat dari CDN.');
      };
      document.body.appendChild(script);
    })();

    const defaultStats = { siswa: 135, tk: 70, smp: 65, persen: 100 };
    const defaultNews = [
      {
        tag: "Sosial",
        date: "12 Juli 2026",
        title: "Pembagian 500 Paket Sembako untuk Warga Terdampak",
        desc: "Yayasan menyalurkan bantuan pangan kepada keluarga prasejahtera di lima desa binaan.",
        img: "https://images.unsplash.com/photo-1593113598332-cd288d649433?q=80&w=700&auto=format&fit=crop"
      },
      {
        tag: "Pendidikan",
        date: "28 Juni 2026",
        title: "Penyerahan Beasiswa kepada 86 Pelajar Berprestasi",
        desc: "Program beasiswa tahun ini menjangkau lebih banyak pelajar dari keluarga kurang mampu.",
        img: "https://images.unsplash.com/photo-1497633762265-9d179a990aa6?q=80&w=700&auto=format&fit=crop"
      },
      {
        tag: "Kesehatan",
        date: "15 Juni 2026",
        title: "Layanan Kesehatan Gratis untuk Lansia & Anak",
        desc: "Bekerja sama dengan tenaga medis relawan, kami membuka layanan pemeriksaan gratis.",
        img: "https://images.unsplash.com/photo-1584515933487-779824d29309?q=80&w=700&auto=format&fit=crop"
      }
    ];

    let statsData = JSON.parse(localStorage.getItem('ar_stats')) || defaultStats;
    let newsData = JSON.parse(localStorage.getItem('ar_news')) || defaultNews;

    const header = document.getElementById('mainHeader');
    window.addEventListener('scroll', function() {
      if (window.scrollY > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });

    window.addEventListener('load', function() {
      document.body.classList.add('page-loaded');
      renderStats();
      renderNews();
      updateAdminUI();
      initNewsCarousel();
    });

    const toggle = document.getElementById('menuToggle');
    const nav = document.getElementById('mainNav');
    toggle.addEventListener('click', () => nav.classList.toggle('open'));
    document.querySelectorAll('#mainNav a').forEach(a => {
      a.addEventListener('click', () => nav.classList.remove('open'));
    });

    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('#mainNav a');
    const setActive = () => {
      let current = sections[0]?.id || '';
      sections.forEach(sec => {
        if (window.scrollY >= sec.offsetTop - 140) current = sec.id;
      });
      navLinks.forEach(a => {
        const href = a.getAttribute('href');
        if (href === '#' + current) {
          a.classList.add('active');
        } else {
          a.classList.remove('active');
        }
      });
    };
    window.addEventListener('scroll', setActive);

    function animateCounter(element) {
      const target = parseInt(element.dataset.target);
      if (isNaN(target)) return;
      element.dataset.animated = 'true';

      element.style.opacity = '0';
      element.style.transform = 'translateY(10px)';

      let current = 0;
      const duration = 1200;
      const startTime = performance.now();

      const step = (timestamp) => {
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        current = Math.floor(eased * target);
        const suffix = element.id === 'stat-persen' ? '%' : '';
        element.textContent = current + suffix;
        element.style.opacity = eased;
        element.style.transform = 'translateY(' + (10 - eased * 10) + 'px)';

        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          element.textContent = target + suffix;
          element.style.opacity = '1';
          element.style.transform = 'translateY(0)';
          element.classList.add('pulse');
          setTimeout(() => element.classList.remove('pulse'), 1000);
        }
      };
      requestAnimationFrame(step);
    }

    const revealEls = document.querySelectorAll('.reveal, .reveal-scale');
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('in');
          const statNum = e.target.querySelector('.n[data-target]');
          if (statNum && statNum.dataset.animated !== 'true') {
            animateCounter(statNum);
          }
        }
      });
    }, { threshold: 0.15 });
    revealEls.forEach(el => io.observe(el));

    document.querySelectorAll('.stat .n').forEach(el => {
      el.addEventListener('mouseenter', function() {
        this.classList.add('pop');
        setTimeout(() => this.classList.remove('pop'), 500);
      });
    });

    function renderStats() {
      const elSiswa = document.getElementById('stat-siswa');
      const elTK = document.getElementById('stat-tk');
      const elSMP = document.getElementById('stat-smp');

      if (elSiswa) { elSiswa.dataset.target = statsData.siswa; animateCounter(elSiswa); }
      if (elTK) { elTK.dataset.target = statsData.tk; animateCounter(elTK); }
      if (elSMP) { elSMP.dataset.target = statsData.smp; animateCounter(elSMP); }

      const inputSiswa = document.getElementById('inputSiswa');
      const inputTK = document.getElementById('inputTK');
      const inputSMP = document.getElementById('inputSMP');

      if (inputSiswa) inputSiswa.value = statsData.siswa;
      if (inputTK) inputTK.value = statsData.tk;
      if (inputSMP) inputSMP.value = statsData.smp;
    }

    function renderNews() {
      const grid = document.getElementById('newsGrid');
      if (!grid) return;

      grid.innerHTML = newsData.map((item, index) => `
        <div class="news-card reveal-scale in">
          <div class="news-img">
            <span class="news-tag">${item.tag}</span>
            <img src="${item.img}" alt="${item.title}">
          </div>
          <div class="news-body">
            <div class="news-date">${item.date}</div>
            <h4>${item.title}</h4>
            <div class="news-desc-wrap">
              <p class="news-desc">${item.desc}</p>
            </div>
            ${isAdmin ? `<button class="btn-danger" data-delete-index="${index}" onclick="deleteNews(${index})">Hapus Berita</button>` : ''}
          </div>
        </div>
      `).join('');

      if (typeof initNewsCarousel === 'function') initNewsCarousel();
    }

    let newsCarouselInited = false;

    function initNewsCarousel() {
      const grid = document.getElementById('newsGrid');
      const prevBtn = document.getElementById('newsPrev');
      const nextBtn = document.getElementById('newsNext');
      const dotsWrap = document.getElementById('newsDots');
      if (!grid || !prevBtn || !nextBtn || !dotsWrap) return;

      function getCardsPerView() {
        const w = window.innerWidth;
        if (w <= 720) return 1;
        if (w <= 980) return 2;
        return 4;
      }

      function buildDots() {
        const total = newsData.length;
        const perView = getCardsPerView();
        const pages = Math.max(1, Math.ceil(total / perView));
        dotsWrap.innerHTML = '';
        for (let i = 0; i < pages; i++) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'news-dot';
          btn.setAttribute('aria-label', 'Ke halaman ' + (i + 1));
          btn.addEventListener('click', () => {
            const card = grid.querySelector('.news-card');
            if (!card) return;
            const cardWidth = card.offsetWidth + 26;
            grid.scrollTo({ left: i * perView * cardWidth, behavior: 'smooth' });
          });
          dotsWrap.appendChild(btn);
        }
        updateNavState();
      }

      function updateNavState() {
        const maxScroll = grid.scrollWidth - grid.clientWidth - 2;
        const scrollLeft = grid.scrollLeft;

        prevBtn.disabled = scrollLeft <= 2;
        nextBtn.disabled = scrollLeft >= maxScroll;

        const perView = getCardsPerView();
        const card = grid.querySelector('.news-card');
        if (!card || !dotsWrap.children.length) return;

        const cardWidth = card.offsetWidth + 26;
        const activeIndex = Math.round(scrollLeft / (cardWidth * perView));
        Array.from(dotsWrap.children).forEach((dot, i) => {
          dot.classList.toggle('active', i === activeIndex);
        });
      }

      function scrollByCards(direction) {
        const card = grid.querySelector('.news-card');
        if (!card) return;
        const perView = getCardsPerView();
        const cardWidth = card.offsetWidth + 26;
        grid.scrollBy({ left: direction * cardWidth * perView, behavior: 'smooth' });
      }

      if (!newsCarouselInited) {
        prevBtn.addEventListener('click', () => scrollByCards(-1));
        nextBtn.addEventListener('click', () => scrollByCards(1));
        grid.addEventListener('scroll', updateNavState, { passive: true });
        window.addEventListener('resize', () => {
          buildDots();
          updateNavState();
        });
        newsCarouselInited = true;
      }

      buildDots();
    }

    function updateAdminUI() {
      const adminPanel = document.getElementById('adminPanel');
      const loginBtn = document.getElementById('loginBtn');

      if (isAdmin) {
        if (adminPanel) adminPanel.classList.add('active');
        if (loginBtn) loginBtn.innerText = "Admin Active";
      } else {
        if (adminPanel) adminPanel.classList.remove('active');
        if (loginBtn) loginBtn.innerText = "Login";
        toggleDrawer(false);
      }
      renderNews();
    }

    const loginModal = document.getElementById('loginModal');
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
      loginBtn.addEventListener('click', () => {
        if (!isAdmin && loginModal) loginModal.classList.add('active');
      });
    }

    const closeLogin = document.getElementById('closeLogin');
    if (closeLogin) {
      closeLogin.addEventListener('click', () => {
        if (loginModal) loginModal.classList.remove('active');
      });
    }

    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', () => {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        togglePassword.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Lihat password');
        if (eyeIcon) {
          eyeIcon.innerHTML = isHidden
            ? '<path d="M3 3L21 21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M10.6 10.7C10.2 11.1 10 11.5 10 12C10 13.1 10.9 14 12 14C12.5 14 12.9 13.8 13.3 13.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M6.5 6.7C4 8.3 2 12 2 12C2 12 5.5 18.5 12 18.5C14 18.5 15.7 17.9 17.1 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.5 5.8C10.3 5.6 11.1 5.5 12 5.5C18.5 5.5 22 12 22 12C22 12 21.2 13.5 19.7 15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>'
            : '<path d="M2 12C2 12 5.5 5.5 12 5.5C18.5 5.5 22 12 22 12C22 12 18.5 18.5 12 18.5C5.5 18.5 2 12 2 12Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>';
        }
      });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const user = document.getElementById('username').value;
        const pass = document.getElementById('password').value;

        try {
          const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=login&username=${encodeURIComponent(user)}&password=${encodeURIComponent(pass)}`
          });

          const result = await response.json();

          if (result.success) {
            isAdmin = true;
            if (loginModal) loginModal.classList.remove('active');
            updateAdminUI();
            const errorEl = document.getElementById('loginError');
            if (errorEl) errorEl.style.display = 'none';
            window.location.reload();
          } else {
            const errorEl = document.getElementById('loginError');
            if (errorEl) {
              errorEl.textContent = result.error || 'Username atau password salah!';
              errorEl.style.display = 'block';
            }
          }
        } catch (err) {
          console.error('Login error:', err);
        }
      });
    }

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', async () => {
        try {
          await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=logout'
          });

          isAdmin = false;
          updateAdminUI();
          window.location.reload();
        } catch (err) {
          console.error('Logout error:', err);
        }
      });
    }

    function toggleDrawer(open) {
      const drawer = document.getElementById('adminDrawer');
      if (drawer) {
        if (open) drawer.classList.add('open');
        else drawer.classList.remove('open');
      }
    }

    const formStats = document.getElementById('formStats');
    if (formStats) {
      formStats.addEventListener('submit', (e) => {
        e.preventDefault();
        const siswa = document.getElementById('inputSiswa').value;
        const tk = document.getElementById('inputTK').value;
        const smp = document.getElementById('inputSMP').value;

        statsData.siswa = parseInt(siswa) || 0;
        statsData.tk = parseInt(tk) || 0;
        statsData.smp = parseInt(smp) || 0;

        localStorage.setItem('ar_stats', JSON.stringify(statsData));
        renderStats();
        alert('Statistik berhasil diperbarui!');
      });
    }

    const newsImgInput = document.getElementById('newsImg');
    const newsImgPreview = document.getElementById('newsImgPreview');
    if (newsImgInput) {
      newsImgInput.addEventListener('change', () => {
        const file = newsImgInput.files[0];
        if (file) {
          newsImgPreview.src = URL.createObjectURL(file);
          newsImgPreview.style.display = 'block';
        } else {
          newsImgPreview.style.display = 'none';
        }
      });
    }

    const formNews = document.getElementById('formNews');
    if (formNews) {
      formNews.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fileInput = document.getElementById('newsImg');
        const file = fileInput.files[0];
        if (!file) { alert('Silakan pilih gambar terlebih dahulu.'); return; }

        const btnSubmit = document.getElementById('btnSubmitNews');
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Mengunggah...';

        try {
          const formData = new FormData();
          formData.append('action', 'upload_image');
          formData.append('image', file);

          const uploadRes = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            body: formData
          });
          const uploadResult = await uploadRes.json();

          if (!uploadResult.success) {
            alert(uploadResult.error || 'Gagal mengunggah gambar.');
            return;
          }

          const newArticle = {
            tag: document.getElementById('newsTag').value,
            title: document.getElementById('newsTitle').value,
            date: document.getElementById('newsDate').value,
            desc: document.getElementById('newsDesc').value,
            img: uploadResult.url,
          };

          newsData.unshift(newArticle);
          localStorage.setItem('ar_news', JSON.stringify(newsData));
          renderNews();
          formNews.reset();
          newsImgPreview.style.display = 'none';
          alert('Berita berhasil ditambahkan!');
        } catch (err) {
          console.error('Upload error:', err);
          alert('Terjadi kesalahan saat mengunggah gambar.');
        } finally {
          btnSubmit.disabled = false;
          btnSubmit.textContent = 'Tambah Berita';
        }
      });
    }

    async function deleteNews(index) {
      if (!isAdmin) {
        alert('Anda harus login sebagai admin untuk menghapus berita.');
        return;
      }

      const item = newsData[index];
      if (!item) return;

      if (!confirm('Apakah Anda yakin ingin menghapus berita ini?\nGambar yang tersimpan di server juga akan dihapus.')) {
        return;
      }

      const btn = document.querySelector(`.btn-danger[data-delete-index="${index}"]`);
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Menghapus...';
      }

      if (item.img && item.img.indexOf('uploads/berita/') === 0) {
        try {
          const fd = new FormData();
          fd.append('action', 'delete_image');
          fd.append('image_url', item.img);

          const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            body: fd
          });
          const result = await res.json();

          if (!result.success) {
            console.warn('Gagal hapus file gambar:', result.error);
          }
        } catch (err) {
          console.error('Error hapus gambar:', err);
        }
      }

      newsData.splice(index, 1);
      localStorage.setItem('ar_news', JSON.stringify(newsData));
      renderNews();

      alert('Berita dan gambar berhasil dihapus.');
    }

    document.addEventListener('DOMContentLoaded', function() {
      renderStats();
      renderNews();
      updateAdminUI();
      initNewsCarousel();
    });

    (function initPillarHint() {
      const hint = document.getElementById('pillarHint');
      if (!hint) return;

      hint.style.display = 'inline-flex';
      try { sessionStorage.removeItem('ar_pillar_hint_dismissed'); } catch(e) {}

      function scrollToPillars() {
        const target = document.getElementById('pillarsNav');

        if (!target) {
          window.location.href = 'index.php#pillarsNav';
          return;
        }

        const headerOffset = 90;
        const targetY = target.getBoundingClientRect().top + window.pageYOffset - headerOffset;

        window.scrollTo({ top: targetY, behavior: 'smooth' });

        target.classList.remove('flash');
        void target.offsetWidth;
        target.classList.add('flash');
        setTimeout(() => target.classList.remove('flash'), 1300);
      }

      hint.addEventListener('click', scrollToPillars);
      hint.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          scrollToPillars();
        }
      });

      if (window.location.hash === '#pillarsNav') {
        setTimeout(scrollToPillars, 300);
      }
    })();

    const IDX_STORAGE_KEY = 'ar_index_content';
    const idxEditableFields = document.querySelectorAll('[data-field]');
    const idxImgWraps = document.querySelectorAll('.img-edit-wrap[data-img-key]');

    function loadIndexContent() {
      let saved = null;
      try { saved = JSON.parse(localStorage.getItem(IDX_STORAGE_KEY)); } catch (e) { saved = null; }
      if (!saved) return;

      if (saved.fields) {
        idxEditableFields.forEach(el => {
          const key = el.dataset.field;
          if (Object.prototype.hasOwnProperty.call(saved.fields, key)) {
            el.innerHTML = saved.fields[key];
          }
        });
      }

      if (saved.images) {
        idxImgWraps.forEach(wrap => {
          const key = wrap.dataset.imgKey;
          const img = wrap.querySelector('img');
          if (img && Object.prototype.hasOwnProperty.call(saved.images, key)) {
            img.src = saved.images[key];
          }
        });
      }
    }

    function collectIndexFields() {
      const data = {};
      idxEditableFields.forEach(el => {
        data[el.dataset.field] = el.innerHTML.trim();
      });
      return data;
    }

    function collectIndexImages() {
      let saved = null;
      try { saved = JSON.parse(localStorage.getItem(IDX_STORAGE_KEY)); } catch (e) { saved = null; }
      const images = (saved && saved.images) ? saved.images : {};
      idxImgWraps.forEach(wrap => {
        const key = wrap.dataset.imgKey;
        const img = wrap.querySelector('img');
        if (img && img.dataset.pendingSrc) {
          images[key] = img.dataset.pendingSrc;
        }
      });
      return images;
    }

    function setIndexEditMode(active) {
      idxEditableFields.forEach(el => {
        el.setAttribute('contenteditable', active ? 'true' : 'false');
      });
      idxImgWraps.forEach(wrap => {
        wrap.classList.toggle('edit-active', active);
      });
    }

    idxImgWraps.forEach(wrap => {
      const fileInput = wrap.querySelector('.img-edit-input');
      const img = wrap.querySelector('img');
      if (fileInput && img) {
        fileInput.addEventListener('change', function() {
          const file = fileInput.files && fileInput.files[0];
          if (!file) return;
          if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran gambar terlalu besar. Maksimal 2MB.');
            fileInput.value = '';
            return;
          }
          const reader = new FileReader();
          reader.onload = function(e) {
            img.src = e.target.result;
            img.dataset.pendingSrc = e.target.result;
            const key = wrap.dataset.imgKey;
            document.querySelectorAll('.img-edit-wrap[data-img-key="' + key + '"] img').forEach(otherImg => {
              otherImg.src = e.target.result;
              otherImg.dataset.pendingSrc = e.target.result;
            });
          };
          reader.readAsDataURL(file);
        });
      }
    });

    const btnToggleEdit = document.getElementById('btnToggleEdit');
    const btnSaveContent = document.getElementById('btnSaveContent');
    let idxEditMode = false;

    if (btnToggleEdit && btnSaveContent) {
      btnToggleEdit.addEventListener('click', function() {
        idxEditMode = !idxEditMode;
        setIndexEditMode(idxEditMode);
        btnToggleEdit.textContent = idxEditMode ? 'Batalkan Edit' : 'Edit Konten Halaman';
        btnSaveContent.style.display = idxEditMode ? 'inline-block' : 'none';
        if (!idxEditMode) {
          idxImgWraps.forEach(wrap => {
            const img = wrap.querySelector('img');
            if (img) delete img.dataset.pendingSrc;
          });
          loadIndexContent();
        }
      });

      btnSaveContent.addEventListener('click', function() {
        const data = {
          fields: collectIndexFields(),
          images: collectIndexImages()
        };
        try {
          localStorage.setItem(IDX_STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
          console.error('Gagal menyimpan konten:', e);
          alert('Gagal menyimpan: penyimpanan browser penuh. Coba gunakan gambar berukuran lebih kecil.');
          return;
        }
        idxImgWraps.forEach(wrap => {
          const img = wrap.querySelector('img');
          if (img) delete img.dataset.pendingSrc;
        });
        idxEditMode = false;
        setIndexEditMode(false);
        btnToggleEdit.textContent = 'Edit Konten Halaman';
        btnSaveContent.style.display = 'none';
        alert('Perubahan konten halaman utama berhasil disimpan.');
      });
    }

    loadIndexContent();
  </script>
</body>
</html>