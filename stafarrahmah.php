<?php
session_start();

// ============================================================
// KONFIGURASI DASAR
// ============================================================
$isAdmin      = isset($_SESSION['ar_is_admin']) && $_SESSION['ar_is_admin'] === true;
$SELF         = basename(__FILE__);
$uploadDir    = __DIR__ . '/uploads/staf/';
$uploadUrl    = 'uploads/staf/';
$stafDataFile = $uploadDir . 'staf_data.json';
$stafBakFile  = $uploadDir . 'staf_data.bak.json';

function ar_json($arr) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ar_ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return is_dir($dir) && is_writable($dir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST) && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    ar_json([
        'success' => false,
        'error'   => 'Data terlalu besar untuk server (melebihi post_max_size = '
                   . ini_get('post_max_size') . '). Perbesar post_max_size & upload_max_filesize di php.ini.'
    ]);
}

function ar_save_data_url($dataUrl, $uploadDir, $uploadUrl) {
    if (!is_string($dataUrl) || strpos($dataUrl, 'data:') !== 0) return null;
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp|gif);base64,#i', $dataUrl, $m)) return null;

    $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
    $b64 = substr($dataUrl, strlen($m[0]));
    $bin = base64_decode($b64, true);
    if ($bin === false || strlen($bin) === 0) return null;
    if (strlen($bin) > 8 * 1024 * 1024) return null;

    if (!ar_ensure_dir($uploadDir)) return null;

    $filename = 'staf_' . substr(sha1($bin), 0, 16) . '.' . $ext;
    $path     = $uploadDir . $filename;
    if (!file_exists($path)) {
        if (file_put_contents($path, $bin, LOCK_EX) === false) return null;
        @chmod($path, 0644);
    }
    return $uploadUrl . $filename;
}

function ar_migrate_images(array $data, $uploadDir, $uploadUrl, &$converted = 0) {
    if (isset($data['images']) && is_array($data['images'])) {
        foreach ($data['images'] as $k => $v) {
            $url = ar_save_data_url($v, $uploadDir, $uploadUrl);
            if ($url !== null) { $data['images'][$k] = $url; $converted++; }
        }
    }
    if (isset($data['extra']) && is_array($data['extra'])) {
        foreach ($data['extra'] as $i => $t) {
            if (!is_array($t)) continue;
            if (isset($t['img'])) {
                $url = ar_save_data_url($t['img'], $uploadDir, $uploadUrl);
                if ($url !== null) { $data['extra'][$i]['img'] = $url; $converted++; }
            }
            if (!empty($t['id'])) {
                $key = $t['id'] . '_img';
                if (!empty($data['extra'][$i]['img'])) {
                    $data['images'][$key] = $data['extra'][$i]['img'];
                }
                unset($data['extra'][$i]['img']);
            }
        }
    }
    return $data;
}

function ar_read_staf($stafDataFile, $stafBakFile) {
    $empty = ['fields' => [], 'images' => [], 'extra' => [], 'deleted' => []];
    foreach ([$stafDataFile, $stafBakFile] as $f) {
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

function ar_write_staf(array $data, $stafDataFile, $stafBakFile) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return 'Gagal encode JSON: ' . json_last_error_msg();

    if (file_exists($stafDataFile)) @copy($stafDataFile, $stafBakFile);

    $tmp = $stafDataFile . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        return 'Gagal menulis file sementara. Cek permission folder uploads/staf/.';
    }
    $check = json_decode(file_get_contents($tmp), true);
    if (!is_array($check)) {
        @unlink($tmp);
        return 'Verifikasi gagal: hasil tulisan tidak valid. Perubahan dibatalkan.';
    }
    if (!@rename($tmp, $stafDataFile)) {
        @unlink($tmp);
        return 'Gagal memindahkan file sementara ke staf_data.json.';
    }
    @chmod($stafDataFile, 0644);
    return true;
}

$ACTION = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ACTION === 'logout') {
    $_SESSION = [];
    session_unset();
    session_destroy();
    ar_json(['success' => true]);
}

if (isset($_GET['check_admin'])) {
    ar_json(['isAdmin' => $isAdmin]);
}

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
        ar_json(['success' => false, 'error' => 'Folder uploads/staf/ tidak ada atau tidak bisa ditulis (chmod 755).']);
    }

    $filename = 'staf_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest     = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        @chmod($dest, 0644);
        ar_json(['success' => true, 'url' => $uploadUrl . $filename]);
    }
    ar_json(['success' => false, 'error' => 'Gagal menyimpan gambar ke server.']);
}

// ============================================================
// HAPUS GAMBAR FISIK DI SERVER
// Hanya boleh hapus file di dalam uploads/staf/ (proteksi path traversal)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ACTION === 'delete_image') {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    $imageUrl = trim($_POST['image_url'] ?? '');
    if ($imageUrl === '') {
        ar_json(['success' => false, 'error' => 'URL gambar kosong.']);
    }

    $prefix = 'uploads/staf/';
    if (strpos($imageUrl, $prefix) !== 0) {
        ar_json(['success' => false, 'error' => 'Hanya gambar di folder uploads/staf/ yang dapat dihapus.']);
    }

    $relative = substr($imageUrl, strlen($prefix));

    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $relative)) {
        ar_json(['success' => false, 'error' => 'Nama file tidak valid.']);
    }

    $relative = basename($relative);

    $fullPath = __DIR__ . '/' . $prefix . $relative;

    if (!file_exists($fullPath)) {
        ar_json(['success' => true, 'message' => 'File sudah tidak ada.']);
    }

    if (!is_file($fullPath)) {
        ar_json(['success' => false, 'error' => 'Bukan file.']);
    }

    if (@unlink($fullPath)) {
        ar_json(['success' => true]);
    } else {
        ar_json(['success' => false, 'error' => 'Gagal menghapus file. Cek permission folder uploads/staf/.']);
    }
}

