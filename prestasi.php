<?php
session_start();

// ============================================================
// KONFIGURASI DASAR
// ============================================================
$isAdmin      = isset($_SESSION['ar_is_admin']) && $_SESSION['ar_is_admin'] === true;
$SELF         = basename(__FILE__);
$uploadDir    = __DIR__ . '/uploads/prestasi/';
$uploadUrl    = 'uploads/prestasi/';
$dataFile     = $uploadDir . 'prestasi_data.json';
$bakFile      = $uploadDir . 'prestasi_data.bak.json';

function ar_json($arr) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ar_ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return is_dir($dir) && is_writable($dir);
}

// Deteksi POST yang dibuang PHP karena melebihi post_max_size
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST) && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    ar_json([
        'success' => false,
        'error'   => 'Data terlalu besar untuk server (melebihi post_max_size = '
                   . ini_get('post_max_size') . '). Perbesar post_max_size & upload_max_filesize di php.ini.'
    ]);
}

// Baca data prestasi dengan fallback ke backup
function ar_read_data($dataFile, $bakFile) {
    $empty = ['fields' => [], 'images' => [], 'extra' => [], 'deleted' => []];
    foreach ([$dataFile, $bakFile] as $f) {
        if (!file_exists($f)) continue;
        $c = file_get_contents($f);
        if ($c === false || trim($c) === '') continue;
        $d = json_decode($c, true);
        if (is_array($d) && isset($d['fields'])) {
            return [
                'fields'  => isset($d['fields'])  && is_array($d['fields'])  ? $d['fields']  : [],
                'images'  => isset($d['images'])  && is_array($d['images'])  ? $d['images']  : [],
                'extra'   => isset($d['extra'])   && is_array($d['extra'])   ? $d['extra']   : [],
                'deleted' => isset($d['deleted']) && is_array($d['deleted']) ? $d['deleted'] : [],
            ];
        }
    }
    return $empty;
}

// Tulis data secara atomic (tulis ke file sementara lalu rename)
function ar_write_data(array $data, $dataFile, $bakFile) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return 'Gagal encode JSON: ' . json_last_error_msg();

    if (file_exists($dataFile)) @copy($dataFile, $bakFile);

    $tmp = $dataFile . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        return 'Gagal menulis file sementara. Cek permission folder uploads/prestasi/.';
    }
    $check = json_decode(file_get_contents($tmp), true);
    if (!is_array($check)) {
        @unlink($tmp);
        return 'Verifikasi gagal: hasil tulisan tidak valid. Perubahan dibatalkan.';
    }
    if (!@rename($tmp, $dataFile)) {
        @unlink($tmp);
        return 'Gagal memindahkan file sementara ke prestasi_data.json.';
    }
    @chmod($dataFile, 0644);
    return true;
}

$ACTION = $_POST['action'] ?? $_GET['action'] ?? '';

// ============================================================
// LOGOUT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ACTION === 'logout') {
    $_SESSION = [];
    session_unset();
    session_destroy();
    ar_json(['success' => true]);
}

// ============================================================
// CEK STATUS ADMIN
// ============================================================
if (isset($_GET['check_admin'])) {
    ar_json(['isAdmin' => $isAdmin]);
}

// ============================================================
// UPLOAD FOTO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ACTION === 'upload_image') {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    if (!isset($_FILES['image'])) {
        ar_json(['success' => false, 'error' => 'Tidak ada file yang terunggah.']);
    }
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE  => 'File melebihi upload_max_filesize di php.ini (' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE => 'File melebihi batas form.',
            UPLOAD_ERR_PARTIAL   => 'File hanya terunggah sebagian, koneksi terputus.',
            UPLOAD_ERR_NO_FILE   => 'Tidak ada file dipilih.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Folder sementara server tidak ada.',
            UPLOAD_ERR_CANT_WRITE=> 'Server gagal menulis file ke disk.',
        ];
        $e = $_FILES['image']['error'];
        ar_json(['success' => false, 'error' => $map[$e] ?? ('Upload gagal (kode ' . $e . ').')]);
    }

    $file = $_FILES['image'];

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        ar_json(['success' => false, 'error' => 'Format file harus JPG, PNG, WEBP, atau GIF.']);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        ar_json(['success' => false, 'error' => 'Ukuran gambar maksimal 5MB.']);
    }
    if (!ar_ensure_dir($uploadDir)) {
        ar_json(['success' => false, 'error' => 'Folder uploads/prestasi/ tidak ada atau tidak bisa ditulis (chmod 755).']);
    }

    $filename = 'prestasi_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest     = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        @chmod($dest, 0644);
        ar_json(['success' => true, 'url' => $uploadUrl . $filename]);
    }
    ar_json(['success' => false, 'error' => 'Gagal menyimpan gambar ke server.']);
}

// ============================================================
// HAPUS GAMBAR FISIK DI SERVER
// Hanya boleh hapus file di dalam uploads/prestasi/ (proteksi path traversal)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ACTION === 'delete_image') {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    $imageUrl = trim($_POST['image_url'] ?? '');
    if ($imageUrl === '') {
        ar_json(['success' => false, 'error' => 'URL gambar kosong.']);
    }

    // Hanya izinkan hapus gambar yang berada di folder uploads/prestasi/
    $prefix = 'uploads/prestasi/';
    if (strpos($imageUrl, $prefix) !== 0) {
        ar_json(['success' => false, 'error' => 'Hanya gambar di folder uploads/prestasi/ yang dapat dihapus.']);
    }

    $relative = substr($imageUrl, strlen($prefix));

    // Validasi nama file (hanya alfanumerik, underscore, dash, titik)
    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $relative)) {
        ar_json(['success' => false, 'error' => 'Nama file tidak valid.']);
    }

    // Cegah path traversal (.. / dsb)
    $relative = basename($relative);

    $fullPath = __DIR__ . '/' . $prefix . $relative;

    if (!file_exists($fullPath)) {
        // Sudah tidak ada → anggap berhasil (idempotent)
        ar_json(['success' => true, 'message' => 'File sudah tidak ada.']);
    }

    if (!is_file($fullPath)) {
        ar_json(['success' => false, 'error' => 'Bukan file.']);
    }

    if (@unlink($fullPath)) {
        ar_json(['success' => true]);
    } else {
        ar_json(['success' => false, 'error' => 'Gagal menghapus file. Cek permission folder uploads/prestasi/.']);
    }
}

