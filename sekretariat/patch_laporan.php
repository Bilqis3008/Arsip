<?php
$dir = 'c:\\laragon\\www\\Arsipp\\sekretariat';
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    if (basename($file) === 'patch_laporan.php') continue;
    $content = file_get_contents($file);
    
    // Replace sidebar label
    $content = str_replace('Monitoring & Laporan', 'Laporan', $content);
    // There are some places that might use 'Monitoring &amp; Laporan'
    $content = str_replace('Monitoring &amp; Laporan', 'Laporan', $content);
    
    file_put_contents($file, $content);
}
echo "Global rename to Laporan completed.";
