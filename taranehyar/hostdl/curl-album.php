<?php
/**
 * TaranehYar | HostDL - endpoint آلبوم (تا ۱۵ ترک)
 *
 * ورودی (POST): token, album_name, artist_en, song_en, cover, tag_site
 *               track_name_{N}, track_file_{N}, track_link320_{N}, track_link128_{N}  (N=1..15)
 * خروجی:        album/{Y/m/d}/{album_name} [320]/{track_file}.mp3
 *               album/{Y/m/d}/{album_name} [128]/{track_file} [128].mp3
 *               album/{Y/m/d}/{album_name} [320].zip
 *               album/{Y/m/d}/{album_name} [128].zip
 */
require_once __DIR__ . '/bootstrap.php';
smp_require_auth();

ini_set('memory_limit', '1024M');

$album_name = smp_safe_name(isset($_POST['album_name']) ? $_POST['album_name'] : '');
$artist_en  = isset($_POST['artist_en']) ? trim((string) $_POST['artist_en']) : '';
$song_en    = isset($_POST['song_en']) ? trim((string) $_POST['song_en']) : '';
$cover      = isset($_POST['cover']) ? trim((string) $_POST['cover']) : '';
$tag_site   = isset($_POST['tag_site']) ? trim((string) $_POST['tag_site']) : '';

$jdate   = jdate_win('Y/m/d', time());
$dir320  = "album/{$jdate}/{$album_name} [320]";
$dir128  = "album/{$jdate}/{$album_name} [128]";
smp_mkdir($dir320);
smp_mkdir($dir128);

$cover_temp = __DIR__ . "/album/{$jdate}/{$album_name}.cover.jpg";
$has_cover  = ($cover !== '') ? smp_download($cover, $cover_temp) : false;

for ($i = 1; $i <= 15; $i++) {
    $tfile = smp_safe_name(isset($_POST["track_file_{$i}"]) ? $_POST["track_file_{$i}"] : '');
    if ($tfile === 'file' || $tfile === '') continue;

    $tl320 = isset($_POST["track_link320_{$i}"]) ? trim((string) $_POST["track_link320_{$i}"]) : '';
    $tl128 = isset($_POST["track_link128_{$i}"]) ? trim((string) $_POST["track_link128_{$i}"]) : '';

    if ($tl320 !== '') {
        $p = __DIR__ . "/{$dir320}/{$tfile}.mp3";
        if (smp_download($tl320, $p)) {
            smp_write_id3($p, $artist_en, $song_en, $has_cover ? $cover_temp : '', $tag_site);
        }
    }
    if ($tl128 !== '') {
        $p = __DIR__ . "/{$dir128}/{$tfile} [128].mp3";
        if (smp_download($tl128, $p)) {
            smp_write_id3($p, $artist_en, $song_en, $has_cover ? $cover_temp : '', $tag_site);
        }
    }
}

if ($has_cover) @unlink($cover_temp);

/** ساخت فایل‌های زیپ آلبوم */
function smp_zip_dir($srcDir, $zipPath)
{
    if (!class_exists('ZipArchive')) return false;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return false;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $zip->addFile($file->getRealPath(), substr($file->getRealPath(), strlen($srcDir) + 1));
        }
    }
    $zip->close();
    return true;
}

smp_zip_dir(__DIR__ . "/{$dir320}", __DIR__ . "/album/{$jdate}/{$album_name} [320].zip");
smp_zip_dir(__DIR__ . "/{$dir128}", __DIR__ . "/album/{$jdate}/{$album_name} [128].zip");

http_response_code(200);
echo 'OK';
