<?php
$basePath = dirname(__DIR__);

// Clean up all daily CSV files and regenerate from scratch
foreach (glob($basePath . '/docs/daily/*/*.csv') as $csvFile) {
    unlink($csvFile);
}

// Load geo data for mapping
$geoFile = dirname($basePath) . '/taiwan_basecode/cunli/s_geo/20250620.json';
$geoData = json_decode(file_get_contents($geoFile), true);
$villageMap = [];

// Build village mapping index and collect unique county codes
$countyCodes = [];
foreach ($geoData['features'] as $feature) {
    $props = $feature['properties'];
    $key = $props['COUNTYNAME'] . '_' . $props['TOWNNAME'] . '_' . $props['VILLNAME'];
    $villageMap[$key] = [
        'VILLCODE' => $props['VILLCODE'],
        'COUNTYCODE' => $props['COUNTYCODE'],
        'COUNTYNAME' => $props['COUNTYNAME'],
        'TOWNNAME' => $props['TOWNNAME'],
        'VILLNAME' => $props['VILLNAME'],
    ];
    $countyCodes[$props['COUNTYCODE']] = true;
}

// Also handle variations in county names
$countyNameVariations = [
    '臺北市' => '台北市',
    '臺中市' => '台中市',
    '臺南市' => '台南市',
    '臺東縣' => '台東縣',
];

foreach ($countyNameVariations as $original => $variation) {
    foreach ($geoData['features'] as $feature) {
        $props = $feature['properties'];
        if ($props['COUNTYNAME'] === $original) {
            $key = $variation . '_' . $props['TOWNNAME'] . '_' . $props['VILLNAME'];
            $villageMap[$key] = [
                'VILLCODE' => $props['VILLCODE'],
                'COUNTYCODE' => $props['COUNTYCODE'],
                'COUNTYNAME' => $props['COUNTYNAME'],
                'TOWNNAME' => $props['TOWNNAME'],
                'VILLNAME' => $props['VILLNAME'],
            ];
        }
    }
}

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$fh = fopen('https://od.cdc.gov.tw/eic/Dengue_Daily.csv', 'r', false, $context);
$header = fgetcsv($fh, 2048);
if (!isset($header[20])) {
    exit();
}

// Add VILLCODE and COUNTYCODE to header
$header[] = 'VILLCODE';
$header[] = 'COUNTYCODE';

$sum = $cunli = $cunliImported = [];
$countyData = [];

// Build cityList dynamically from collected county codes
$cityList = [];
foreach (array_keys($countyCodes) as $code) {
    $cityList[$code] = 0;
}
ksort($cityList);
$cityCode = [
    'A13' => '10013',
    'A02' => '10002',
    'A64' => '64000',
    'A65' => '65000',
    'A63' => '63000',
    'A67' => '67000',
    'A04' => '10004',
    'A08' => '10008',
    'A66' => '66000',
    'A09' => '10009',
    'A07' => '10007',
    'A15' => '10015',
    'A14' => '10014',
    'A10' => '10010',
    'A20' => '10020',
    'A17' => '10017',
    'A05' => '10005',
    'A18' => '10018',
    'A16' => '10016',
    'A97' => '09007',
    'A92' => '09020',
    'A68' => '68000',
    '屏東縣' => '10013',
    '宜蘭縣' => '10002',
    '高雄市' => '64000',
    '桃園市' => '68000',
    '新北市' => '65000',
    '台北市' => '63000',
    '台南市' => '67000',
    '新竹縣' => '10004',
    '南投縣' => '10008',
    '台中市' => '66000',
    '雲林縣' => '10009',
    '彰化縣' => '10007',
    '花蓮縣' => '10015',
    '台東縣' => '10014',
    '嘉義縣' => '10010',
    '嘉義市' => '10020',
    '基隆市' => '10017',
    '苗栗縣' => '10005',
    '新竹市' => '10018',
    '澎湖縣' => '10016',
    '連江縣' => '09007',
    '金門縣' => '09020',
];

$yearDivide = 2025;

// Find column indices for residence data
$countyIndex = array_search('居住縣市', $header);
$townIndex = array_search('居住鄉鎮', $header);
$villageIndex = array_search('居住村里', $header);
$sickDateIndex = array_search('發病日', $header);
$caseCountIndex = array_search('確定病例數', $header);