// ============================================================
// AMBIL DATA PRESTASI (semua orang boleh baca)
// ============================================================
if ($ACTION === 'get_data') {
    $data = ar_read_data($dataFile, $bakFile);
    ar_json([
        'fields'  => (object) $data['fields'],
        'images'  => (object) $data['images'],
        'extra'   => array_values($data['extra']),
        'deleted' => array_values($data['deleted']),
    ]);
}

// ============================================================
// SIMPAN DATA PRESTASI (hanya admin)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ACTION === 'save_data') {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    $raw = $_POST['data'] ?? '';
    if ($raw === '') ar_json(['success' => false, 'error' => 'Data kosong tidak dikirim.']);

    $incoming = json_decode($raw, true);
    if (!is_array($incoming)) {
        ar_json(['success' => false, 'error' => 'Data tidak valid: ' . json_last_error_msg()]);
    }

    $existing = ar_read_data($dataFile, $bakFile);

    $inFields = isset($incoming['fields']) && is_array($incoming['fields']) ? $incoming['fields'] : [];
    $inImages = isset($incoming['images']) && is_array($incoming['images']) ? $incoming['images'] : [];

    $merged = [
        'fields'  => array_merge($existing['fields'], $inFields),
        'images'  => array_merge($existing['images'], $inImages),
        'extra'   => (isset($incoming['extra']) && is_array($incoming['extra']))
                        ? array_values($incoming['extra'])
                        : array_values($existing['extra']),
        'deleted' => (isset($incoming['deleted']) && is_array($incoming['deleted']))
                        ? array_values(array_unique($incoming['deleted']))
                        : array_values($existing['deleted']),
    ];

    foreach (['fields', 'images'] as $sec) {
        foreach ($merged[$sec] as $k => $v) {
            if ($v === null || $v === '') unset($merged[$sec][$k]);
        }
    }

    if (isset($incoming['extra']) && is_array($incoming['extra'])) {
        $idAktif = [];
        foreach ($merged['extra'] as $t) {
            if (is_array($t) && !empty($t['id'])) $idAktif[$t['id']] = true;
        }
        foreach (['fields', 'images'] as $sec) {
            foreach ($merged[$sec] as $k => $v) {
                if (preg_match('/^(p_[a-z0-9]+_[a-z0-9]+)_(badge|title|desc|label1|list1|label2|list2|img)$/', $k, $m)) {
                    if (!isset($idAktif[$m[1]])) unset($merged[$sec][$k]);
                }
            }
        }
    }

    foreach ($merged['extra'] as $i => $t) {
        if (!is_array($t) || empty($t['id'])) continue;
        foreach (['badge', 'title', 'desc', 'label1', 'list1', 'label2', 'list2'] as $sfx) {
            $key = $t['id'] . '_' . $sfx;
            if (isset($merged['fields'][$key]) && $merged['fields'][$key] !== '') {
                $merged['extra'][$i][$sfx] = $merged['fields'][$key];
            } elseif (isset($t[$sfx]) && $t[$sfx] !== '') {
                $merged['fields'][$key] = $t[$sfx];
            }
        }
    }

    $res = ar_write_data($merged, $dataFile, $bakFile);
    if ($res !== true) ar_json(['success' => false, 'error' => $res]);

    ar_json(['success' => true]);
}

// ============================================================
// SEMBUNYIKAN / TAMPILKAN KEMBALI PRESTASI BAWAAN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($ACTION === 'delete_default_prestasi' || $ACTION === 'restore_default_prestasi')) {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    $key = trim($_POST['prestasi_key'] ?? '');
    if ($key === '' || !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
        ar_json(['success' => false, 'error' => 'Key prestasi tidak valid.']);
    }

    $data = ar_read_data($dataFile, $bakFile);
    $list = $data['deleted'];

    if ($ACTION === 'delete_default_prestasi') {
        if (!in_array($key, $list, true)) $list[] = $key;
    } else {
        $list = array_values(array_diff($list, [$key]));
    }
    $data['deleted'] = array_values(array_unique($list));

    $res = ar_write_data($data, $dataFile, $bakFile);
    if ($res !== true) ar_json(['success' => false, 'error' => $res]);

    ar_json(['success' => true, 'deleted' => $data['deleted']]);
}

