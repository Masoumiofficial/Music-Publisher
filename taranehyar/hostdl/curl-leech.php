<?php
/**
 * TaranehYar | HostDL - endpoint لیچ فایل
 *
 * ورودی (POST): token, file_name, artist_en, song_en, linkfilemp3, linkfilemp4, cover, tag_site
 * خروجی:        music/{Y/m/d}/{file_name}.mp3
 *               music/{Y/m/d}/{file_name}.mp4
 */
require_once __DIR__ . '/bootstrap.php';
smp_require_auth();

$file_name = smp_safe_name(isset($_POST['file_name']) ? $_POST['file_name'] : '');
$artist_en = isset($_POST['artist_en']) ? trim((string) $_POST['artist_en']) : '';
$song_en   = isset($_POST['song_en']) ? trim((string) $_POST['song_en']) : '';
$link_mp3  = isset($_POST['linkfilemp3']) ? trim((string) $_POST['linkfilemp3']) : '';
$link_mp4  = isset($_POST['linkfilemp4']) ? trim((string) $_POST['linkfilemp4']) : '';
$cover     = isset($_POST['cover']) ? trim((string) $_POST['cover']) : '';
$tag_site  = isset($_POST['tag_site']) ? trim((string) $_POST['tag_site']) : '';

$dir_file = 'music/' . jdate_win('Y/m/d', time());
smp_mkdir($dir_file);

$cover_temp = __DIR__ . "/{$dir_file}/{$file_name}.cover.jpg";
$has_cover  = ($cover !== '') ? smp_download($cover, $cover_temp) : false;

if ($link_mp3 !== '') {
    $mp3 = __DIR__ . "/{$dir_file}/{$file_name}.mp3";
    if (smp_download($link_mp3, $mp3)) {
        smp_write_id3($mp3, $artist_en, $song_en, $has_cover ? $cover_temp : '', $tag_site);
    }
}
if ($link_mp4 !== '') {
    smp_download($link_mp4, __DIR__ . "/{$dir_file}/{$file_name}.mp4");
}

if ($has_cover) @unlink($cover_temp);

http_response_code(200);
echo 'OK';
