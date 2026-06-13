<?php

$token = $_GET['t'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(404);
    exit('Link not found.');
}

$pdo = new PDO('sqlite:' . __DIR__ . '/share.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT original_name, mime, size, expires_at FROM files WHERE token = ?');
$stmt->execute([$token]);
$file = $stmt->fetch();

$path = __DIR__ . '/uploads/' . $token . '.bin';

if (!$file || !is_file($path)) {
    http_response_code(404);
    exit('Link not found.');
}

if ($file['expires_at'] < time()) {
    @unlink($path);
    $pdo->prepare('DELETE FROM files WHERE token = ?')->execute([$token]);
    http_response_code(410);
    exit('This link has expired.');
}

$pdo->prepare('UPDATE files SET downloads = downloads + 1 WHERE token = ?')->execute([$token]);

header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . $file['size']);
header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
