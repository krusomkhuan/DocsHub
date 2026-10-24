<?php
/**
 * นำเข้าข้อมูลคลังรูปภาพเดิมจากไฟล์ (.xlsx / .csv / .tsv / .txt) หรือข้อความที่วาง
 * - อ่าน Excel ด้วย ZipArchive + SimpleXML (ไม่ต้องติดตั้งไลบรารีเพิ่ม) รองรับลิงก์แบบ hyperlink ในเซลล์
 * - CSV รองรับ UTF-8 และภาษาไทยแบบเก่า (Windows-874/TIS-620)
 * - หาคอลัมน์อัตโนมัติจากหัวตาราง (ชื่อ/อัลบั้ม, ลิงก์/link/url, วันที่, ปีการศึกษา)
 *   ถ้าไม่มีหัวตาราง ใช้เซลล์ที่เป็นลิงก์เป็นคอลัมน์ลิงก์ และข้อความที่ยาวที่สุดเป็นชื่ออัลบั้ม
 */

define('ALBUM_IMPORT_MAX_ROWS', 5000);

/** อ่านไฟล์เป็นตาราง (array ของแถว แต่ละแถวเป็น array ของเซลล์) */
function album_import_read_file(string $path, string $name): array
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') return album_read_xlsx($path);
    if (in_array($ext, ['csv', 'tsv', 'txt'], true)) return album_read_delimited((string)file_get_contents($path), $ext);
    throw new RuntimeException('รองรับไฟล์ .xlsx, .csv, .tsv หรือ .txt (ไฟล์ .xls แบบเก่าให้เปิดใน Excel แล้วบันทึกเป็น .xlsx ก่อน)');
}

/** แปลงข้อความเป็น UTF-8 (รองรับไฟล์ภาษาไทยที่บันทึกจาก Excel รุ่นเก่า) */
function album_to_utf8(string $s): string
{
    if (str_starts_with($s, "\xEF\xBB\xBF")) return substr($s, 3);
    if (str_starts_with($s, "\xFF\xFE")) return (string)iconv('UTF-16LE', 'UTF-8//IGNORE', substr($s, 2));
    if (mb_check_encoding($s, 'UTF-8')) return $s;
    $conv = @iconv('CP874', 'UTF-8//IGNORE', $s);
    return $conv !== false ? $conv : $s;
}

function album_read_delimited(string $raw, string $ext): array
{
    $text = album_to_utf8($raw);
    $first = strtok($text, "\n") ?: '';
    $delim = $ext === 'tsv' || substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',';
    if ($ext === 'txt' && !str_contains($first, "\t") && !str_contains($first, ',')) {
        // ข้อความธรรมดา: บรรทัดละ 1 รายการ แยกชื่อกับลิงก์ด้วย | หรือเว้นวรรค
        return array_map(fn($l) => [$l], preg_split('/\R/', $text));
    }
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $text);
    rewind($fh);
    $rows = [];
    while (($r = fgetcsv($fh, 0, $delim)) !== false) {
        $rows[] = $r;
        if (count($rows) > ALBUM_IMPORT_MAX_ROWS + 10) break;
    }
    fclose($fh);
    return $rows;
}

