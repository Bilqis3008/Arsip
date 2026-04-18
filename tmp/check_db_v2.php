<?php
require_once 'config/db.php';
$tables = ['surat_masuk', 'surat_keluar', 'disposisi', 'users', 'seksi', 'bidang'];
$out = "";
foreach($tables as $t) {
    $out .= "--- TABLE: $t ---\n";
    $stmt = $pdo->query("DESCRIBE $t");
    $cols = $stmt->fetchAll();
    foreach($cols as $c) {
        $out .= $c['Field'] . " (" . $c['Type'] . ")\n";
    }
    $out .= "\n";
}
file_put_contents('tmp/schema_dump.txt', $out);
echo "SUCCESS";
