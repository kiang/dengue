<?php
$basePath = dirname(__DIR__);

$fh = fopen($basePath . '/docs/daily/2015/67000.csv', 'r');
$header = fgetcsv($fh, 2048);
$pool = [];
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if ($data['是否境外移入'] !== '否') {
        continue;
    }
    $day = date('md', strtotime($data['通報日']));
    if (!isset($pool[$day])) {
        $pool[$day] = [
            2015 => 0,
            2023 => 0,
            '2015_sum' => 0,
            '2023_sum' => 0,
        ];
    }
    $pool[$day][2015] += intval($data['確定病例數']);
}

$fh = fopen($basePath . '/docs/daily/2023/67000.csv', 'r');
$header = fgetcsv($fh, 2048);
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if ($data['是否境外移入'] !== '否') {
        continue;
    }
    $day = date('md', strtotime($data['通報日']));
    if (!isset($pool[$day])) {
        $pool[$day] = [
            2015 => 0,
            2023 => 0,
            '2015_sum' => 0,
            '2023_sum' => 0,
        ];
    }
    $pool[$day][2023] += intval($data['確定病例數']);
}
ksort($pool);

$pool2 = $pool;
foreach ($pool as $k => $v) {
    foreach ($pool2 as $i => $j) {
        if ($i <= $k) {
            $pool[$k]['2015_sum'] += $j[2015];
            $pool[$k]['2023_sum'] += $j[2023];
        }
    }
}

$oFh = fopen(__DIR__ . '/compare.csv', 'w');

$header = false;
foreach ($pool as $k => $line) {
    if (false !== $header) {
        $header = true;
        fputcsv($oFh, array_merge(['date'], array_keys($line)));
    }
    $key = substr($k, 0, 2) . '-' . substr($k, 2, 2);
    fputcsv($oFh, array_merge([$key], $line));
}