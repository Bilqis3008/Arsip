<?php
$dir = __DIR__ . '/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    if (basename($file) === 'arsip_digital.php' || basename($file) === 'surat_keluar.php') {
        unlink($file);
        echo "Deleted " . basename($file) . "\n";
        continue;
    }

    if (basename($file) === 'update_staff_sidebar.php') continue;

    $content = file_get_contents($file);
    $original = $content;

    // Remove Surat Keluar and Arsip Digital
    $content = preg_replace('/<a href="surat_keluar\.php"[^>]*>.*?<\/a>\s+/is', '', $content);
    $content = preg_replace('/<a href="arsip_digital\.php"[^>]*>.*?<\/a>\s+/is', '', $content);

    if ($original !== $content) {
        file_put_contents($file, $content);
        echo "Updated " . basename($file) . "\n";
    }
}
echo "Sidebar update complete.\n";
