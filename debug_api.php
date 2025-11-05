<?php

// Debug the API query issue
$url = "http://localhost:8080/api/amisafe/aggregated?resolution=5&h3_index=852a134bfffffff&limit=1";

echo "Testing URL: $url\n\n";

$response = file_get_contents($url);
$data = json_decode($response, true);

echo "Response count: " . count($data['hexagons']) . "\n";
echo "Meta filters: " . json_encode($data['meta']['filters']) . "\n";

if (count($data['hexagons']) > 0) {
    echo "First hexagon h3_index: " . $data['hexagons'][0]['h3_index'] . "\n";
    echo "First hexagon resolution: " . $data['hexagons'][0]['resolution'] . "\n";
    echo "First hexagon incident_count: " . $data['hexagons'][0]['incident_count'] . "\n";
} else {
    echo "No hexagons returned!\n";
}

echo "\nTesting direct database query...\n";
$pdo = new PDO('mysql:host=localhost;dbname=theoryofconspiracies_dev', 'root', 'root');
$stmt = $pdo->prepare("SELECT h3_index, h3_resolution, incident_count FROM amisafe_h3_aggregated WHERE h3_resolution = 5 AND h3_index = '852a134bfffffff' LIMIT 1");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "Database result: " . json_encode($result) . "\n";
} else {
    echo "No database result!\n";
}

echo "\nTesting without resolution filter...\n";
$stmt2 = $pdo->prepare("SELECT h3_index, h3_resolution, incident_count FROM amisafe_h3_aggregated WHERE h3_index = '852a134bfffffff' LIMIT 1");
$stmt2->execute();
$result2 = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($result2) {
    echo "Database result (no resolution filter): " . json_encode($result2) . "\n";
} else {
    echo "No database result without resolution filter!\n";
}