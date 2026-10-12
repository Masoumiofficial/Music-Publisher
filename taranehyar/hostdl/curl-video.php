<?php
/**
 * TaranehYar | HostDL - endpoint موزیک ویدیو
 *
 * ورودی (POST): token, file_name, artist_en, song_en, link1080, link720, link480, link320, cover, tag_site
 * خروجی:        video/{Y/m/d}/{file_name} [1080].mp4
 *               video/{Y/m/d}/{file_name} [720].mp4
 *               video/{Y/m/d}/{file_name} [480].mp4
 *               video/{Y/m/d}/{file_name}.mp3
 */
require_once __DIR__ . '/bootstrap.php';
smp_require_auth();

$file_name = smp_safe_name(isset($_POST['file_name']) ? $_POST['file_name'] : '');
$artist_en = isset($_POST['artist_en']) ? trim((string) $_POST['artist_en']) : '';
$song_en   = isset($_POST['song_en']) ? trim((string) $_POST['song_en']) : '';
$link1080  = isset($_POST['link1080']) ? trim((string) $_POST['link1080']) : '';
$link720   = isset($_POST['link720']) ? trim((string) $_POST['link720']) : '';
$link480   = isset($_POST['link480']) ? trim((string) $_POST['link480']) : '';
$link320   = isset($_POST['link320']) ? trim((string) $_POST['link320']) : '';
$cover     = isset($_POST['cover']) ? trim((string) $_POST['cover']) : '';
$tag_site  = isset($_POST['tag_site']) ? trim((string) $_POST['tag_site']) : '';

$dir_file = 'video/' . jdate_win('Y/m/d', time());
smp_mkdir($dir_file);

if ($link1080 !== '') smp_download($link1080, __DIR__ . "/{$dir_file}/{$file_name} [1080].mp4");
if ($link720 !== '')  smp_download($link720,  __DIR__ . "/{$dir_file}/{$file_name} [720].mp4");
if ($link480 !== '')  smp_download($link480,  __DIR__ . "/{$dir_file}/{$file_name} [480].mp4");

if ($link320 !== '') {
    $mp3 = __DIR__ . "/{$dir_file}/{$file_name}.mp3";
    if (smp_download($link320, $mp3)) {
        $cover_temp = __DIR__ . "/{$dir_file}/{$file_name}.cover.jpg";
        $has_cover  = ($cover !== '') ? smp_download($cover, $cover_temp) : false;
        smp_write_id3($mp3, $artist_en, $song_en, $has_cover ? $cover_temp : '', $tag_site);
        if ($has_cover) @unlink($cover_temp);
    }
}

http_response_code(200);
echo 'OK';
