<?php
/**
 * TaranehYar | HostDL - endpoint تک آهنگ / ریمیکس / نوحه
 *
 * ورودی (POST): token, file_name, artist_en, song_en, link320, link128, cover, tag_site
 * خروجی:        music/{Y/m/d}/{file_name}.mp3
 *               music/{Y/m/d}/{file_name} [128].mp3
 */
require_once __DIR__ . '/bootstrap.php';
smp_require_auth();

$file_name = smp_safe_name(isset($_POST['file_name']) ? $_POST['file_name'] : '');
$artist_en = isset($_POST['artist_en']) ? trim((string) $_POST['artist_en']) : '';
$song_en   = isset($_POST['song_en']) ? trim((string) $_POST['song_en']) : '';
$link320   = isset($_POST['link320']) ? trim((string) $_POST['link320']) : '';
$link128   = isset($_POST['link128']) ? trim((string) $_POST['link128']) : '';
$cover     = isset($_POST['cover']) ? trim((string) $_POST['cover']) : '';
$tag_site  = isset($_POST['tag_site']) ? trim((string) $_POST['tag_site']) : '';

$dir_file = 'music/' . jdate_win('Y/m/d', time());
smp_mkdir($dir_file);

$path320 = __DIR__ . "/{$dir_file}/{$file_name}.mp3";
$path128 = __DIR__ . "/{$dir_file}/{$file_name} [128].mp3";
$cover_temp = __DIR__ . "/{$dir_file}/{$file_name}.cover.jpg";

$has_cover = ($cover !== '') ? smp_download($cover, $cover_temp) : false;

if ($link320 !== '' && smp_download($link320, $path320)) {
    smp_write_id3($path320, $artist_en, $song_en, $has_cover ? $cover_temp : '', $tag_site);
}
if ($link128 !== '' && smp_download($link128, $path128)) {
    smp_write_id3($path128, $artist_en, $song_en, $has_cover ? $cover_temp : '', $tag_site);
}

if ($has_cover) @unlink($cover_temp);

http_response_code(200);
echo 'OK';
