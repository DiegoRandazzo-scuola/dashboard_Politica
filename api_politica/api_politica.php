<?php
// api_politica.php
header('Content-Type: application/json');

// Configurazione cache
$cacheFile = __DIR__ . '/cache_politica.json';
$cacheTime = 600; // 10 minuti

// Se la cache è ancora valida, restituiscila
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    readfile($cacheFile);
    exit;
}

// Funzione per scaricare un feed XML
function fetchFeed($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; DashboardPolitica/1.0)'
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // Non serve più curl_close() in PHP 8.5+; l'handle viene distrutto automaticamente
    // curl_close($ch);
    
    if ($httpCode !== 200 || empty($data)) {
        return false;
    }
    return $data;
}

// Funzione per estrarre gli item da un feed XML
function parseFeed($xmlData, $sourceName) {
    if (!$xmlData) return [];
    
    $items = [];
    $xml = simplexml_load_string($xmlData);
    if ($xml === false) return [];
    
    $channel = $xml->channel;
    
    foreach ($channel->item as $item) {
        $title = (string)$item->title;
        $link = (string)$item->link;
        $description = '';
        if (isset($item->children('content', true)->encoded)) {
            $description = strip_tags((string)$item->children('content', true)->encoded);
        } elseif (isset($item->description)) {
            $description = strip_tags((string)$item->description);
        }
        $pubDate = (string)$item->pubDate;
        $timestamp = strtotime($pubDate);
        
        $items[] = [
            'title'       => $title,
            'link'        => $link,
            'description' => $description,
            'pubDate'     => $pubDate,
            'timestamp'   => $timestamp,
            'source'      => $sourceName
        ];
    }
    
    return $items;
}

// Lista dei feed da aggregare
$feeds = [
    [
        'url'    => 'http://www.ansa.it/sito/notizie/politica/politica_rss.xml',
        'name'   => 'ANSA Politica'
    ],
    [
        'url'    => 'http://www.gazzettaufficiale.it/rss/atti.go?tipo=PRIMASERIE',
        'name'   => 'Gazzetta Ufficiale'
    ]
];

$allItems = [];
$errors = [];

foreach ($feeds as $feed) {
    $xmlData = fetchFeed($feed['url']);
    if ($xmlData === false) {
        $errors[] = "Impossibile raggiungere il feed: {$feed['name']}";
        continue;
    }
    $items = parseFeed($xmlData, $feed['name']);
    $allItems = array_merge($allItems, $items);
}

// Ordina per data decrescente
usort($allItems, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Limita a 50 notizie per non appesantire
$allItems = array_slice($allItems, 0, 50);

// Prepara la risposta JSON
$response = [
    'items' => $allItems,
    'errors' => $errors,
    'lastUpdate' => date('Y-m-d H:i:s')
];

// Se non ci sono item e ci sono errori, restituisci un messaggio di errore
if (empty($allItems) && !empty($errors)) {
    http_response_code(503);
    echo json_encode(['error' => 'Servizio momentaneamente non disponibile']);
    exit;
}

$json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Salva in cache
file_put_contents($cacheFile, $json);

echo $json;