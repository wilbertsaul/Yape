<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=yape', 'root', 'root');

echo "=== saldos_iniciales table ===\n";
$stmt = $pdo->query('DESCRIBE saldos_iniciales');
foreach ($stmt as $row) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . "\n";
}

echo "\n=== saldos_iniciales data ===\n";
$stmt = $pdo->query('SELECT * FROM saldos_iniciales');
foreach ($stmt as $row) {
    print_r($row);
}

echo "\n=== Transacciones summary ===\n";
$stmt = $pdo->query("SELECT tipo_movimiento, COUNT(*) as cnt, SUM(monto_transferencia) as total FROM transacciones GROUP BY tipo_movimiento");
foreach ($stmt as $row) {
    echo $row['tipo_movimiento'] . ': count=' . $row['cnt'] . ' total=' . $row['total'] . "\n";
}
