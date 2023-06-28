<?php
$basePath = dirname(__DIR__);

$yearDivide = date('Y', strtotime('-1 year'));
foreach (glob($basePath . '/docs/daily/*/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $p2 = pathinfo($p['dirname']);
    if ($p2['filename'] < $yearDivide) {
        continue;
    }
    unlink($csvFile);
}

$fh = fopen('https://od.cdc.gov.tw/eic/Dengue_Daily.csv', 'r');
$header = fgetcsv($fh, 2048);
$sum = [];
$cityList = [
    "09007" => 0,
    "09020" => 0,
    "10002" => 0,
    "10004" => 0,
    "10005" => 0,
    "10007" => 0,
    "10008" => 0,
    "10009" => 0,
    "10010" => 0,
    "10013" => 0,
    "10014" => 0,
    "10015" => 0,
    "10016" => 0,
    "10017" => 0,
    "10018" => 0,
    "10020" => 0,
    "63000" => 0,
    "64000" => 0,
    "65000" => 0,
    "66000" => 0,
    "67000" => 0,
    "68000" => 0,
];
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
    if ($y < $yearDivide) {
        continue;
    }
    if (!empty($line[22])) {
        $city = str_pad($line[22], 5, '0', STR_PAD_RIGHT);
    } elseif (!empty($line[5])) {
        $city = $cityCode[$line[5]];
    } elseif (!empty($line[8])) {
        $city = $cityCode[substr($line[8], 0, 3)];
    } else {
        continue;
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
    }

    if (!isset($sum[$y])) {
        $sum[$y] = [
            'local' => $cityList,
            'import' => $cityList,
        ];
    }
    $sum[$y][$type][$city] += intval($line[18]);
}

foreach ($sum as $y => $data) {
    foreach ($data as $k => $v) {
        ksort($data[$k]);
    }
    $targetFile = $basePath . '/docs/daily/' . $y . '/sum.json';
    file_put_contents($targetFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}