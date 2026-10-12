<?php
/**
 * تست‌های عملکردی توابع هاست دانلود — بدون نیاز به وردپرس یا وب‌سرور
 * اجرا با هر PHP:  php tests/test-hostdl.php
 */
require __DIR__ . '/../taranehyar/hostdl/bootstrap.php';

$fails = 0;
function ok($cond, $label) {
    global $fails;
    if ($cond) { echo "PASS: $label\n"; } else { echo "FAIL: $label\n"; $fails++; }
}

// ── smp_safe_name: ضد Path Traversal ──
ok(smp_safe_name('../../etc/passwd') === 'passwd', 'traversal ../ removed (basename)');
ok(smp_safe_name('..\\..\\windows\\cmd') === 'cmd', 'backslash traversal removed');
ok(smp_safe_name('Artist - Track') === 'Artist - Track', 'normal name unchanged');
ok(smp_safe_name('Track [128]') === 'Track [128]', 'brackets kept');
ok(smp_safe_name('A/B.mp3') === 'B.mp3', 'slash path stripped');
ok(smp_safe_name('name;rm -rf /') === 'namerm -rf', 'special chars removed');
ok(smp_safe_name('') === 'file', 'empty falls back to file');
ok(smp_safe_name('...') === 'file', 'dots-only falls back');
ok(strpos(smp_safe_name('a' . chr(0) . 'b'), chr(0)) === false, 'null byte removed');

// ── jdate: خروجی شمسی ──
$d = jdate_win('Y/m/d', time());
ok(preg_match('#^\d{4}/\d{2}/\d{2}$#', $d) === 1, "jdate format Y/m/d ($d)");

// ── قرارداد نام‌گذاری خروجی (هم‌سانی با متای وردپرس) ──
$album = smp_safe_name('Artist - Album');
ok($album . ' [320]' === 'Artist - Album [320]', 'album 320 dir pattern');
ok($album . ' [320].zip' === 'Artist - Album [320].zip', 'album 320 zip pattern');
$track = smp_safe_name('Artist - Track');
ok($track . ' [128].mp3' === 'Artist - Track [128].mp3', '128k file pattern');

echo $fails === 0 ? "\nALL TESTS PASSED\n" : "\n$fails TEST(S) FAILED\n";
exit($fails === 0 ? 0 : 1);
