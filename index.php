<?php

const MAX_BYTES = 25 * 1024 * 1024;
const EXPIRY_OPTIONS = [
    '1h' => 3600,
    '1d' => 86400,
    '7d' => 604800,
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/share.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS files (
                token TEXT PRIMARY KEY,
                original_name TEXT NOT NULL,
                size INTEGER NOT NULL,
                mime TEXT NOT NULL,
                expires_at INTEGER NOT NULL,
                downloads INTEGER NOT NULL DEFAULT 0,
                uploaded_at INTEGER NOT NULL
            );
        ');
    }
    return $pdo;
}

function cleanup(): void
{
    $expired = db()->prepare('SELECT token FROM files WHERE expires_at < ?');
    $expired->execute([time()]);
    foreach ($expired->fetchAll() as $row) {
        @unlink(__DIR__ . '/uploads/' . $row['token'] . '.bin');
    }
    db()->prepare('DELETE FROM files WHERE expires_at < ?')->execute([time()]);
}

function human_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

cleanup();

$link = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['file'] ?? null;
    $expiry = $_POST['expiry'] ?? '1d';

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Pick a file first.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = match($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large. Maximum is 25 MB.',
            UPLOAD_ERR_PARTIAL    => 'Upload was cut off, please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server misconfiguration: no temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the file to disk.',
            default => 'Upload failed (code ' . $file['error'] . '), try again.',
        };
    } elseif ($file['size'] > MAX_BYTES) {
        $error = 'Files are capped at 25 MB.';
    } elseif (!isset(EXPIRY_OPTIONS[$expiry])) {
        $error = 'Pick a valid expiry.';
    } else {
        $token = bin2hex(random_bytes(16));
        $dest = __DIR__ . '/uploads/' . $token . '.bin';

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $error = 'Could not store the file.';
        } else {
            $name = preg_replace('/[^\w.\- ]/u', '_', basename($file['name'])) ?: 'file';
            $mime = mime_content_type($dest) ?: 'application/octet-stream';

            $stmt = db()->prepare(
                'INSERT INTO files (token, original_name, size, mime, expires_at, uploaded_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$token, $name, $file['size'], $mime, time() + EXPIRY_OPTIONS[$expiry], time()]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $link = $base . '/d.php?t=' . $token;
        }
    }
}

$recent = db()->query('SELECT original_name, size, downloads, expires_at FROM files ORDER BY uploaded_at DESC LIMIT 5')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driftbox — files that vanish</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  background: #101216;
  color: #e8e8e4;
  font-family: system-ui, sans-serif;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 9vh 1.4rem 4rem;
}
h1 { font-size: 2.4rem; letter-spacing: 0.04em; }
h1 span { color: #4cc9a8; }
.tag { color: #7c8088; margin-top: 0.5rem; font-size: 0.92rem; }
form {
  margin-top: 2.6rem;
  width: min(460px, 100%);
  background: #171a20;
  border: 1px solid #262b33;
  border-radius: 16px;
  padding: 1.8rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}
input[type="file"] {
  border: 1px dashed #3a414c;
  border-radius: 10px;
  padding: 1.6rem 1rem;
  color: #7c8088;
  cursor: pointer;
}
.row { display: flex; gap: 0.8rem; align-items: center; }
select {
  flex: 1;
  background: #101216;
  color: #e8e8e4;
  border: 1px solid #262b33;
  border-radius: 8px;
  padding: 0.65em 0.8em;
  font: inherit;
}
button {
  background: #4cc9a8;
  color: #0c1410;
  border: none;
  border-radius: 8px;
  font: inherit;
  font-weight: 600;
  padding: 0.8em 1.8em;
  cursor: pointer;
}
button:hover { filter: brightness(1.1); }
.error { color: #ff7d6b; font-size: 0.88rem; }
.link-box {
  margin-top: 1.6rem;
  width: min(460px, 100%);
  background: #14241f;
  border: 1px solid #2c5247;
  border-radius: 12px;
  padding: 1.1rem 1.3rem;
  word-break: break-all;
}
.link-box a { color: #4cc9a8; }
.recent {
  margin-top: 3rem;
  width: min(460px, 100%);
  font-size: 0.85rem;
  color: #7c8088;
}
.recent h2 { font-size: 0.8rem; letter-spacing: 0.2em; text-transform: uppercase; margin-bottom: 0.8rem; }
.recent li {
  list-style: none;
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #1d2129;
}
</style>
</head>
<body>

<h1>drift<span>box</span></h1>
<p class="tag">Share a file with a link that self-destructs.</p>

<form method="post" enctype="multipart/form-data">
  <input type="file" name="file" required>
  <div class="row">
    <select name="expiry">
      <option value="1h">Expires in 1 hour</option>
      <option value="1d" selected>Expires in 1 day</option>
      <option value="7d">Expires in 7 days</option>
    </select>
    <button type="submit">Upload</button>
  </div>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p><?php endif; ?>
</form>

<?php if ($link): ?>
  <div class="link-box">
    Your link: <a href="<?= htmlspecialchars($link, ENT_QUOTES) ?>"><?= htmlspecialchars($link, ENT_QUOTES) ?></a>
  </div>
<?php endif; ?>

<?php if ($recent): ?>
  <div class="recent">
    <h2>Recent uploads</h2>
    <ul>
      <?php foreach ($recent as $row): ?>
        <li>
          <span><?= htmlspecialchars($row['original_name'], ENT_QUOTES) ?></span>
          <span><?= human_size((int) $row['size']) ?> · <?= (int) $row['downloads'] ?> dl</span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

</body>
</html>