while ($line = fgetcsv($fh, 2048)) {
    if (empty($line[0])) {
        continue;
    }
    foreach ($line as $k => $v) {
        if ($v === 'None') {
            $line[$k] = '';
        }
    }
    $y = substr($line[0], 0, 4);

    // Try to map location to VILLCODE and COUNTYCODE
    $villCode = '';
    $countyCode = '';

    if (!empty($line[$countyIndex]) && !empty($line[$townIndex]) && !empty($line[$villageIndex])) {
        $locationKey = $line[$countyIndex] . '_' . $line[$townIndex] . '_' . $line[$villageIndex];
        if (isset($villageMap[$locationKey])) {
            $villCode = $villageMap[$locationKey]['VILLCODE'];
            $countyCode = $villageMap[$locationKey]['COUNTYCODE'];
        }
    }

    // Add VILLCODE and COUNTYCODE to line
    $line[] = $villCode;
    $line[] = $countyCode;

    // Store data by county code for {year}/{COUNTYCODE}.csv (all years)
    if (!empty($countyCode)) {
        if (!isset($countyData[$y][$countyCode])) {
            $countyData[$y][$countyCode] = [];
        }
        $countyData[$y][$countyCode][] = $line;
    }

    // Determine city code for sum.json
    if ($y >= $yearDivide) {
        $city = $countyCode;
        if (empty($city)) {
            continue;
        }
    } else {
        if (!empty($line[22])) {
            $city = str_pad($line[22], 5, '0', STR_PAD_RIGHT);
        } elseif (!empty($line[5])) {
            $city = $cityCode[$line[5]] ?? '';
        } elseif (!empty($line[8])) {
            $city = $cityCode[substr($line[8], 0, 3)] ?? '';
        } else {
            continue;
        }
        if (empty($city)) {
            continue;
        }
    }

    $path = $basePath . '/docs/daily/' . $y;
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
    $targetFile = $path . '/' . $city . '.csv';
    if (!file_exists($targetFile)) {
        $oFh = fopen($targetFile, 'w');
        fputcsv($oFh, $header);
        fputcsv($oFh, $line);
        fclose($oFh);
    } else {
        $oFh = fopen($targetFile, 'a');
        fputcsv($oFh, $line);
        fclose($oFh);
    }

    // 是否境外移入
    $type = 'import';
    if ($line[16] === '否') {
        $type = 'local';

        // Track local cunli data
        if (!empty($villCode)) {
            if (!isset($cunli[$y][$villCode])) {
                $cunli[$y][$villCode] = [
                    'count' => 0,
                    'latest_sick_date' => '',
                ];
            }

            $caseCount = intval($line[$caseCountIndex]);
            $cunli[$y][$villCode]['count'] += $caseCount;

            if (!empty($line[$sickDateIndex])) {
                if (empty($cunli[$y][$villCode]['latest_sick_date']) ||
                    $line[$sickDateIndex] > $cunli[$y][$villCode]['latest_sick_date']) {
                    $cunli[$y][$villCode]['latest_sick_date'] = $line[$sickDateIndex];
                }
            }
        }
    } else {
        // Track imported cunli data
        if (!empty($villCode)) {
            if (!isset($cunliImported[$y][$villCode])) {
                $cunliImported[$y][$villCode] = [
                    'count' => 0,
                    'latest_sick_date' => '',
                ];
            }

            $caseCount = intval($line[$caseCountIndex]);
            $cunliImported[$y][$villCode]['count'] += $caseCount;

            if (!empty($line[$sickDateIndex])) {
                if (empty($cunliImported[$y][$villCode]['latest_sick_date']) ||
                    $line[$sickDateIndex] > $cunliImported[$y][$villCode]['latest_sick_date']) {
                    $cunliImported[$y][$villCode]['latest_sick_date'] = $line[$sickDateIndex];
                }
            }
        }
    }

    if (!isset($sum[$y])) {
        $sum[$y] = [
            'local' => $cityList,
            'import' => $cityList,
        ];
    }
    $sum[$y][$type][$city] += intval($line[$caseCountIndex]);
}

// Write summary files for all years
foreach ($sum as $y => $data) {
    $yearPath = $basePath . '/docs/daily/' . $y;

    // Write sum.json
    foreach ($data as $k => $v) {
        ksort($data[$k]);
    }
    file_put_contents($yearPath . '/sum.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Write county-specific CSV files and cunli JSON for all years
    if (!empty($countyData[$y])) {
        foreach ($countyData[$y] as $countyCode => $lines) {
            $countyFile = $yearPath . '/' . $countyCode . '.csv';
            $fh = fopen($countyFile, 'w');
            fputcsv($fh, $header);
            foreach ($lines as $line) {
                fputcsv($fh, $line);
            }
            fclose($fh);
        }
    }

    // Write cunli.json for all years
    $yearCunli = $cunli[$y] ?? [];
    ksort($yearCunli);
    file_put_contents($yearPath . '/cunli.json', json_encode($yearCunli, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Write cunli_imported.json for all years
    $yearCunliImported = $cunliImported[$y] ?? [];
    ksort($yearCunliImported);
    file_put_contents($yearPath . '/cunli_imported.json', json_encode($yearCunliImported, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
