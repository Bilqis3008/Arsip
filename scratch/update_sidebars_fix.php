<?php
$files = [
    'sekretariat/surat_masuk.php'
];

$menu_item_active = <<<EOD
            <a href="surat_keluar.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
                Surat Keluar
            </a>
EOD;

foreach ($files as $file) {
    $path = 'c:/laragon/www/Arsip/' . $file;
    if (!file_exists($path)) {
        echo "File not found: $path\n";
        continue;
    }
    $content = file_get_contents($path);
    
    if (strpos($content, 'href="surat_keluar.php"') !== false) {
        echo "Already has surat_keluar in $file\n";
        continue;
    }
    
    // Look for active link as well
    $pattern = '/<a href="surat_masuk.php" class="menu-item active">.*?<\/a>/s';
    if (preg_match($pattern, $content, $matches)) {
        $new_content = str_replace($matches[0], $matches[0] . "\n" . $menu_item_active, $content);
        file_put_contents($path, $new_content);
        echo "Updated $file\n";
    } else {
        echo "Could not find surat_masuk.php link in $file\n";
    }
}
?>