/** อ่านชีตแรกของไฟล์ .xlsx */
function album_read_xlsx(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('เปิดไฟล์ Excel ไม่ได้ (ไฟล์อาจเสียหาย)');
    $nsRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    // ข้อความที่ใช้ร่วมกัน
    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        foreach ($sx->si as $si) {
            $t = '';
            foreach ($si->xpath('.//*[local-name()="t"]') as $node) $t .= (string)$node;
            $shared[] = $t;
        }
    }
    // หาไฟล์ของชีตแรก
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $wbRels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb && $wbRels) {
        $wx = simplexml_load_string($wb, 'SimpleXMLElement', LIBXML_NONET);
        $first = $wx->sheets->sheet[0] ?? null;
        $rid = $first ? (string)$first->attributes($nsRel)['id'] : '';
        foreach (simplexml_load_string($wbRels, 'SimpleXMLElement', LIBXML_NONET)->Relationship as $rel) {
            if ((string)$rel['Id'] === $rid) {
                $target = ltrim((string)$rel['Target'], '/');
                $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
    }
    $sheetXml = $zip->getFromName($sheetPath);
    if ($sheetXml === false) throw new RuntimeException('ไม่พบข้อมูลในชีตแรกของไฟล์ Excel');

    // ลิงก์แบบ hyperlink (ข้อความในเซลล์ไม่ใช่ URL แต่คลิกแล้วไปยัง URL)
    $links = [];
    $relsPath = dirname($sheetPath) . '/_rels/' . basename($sheetPath) . '.rels';
    $relTargets = [];
    if (($rx = $zip->getFromName($relsPath)) !== false) {
        foreach (simplexml_load_string($rx, 'SimpleXMLElement', LIBXML_NONET)->Relationship as $rel) {
            $relTargets[(string)$rel['Id']] = (string)$rel['Target'];
        }
    }
    // ใช้การเข้าถึงตรง (namespace เริ่มต้น) เพราะ children($ns) ทำให้อ่าน attribute ธรรมดา เช่น r="A1" ไม่ได้
    $sheet = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NONET);
    if (isset($sheet->hyperlinks)) {
        foreach ($sheet->hyperlinks->hyperlink as $h) {
            $rid = (string)$h->attributes($nsRel)['id'];
            if (isset($relTargets[$rid])) $links[(string)$h['ref']] = $relTargets[$rid];
        }
    }
    $zip->close();

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $col = album_col_index(preg_replace('/\d+/', '', $ref));
            $type = (string)$c['t'];
            if ($type === 's') $val = $shared[(int)$c->v] ?? '';
            elseif ($type === 'inlineStr') $val = implode('', array_map('strval', $c->xpath('.//*[local-name()="t"]')));
            else $val = (string)$c->v;
            // สูตร HYPERLINK("url","ข้อความ")
            if (isset($c->f) && preg_match('/HYPERLINK\(\s*"([^"]+)"/i', (string)$c->f, $m)) $links[$ref] = $m[1];
            if (isset($links[$ref]) && !preg_match('#^https?://#i', trim($val))) {
                $val = trim($val) . ' ' . $links[$ref];
            }
            $cells[$col] = $val;
        }
        if ($cells) {
            $max = max(array_keys($cells));
            $rows[] = array_map(fn($i) => $cells[$i] ?? '', range(0, $max));
        }
        if (count($rows) > ALBUM_IMPORT_MAX_ROWS + 10) break;
    }
    return $rows;
}

function album_col_index(string $letters): int
{
    $n = 0;
    foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
    return $n - 1;
}

/** แปลงวันที่จากไฟล์ (เลขวันที่ของ Excel, 2026-08-12, 12/08/2569, 12 ส.ค. 2569) เป็น Y-m-d */
function album_parse_date(string $s): ?string
{
    $s = trim($s);
    if ($s === '') return null;
    if (preg_match('/^\d{5}(\.\d+)?$/', $s)) return date('Y-m-d', ((int)$s - 25569) * 86400); // Excel serial
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $s, $m)) [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    else {
        $months = ['ม.ค.' => 1, 'ก.พ.' => 2, 'มี.ค.' => 3, 'เม.ย.' => 4, 'พ.ค.' => 5, 'มิ.ย.' => 6, 'ก.ค.' => 7, 'ส.ค.' => 8, 'ก.ย.' => 9, 'ต.ค.' => 10, 'พ.ย.' => 11, 'ธ.ค.' => 12,
                   'มกราคม' => 1, 'กุมภาพันธ์' => 2, 'มีนาคม' => 3, 'เมษายน' => 4, 'พฤษภาคม' => 5, 'มิถุนายน' => 6, 'กรกฎาคม' => 7, 'สิงหาคม' => 8, 'กันยายน' => 9, 'ตุลาคม' => 10, 'พฤศจิกายน' => 11, 'ธันวาคม' => 12];
        if (!preg_match('/^(\d{1,2})\s*(\S+)\s*(\d{4})$/u', $s, $m) || !isset($months[$m[2]])) return null;
        [$d, $mo, $y] = [(int)$m[1], $months[$m[2]], (int)$m[3]];
    }
    if ($y < 100) $y += 2500;          // 69 → 2569
    if ($y > 2400) $y -= 543;          // พ.ศ. → ค.ศ.
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
}

/**
 * แปลงตารางเป็นรายการอัลบั้มพร้อมสถานะ: ok / dup (ลิงก์ซ้ำในคลังหรือในไฟล์) / bad (อ่านไม่ได้)
 * คืนค่า ['items' => [...], 'mapping' => คำอธิบายคอลัมน์ที่ใช้]
 */