if ($ACTION === 'get_staf_data') {
    $data = ar_read_staf($stafDataFile, $stafBakFile);
    ar_json([
        'fields'  => (object) $data['fields'],
        'images'  => (object) $data['images'],
        'extra'   => array_values($data['extra']),
        'deleted' => array_values($data['deleted']),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ACTION === 'save_staf_data') {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    $raw = $_POST['data'] ?? '';
    if ($raw === '') ar_json(['success' => false, 'error' => 'Data kosong tidak dikirim.']);

    $incoming = json_decode($raw, true);
    if (!is_array($incoming)) {
        ar_json(['success' => false, 'error' => 'Data tidak valid: ' . json_last_error_msg()]);
    }

    $existing = ar_read_staf($stafDataFile, $stafBakFile);

    $converted = 0;
    $incoming  = ar_migrate_images($incoming, $uploadDir, $uploadUrl, $converted);

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
                if (preg_match('/^(t_[a-z0-9]+_[a-z0-9]+)_(img|badge|name|subject|desc|tugas|pendidikan)$/', $k, $m)) {
                    if (!isset($idAktif[$m[1]])) unset($merged[$sec][$k]);
                }
            }
        }
    }

    foreach ($merged['extra'] as $i => $t) {
        if (!is_array($t) || empty($t['id'])) continue;
        foreach (['badge', 'name', 'subject', 'desc', 'tugas', 'pendidikan'] as $sfx) {
            $key = $t['id'] . '_' . $sfx;
            if (isset($merged['fields'][$key]) && $merged['fields'][$key] !== '') {
                $merged['extra'][$i][$sfx] = $merged['fields'][$key];
            } elseif (isset($t[$sfx]) && $t[$sfx] !== '') {
                $merged['fields'][$key] = $t[$sfx];
            }
        }
        unset($merged['extra'][$i]['img']);
    }

    $oldCount = count($existing['extra']);
    $newCount = count($merged['extra']);
    if ($oldCount >= 2 && $newCount < ceil($oldCount / 2) && empty($_POST['force'])) {
        ar_json([
            'success' => false,
            'error'   => 'Dibatalkan demi keamanan: jumlah staf tambahan turun dari '
                       . $oldCount . ' ke ' . $newCount . '. Muat ulang halaman lalu coba lagi.'
        ]);
    }

    $res = ar_write_staf($merged, $stafDataFile, $stafBakFile);
    if ($res !== true) ar_json(['success' => false, 'error' => $res]);

    ar_json(['success' => true, 'converted_images' => $converted]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($ACTION === 'delete_default_teacher' || $ACTION === 'restore_default_teacher')) {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    $key = trim($_POST['teacher_key'] ?? '');
    if ($key === '' || !preg_match('/^[A-Za-z0-9_]+$/', $key)) {
        ar_json(['success' => false, 'error' => 'Key guru tidak valid.']);
    }

    $data = ar_read_staf($stafDataFile, $stafBakFile);
    $list = $data['deleted'];

    if ($ACTION === 'delete_default_teacher') {
        if (!in_array($key, $list, true)) $list[] = $key;
    } else {
        $list = array_values(array_diff($list, [$key]));
    }
    $data['deleted'] = array_values(array_unique($list));

    $res = ar_write_staf($data, $stafDataFile, $stafBakFile);
    if ($res !== true) ar_json(['success' => false, 'error' => $res]);

    ar_json(['success' => true, 'deleted' => $data['deleted']]);
}

if ($ACTION === 'migrate_images') {
    if (!$isAdmin) ar_json(['success' => false, 'error' => 'Anda harus login sebagai admin.']);

    $before    = file_exists($stafDataFile) ? filesize($stafDataFile) : 0;
    $data      = ar_read_staf($stafDataFile, $stafBakFile);
    $converted = 0;
    $data      = ar_migrate_images($data, $uploadDir, $uploadUrl, $converted);

    $res = ar_write_staf($data, $stafDataFile, $stafBakFile);
    if ($res !== true) ar_json(['success' => false, 'error' => $res]);

    ar_json([
        'success'          => true,
        'converted_images' => $converted,
        'ukuran_sebelum'   => $before,
        'ukuran_sesudah'   => filesize($stafDataFile),
        'pesan'            => $converted . ' foto dipindahkan dari JSON ke folder uploads/staf/.'
    ]);
}