$INITIAL = ar_read_data($dataFile, $bakFile);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prestasi Siswa - Yayasan Ar-Rahmah</title>
  
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

    .card-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
      align-items: start;
    }

    .achievement-card {
      background: var(--white);
      border-radius: 18px;
      border: 1px solid var(--border);
      overflow: hidden;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .achievement-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px -18px rgba(43,33,24,0.4);
    }

    .achievement-img {
      height: 280px;
      background: #ffffff;
      overflow: hidden;
      box-sizing: border-box;
      padding: 0;
      border-bottom: 1px solid var(--border);
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .achievement-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      transition: transform 0.4s ease;
    }

    .achievement-card:hover .achievement-img img {
      transform: scale(1.04);
    }
    
    .img-edit-wrap {
      position: relative;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .img-edit-btn {
      display: none;
      position: absolute;
      right: 6px;
      bottom: 6px;
      z-index: 5;
      background: var(--orange);
      color: var(--white);
      border: none;
      border-radius: 100px;
      padding: 7px 13px;
      font-size: 12px;
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

    .achievement-body {
      padding: 26px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .achievement-body h3 {
      font-family: 'Amiri', serif;
      color: var(--green);
      font-size: 24px;
      margin: 0 0 10px 0;
    }

    .achievement-body p {
      color: var(--ink-soft);
      font-size: 14.5px;
      line-height: 1.6;
      margin: 0;
    }

    .badge-tag {
      display: inline-block;
      align-self: flex-start;
      background: var(--green-pale);
      color: var(--green);
      font-size: 12px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 50px;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .achievement-detail {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), 
                  opacity 0.4s ease, 
                  margin-top 0.3s ease, 
                  padding-top 0.3s ease;
    }

    .achievement-card.active .achievement-detail {
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

    .btn-danger {
      background: #d9534f;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 12.5px;
      font-weight: 700;
      margin-top: 10px;
      width: 100%;
    }

    .btn-danger:hover { background: #c9302c; }
    .btn-danger:disabled { opacity: 0.6; cursor: not-allowed; }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.65);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 2000;
      backdrop-filter: blur(5px);
      padding: 20px;
    }

    .modal-overlay.active { display: flex; }

    .modal-box {
      background: var(--white);
      border-radius: var(--radius);
      padding: 32px;
      width: 100%;
      max-width: 460px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
    }

    .modal-box-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .modal-box h3 { color: var(--green); font-size: 22px; }

    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--ink); }
    .form-group .hint { display: block; font-size: 11.5px; color: var(--ink-soft); margin-top: 4px; }

    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s;
      box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group textarea:focus { border-color: var(--orange); }

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

    .page-loaded header .nav-wrap { animation: slideDown 0.6s ease forwards; }

    @keyframes slideDown {
      0% { opacity: 0; transform: translateY(-20px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    .page-loaded .section-head {
      animation: fadeDownSection 0.8s ease both;
    }
    @keyframes fadeDownSection {
      0% { opacity: 0; transform: translateY(-24px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    .page-loaded .action-bar {
      animation: fadeInAction 0.7s ease 0.15s both;
    }
    @keyframes fadeInAction {
      0% { opacity: 0; transform: translateY(10px); }
      100% { opacity: 1; transform: translateY(0); }
    }

    .page-loaded .card-grid .achievement-card {
      animation: cardEntrance 0.75s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    .page-loaded .card-grid .achievement-card:nth-child(1) { animation-delay: 0.30s; }
    .page-loaded .card-grid .achievement-card:nth-child(2) { animation-delay: 0.45s; }
    .page-loaded .card-grid .achievement-card:nth-child(3) { animation-delay: 0.60s; }
    .page-loaded .card-grid .achievement-card:nth-child(4) { animation-delay: 0.75s; }
    .page-loaded .card-grid .achievement-card:nth-child(5) { animation-delay: 0.90s; }
    .page-loaded .card-grid .achievement-card:nth-child(6) { animation-delay: 1.05s; }

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
      .page-loaded .card-grid .achievement-card {
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

    @media (max-width: 980px) {
      .card-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 720px) {
      .menu-toggle { display: flex; }
      .section-head h1 { font-size: 32px; }
      .card-grid { grid-template-columns: 1fr; }
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

  <section>
    <div class="container">
      <div class="section-head reveal">
        <span class="eyebrow">Capaian &amp; Kebanggaan</span>
        <h1 data-field="psTitle">Prestasi Siswa-Siswi Ar-Rahmah</h1>
        <p data-field="psSubtitle">Apresiasi atas semangat, dedikasi, dan pencapaian luar biasa santri serta siswa-siswi Yayasan Ar-Rahmah dalam bidang keagamaan, akademik, dan keahlian.</p>
      </div>

      <div class="action-bar reveal">
        <a href="index.php#beranda" class="nav-cta">Kembali ke Beranda</a>
        <?php if ($isAdmin): ?>
          <a href="index.php#beranda" class="nav-cta nav-cta-green">Mode Admin Aktif</a>
          <button type="button" id="btnToggleEdit" class="nav-cta">Edit Konten Halaman</button>
          <button type="button" id="btnSaveContent" class="nav-cta nav-cta-green" style="display:none;">Simpan Perubahan</button>
          <button type="button" id="btnAddPrestasi" class="nav-cta nav-cta-green">+ Tambah Prestasi</button>
        <?php endif; ?>
      </div>

      <div class="card-grid" id="cardGrid">
        <div class="achievement-card reveal-scale default-prestasi" data-default-key="ach1">
          <div class="achievement-img">
            <div class="img-edit-wrap" data-img-key="ach1Img">
              <img src="logoyayasan.png" alt="">
              <?php if ($isAdmin): ?>
              <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
              <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
              <?php endif; ?>
            </div>
          </div>
          <div class="achievement-body">
            <span class="badge-tag" data-field="ach1Badge">Keagamaan</span>
            <h3 data-field="ach1Title">Juara Tahfidz Al-Qur'an</h3>
            <p data-field="ach1Desc">Capaian membanggakan para santri dalam Musabaqah Hifdzil Qur'an (MHQ) tingkat kabupaten dan provinsi.</p>
            
            <div class="achievement-detail">
              <div class="detail-section-title" data-field="ach1Label1">Pencapaian Utama:</div>
              <ul class="detail-list" data-field="ach1List1">
                <li>Juara 1 MHQ Kategori Juz 'Amma (TK Ar-Rahmah)</li>
                <li>Juara 2 MHQ Kategori 5 Juz (SMP Ar-Rahmah)</li>
              </ul>
              <div class="detail-section-title" data-field="ach1Label2">Apresiasi Yayasan:</div>
              <ul class="detail-list" data-field="ach1List2">
                <li>Beasiswa Bebas SPP Pendidikan Full</li>
                <li>Piagam &amp; Tabungan Pembinaan Santri</li>
              </ul>
            </div>

            <button class="btn-detail" onclick="toggleDetail(this, 'Tahfidz')">
              <span>Lihat Detail Prestasi</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <?php if ($isAdmin): ?>
            <button type="button" class="btn-danger" onclick="deleteDefaultPrestasi('ach1')">Hapus Prestasi</button>
            <?php endif; ?>
          </div>
        </div>

        <div class="achievement-card reveal-scale default-prestasi" data-default-key="ach2">
          <div class="achievement-img">
            <div class="img-edit-wrap" data-img-key="ach2Img">
              <img src="logosmp.jpeg" alt="">
              <?php if ($isAdmin): ?>
              <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
              <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
              <?php endif; ?>
            </div>
          </div>
          <div class="achievement-body">
            <span class="badge-tag" data-field="ach2Badge">Akademik</span>
            <h3 data-field="ach2Title">Olimpiade Sains &amp; Matematika</h3>
            <p data-field="ach2Desc">Keberhasilan siswa SMP Ar-Rahmah meraih medali dalam ajang kompetisi sains dan matematika antar-sekolah.</p>
            
            <div class="achievement-detail">
              <div class="detail-section-title" data-field="ach2Label1">Pencapaian Utama:</div>
              <ul class="detail-list" data-field="ach2List1">
                <li>Medali Emas Olimpiade Matematika Terpadu</li>
                <li>Juara Harapan 1 Kompetisi Sains (IPA)</li>
              </ul>
              <div class="detail-section-title" data-field="ach2Label2">Program Pendukung:</div>
              <ul class="detail-list" data-field="ach2List2">
                <li>Bimbingan Intensif Klub Sains Ar-Rahmah</li>
                <li>Pelatihan Pembahasan Soal-Soal Standar Nasional</li>
              </ul>
            </div>

            <button class="btn-detail" onclick="toggleDetail(this, 'Sains')">
              <span>Lihat Detail Prestasi</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <?php if ($isAdmin): ?>
            <button type="button" class="btn-danger" onclick="deleteDefaultPrestasi('ach2')">Hapus Prestasi</button>
            <?php endif; ?>
          </div>
        </div>

        <div class="achievement-card reveal-scale default-prestasi" data-default-key="ach3">
          <div class="achievement-img">
            <div class="img-edit-wrap" data-img-key="ach3Img">
              <img src="logosmp.jpeg" alt="">
              <?php if ($isAdmin): ?>
              <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
              <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
              <?php endif; ?>
            </div>
          </div>
          <div class="achievement-body">
            <span class="badge-tag" data-field="ach3Badge">Teknologi &amp; Vokasi</span>
            <h3 data-field="ach3Title">Lomba Teknologi &amp; IT</h3>
            <p data-field="ach3Desc">Penghargaan siswa SMK Ar-Rahmah dalam Lomba Kompetensi Siswa (LKS) bidang Desain Web &amp; Jaringan.</p>
            
            <div class="achievement-detail">
              <div class="detail-section-title" data-field="ach3Label1">Pencapaian Utama:</div>
              <ul class="detail-list" data-field="ach3List1">
                <li>Juara 1 Web Design &amp; Development</li>
                <li>Sertifikasi Kompetensi Keahlian IT Nasional</li>
              </ul>
              <div class="detail-section-title" data-field="ach3Label2">Karya Unggulan:</div>
              <ul class="detail-list" data-field="ach3List2">
                <li>Aplikasi Web Platform Digital Desa &amp; Yayasan</li>
                <li>Sistem Informasi Portofolio Sekolah</li>
              </ul>
            </div>

            <button class="btn-detail" onclick="toggleDetail(this, 'IT')">
              <span>Lihat Detail Prestasi</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <?php if ($isAdmin): ?>
            <button type="button" class="btn-danger" onclick="deleteDefaultPrestasi('ach3')">Hapus Prestasi</button>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </section>

  <div class="modal-overlay" id="addPrestasiModal">
    <div class="modal-box">
      <div class="modal-box-header">
        <h3>Tambah Prestasi</h3>
        <button type="button" onclick="closeAddPrestasiModal()" style="border:none; background:none; cursor:pointer; font-size:22px; color:var(--ink-soft);">✕</button>
      </div>
      <form id="formAddPrestasi">
        <div class="form-group">
          <label>Kategori / Badge</label>
          <input type="text" id="npBadge" placeholder="Keagamaan / Akademik / Vokasi" required>
        </div>
        <div class="form-group">
          <label>Judul Prestasi</label>
          <input type="text" id="npTitle" placeholder="Judul prestasi" required>
        </div>
        <div class="form-group">
          <label>Deskripsi Singkat</label>
          <textarea id="npDesc" rows="2" placeholder="Ringkasan singkat prestasi..." required></textarea>
        </div>
        <div class="form-group">
          <label>Label Bagian 1</label>
          <input type="text" id="npLabel1" placeholder="Contoh: Pencapaian Utama" required>
        </div>
        <div class="form-group">
          <label>Daftar Bagian 1</label>
          <textarea id="npList1" rows="3" placeholder="Satu baris untuk satu poin" required></textarea>
          <span class="hint">Tulis tiap poin di baris baru.</span>
        </div>
        <div class="form-group">
          <label>Label Bagian 2</label>
          <input type="text" id="npLabel2" placeholder="Contoh: Apresiasi Yayasan" required>
        </div>
        <div class="form-group">
          <label>Daftar Bagian 2</label>
          <textarea id="npList2" rows="3" placeholder="Satu baris untuk satu poin" required></textarea>
          <span class="hint">Tulis tiap poin di baris baru.</span>
        </div>
        <div class="form-group">
          <label>Foto Prestasi</label>
          <input type="file" id="npImg" accept="image/jpeg,image/png,image/webp,image/gif" required>
          <img id="npImgPreview" style="display:none; margin-top:8px; max-height:140px; border-radius:8px; border:1px solid var(--border);">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:10px;" id="btnSubmitPrestasi">Tambah Prestasi</button>
      </form>
    </div>
  </div>

  <div class="admin-panel" id="adminPanel">
    <span style="font-size: 13px;"><strong>Mode Admin Active</strong></span>
    <button class="btn btn-ghost" id="logoutBtn">Logout</button>
  </div>

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

  <script>
    const AR_ENDPOINT = <?php echo json_encode($SELF, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    let isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    let editModeActive = false;

    let serverData = Object.assign(
      { fields: {}, images: {}, extra: [], deleted: [] },
      <?php echo json_encode([
          'fields'  => (object) $INITIAL['fields'],
          'images'  => (object) $INITIAL['images'],
          'extra'   => array_values($INITIAL['extra']),
          'deleted' => array_values($INITIAL['deleted']),
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
    );

    function arPost(fields) {
      const fd = new FormData();
      Object.keys(fields).forEach(k => fd.append(k, fields[k]));
      return fetch(AR_ENDPOINT, { method: 'POST', body: fd }).then(async res => {
        const text = await res.text();
        try { return JSON.parse(text); }
        catch (e) {
          console.error('Respon server bukan JSON:', text.slice(0, 300));
          return { success: false, error: 'Server tidak mengembalikan JSON. Cek Console.' };
        }
      }).catch(err => {
        console.error('Koneksi gagal:', err);
        return { success: false, error: 'Koneksi ke server gagal.' };
      });
    }

    async function arUploadFile(file) {
      const fd = new FormData();
      fd.append('action', 'upload_image');
      fd.append('image', file);
      try {
        const res  = await fetch(AR_ENDPOINT, { method: 'POST', body: fd });
        const text = await res.text();
        try { return JSON.parse(text); }
        catch (e) {
          return { success: false, error: 'Upload ditolak server.' };
        }
      } catch (err) {
        return { success: false, error: 'Koneksi gagal saat mengunggah foto.' };
      }
    }

    // ============================================================
    // HAPUS FILE GAMBAR DI SERVER
    // ============================================================
    async function arDeleteImage(imageUrl) {
      if (!imageUrl || imageUrl.indexOf('uploads/prestasi/') !== 0) {
        return { success: true, skipped: true };
      }
      try {
        const fd = new FormData();
        fd.append('action', 'delete_image');
        fd.append('image_url', imageUrl);
        const res = await fetch(AR_ENDPOINT, { method: 'POST', body: fd });
        const text = await res.text();
        try { return JSON.parse(text); }
        catch (e) {
          return { success: false, error: 'Respon server bukan JSON.' };
        }
      } catch (err) {
        return { success: false, error: 'Koneksi gagal saat menghapus gambar.' };
      }
    }

    function updateAdminUI() {
      const adminPanel = document.getElementById('adminPanel');
      if (adminPanel) adminPanel.classList.toggle('active', isAdmin);
      document.querySelectorAll('.default-prestasi .btn-danger').forEach(btn => {
        btn.style.display = isAdmin ? 'block' : 'none';
      });
    }

    async function refreshDataFromServer() {
      try {
        const res  = await fetch(AR_ENDPOINT + '?action=get_data', { cache: 'no-store' });
        const data = await res.json();
        serverData = {
          fields:  (data && data.fields) || {},
          images:  (data && data.images) || {},
          extra:   (data && Array.isArray(data.extra)) ? data.extra : [],
          deleted: (data && Array.isArray(data.deleted)) ? data.deleted : []
        };
      } catch (err) {
        console.error('Gagal memuat data:', err);
      }
      return serverData;
    }

    function saveDataToServer() {
      return arPost({ action: 'save_data', data: JSON.stringify(serverData) });
    }

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        if (editModeActive && !confirm('Mode edit masih aktif. Keluar tanpa menyimpan?')) return;
        await arPost({ action: 'logout' });
        window.location.href = 'index.php';
      });
    }

    const header = document.getElementById('mainHeader');
    window.addEventListener('scroll', function() {
      if (header) header.classList.toggle('scrolled', window.scrollY > 50);
    });

    function toggleDetail(btn, unit) {
      const card = btn.closest('.achievement-card');
      const isExpanded = card.classList.contains('active');
      const textSpan = btn.querySelector('span');
      const iconSvg = btn.querySelector('svg');
      
      if (isExpanded) {
        card.classList.remove('active');
        textSpan.innerText = 'Lihat Detail Prestasi';
        if (iconSvg) iconSvg.style.transform = 'rotate(0deg)';
      } else {
        document.querySelectorAll('.achievement-card.active').forEach(c => {
          if (c !== card) {
            c.classList.remove('active');
            const otherBtn = c.querySelector('.btn-detail');
            if (otherBtn) {
              const otherSpan = otherBtn.querySelector('span');
              const otherIcon = otherBtn.querySelector('svg');
              if (otherSpan) otherSpan.innerText = 'Lihat Detail Prestasi';
              if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
            }
          }
        });
        card.classList.add('active');
        textSpan.innerText = 'Tutup Detail';
        if (iconSvg) iconSvg.style.transform = 'rotate(180deg)';
      }
    }

    function applyDeletedDefaults() {
      const deleted = serverData.deleted || [];
      document.querySelectorAll('.default-prestasi[data-default-key]').forEach(card => {
        const key = card.dataset.defaultKey;
        card.style.display = deleted.indexOf(key) !== -1 ? 'none' : '';
      });
    }

    // ============================================================
    // HAPUS PRESTASI BAWAAN + HAPUS GAMBAR FISIK (KALAU ADA)
    // ============================================================
    async function deleteDefaultPrestasi(prestasiKey) {
      if (!isAdmin) { alert('Anda harus login sebagai admin untuk menghapus prestasi.'); return; }
      if (!confirm('Sembunyikan prestasi ini dari halaman? Bisa ditampilkan lagi nanti.')) return;

      // Ambil URL gambar dari server data (kalau ada di uploads/prestasi/)
      const imgKey = prestasiKey + 'Img';
      const imgUrl = serverData.images && serverData.images[imgKey];

      const result = await arPost({ action: 'delete_default_prestasi', prestasi_key: prestasiKey });
      if (!result.success) { alert(result.error || 'Gagal menghapus prestasi.'); return; }

      // Hapus file fisik gambar bawaan (kalau ada)
      if (imgUrl) {
        const delRes = await arDeleteImage(imgUrl);
        if (!delRes.success && !delRes.skipped) {
          console.warn('Gagal hapus gambar:', delRes.error);
        } else if (!delRes.skipped) {
          // Hapus referensi dari serverData.images
          delete serverData.images[imgKey];
        }
      }

      serverData.deleted = result.deleted || [];
      applyDeletedDefaults();
      alert('Prestasi berhasil disembunyikan.');
    }

    async function restoreDefaultPrestasi(prestasiKey) {
      if (!isAdmin) return;
      const result = await arPost({ action: 'restore_default_prestasi', prestasi_key: prestasiKey });
      if (!result.success) { alert(result.error || 'Gagal memulihkan prestasi.'); return; }
      serverData.deleted = result.deleted || [];
      applyDeletedDefaults();
    }

    function getAllEditableFields() { return document.querySelectorAll('[data-field]'); }
    function getAllImgWraps() { return document.querySelectorAll('.img-edit-wrap[data-img-key]'); }

    function loadContent() {
      getAllEditableFields().forEach(el => {
        const key = el.dataset.field;
        if (serverData.fields && Object.prototype.hasOwnProperty.call(serverData.fields, key)) {
          el.innerHTML = serverData.fields[key];
        }
      });
      getAllImgWraps().forEach(wrap => {
        const key = wrap.dataset.imgKey;
        const img = wrap.querySelector('img');
        if (img && serverData.images && Object.prototype.hasOwnProperty.call(serverData.images, key)) {
          const val = serverData.images[key];
          if (val) img.src = val;
        }
      });
    }

    function collectFields() {
      const data = Object.assign({}, serverData.fields || {});
      getAllEditableFields().forEach(el => {
        data[el.dataset.field] = el.innerHTML.trim();
      });
      return data;
    }

    function collectImages() {
      const images = Object.assign({}, serverData.images || {});
      getAllImgWraps().forEach(wrap => {
        const key = wrap.dataset.imgKey;
        const img = wrap.querySelector('img');
        if (img && img.dataset.pendingSrc) images[key] = img.dataset.pendingSrc;
      });
      return images;
    }

    function setEditMode(active) {
      editModeActive = active;
      getAllEditableFields().forEach(el => {
        el.setAttribute('contenteditable', active ? 'true' : 'false');
      });
      getAllImgWraps().forEach(wrap => wrap.classList.toggle('edit-active', active));
    }

    function initImageUploadListeners() {
      getAllImgWraps().forEach(wrap => {
        const fileInput = wrap.querySelector('.img-edit-input');
        const img = wrap.querySelector('img');
        if (!fileInput || !img) return;
        if (fileInput.dataset.bound === '1') return;
        fileInput.dataset.bound = '1';

        fileInput.addEventListener('change', async function() {
          const file = fileInput.files && fileInput.files[0];
          if (!file) return;
          if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran gambar terlalu besar. Maksimal 5MB.');
            fileInput.value = '';
            return;
          }
          const btn = wrap.querySelector('.img-edit-btn');
          const oldLabel = btn ? btn.textContent : '';
          if (btn) { btn.textContent = 'Mengunggah...'; btn.disabled = true; }

          const previous = img.src;
          img.src = URL.createObjectURL(file);

          const result = await arUploadFile(file);

          if (btn) { btn.textContent = oldLabel; btn.disabled = false; }
          fileInput.value = '';

          if (!result.success) {
            img.src = previous;
            alert(result.error || 'Gagal mengunggah foto.');
            return;
          }
          img.src = result.url;
          img.dataset.pendingSrc = result.url;
        });
      });
    }

    const btnToggleEdit  = document.getElementById('btnToggleEdit');
    const btnSaveContent = document.getElementById('btnSaveContent');

    if (btnToggleEdit && btnSaveContent) {
      btnToggleEdit.addEventListener('click', function() {
        const isActive = !editModeActive;
        setEditMode(isActive);
        btnToggleEdit.textContent = isActive ? 'Batalkan Edit' : 'Edit Konten Halaman';
        btnSaveContent.style.display = isActive ? 'inline-block' : 'none';
        if (!isActive) {
          getAllImgWraps().forEach(wrap => {
            const img = wrap.querySelector('img');
            if (img) delete img.dataset.pendingSrc;
          });
          loadContent();
        }
      });

      btnSaveContent.addEventListener('click', async function() {
        serverData.fields = collectFields();
        serverData.images = collectImages();

        serverData.extra = getExtraPrestasi().map(t => {
          if (!t.id) return t;
          const f = serverData.fields;
          const k = t.id;
          const pick = (sfx, cur) =>
            Object.prototype.hasOwnProperty.call(f, k + sfx) ? f[k + sfx] : cur;
          return {
            id:     t.id,
            badge:  pick('_badge', t.badge),
            title:  pick('_title', t.title),
            desc:   pick('_desc', t.desc),
            label1: pick('_label1', t.label1),
            list1:  pick('_list1', t.list1),
            label2: pick('_label2', t.label2),
            list2:  pick('_list2', t.list2)
          };
        });

        btnSaveContent.disabled = true;
        const originalLabel = btnSaveContent.textContent;
        btnSaveContent.textContent = 'Menyimpan...';

        const result = await saveDataToServer();

        btnSaveContent.disabled = false;
        btnSaveContent.textContent = originalLabel;

        if (!result || !result.success) {
          alert((result && result.error) || 'Gagal menyimpan perubahan ke server.');
          return;
        }

        getAllImgWraps().forEach(wrap => {
          const img = wrap.querySelector('img');
          if (img) delete img.dataset.pendingSrc;
        });

        setEditMode(false);
        btnToggleEdit.textContent = 'Edit Konten Halaman';
        btnSaveContent.style.display = 'none';

        await refreshDataFromServer();
        renderExtraPrestasi();

        alert('Perubahan prestasi berhasil disimpan di server.');
      });
    }

    function getExtraPrestasi() { return serverData.extra || []; }
    function saveExtraPrestasi(list) { serverData.extra = list; }

    function escapeAttr(str) {
      return String(str == null ? '' : str)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function linesToListItems(text) {
      const s = String(text == null ? '' : text);
      if (s.indexOf('<li>') !== -1) return s;
      return s.split('\n').map(l => l.trim()).filter(Boolean)
              .map(l => '<li>' + escapeAttr(l) + '</li>').join('');
    }

    function generatePrestasiId() {
      return 'p_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function prestasiImgSrc(t) {
      const key = t.id + '_img';
      if (serverData.images && serverData.images[key]) return serverData.images[key];
      return 'logoyayasan.png';
    }

    function renderExtraPrestasi() {
      const grid = document.getElementById('cardGrid');
      if (!grid) return;

      grid.querySelectorAll('.achievement-card[data-extra="true"]').forEach(el => el.remove());

      let list = getExtraPrestasi();
      let needsMigration = false;
      list = list.map(t => {
        if (!t.id) { needsMigration = true; return Object.assign({}, t, { id: generatePrestasiId() }); }
        return t;
      });
      if (needsMigration) { saveExtraPrestasi(list); if (isAdmin) saveDataToServer(); }

      list.forEach(t => {
        const key  = t.id;
        const card = document.createElement('div');
        card.className = 'achievement-card reveal-scale';
        card.dataset.extra = 'true';
        card.dataset.achievement = key;

        card.innerHTML =
          '<div class="achievement-img">' +
            '<div class="img-edit-wrap" data-img-key="' + escapeAttr(key) + '_img">' +
              '<img src="' + escapeAttr(prestasiImgSrc(t)) + '" alt="">' +
              (isAdmin
                ? '<button type="button" class="img-edit-btn" onclick="this.parentNode.querySelector(\'.img-edit-input\').click()">Ganti Foto</button>' +
                  '<input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">'
                : '') +
            '</div>' +
          '</div>' +
          '<div class="achievement-body">' +
            '<span class="badge-tag" data-field="' + escapeAttr(key) + '_badge">' + (t.badge || '') + '</span>' +
            '<h3 data-field="' + escapeAttr(key) + '_title">' + (t.title || '') + '</h3>' +
            '<p data-field="' + escapeAttr(key) + '_desc">' + (t.desc || '') + '</p>' +
            '<div class="achievement-detail">' +
              '<div class="detail-section-title" data-field="' + escapeAttr(key) + '_label1">' + (t.label1 || 'Pencapaian Utama:') + '</div>' +
              '<ul class="detail-list" data-field="' + escapeAttr(key) + '_list1">' + linesToListItems(t.list1) + '</ul>' +
              '<div class="detail-section-title" data-field="' + escapeAttr(key) + '_label2">' + (t.label2 || 'Apresiasi:') + '</div>' +
              '<ul class="detail-list" data-field="' + escapeAttr(key) + '_list2">' + linesToListItems(t.list2) + '</ul>' +
            '</div>' +
            '<button class="btn-detail" onclick="toggleDetail(this, \'' + escapeAttr(key) + '\')">' +
              '<span>Lihat Detail Prestasi</span>' +
              '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>' +
            '</button>' +
            (isAdmin
              ? '<button type="button" class="btn-danger" data-delete-extra="' + escapeAttr(key) + '" onclick="deleteExtraPrestasi(\'' + escapeAttr(key) + '\')">Hapus Prestasi</button>'
              : '') +
          '</div>';

        grid.appendChild(card);
        if (typeof io !== 'undefined') io.observe(card);
      });

      initImageUploadListeners();
      setEditMode(editModeActive);
      loadContent();
      applyDeletedDefaults();
    }

    // ============================================================
    // HAPUS PRESTASI TAMBAHAN + HAPUS GAMBAR FISIK DI SERVER
    // ============================================================
    async function deleteExtraPrestasi(prestasiId) {
      if (!isAdmin) { alert('Anda harus login sebagai admin.'); return; }

      const item = getExtraPrestasi().find(t => t.id === prestasiId);
      if (!item) return;

      const imgKey = prestasiId + '_img';
      const imgUrl = serverData.images && serverData.images[imgKey];

      if (!confirm('Apakah Anda yakin ingin menghapus prestasi ini?\nGambar yang tersimpan di server juga akan dihapus.')) return;

      // Disable tombol sementara
      const btn = document.querySelector(`.btn-danger[data-delete-extra="${prestasiId}"]`);
      if (btn) { btn.disabled = true; btn.textContent = 'Menghapus...'; }

      // 1. Hapus file gambar fisik dulu (kalau ada di uploads/prestasi/)
      if (imgUrl) {
        const delRes = await arDeleteImage(imgUrl);
        if (!delRes.success && !delRes.skipped) {
          console.warn('Gagal hapus gambar:', delRes.error);
          // Tetap lanjut hapus data prestasi
        }
      }

      // 2. Hapus data dari array & serverData
      const list = getExtraPrestasi().slice();
      const index = list.findIndex(t => t.id === prestasiId);
      if (index === -1) return;
      list.splice(index, 1);
      serverData.extra = list;

      ['_badge', '_title', '_desc', '_label1', '_list1', '_label2', '_list2', '_img']
        .forEach(sfx => {
          delete serverData.fields[prestasiId + sfx];
          delete serverData.images[prestasiId + sfx];
        });

      // 3. Simpan ke server
      const result = await arPost({
        action: 'save_data',
        data: JSON.stringify(serverData),
        force: '1'
      });

      if (!result || !result.success) {
        alert((result && result.error) || 'Gagal menghapus prestasi di server.');
        await refreshDataFromServer();
        renderExtraPrestasi();
        return;
      }

      await refreshDataFromServer();
      renderExtraPrestasi();

      alert('Prestasi dan gambar berhasil dihapus.');
    }

    const btnAddPrestasi = document.getElementById('btnAddPrestasi');
    const addPrestasiModal = document.getElementById('addPrestasiModal');
    if (btnAddPrestasi && addPrestasiModal) {
      btnAddPrestasi.addEventListener('click', () => addPrestasiModal.classList.add('active'));
    }
    function closeAddPrestasiModal() {
      if (addPrestasiModal) addPrestasiModal.classList.remove('active');
    }

    const npImgInput   = document.getElementById('npImg');
    const npImgPreview = document.getElementById('npImgPreview');
    if (npImgInput && npImgPreview) {
      npImgInput.addEventListener('change', () => {
        const file = npImgInput.files[0];
        if (file) {
          npImgPreview.src = URL.createObjectURL(file);
          npImgPreview.style.display = 'block';
        } else {
          npImgPreview.style.display = 'none';
        }
      });
    }

    const formAddPrestasi = document.getElementById('formAddPrestasi');
    if (formAddPrestasi) {
      formAddPrestasi.addEventListener('submit', async (e) => {
        e.preventDefault();

        const file = npImgInput && npImgInput.files[0];
        if (!file) { alert('Silakan pilih foto prestasi terlebih dahulu.'); return; }

        const btnSubmit = document.getElementById('btnSubmitPrestasi');
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Mengunggah...';

        try {
          const uploadResult = await arUploadFile(file);
          if (!uploadResult.success) {
            alert(uploadResult.error || 'Gagal mengunggah foto.');
            return;
          }

          const id = generatePrestasiId();
          const newPrestasi = {
            id:     id,
            badge:  document.getElementById('npBadge').value,
            title:  document.getElementById('npTitle').value,
            desc:   document.getElementById('npDesc').value,
            label1: document.getElementById('npLabel1').value,
            list1:  document.getElementById('npList1').value,
            label2: document.getElementById('npLabel2').value,
            list2:  document.getElementById('npList2').value
          };

          const list = getExtraPrestasi().slice();
          list.push(newPrestasi);
          saveExtraPrestasi(list);
          serverData.images[id + '_img'] = uploadResult.url;

          btnSubmit.textContent = 'Menyimpan...';
          const saveResult = await saveDataToServer();
          if (!saveResult || !saveResult.success) {
            alert((saveResult && saveResult.error) || 'Gagal menyimpan prestasi baru ke server.');
            return;
          }

          await refreshDataFromServer();
          renderExtraPrestasi();

          formAddPrestasi.reset();
          if (npImgPreview) npImgPreview.style.display = 'none';
          closeAddPrestasiModal();
          alert('Prestasi berhasil ditambahkan!');
        } finally {
          btnSubmit.disabled = false;
          btnSubmit.textContent = 'Tambah Prestasi';
        }
      });
    }

    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
      menuToggle.addEventListener('click', function() {
        const nav = document.querySelector('nav');
        if (nav) nav.classList.toggle('open');
      });
    }

    window.addEventListener('beforeunload', function(e) {
      if (editModeActive) { e.preventDefault(); e.returnValue = ''; }
    });

    updateAdminUI();
    renderExtraPrestasi();
    applyDeletedDefaults();

    window.addEventListener('load', function() {
      document.body.classList.add('page-loaded');

      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
      }, { threshold: 0.12 });
      document.querySelectorAll('.reveal, .reveal-scale').forEach(el => io.observe(el));
    });
  </script>
</body>
</html>