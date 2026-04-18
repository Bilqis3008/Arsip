<?php
$dir = __DIR__ . '/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    if (basename($file) === 'arsip_digital.php') {
        unlink($file);
        echo "Deleted arsip_digital.php\n";
        continue;
    }

    if (basename($file) === 'update_sidebar.php') continue;

    $content = file_get_contents($file);
    $original = $content;

    // Remove Arsip Digital
    $content = preg_replace('/<a href="arsip_digital\.php"[^>]*>.*?<\/a>\s+/is', '', $content);
    
    // Rename Laporan Bidang to Laporan
    $content = str_replace('> Laporan Bidang</a>', '> Laporan</a>', $content);

    if ($original !== $content) {
        file_put_contents($file, $content);
        echo "Updated " . basename($file) . "\n";
    }
}
echo "Sidebar update complete.\n";