$INITIAL_STAF = ar_read_staf($stafDataFile, $stafBakFile);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staf & Pengajar - Yayasan Ar-Rahmah</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --red-accent: #A31D1D; 
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

    h1, h2, h3, h4, .brand-name {
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
      max-width: 720px;
      margin: 0 auto 36px;
    }

    .section-head h1 {
      font-size: 42px;
      color: var(--green);
      margin: 14px 0 12px;
    }

    .section-head p { color: var(--ink-soft); font-size: 16.5px; }

    .action-bar {
      margin-bottom: 36px;
      display: flex;
      justify-content: flex-start;
      gap: 14px;
      flex-wrap: wrap;
    }

    .leader-featured {
      background: var(--white);
      border-radius: 20px;
      border: 1px solid var(--border);
      overflow: hidden;
      box-shadow: var(--shadow);
      display: grid;
      grid-template-columns: 0.9fr 1.1fr;
      margin-bottom: 64px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .leader-featured:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 40px -18px rgba(43,33,24,0.4);
    }

    .leader-img {
      position: relative;
      background: var(--green-pale);
      min-height: 380px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .leader-img img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      object-position: center top;
    }

    .leader-badge {
      position: absolute;
      top: 18px;
      left: 18px;
      background: var(--orange);
      color: var(--white);
      padding: 6px 16px;
      border-radius: 100px;
      font-size: 12.5px;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      z-index: 2;
    }

    .leader-content {
      padding: 36px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .leader-content .role {
      font-size: 13px;
      font-weight: 700;
      color: var(--orange-deep);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .leader-content h2 {
      font-size: 32px;
      color: var(--green);
      margin: 6px 0 14px;
    }

    .leader-content .quote {
      font-style: italic;
      color: var(--ink-soft);
      border-left: 3px solid var(--orange);
      padding-left: 16px;
      margin-bottom: 18px;
      font-size: 14.5px;
    }

    .leader-details-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 14px;
      margin-top: 10px;
      padding-top: 18px;
      border-top: 1px dashed var(--border);
    }

    .detail-box .lbl {
      font-size: 11.5px;
      color: var(--ink-soft);
      text-transform: uppercase;
      font-weight: 700;
    }

    .detail-box .val {
      font-size: 14px;
      font-weight: 600;
      color: var(--ink);
    }

    .section-title-sub {
      text-align: center;
      margin-bottom: 36px;
    }

    .section-title-sub h2 {
      font-size: 32px;
      color: var(--green);
      position: relative;
      display: inline-block;
    }

    .section-title-sub h2::after {
      content: '';
      display: block;
      width: 50%;
      height: 3px;
      background: var(--orange);
      margin: 8px auto 0;
      border-radius: 2px;
    }

    .card-carousel {
      position: relative;
      margin: 0 -8px;
    }

    .card-grid {
      display: flex;
      gap: 28px;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      scroll-behavior: smooth;
      padding: 8px 8px 22px;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: thin;
      scrollbar-color: var(--border) transparent;
      align-items: start;
    }

    .card-grid::-webkit-scrollbar { height: 8px; }
    .card-grid::-webkit-scrollbar-track { background: var(--cream); border-radius: 10px; }
    .card-grid::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
    .card-grid::-webkit-scrollbar-thumb:hover { background: var(--orange-light); }

    .teacher-card {
      background: var(--white);
      border-radius: 18px;
      border: 1px solid var(--border);
      overflow: hidden;
      box-shadow: var(--shadow);
      display: flex;
      flex-direction: column;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      position: relative;
      flex: 0 0 calc((100% - 56px) / 3);
      scroll-snap-align: start;
      min-width: 0;
    }

    .teacher-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px -18px rgba(43,33,24,0.4);
    }

    .teacher-img {
      height: 260px;
      background: var(--red-accent);
      overflow: hidden;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .teacher-img img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      object-position: center top;
      transition: transform 0.4s ease;
    }

    .teacher-card:hover .teacher-img img {
      transform: scale(1.03);
    }

    .teacher-body {
      padding: 24px;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
    }

    .teacher-body h3 {
      font-family: 'Amiri', serif;
      color: var(--green);
      font-size: 22px;
      margin: 0 0 4px 0;
      outline: none;
    }

    .teacher-body .subject {
      font-size: 13.5px;
      font-weight: 700;
      color: var(--orange-deep);
      margin-bottom: 10px;
    }

    .teacher-body p {
      color: var(--ink-soft);
      font-size: 14px;
      line-height: 1.6;
      margin: 0;
    }

    .card-nav {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin-top: 8px;
      align-items: center;
    }

    .card-nav-btn {
      width: 42px;
      height: 42px;
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

    .card-nav-btn:hover:not(:disabled) {
      background: var(--orange);
      color: var(--white);
      border-color: var(--orange);
      transform: translateY(-2px);
    }

    .card-nav-btn:disabled {
      opacity: 0.35;
      cursor: not-allowed;
    }

    .card-nav-btn svg { width: 18px; height: 18px; }

    .card-dots {
      display: flex;
      gap: 8px;
      align-items: center;
      padding: 0 8px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .card-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      border: none;
      background: var(--border);
      cursor: pointer;
      padding: 0;
      transition: background .25s ease, transform .25s ease, width .25s ease;
    }

    .card-dot.active {
      background: var(--orange);
      width: 26px;
      border-radius: 6px;
    }

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

    [contenteditable="false"] { outline: none !important; }

    [data-field][contenteditable="true"] {
      outline: 2px dashed var(--orange) !important;
      outline-offset: 4px;
      border-radius: 6px;
      background: rgba(217,113,31,0.06);
      cursor: text;
    }
    
    [data-field][contenteditable="true"]:focus {
      outline: 2px solid var(--orange-deep) !important;
      background: rgba(217,113,31,0.1);
    }

    [data-field][contenteditable="true"] {
      min-height: 1.35em;
      outline: 1px dashed var(--border);
      outline-offset: 4px;
      border-radius: 5px;
      transition: outline-color .15s ease;
    }
    [data-field][contenteditable="true"]:hover { outline-color: var(--orange-light); }
    [data-field][contenteditable="true"]:focus {
      outline: 2px solid var(--orange);
      background: rgba(217,113,31,0.04);
    }
    [data-field][data-placeholder][contenteditable="true"]:empty::before {
      content: attr(data-placeholder);
      color: #B9AC99;
      font-style: italic;
      font-weight: 400;
      pointer-events: none;
    }
    [data-field].detail-list[contenteditable="true"] { padding-left: 20px; }

    .teacher-body h3[data-field]:empty,
    .teacher-body .subject[data-field]:empty,
    .leader-content h2[data-field]:empty { min-height: 1.3em; }
    .teacher-body p[data-field]:empty { min-height: 2.6em; }

    .foto-kosong { object-fit: cover; width: 100%; height: 100%; }

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

    .badge-tag {
      display: inline-block;
      align-self: flex-start;
      background: var(--green-pale);
      color: var(--green);
      font-size: 11.5px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 50px;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .teacher-detail {
      max-height: 0;
      overflow: hidden;
      opacity: 0;
      transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), 
                  opacity 0.4s ease, 
                  margin-top 0.3s ease, 
                  padding-top 0.3s ease;
    }

    .teacher-card.active .teacher-detail {
      max-height: 600px;
      opacity: 1;
      margin-top: 18px;
      padding-top: 18px;
      border-top: 1px dashed var(--border);
    }

    .detail-section-title {
      font-size: 12px;
      font-weight: 700;
      color: var(--orange-deep);
      text-transform: uppercase;
      letter-spacing: 0.6px;
      margin-top: 10px;
      margin-bottom: 4px;
    }

    .detail-list {
      margin: 0 0 12px 0;
      padding-left: 18px;
      color: var(--ink-soft);
      font-size: 13px;
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
      font-size: 14px;
      margin-top: 20px;
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

    .btn-detail:active { transform: translateY(0); }
    .btn-detail svg { transition: transform 0.3s ease; }

    .btn-delete-default {
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
      transition: background 0.2s ease;
    }

    .btn-delete-default:hover { background: #c9302c; }
    .btn-delete-default:disabled { opacity: 0.6; cursor: not-allowed; }

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

    @media (prefers-reduced-motion: reduce) {
      .reveal, .reveal-scale, .page-loaded header .nav-wrap { 
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

    @media (max-width: 980px) {
      .leader-featured { grid-template-columns: 1fr; }
      .leader-img { min-height: 300px; }
      .teacher-card { flex: 0 0 calc((100% - 28px) / 2); }
    }

    @media (max-width: 720px) {
      .menu-toggle { display: flex; }
      .section-head h1 { font-size: 32px; }
      .teacher-card { flex: 0 0 100%; }
      section { padding: 50px 0; }
      .leader-content { padding: 24px; }
      .leader-details-grid { grid-template-columns: 1fr; }
      .footer-bottom { flex-direction: column; align-items: flex-start; text-align: left; }
      .action-bar { justify-content: center; }
      .admin-panel {
        bottom: 12px;
        right: 12px;
        padding: 10px 14px;
        flex-wrap: wrap;
        gap: 8px;
      }
      .admin-panel .btn { font-size: 12px; padding: 6px 12px; }
      .teacher-img { height: 200px; }
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
        <span class="eyebrow" data-field="headEyebrow">Struktur Pendidik &amp; Pengurus</span>
        <h1 data-field="headTitle">Staf &amp; Guru Yayasan Ar-Rahmah</h1>
        <p data-field="headSubtitle">Mengenal lebih dekat para pendidik dan pengurus amanah yang berdedikasi membimbing serta membentuk generasi penerus yang berakhlak mulia.</p>
      </div>

      <div class="action-bar reveal">
        <a href="index.php#beranda" class="nav-cta">Kembali ke Beranda</a>
        <?php if ($isAdmin): ?>
          <a href="index.php#beranda" class="nav-cta nav-cta-green">Mode Admin Aktif</a>
          <button type="button" id="btnToggleEdit" class="nav-cta">Edit Konten Halaman</button>
          <button type="button" id="btnSaveContent" class="nav-cta nav-cta-green" style="display:none;">Simpan Perubahan</button>
          <button type="button" id="btnAddTeacher" class="nav-cta nav-cta-green">+ Tambah Profil Guru</button>
        <?php endif; ?>
      </div>

      <div class="leader-featured reveal-scale">
        <div class="leader-img">
          <div class="img-edit-wrap" data-img-key="leaderImg" style="width:100%; height:100%;">
            <span class="leader-badge">Ketua Yayasan</span>
            <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E" alt="" class="foto-kosong">
            <?php if ($isAdmin): ?>
            <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
            <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
            <?php endif; ?>
          </div>
        </div>
        <div class="leader-content">
          <span class="role" data-field="leaderRole" data-placeholder="Jabatan Pimpinan"></span>
          <h2 data-field="leaderName" data-placeholder="Nama Ketua Yayasan"></h2>
          <div class="quote" data-field="leaderQuote" data-placeholder="Kutipan / motto pimpinan"></div>
          <p style="font-size: 14px; color: var(--ink-soft); margin-bottom: 12px;" data-field="leaderBio" data-placeholder="Deskripsi singkat pimpinan"></p>

          <div class="leader-details-grid">
            <div class="detail-box">
              <div class="lbl">Pendidikan Terakhir</div>
              <div class="val" data-field="leaderPendidikan" data-placeholder="Pendidikan terakhir"></div>
            </div>
            <div class="detail-box">
              <div class="lbl">Masa Khidmat</div>
              <div class="val" data-field="leaderMasaKhidmat" data-placeholder="Contoh: 2013 - Sekarang"></div>
            </div>
            <div class="detail-box">
              <div class="lbl">Fokus Pembinaan</div>
              <div class="val" data-field="leaderFokus" data-placeholder="Fokus pembinaan"></div>
            </div>
            <div class="detail-box">
              <div class="lbl">Email Kontak</div>
              <div class="val" data-field="leaderEmail" data-placeholder="email@ar-rahmah.or.id"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="section-title-sub reveal">
        <h2 data-field="subTitle">Guru &amp; Tenaga Pendidik Aktif</h2>
      </div>

      <div class="card-carousel">
        <div class="card-grid" id="cardGrid">
          
          <!-- Guru 1 -->
          <div class="teacher-card reveal-scale default-teacher" data-default-key="t1">
            <div class="teacher-img">
              <div class="img-edit-wrap" data-img-key="t1Img" style="width:100%; height:100%;">
                <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E" alt="" class="foto-kosong">
                <?php if ($isAdmin): ?>
                <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
                <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php endif; ?>
              </div>
            </div>
            <div class="teacher-body">
              <span class="badge-tag" data-field="t1Badge" data-placeholder="Unit / Sekolah"></span>
              <h3 data-field="t1Name" data-placeholder="Nama Guru"></h3>
              <div class="subject" data-field="t1Subject" data-placeholder="Mata Pelajaran / Jabatan"></div>
              <p data-field="t1Desc" data-placeholder="Deskripsi singkat guru"></p>
              
              <div class="teacher-detail">
                <div class="detail-section-title">Tugas &amp; Tanggung Jawab:</div>
                <ul class="detail-list" data-field="t1Tugas" data-placeholder="Tugas &amp; tanggung jawab"></ul>
                <div class="detail-section-title">Pendidikan &amp; Pengalaman:</div>
                <ul class="detail-list" data-field="t1Pendidikan" data-placeholder="Pendidikan &amp; pengalaman"></ul>
              </div>

              <button class="btn-detail" onclick="toggleDetail(this)">
                <span>Lihat Detail Staf</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <?php if ($isAdmin): ?>
              <button type="button" class="btn-delete-default" data-delete-default="t1" onclick="deleteDefaultTeacher('t1')">Hapus Guru</button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Guru 2 -->
          <div class="teacher-card reveal-scale default-teacher" data-default-key="t2">
            <div class="teacher-img">
              <div class="img-edit-wrap" data-img-key="t2Img" style="width:100%; height:100%;">
                <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E" alt="" class="foto-kosong">
                <?php if ($isAdmin): ?>
                <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
                <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php endif; ?>
              </div>
            </div>
            <div class="teacher-body">
              <span class="badge-tag" data-field="t2Badge" data-placeholder="Unit / Sekolah"></span>
              <h3 data-field="t2Name" data-placeholder="Nama Guru"></h3>
              <div class="subject" data-field="t2Subject" data-placeholder="Mata Pelajaran / Jabatan"></div>
              <p data-field="t2Desc" data-placeholder="Deskripsi singkat guru"></p>
              
              <div class="teacher-detail">
                <div class="detail-section-title">Tugas &amp; Tanggung Jawab:</div>
                <ul class="detail-list" data-field="t2Tugas" data-placeholder="Tugas &amp; tanggung jawab"></ul>
                <div class="detail-section-title">Pendidikan &amp; Pengalaman:</div>
                <ul class="detail-list" data-field="t2Pendidikan" data-placeholder="Pendidikan &amp; pengalaman"></ul>
              </div>

              <button class="btn-detail" onclick="toggleDetail(this)">
                <span>Lihat Detail Staf</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <?php if ($isAdmin): ?>
              <button type="button" class="btn-delete-default" data-delete-default="t2" onclick="deleteDefaultTeacher('t2')">Hapus Guru</button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Guru 3 -->
          <div class="teacher-card reveal-scale default-teacher" data-default-key="t3">
            <div class="teacher-img">
              <div class="img-edit-wrap" data-img-key="t3Img" style="width:100%; height:100%;">
                <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E" alt="" class="foto-kosong">
                <?php if ($isAdmin): ?>
                <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
                <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php endif; ?>
              </div>
            </div>
            <div class="teacher-body">
              <span class="badge-tag" data-field="t3Badge" data-placeholder="Unit / Sekolah"></span>
              <h3 data-field="t3Name" data-placeholder="Nama Guru"></h3>
              <div class="subject" data-field="t3Subject" data-placeholder="Mata Pelajaran / Jabatan"></div>
              <p data-field="t3Desc" data-placeholder="Deskripsi singkat guru"></p>
              
              <div class="teacher-detail">
                <div class="detail-section-title">Tugas &amp; Tanggung Jawab:</div>
                <ul class="detail-list" data-field="t3Tugas" data-placeholder="Tugas &amp; tanggung jawab"></ul>
                <div class="detail-section-title">Pendidikan &amp; Pengalaman:</div>
                <ul class="detail-list" data-field="t3Pendidikan" data-placeholder="Pendidikan &amp; pengalaman"></ul>
              </div>

              <button class="btn-detail" onclick="toggleDetail(this)">
                <span>Lihat Detail Staf</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <?php if ($isAdmin): ?>
              <button type="button" class="btn-delete-default" data-delete-default="t3" onclick="deleteDefaultTeacher('t3')">Hapus Guru</button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Guru 4 -->
          <div class="teacher-card reveal-scale default-teacher" data-default-key="t4">
            <div class="teacher-img">
              <div class="img-edit-wrap" data-img-key="t4Img" style="width:100%; height:100%;">
                <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E" alt="" class="foto-kosong">
                <?php if ($isAdmin): ?>
                <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
                <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php endif; ?>
              </div>
            </div>
            <div class="teacher-body">
              <span class="badge-tag" data-field="t4Badge" data-placeholder="Unit / Sekolah"></span>
              <h3 data-field="t4Name" data-placeholder="Nama Guru"></h3>
              <div class="subject" data-field="t4Subject" data-placeholder="Mata Pelajaran / Jabatan"></div>
              <p data-field="t4Desc" data-placeholder="Deskripsi singkat guru"></p>
              
              <div class="teacher-detail">
                <div class="detail-section-title">Tugas &amp; Tanggung Jawab:</div>
                <ul class="detail-list" data-field="t4Tugas" data-placeholder="Tugas &amp; tanggung jawab"></ul>
                <div class="detail-section-title">Pendidikan &amp; Pengalaman:</div>
                <ul class="detail-list" data-field="t4Pendidikan" data-placeholder="Pendidikan &amp; pengalaman"></ul>
              </div>

              <button class="btn-detail" onclick="toggleDetail(this)">
                <span>Lihat Detail Staf</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <?php if ($isAdmin): ?>
              <button type="button" class="btn-delete-default" data-delete-default="t4" onclick="deleteDefaultTeacher('t4')">Hapus Guru</button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Guru 5 -->
          <div class="teacher-card reveal-scale default-teacher" data-default-key="t5">
            <div class="teacher-img">
              <div class="img-edit-wrap" data-img-key="t5Img" style="width:100%; height:100%;">
                <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E" alt="" class="foto-kosong">
                <?php if ($isAdmin): ?>
                <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
                <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php endif; ?>
              </div>
            </div>
            <div class="teacher-body">
              <span class="badge-tag" data-field="t5Badge" data-placeholder="Unit / Sekolah"></span>
              <h3 data-field="t5Name" data-placeholder="Nama Guru"></h3>
              <div class="subject" data-field="t5Subject" data-placeholder="Mata Pelajaran / Jabatan"></div>
              <p data-field="t5Desc" data-placeholder="Deskripsi singkat guru"></p>
              
              <div class="teacher-detail">
                <div class="detail-section-title">Tugas &amp; Tanggung Jawab:</div>
                <ul class="detail-list" data-field="t5Tugas" data-placeholder="Tugas &amp; tanggung jawab"></ul>
                <div class="detail-section-title">Pendidikan &amp; Pengalaman:</div>
                <ul class="detail-list" data-field="t5Pendidikan" data-placeholder="Pendidikan &amp; pengalaman"></ul>
              </div>

              <button class="btn-detail" onclick="toggleDetail(this)">
                <span>Lihat Detail Staf</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <?php if ($isAdmin): ?>
              <button type="button" class="btn-delete-default" data-delete-default="t5" onclick="deleteDefaultTeacher('t5')">Hapus Guru</button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Guru 6 -->
          <div class="teacher-card reveal-scale default-teacher" data-default-key="t6">
            <div class="teacher-img">
              <div class="img-edit-wrap" data-img-key="t6Img" style="width:100%; height:100%;">
                <img src="data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E" alt="" class="foto-kosong">
                <?php if ($isAdmin): ?>
                <button type="button" class="img-edit-btn" onclick="this.nextElementSibling.click()">Ganti Foto</button>
                <input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php endif; ?>
              </div>
            </div>
            <div class="teacher-body">
              <span class="badge-tag" data-field="t6Badge" data-placeholder="Unit / Sekolah"></span>
              <h3 data-field="t6Name" data-placeholder="Nama Guru"></h3>
              <div class="subject" data-field="t6Subject" data-placeholder="Mata Pelajaran / Jabatan"></div>
              <p data-field="t6Desc" data-placeholder="Deskripsi singkat guru"></p>
              
              <div class="teacher-detail">
                <div class="detail-section-title">Tugas &amp; Tanggung Jawab:</div>
                <ul class="detail-list" data-field="t6Tugas" data-placeholder="Tugas &amp; tanggung jawab"></ul>
                <div class="detail-section-title">Pendidikan &amp; Pengalaman:</div>
                <ul class="detail-list" data-field="t6Pendidikan" data-placeholder="Pendidikan &amp; pengalaman"></ul>
              </div>

              <button class="btn-detail" onclick="toggleDetail(this)">
                <span>Lihat Detail Staf</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
              </button>
              <?php if ($isAdmin): ?>
              <button type="button" class="btn-delete-default" data-delete-default="t6" onclick="deleteDefaultTeacher('t6')">Hapus Guru</button>
              <?php endif; ?>
            </div>
          </div>

        </div>

        <div class="card-nav" id="cardNav">
          <button type="button" class="card-nav-btn" id="cardPrev" aria-label="Guru sebelumnya">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
          </button>
          <div class="card-dots" id="cardDots"></div>
          <button type="button" class="card-nav-btn" id="cardNext" aria-label="Guru berikutnya">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
          </button>
        </div>
      </div>

    </div>
  </section>

  <div class="modal-overlay" id="addTeacherModal">
    <div class="modal-box">
      <div class="modal-box-header">
        <h3>Tambah Profil Guru</h3>
        <button type="button" onclick="closeAddTeacherModal()" style="border:none; background:none; cursor:pointer; font-size:22px; color:var(--ink-soft);">✕</button>
      </div>
      <form id="formAddTeacher">
        <div class="form-group">
          <label>Kategori / Badge</label>
          <input type="text" id="ntBadge" placeholder="Unit / sekolah" required>
        </div>
        <div class="form-group">
          <label>Nama Lengkap &amp; Gelar</label>
          <input type="text" id="ntName" placeholder="Nama lengkap &amp; gelar" required>
        </div>
        <div class="form-group">
          <label>Jabatan / Mata Pelajaran</label>
          <input type="text" id="ntSubject" placeholder="Jabatan / mata pelajaran" required>
        </div>
        <div class="form-group">
          <label>Deskripsi Singkat</label>
          <textarea id="ntDesc" rows="2" placeholder="Ringkasan singkat tentang guru ini..." required></textarea>
        </div>
        <div class="form-group">
          <label>Tugas &amp; Tanggung Jawab</label>
          <textarea id="ntTugas" rows="3" placeholder="Satu baris untuk satu poin" required></textarea>
          <span class="hint">Tulis tiap poin di baris baru.</span>
        </div>
        <div class="form-group">
          <label>Pendidikan &amp; Pengalaman</label>
          <textarea id="ntPendidikan" rows="3" placeholder="Satu baris untuk satu poin" required></textarea>
          <span class="hint">Tulis tiap poin di baris baru.</span>
        </div>
        <div class="form-group">
          <label>Foto Guru</label>
          <input type="file" id="ntImg" accept="image/jpeg,image/png,image/webp,image/gif" required>
          <img id="ntImgPreview" style="display:none; margin-top:8px; max-height:140px; border-radius:8px; border:1px solid var(--border);">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:10px;" id="btnSubmitTeacher">Tambah Guru</button>
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

    const FOTO_KOSONG = "data:image/svg+xml;utf8,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27"
      + "%20viewBox=%270%200%20600%20600%27%3E%3Crect%20width=%27600%27%20height=%27600%27"
      + "%20fill=%27%23EFE7D9%27/%3E%3Ccircle%20cx=%27300%27%20cy=%27232%27%20r=%2786%27"
      + "%20fill=%27%23D3C6B0%27/%3E%3Cpath%20d=%27M126%20524c0-96%2078-174%20174-174s174%2078%20174%20174z%27"
      + "%20fill=%27%23D3C6B0%27/%3E%3C/svg%3E";

    let isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    let editModeActive = false;

    let stafServerData = Object.assign(
      { fields: {}, images: {}, extra: [], deleted: [] },
      <?php echo json_encode([
          'fields'  => (object) $INITIAL_STAF['fields'],
          'images'  => (object) $INITIAL_STAF['images'],
          'extra'   => array_values($INITIAL_STAF['extra']),
          'deleted' => array_values($INITIAL_STAF['deleted']),
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
    );

    function arPost(fields) {
      const fd = new FormData();
      Object.keys(fields).forEach(k => fd.append(k, fields[k]));
      return fetch(AR_ENDPOINT, { method: 'POST', body: fd }).then(async res => {
        const text = await res.text();
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error('Respon server bukan JSON:', text.slice(0, 300));
          return {
            success: false,
            error: 'Server tidak mengembalikan JSON. Cek Console untuk detail.'
          };
        }
      }).catch(err => {
        console.error('Koneksi gagal:', err);
        return { success: false, error: 'Koneksi ke server gagal. Perubahan TIDAK tersimpan.' };
      });
    }

    async function checkAdminStatus() {
      try {
        const res = await fetch(AR_ENDPOINT + '?check_admin=1');
        const data = await res.json();
        if (typeof data.isAdmin === 'boolean' && data.isAdmin !== isAdmin) {
          window.location.reload();
          return;
        }
      } catch (err) {
        console.error('Gagal cek status admin:', err);
      }
      updateAdminUI();
    }

    function updateAdminUI() {
      const adminPanel = document.getElementById('adminPanel');
      if (adminPanel) adminPanel.classList.toggle('active', isAdmin);
      document.querySelectorAll('.default-teacher .btn-delete-default').forEach(btn => {
        btn.style.display = isAdmin ? 'block' : 'none';
      });
    }

    async function refreshStafDataFromServer() {
      try {
        const res  = await fetch(AR_ENDPOINT + '?action=get_staf_data', { cache: 'no-store' });
        const data = await res.json();
        stafServerData = {
          fields:  (data && data.fields)  || {},
          images:  (data && data.images)  || {},
          extra:   (data && Array.isArray(data.extra))   ? data.extra   : [],
          deleted: (data && Array.isArray(data.deleted)) ? data.deleted : []
        };
      } catch (err) {
        console.error('Gagal memuat data staf dari server:', err);
      }
      return stafServerData;
    }

    function saveStafDataToServer() {
      return arPost({ action: 'save_staf_data', data: JSON.stringify(stafServerData) });
    }

    // ============================================================
    // HAPUS FILE GAMBAR DI SERVER
    // ============================================================
    async function arDeleteImage(imageUrl) {
      if (!imageUrl || imageUrl.indexOf('uploads/staf/') !== 0) {
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

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', async function (e) {
        e.preventDefault();
        if (editModeActive && !confirm('Mode edit masih aktif. Keluar tanpa menyimpan?')) return;
        await arPost({ action: 'logout' });
        window.location.href = 'index.php';
      });
    }

    const header = document.getElementById('mainHeader');
    window.addEventListener('scroll', function () {
      if (!header) return;
      header.classList.toggle('scrolled', window.scrollY > 50);
    });

    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal, .reveal-scale').forEach(el => io.observe(el));

    function toggleDetail(btn) {
      const card = btn.closest('.teacher-card');
      const isExpanded = card.classList.contains('active');
      const textSpan = btn.querySelector('span');
      const iconSvg  = btn.querySelector('svg');

      if (isExpanded) {
        card.classList.remove('active');
        if (textSpan) textSpan.innerText = 'Lihat Detail Staf';
        if (iconSvg) iconSvg.style.transform = 'rotate(0deg)';
        return;
      }

      document.querySelectorAll('.teacher-card.active').forEach(c => {
        if (c === card) return;
        c.classList.remove('active');
        const otherBtn = c.querySelector('.btn-detail');
        if (!otherBtn) return;
        const otherSpan = otherBtn.querySelector('span');
        const otherIcon = otherBtn.querySelector('svg');
        if (otherSpan) otherSpan.innerText = 'Lihat Detail Staf';
        if (otherIcon) otherIcon.style.transform = 'rotate(0deg)';
      });

      card.classList.add('active');
      if (textSpan) textSpan.innerText = 'Tutup Detail';
      if (iconSvg) iconSvg.style.transform = 'rotate(180deg)';
    }

    function applyDeletedDefaults() {
      const deleted = stafServerData.deleted || [];
      document.querySelectorAll('.default-teacher[data-default-key]').forEach(card => {
        const key = card.dataset.defaultKey;
        card.style.display = deleted.indexOf(key) !== -1 ? 'none' : '';
      });
    }

    // ============================================================
    // HAPUS GURU BAWAAN + HAPUS GAMBAR FISIK (KALAU ADA)
    // ============================================================
    async function deleteDefaultTeacher(teacherKey) {
      if (!isAdmin) { alert('Anda harus login sebagai admin untuk menghapus guru.'); return; }
      if (!confirm('Sembunyikan guru ini dari halaman? Bisa ditampilkan lagi nanti.')) return;

      const imgKey = teacherKey + 'Img';
      const imgUrl = stafServerData.images && stafServerData.images[imgKey];

      const btn = document.querySelector(`.btn-delete-default[data-delete-default="${teacherKey}"]`);
      if (btn) { btn.disabled = true; btn.textContent = 'Menghapus...'; }

      const result = await arPost({ action: 'delete_default_teacher', teacher_key: teacherKey });
      if (!result.success) {
        if (btn) { btn.disabled = false; btn.textContent = 'Hapus Guru'; }
        alert(result.error || 'Gagal menghapus guru.');
        return;
      }

      // Hapus gambar fisik bawaan (kalau ada di uploads/staf/)
      if (imgUrl) {
        const delRes = await arDeleteImage(imgUrl);
        if (!delRes.success && !delRes.skipped) {
          console.warn('Gagal hapus gambar:', delRes.error);
        } else if (!delRes.skipped) {
          delete stafServerData.images[imgKey];
        }
      }

      stafServerData.deleted = result.deleted || [];
      applyDeletedDefaults();
      if (typeof initCardCarousel === 'function') initCardCarousel();
      alert('Guru berhasil disembunyikan.');
    }

    async function restoreDefaultTeacher(teacherKey) {
      if (!isAdmin) return;
      const result = await arPost({ action: 'restore_default_teacher', teacher_key: teacherKey });
      if (!result.success) { alert(result.error || 'Gagal memulihkan guru.'); return; }
      stafServerData.deleted = result.deleted || [];
      applyDeletedDefaults();
      if (typeof initCardCarousel === 'function') initCardCarousel();
    }

    function getAllEditableFields() { return document.querySelectorAll('[data-field]'); }
    function getAllImgWraps()       { return document.querySelectorAll('.img-edit-wrap[data-img-key]'); }

    function loadStafContent() {
      const saved = stafServerData;
      if (!saved) return;

      getAllEditableFields().forEach(el => {
        const key = el.dataset.field;
        if (saved.fields && Object.prototype.hasOwnProperty.call(saved.fields, key)) {
          el.innerHTML = saved.fields[key];
        }
      });

      getAllImgWraps().forEach(wrap => {
        const key = wrap.dataset.imgKey;
        const img = wrap.querySelector('img');
        if (img && saved.images && Object.prototype.hasOwnProperty.call(saved.images, key)) {
          const val = saved.images[key];
          if (val) img.src = val;
        }
      });
    }

    function collectStafFields() {
      const data = Object.assign({}, stafServerData.fields || {});
      getAllEditableFields().forEach(el => {
        data[el.dataset.field] = el.innerHTML.trim();
      });
      return data;
    }

    function collectStafImages() {
      const images = Object.assign({}, stafServerData.images || {});
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

    function initImageUploadListeners() {
      getAllImgWraps().forEach(wrap => {
        const fileInput = wrap.querySelector('.img-edit-input');
        const img = wrap.querySelector('img');
        if (!fileInput || !img) return;

        if (fileInput.dataset.bound === '1') return;
        fileInput.dataset.bound = '1';

        fileInput.addEventListener('change', async function () {
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
      btnToggleEdit.addEventListener('click', function () {
        const isActive = !editModeActive;
        setEditMode(isActive);
        btnToggleEdit.textContent = isActive ? 'Batalkan Edit' : 'Edit Konten Halaman';
        btnSaveContent.style.display = isActive ? 'inline-block' : 'none';
        if (!isActive) {
          getAllImgWraps().forEach(wrap => {
            const img = wrap.querySelector('img');
            if (img) delete img.dataset.pendingSrc;
          });
          loadStafContent();
        }
      });

      btnSaveContent.addEventListener('click', async function () {
        stafServerData.fields = collectStafFields();
        stafServerData.images = collectStafImages();

        stafServerData.extra = getExtraTeachers().map(t => {
          if (!t.id) return t;
          const f = stafServerData.fields;
          const k = t.id;
          const pick = (sfx, cur) =>
            Object.prototype.hasOwnProperty.call(f, k + sfx) ? f[k + sfx] : cur;
          return {
            id:         t.id,
            badge:      pick('_badge', t.badge),
            name:       pick('_name', t.name),
            subject:    pick('_subject', t.subject),
            desc:       pick('_desc', t.desc),
            tugas:      pick('_tugas', t.tugas),
            pendidikan: pick('_pendidikan', t.pendidikan)
          };
        });

        btnSaveContent.disabled = true;
        const originalLabel = btnSaveContent.textContent;
        btnSaveContent.textContent = 'Menyimpan...';

        const result = await saveStafDataToServer();

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

        await refreshStafDataFromServer();
        renderExtraTeachers();

        alert('Perubahan staf & guru berhasil disimpan di server.');
      });
    }

    function getExtraTeachers() { return stafServerData.extra || []; }
    function saveExtraTeachers(list) { stafServerData.extra = list; }

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

    function generateTeacherId() {
      return 't_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function teacherImgSrc(t) {
      const key = t.id + '_img';
      if (stafServerData.images && stafServerData.images[key]) return stafServerData.images[key];
      if (t.img) return t.img;
      return FOTO_KOSONG;
    }

    function renderExtraTeachers() {
      const grid = document.getElementById('cardGrid');
      if (!grid) return;

      grid.querySelectorAll('.teacher-card[data-extra="true"]').forEach(el => el.remove());

      let list = getExtraTeachers();
      let needsMigration = false;
      list = list.map(t => {
        if (!t.id) { needsMigration = true; return Object.assign({}, t, { id: generateTeacherId() }); }
        return t;
      });
      if (needsMigration) { saveExtraTeachers(list); if (isAdmin) saveStafDataToServer(); }

      list.forEach(t => {
        const key  = t.id;
        const card = document.createElement('div');
        card.className = 'teacher-card reveal-scale';
        card.dataset.extra = 'true';
        card.dataset.teacher = key;

        card.innerHTML =
          '<div class="teacher-img">' +
            '<div class="img-edit-wrap" data-img-key="' + escapeAttr(key) + '_img" style="width:100%; height:100%;">' +
              '<img src="' + escapeAttr(teacherImgSrc(t)) + '" alt="">' +
              (isAdmin
                ? '<button type="button" class="img-edit-btn" onclick="this.parentNode.querySelector(\'.img-edit-input\').click()">Ganti Foto</button>' +
                  '<input type="file" class="img-edit-input" accept="image/jpeg,image/png,image/webp,image/gif">'
                : '') +
            '</div>' +
          '</div>' +
          '<div class="teacher-body">' +
            '<span class="badge-tag" data-placeholder="Unit / Sekolah" data-field="' + escapeAttr(key) + '_badge">' + (t.badge || '') + '</span>' +
            '<h3 data-placeholder="Nama Guru" data-field="' + escapeAttr(key) + '_name">' + (t.name || '') + '</h3>' +
            '<div class="subject" data-placeholder="Mata Pelajaran / Jabatan" data-field="' + escapeAttr(key) + '_subject">' + (t.subject || '') + '</div>' +
            '<p data-placeholder="Deskripsi singkat guru" data-field="' + escapeAttr(key) + '_desc">' + (t.desc || '') + '</p>' +
            '<div class="teacher-detail">' +
              '<div class="detail-section-title">Tugas &amp; Tanggung Jawab:</div>' +
              '<ul class="detail-list" data-placeholder="Tugas &amp; tanggung jawab" data-field="' + escapeAttr(key) + '_tugas">' + linesToListItems(t.tugas) + '</ul>' +
              '<div class="detail-section-title">Pendidikan &amp; Pengalaman:</div>' +
              '<ul class="detail-list" data-placeholder="Pendidikan &amp; pengalaman" data-field="' + escapeAttr(key) + '_pendidikan">' + linesToListItems(t.pendidikan) + '</ul>' +
            '</div>' +
            '<button class="btn-detail" onclick="toggleDetail(this)">' +
              '<span>Lihat Detail Staf</span>' +
              '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>' +
            '</button>' +
            (isAdmin
              ? '<button type="button" class="btn-danger" data-delete-extra="' + escapeAttr(key) + '" onclick="deleteExtraTeacher(\'' + escapeAttr(key) + '\')">Hapus Guru</button>'
              : '') +
          '</div>';

        grid.appendChild(card);
        if (typeof io !== 'undefined') io.observe(card);
      });

      initImageUploadListeners();
      setEditMode(editModeActive);
      loadStafContent();
      applyDeletedDefaults();

      if (typeof initCardCarousel === 'function') initCardCarousel();
    }

    // ============================================================
    // HAPUS GURU TAMBAHAN + HAPUS GAMBAR FISIK DI SERVER
    // ============================================================
    async function deleteExtraTeacher(teacherId) {
      if (!isAdmin) { alert('Anda harus login sebagai admin.'); return; }

      const item = getExtraTeachers().find(t => t.id === teacherId);
      if (!item) return;

      const imgKey = teacherId + '_img';
      const imgUrl = stafServerData.images && stafServerData.images[imgKey];

      if (!confirm('Apakah Anda yakin ingin menghapus profil guru ini?\nGambar yang tersimpan di server juga akan dihapus.')) return;

      const btn = document.querySelector(`.btn-danger[data-delete-extra="${teacherId}"]`);
      if (btn) { btn.disabled = true; btn.textContent = 'Menghapus...'; }

      // 1. Hapus file gambar fisik dulu
      if (imgUrl) {
        const delRes = await arDeleteImage(imgUrl);
        if (!delRes.success && !delRes.skipped) {
          console.warn('Gagal hapus gambar:', delRes.error);
        }
      }

      // 2. Hapus data dari array
      const list  = getExtraTeachers().slice();
      const index = list.findIndex(t => t.id === teacherId);
      if (index === -1) return;
      list.splice(index, 1);
      stafServerData.extra = list;

      ['_badge', '_name', '_subject', '_desc', '_tugas', '_pendidikan', '_img']
        .forEach(sfx => {
          delete stafServerData.fields[teacherId + sfx];
          delete stafServerData.images[teacherId + sfx];
        });

      // 3. Simpan ke server
      const result = await arPost({
        action: 'save_staf_data',
        data:   JSON.stringify(stafServerData),
        force:  '1'
      });

      if (!result || !result.success) {
        alert((result && result.error) || 'Gagal menghapus guru di server.');
        await refreshStafDataFromServer();
        renderExtraTeachers();
        return;
      }

      await refreshStafDataFromServer();
      renderExtraTeachers();

      alert('Profil guru dan gambar berhasil dihapus.');
    }

    let cardCarouselInited = false;

    function initCardCarousel() {
      const grid = document.getElementById('cardGrid');
      const prevBtn = document.getElementById('cardPrev');
      const nextBtn = document.getElementById('cardNext');
      const dotsWrap = document.getElementById('cardDots');
      if (!grid || !prevBtn || !nextBtn || !dotsWrap) return;

      function getCardsPerView() {
        const w = window.innerWidth;
        if (w <= 720) return 1;
        if (w <= 980) return 2;
        return 3;
      }

      function getVisibleCards() {
        return Array.from(grid.querySelectorAll('.teacher-card'))
          .filter(card => card.style.display !== 'none');
      }

      function buildDots() {
        const visible = getVisibleCards();
        const total = visible.length;
        const perView = getCardsPerView();
        const pages = Math.max(1, Math.ceil(total / perView));
        dotsWrap.innerHTML = '';
        for (let i = 0; i < pages; i++) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'card-dot';
          btn.setAttribute('aria-label', 'Ke halaman ' + (i + 1));
          btn.addEventListener('click', () => {
            const card = grid.querySelector('.teacher-card');
            if (!card) return;
            const cardWidth = card.offsetWidth + 28;
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
        const card = grid.querySelector('.teacher-card');
        if (!card || !dotsWrap.children.length) return;

        const cardWidth = card.offsetWidth + 28;
        const activeIndex = Math.round(scrollLeft / (cardWidth * perView));
        Array.from(dotsWrap.children).forEach((dot, i) => {
          dot.classList.toggle('active', i === activeIndex);
        });
      }

      function scrollByCards(direction) {
        const card = grid.querySelector('.teacher-card');
        if (!card) return;
        const perView = getCardsPerView();
        const cardWidth = card.offsetWidth + 28;
        grid.scrollBy({ left: direction * cardWidth * perView, behavior: 'smooth' });
      }

      if (!cardCarouselInited) {
        prevBtn.addEventListener('click', () => scrollByCards(-1));
        nextBtn.addEventListener('click', () => scrollByCards(1));
        grid.addEventListener('scroll', updateNavState, { passive: true });
        window.addEventListener('resize', () => {
          buildDots();
          updateNavState();
        });
        cardCarouselInited = true;
      }

      buildDots();
    }

    const btnAddTeacher  = document.getElementById('btnAddTeacher');
    const addTeacherModal = document.getElementById('addTeacherModal');
    if (btnAddTeacher && addTeacherModal) {
      btnAddTeacher.addEventListener('click', () => addTeacherModal.classList.add('active'));
    }
    function closeAddTeacherModal() {
      if (addTeacherModal) addTeacherModal.classList.remove('active');
    }

    const ntImgInput   = document.getElementById('ntImg');
    const ntImgPreview = document.getElementById('ntImgPreview');
    if (ntImgInput && ntImgPreview) {
      ntImgInput.addEventListener('change', () => {
        const file = ntImgInput.files[0];
        if (file) {
          ntImgPreview.src = URL.createObjectURL(file);
          ntImgPreview.style.display = 'block';
        } else {
          ntImgPreview.style.display = 'none';
        }
      });
    }

    const formAddTeacher = document.getElementById('formAddTeacher');
    if (formAddTeacher) {
      formAddTeacher.addEventListener('submit', async (e) => {
        e.preventDefault();

        const file = ntImgInput && ntImgInput.files[0];
        if (!file) { alert('Silakan pilih foto guru terlebih dahulu.'); return; }

        const btnSubmit = document.getElementById('btnSubmitTeacher');
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Mengunggah...';

        try {
          const uploadResult = await arUploadFile(file);
          if (!uploadResult.success) {
            alert(uploadResult.error || 'Gagal mengunggah foto.');
            return;
          }

          const id = generateTeacherId();
          const newTeacher = {
            id:         id,
            badge:      document.getElementById('ntBadge').value,
            name:       document.getElementById('ntName').value,
            subject:    document.getElementById('ntSubject').value,
            desc:       document.getElementById('ntDesc').value,
            tugas:      document.getElementById('ntTugas').value,
            pendidikan: document.getElementById('ntPendidikan').value
          };

          const list = getExtraTeachers().slice();
          list.push(newTeacher);
          saveExtraTeachers(list);
          stafServerData.images[id + '_img'] = uploadResult.url;

          btnSubmit.textContent = 'Menyimpan...';
          const saveResult = await saveStafDataToServer();
          if (!saveResult || !saveResult.success) {
            alert((saveResult && saveResult.error) || 'Gagal menyimpan guru baru ke server.');
            return;
          }

          await refreshStafDataFromServer();
          renderExtraTeachers();

          formAddTeacher.reset();
          if (ntImgPreview) ntImgPreview.style.display = 'none';
          closeAddTeacherModal();
          alert('Profil guru berhasil ditambahkan!');
        } finally {
          btnSubmit.disabled = false;
          btnSubmit.textContent = 'Tambah Guru';
        }
      });
    }

    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) {
      menuToggle.addEventListener('click', function () {
        const nav = document.querySelector('nav');
        if (nav) nav.classList.toggle('open');
      });
    }

    window.addEventListener('beforeunload', function (e) {
      if (editModeActive) { e.preventDefault(); e.returnValue = ''; }
    });

    updateAdminUI();
    renderExtraTeachers();
    initCardCarousel();

    window.addEventListener('load', function () {
      document.body.classList.add('page-loaded');
      checkAdminStatus();
      initCardCarousel();
    });
  </script>
</body>
</html>