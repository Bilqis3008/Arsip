<?php
$dir = __DIR__ . '/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    if (in_array(basename($file), ['surat_keluar.php', 'update_sidebar.php', 'update_sidebar2.php', 'update_sidebar3.php'])) continue;

    $content = file_get_contents($file);
    $original = $content;

    // Remove if accidentally added multiple times, then add it back cleanly
    $content = preg_replace('/<a href="surat_keluar\.php".*?<\/a>\s*/is', '', $content);
    
    $replacement = "$1\n            <a href=\"surat_keluar.php\" class=\"menu-item\"><svg class=\"icon\"><line x1=\"22\" y1=\"2\" x2=\"11\" y2=\"13\"></line><polygon points=\"22 2 15 22 11 13 2 9 22 2\"></polygon></svg> Surat Keluar</a>\n";
    $content = preg_replace('/(<a href="monitoring_tindakLanjut\.php"[^>]*>.*?<\/a>)/is', $replacement, $content);

    if ($original !== $content) {
        file_put_contents($file, $content);
        echo "Updated " . basename($file) . "\n";
    }
}
echo "Sidebar update complete.\n";
