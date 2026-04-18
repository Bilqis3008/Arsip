<?php
$dir = __DIR__ . '/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    $basename = basename($file);
    if ($basename === 'arsip_digital.php') {
        unlink($file);
        echo "Deleted arsip_digital.php\n";
        continue;
    }
    
    // skip the script itself
    if ($basename === 'update_sidebar.php') continue;

    $content = file_get_contents($file);
    $original = $content;

    // Remove Arsip Digital menu item
    $content = preg_replace('/<a href="arsip_digital\.php"[^>]*>.*?<\/a>\s*/is', '', $content);
    
    // Rename Laporan Executive to Laporan
    $content = str_replace('> Laporan Executive</a>', '> Laporan</a>', $content);

    if ($original !== $content) {
        file_put_contents($file, $content);
        echo "Updated $basename\n";
    }
}
echo "Sidebar update complete.\n";
