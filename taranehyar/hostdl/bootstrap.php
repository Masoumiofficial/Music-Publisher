<?php
/**
 * TaranehYar | HostDL - توابع مشترک
 * احراز هویت، نام فایل امن، دانلود امن و تگ‌گذاری ID3
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jdf.php';

ini_set('max_execution_time', '0');
set_time_limit(0);

/** ── احراز هویت: همه endpointها باید این را صدا بزنند ────────────── */
function smp_require_auth()
{
    global $smp_token;
    $sent = isset($_POST['token']) ? (string) $_POST['token'] : '';
    if (!is_string($smp_token) || $smp_token === '' || $smp_token === 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING') {
        http_response_code(503);
        exit('HostDL is not configured: set $smp_token in config.php');
    }
    if (!hash_equals((string) $smp_token, $sent)) {
        http_response_code(403);
        exit('Forbidden: invalid token');
    }
}

/** ── نام فایل امن (حذف مسیر و کاراکترهای خطرناک) ─────────────────── */
function smp_safe_name($name)
{
    $name = basename(str_replace('\\', '/', (string) $name));
    $name = preg_replace('/[^A-Za-z0-9\-\_\.\(\)\[\] ]/', '', $name);
    $name = trim(preg_replace('/\s+/', ' ', $name), ' .-');
    return $name !== '' ? $name : 'file';
}

/** ── آیا میزبان عمومی است؟ (ضد SSRF) ─────────────────────────────── */
function smp_host_is_public($host)
{
    if (!is_string($host) || $host === '') return false;
    if (in_array(strtolower($host), array('localhost', '0.0.0.0', '::1'), true)) return false;
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    $ips = gethostbynamel($host);
    if (!is_array($ips) || empty($ips)) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

/** ── دانلود استریم (بدون لود کامل در حافظه) ───────────────────────── */
function smp_download($url, $saveto)
{
    if (!is_string($url) || $url === '') return false;
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return false;
    }
    if (empty($parts['host']) || !smp_host_is_public($parts['host'])) return false;

    $fp = @fopen($saveto, 'wb');
    if (!$fp) return false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_FILE            => $fp,
            CURLOPT_FOLLOWLOCATION  => false, // جلوگیری از ریدایرکت به آدرس داخلی
            CURLOPT_CONNECTTIMEOUT  => 20,
            CURLOPT_TIMEOUT         => 3600,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_USERAGENT       => 'TaranehYar-HostDL/1.0',
        ));
        $ok   = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $code !== 200) {
            @unlink($saveto);
            return false;
        }
    } else {
        $ctx  = stream_context_create(array('http' => array('follow_location' => 0, 'timeout' => 3600)));
        $in   = @fopen($url, 'rb', false, $ctx);
        if (!$in) {
            fclose($fp);
            return false;
        }
        stream_copy_to_stream($in, $fp);
        fclose($in);
        fclose($fp);
    }

    if (!file_exists($saveto) || filesize($saveto) === 0) {
        @unlink($saveto);
        return false;
    }
    return true;
}

/** ── تگ‌گذاری ID3 (نام سایت مشتری به‌جای برند ثابت) ───────────────── */
function smp_write_id3($file, $artist, $title, $coverPath, $tagSite)
{
    if (!file_exists($file)) return;

    require_once __DIR__ . '/getid3/getid3.php';
    require_once __DIR__ . '/getid3/write.php';

    $getID3 = new getID3;
    $getID3->setOption(array('encoding' => 'UTF-8'));

    $tagwriter = new getid3_writetags;
    $tagwriter->filename          = $file;
    $tagwriter->tagformats        = array('id3v2.3');
    $tagwriter->overwrite_tags    = true;
    $tagwriter->remove_other_tags = true;
    $tagwriter->tag_encoding      = 'UTF-8';

    $TagData = array(
        'title'  => array((string) $title),
        'artist' => array((string) $artist),
        'album'  => array($title . ' Single'),
        'year'   => array(date('Y')),
        'track'  => array(''),
    );
    if (is_string($tagSite) && $tagSite !== '') {
        $TagData['genre']   = array($tagSite);
        $TagData['comment'] = array('Download By ' . $tagSite);
    }

    if (is_string($coverPath) && $coverPath !== '' && file_exists($coverPath)) {
        $img = @file_get_contents($coverPath);
        if ($img !== false && $img !== '') {
            $mime = (stripos($coverPath, '.png') !== false) ? 'image/png' : 'image/jpeg';
            $TagData['attached_picture'] = array(0 => array(
                'data'          => $img,
                'picturetypeid' => 2,
                'description'   => 'cover',
                'mime'          => $mime,
            ));
        }
    }

    $tagwriter->tag_data = $TagData;
    $tagwriter->WriteTags();
}

/** ── ساخت پوشه در صورت نیاز ───────────────────────────────────────── */
function smp_mkdir($dir)
{
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
