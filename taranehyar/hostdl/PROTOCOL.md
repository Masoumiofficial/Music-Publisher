# قرارداد ارتباطی TaranehYar ↔ HostDL (نسخه 1)

همه درخواست‌ها به‌صورت `POST` (فرم-urlencoded) به endpoint مربوطه روی هاست دانلود ارسال می‌شوند.
افزونه به‌صورت خودکار فیلد `token` را به همه درخواست‌ها اضافه می‌کند.

## فیلدهای مشترک

| فیلد | توضیح |
|---|---|
| `token` | توکن امنیتی — باید با `$smp_token` در `config.php` هاست دانلود یکسان باشد |
| `tag_site` | نام سایت مشتری برای تگ‌گذاری ID3 (اختیاری) |

در صورت توکن نامعتبر: `403` · در صورت تنظیم‌نشدن توکن: `503` · موفقیت: `200` + `OK`

## `curl-music.php` — تک آهنگ / ریمیکس / نوحه

| فیلد | توضیح |
|---|---|
| `file_name` | نام پایه فایل خروجی (مثل `Artist - Track`) — **خروجی دقیقاً با همین نام ساخته می‌شود** |
| `artist_en`, `song_en` | برای تگ ID3 |
| `link320`, `link128` | لینک دانلود فایل صوتی (هر کدام می‌تواند خالی باشد) |
| `cover` | لینک کاور (برای تگ ID3، پس از تگ‌گذاری حذف می‌شود) |

**خروجی:** `music/{Y/m/d}/{file_name}.mp3` و `music/{Y/m/d}/{file_name} [128].mp3`
(تاریخ شمسی — هم‌زمان با تاریخ ذخیره‌شده در متای وردپرس)

## `curl-video.php` — موزیک ویدیو

| فیلد | توضیح |
|---|---|
| `file_name`, `artist_en`, `song_en`, `cover`, `tag_site` | مانند بالا |
| `link1080`, `link720`, `link480` | لینک ویدیوها |
| `link320` | لینک فایل صوتی MP3 |

**خروجی:** `video/{Y/m/d}/{file_name} [1080].mp4`، `[720].mp4`، `[480].mp4` و `{file_name}.mp3`

## `curl-album.php` — آلبوم

| فیلد | توضیح |
|---|---|
| `album_name` | نام پایه آلبوم (مثل `Artist - Album`) |
| `artist_en`, `song_en`, `cover`, `tag_site` | مانند بالا |
| `track_name_{N}` | نام ترک N (N=۱..۱۵) |
| `track_file_{N}` | نام پایه فایل ترک N (مثل `Artist - Track`) |
| `track_link320_{N}`, `track_link128_{N}` | لینک ترک‌ها |

**خروجی:**
- پوشه‌ها: `album/{Y/m/d}/{album_name} [320]/` و `album/{Y/m/d}/{album_name} [128]/`
- ترک‌ها: `{track_file}.mp3` و `{track_file} [128].mp3`
- زیپ‌ها: `album/{Y/m/d}/{album_name} [320].zip` و `album/{Y/m/d}/{album_name} [128].zip`

## `curl-leech.php` — لیچ فایل

| فیلد | توضیح |
|---|---|
| `file_name`, `artist_en`, `song_en`, `cover`, `tag_site` | مانند بالا |
| `linkfilemp3`, `linkfilemp4` | لینک فایل‌ها (معمولاً لینک موقت از سایت اصلی) |

**خروجی:** `music/{Y/m/d}/{file_name}.mp3` و `music/{Y/m/d}/{file_name}.mp4`

## نکات امنیتی

- همه endpointها بدون توکن معتبر هیچ کاری انجام نمی‌دهند.
- نام فایل‌ها با `smp_safe_name()` از مسیر و کاراکترهای خطرناک پاک می‌شوند (ضد Path Traversal).
- دانلود منابع فقط از آدرس‌های http/https عمومی (ضد SSRF) و بدون دنبال‌کردن ریدایرکت انجام می‌شود.
