<?php
// --- CONFIGURAZIONE MONDO 296 ---
$serverID = "LKWorldServer-RE-IT-5";        // ID del 296 (Retro Server IT 5)
$fileDatabase = 'database_mondo_296.json';  // File JSON di output
$backendURL = "https://backend1.lordsandknights.com"; // Backend 1 (HTTPS)
// --------------------------------

$tempMap = [];
$puntiCaldi = []; 

// 1. Carica dati esistenti per sapere dove guardare (se esistono)
if (file_exists($fileDatabase)) {
    $content = file_get_contents($fileDatabase);
    $currentData = json_decode($content, true);
    
    if (is_array($currentData)) {
        foreach ($currentData as $entry) {
            if (is_array($entry) && isset($entry['x'], $entry['y'])) {
                $key = $entry['x'] . "_" . $entry['y'];
                $tempMap[$key] = $entry;
                // Salviamo i "quadranti noti"
                $jtileX = floor($entry['x'] / 32); 
                $jtileY = floor($entry['y'] / 32);
                $puntiCaldi[$jtileX . "_" . $jtileY] = ['x' => $jtileX, 'y' => $jtileY];
            }
        }
    }
}

echo "Dati caricati. Analisi di " . count($puntiCaldi) . " quadranti conosciuti...\n";

// Fase 1: Aggiorna SOLO le zone note
foreach ($puntiCaldi as $zona) {
    processTile($zona['x'], $zona['y'], $serverID, $tempMap, $backendURL);
}

// Fase 2: Espansione a Spirale (Parte dal centro della mappa o dai dati noti)
$centerX = 500; $centerY = 500; // Coordinate centrali standard
if (count($tempMap) > 0) {
    // Ricalcola il centro in base alla media dei giocatori trovati per ottimizzare
    $sumX = 0; $sumY = 0;
    foreach ($tempMap as $h) { $sumX += floor($h['x']/32); $sumY += floor($h['y']/32); }
    $centerX = round($sumX / count($tempMap));
    $centerY = round($sumY / count($tempMap));
}

$raggioMax = 150; 
$limiteVuoti = 10; // STOP dopo 10 giri vuoti
$contatoreVuoti = 0;

echo "Avvio espansione a spirale dal centro ($centerX, $centerY)...\n";

for ($r = 0; $r <= $raggioMax; $r++) {
    $trovatoNuovo = false;
    $xMin = $centerX - $r; $xMax = $centerX + $r;
    $yMin = $centerY - $r; $yMax = $centerY + $r;
    
    // Genera coordinate anello
    $punti = [];
    for ($i = $xMin; $i <= $xMax; $i++) { $punti[] = [$i, $yMin]; $punti[] = [$i, $yMax]; }
    for ($j = $yMin + 1; $j < $yMax; $j++) { $punti[] = [$xMin, $j]; $punti[] = [$xMax, $j]; }
    
    foreach ($punti as $p) {
        if (isset($puntiCaldi[$p[0] . "_" . $p[1]])) continue;
        
        if (processTile($p[0], $p[1], $serverID, $tempMap, $backendURL)) {
            $trovatoNuovo = true;
        }
    }
    
    if ($trovatoNuovo) {
        $contatoreVuoti = 0; 
    } else {
        $contatoreVuoti++;
    }
    
    if ($contatoreVuoti >= $limiteVuoti) {
        echo "🛑 STOP: Trovato il vuoto per $limiteVuoti giri consecutivi.\n";
        break;
    }
}

// Pulizia dati vecchi (> 72h) e salvataggio
$limite = time() - (72 * 3600);
$mappaPulita = array_filter($tempMap, function($e) use ($limite) { 
    return !isset($e['d']) || $e['d'] > $limite; 
});

file_put_contents($fileDatabase, json_encode(array_values($mappaPulita), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Fine. Database aggiornato: " . count($mappaPulita) . " castelli.\n";

// Funzione di download
function processTile($x, $y, $sid, &$tmp, $bk) {
    // URL aggiornato con HTTPS
    $url = "$bk/maps/$sid/{$x}_{$y}.jtile";
    
    // Usiamo un contesto HTTP minimo se necessario, ma solitamente i tile sono pubblici
    $c = @file_get_contents($url);
    
    if (!$c || $c === 'callback_politicalmap({})') return false; 
    
    if (preg_match('/\((.*)\)/s', $c, $m)) {
        $j = json_decode($m[1], true);
        if (isset($j['habitatArray']) && count($j['habitatArray']) > 0) {
            foreach ($j['habitatArray'] as $h) {
                $tmp[$h['mapx']."_".$h['mapy']] = [
                    'p' => (int)$h['playerid'],
                    'a' => (int)$h['allianceid'],
                    'n' => $h['name'] ?? '',
                    'x' => (int)$h['mapx'],
                    'y' => (int)$h['mapy'],
                    'pt'=> (int)$h['points'],
                    't' => (int)$h['habitattype'],
                    'd' => time()
                ];
            }
            return true;
        }
    }
    return false;
}
?>