function album_import_map(array $rows): array
{
    $isUrl = fn($v) => (bool)preg_match('#https?://\S+#i', (string)$v);
    $headerIdx = null;
    $cols = ['title' => null, 'url' => null, 'date' => null, 'year' => null];

    // หาแถวหัวตารางใน 5 แถวแรก
    foreach (array_slice($rows, 0, 5, true) as $i => $r) {
        $found = ['title' => null, 'url' => null, 'date' => null, 'year' => null];
        foreach ($r as $c => $v) {
            $v = mb_strtolower(trim((string)$v));
            if ($v === '') continue;
            if ($found['url'] === null && preg_match('/ลิงก์|ลิงค์|link|url/u', $v)) $found['url'] = $c;
            elseif ($found['title'] === null && preg_match('/ชื่อ|อัลบั้ม|กิจกรรม|โครงการ|title|name|album/u', $v)) $found['title'] = $c;
            elseif ($found['date'] === null && preg_match('/วันที่|date/u', $v)) $found['date'] = $c;
            elseif ($found['year'] === null && preg_match('/ปีการศึกษา|ปี|year/u', $v)) $found['year'] = $c;
        }
        if ($found['url'] !== null && $found['title'] !== null) {
            $headerIdx = $i;
            $cols = $found;
            break;
        }
    }

    $items = [];
    $seen = [];
    foreach ($rows as $i => $r) {
        if ($headerIdx !== null && $i <= $headerIdx) continue;
        $r = array_map(fn($v) => trim((string)$v), $r);
        if (!array_filter($r, fn($v) => $v !== '')) continue;
        $title = $url = '';
        $date = $year = null;

        if ($headerIdx !== null) {
            $title = $r[$cols['title']] ?? '';
            $cell = $r[$cols['url']] ?? '';
            if (preg_match('#https?://\S+#i', $cell, $m)) $url = $m[0];
            if ($cols['date'] !== null) $date = album_parse_date($r[$cols['date']] ?? '');
            if ($cols['year'] !== null && preg_match('/\b(25\d{2}|26\d{2})\b/', $r[$cols['year']] ?? '', $m)) $year = $m[1];
            // ชื่อกับลิงก์อยู่ในเซลล์เดียวกัน
            if ($url === '' && preg_match('#^(.*?)\s*(https?://\S+)\s*$#u', $title, $m)) [$title, $url] = [$m[1], $m[2]];
        } else {
            foreach ($r as $v) {
                if ($url === '' && preg_match('#https?://\S+#i', $v, $m)) {
                    $url = $m[0];
                    $rest = trim(str_replace($m[0], '', $v), " \t|");
                    if (mb_strlen($rest) > mb_strlen($title)) $title = $rest;
                } elseif (!$isUrl($v) && !preg_match('/^\d+\s*[.)]?$/', $v) && mb_strlen($v) > mb_strlen($title)) {
                    if ($date === null && ($d = album_parse_date($v))) { $date = $d; continue; }
                    $title = $v;
                }
            }
        }
        $title = trim(preg_replace('/^\d+\s*[.)]\s*/u', '', trim($title, " \t|")));
        $url = rtrim($url, '.,);');
        $item = ['line' => $i + 1, 'title' => mb_substr($title, 0, 255), 'url' => $url, 'date' => $date, 'year' => $year, 'state' => 'ok', 'note' => ''];
        if ($title === '' || !album_valid_url($url)) {
            $item['state'] = 'bad';
            $item['note'] = $url === '' ? 'ไม่พบลิงก์' : ($title === '' ? 'ไม่มีชื่ออัลบั้ม' : 'ลิงก์ไม่ถูกต้อง');
        } elseif (isset($seen[$url])) {
            $item['state'] = 'dup';
            $item['note'] = 'ซ้ำกับแถวที่ ' . $seen[$url] . ' ในไฟล์';
        } elseif ($d = album_duplicate($url)) {
            $item['state'] = 'dup';
            $item['note'] = 'มีในคลังแล้ว';
        }
        if ($item['state'] === 'ok') $seen[$url] = $i + 1;
        $items[] = $item;
        if (count($items) >= ALBUM_IMPORT_MAX_ROWS) break;
    }

    $letters = fn($c) => $c === null ? '-' : chr(65 + $c);
    $mapping = $headerIdx !== null
        ? 'พบหัวตารางที่แถว ' . ($headerIdx + 1) . ' · ชื่ออัลบั้ม = คอลัมน์ ' . $letters($cols['title']) . ' · ลิงก์ = คอลัมน์ ' . $letters($cols['url'])
          . ($cols['date'] !== null ? ' · วันที่ = คอลัมน์ ' . $letters($cols['date']) : '') . ($cols['year'] !== null ? ' · ปีการศึกษา = คอลัมน์ ' . $letters($cols['year']) : '')
        : 'ไม่พบหัวตาราง · ใช้เซลล์ที่เป็นลิงก์ และข้อความที่ยาวที่สุดในแถวเป็นชื่ออัลบั้ม';
    return ['items' => $items, 'mapping' => $mapping];
}
