<?php
$dir = __DIR__ . '/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    if (basename($file) === 'surat_keluar.php') {
        unlink($file);
        echo "Deleted surat_keluar.php\n";
        continue;
    }

    if (basename($file) === 'update_sidebar.php' || basename($file) === 'update_sidebar2.php') continue;

    $content = file_get_contents($file);
    $original = $content;

    // Remove Surat Keluar menu item
    $content = preg_replace('/<a href="surat_keluar\.php"[^>]*>.*?<\/a>\s+/is', '', $content);

    if ($original !== $content) {
        file_put_contents($file, $content);
        echo "Updated " . basename($file) . "\n";
    }
}
echo "Sidebar update complete.\n";
