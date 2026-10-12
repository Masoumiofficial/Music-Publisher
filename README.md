# ترانه‌یار | TaranehYar

پنل حرفه‌ای ارسال پست موزیک، ریمیکس، نوحه، موزیک ویدیو، آلبوم و لیچ فایل برای سایت‌های موزیک وردپرسی.

**نسخه فعلی:** 1.0.0 · **لایسنس:** GPL-2.0-or-later · **نویسنده:** [سجاد معصومی](https://sajadmasoumi.com)

## ساختار مخزن

```
taranehyar/            سورس افزونه وردپرس (نصب‌شونده)
├── taranehyar.php     فایل اصلی افزونه
├── includes/          کلاس‌های تنظیمات، صفحات ادمین و پردازش فرم‌ها
├── assets/            CSS / JS / فونت
├── hostdl/            اسکریپت‌های هاست دانلود (جداگانه روی هاست دانلود نصب می‌شود)
│   ├── config.php     توکن امنیتی
│   ├── bootstrap.php  توابع مشترک (احراز هویت، دانلود امن، تگ ID3)
│   ├── curl-*.php     endpointها
│   ├── getid3/        کتابخانه تگ‌گذاری ID3 (GPL)
│   └── PROTOCOL.md    قرارداد ارتباطی افزونه ↔ هاست دانلود
├── readme.txt         صفحه محصول (وردپرس)
└── uninstall.php
build.sh               ساخت بسته‌های انتشار در dist/
publish-release.sh     انتشار ریلیز گیت‌هاب
.github/workflows/     وورکفلوهای GitHub Actions (CI + Release)
ANALYSIS.md            گزارش تحلیل و آماده‌سازی برای فروش
```

## وورکفلوها (GitHub Actions)

| وورکفلو | فایل | اجرا با | کار |
|---|---|---|---|
| **CI** | `.github/workflows/ci.yml` | push روی `main`، هر PR، اجرای دستی | بررسی نحو همه فایل‌های PHP (`php -l`)، اجرای `tests/test-hostdl.php` روی PHP 7.4/8.1/8.3، ساخت بسته‌ها با `build.sh` و بارگذاری آن‌ها به‌عنوان Artifact |
| **Release** | `.github/workflows/release.yml` | push برچسب `v*`، اجرای دستی | اجرای تست‌ها، سپس `./publish-release.sh` برای ساخت بسته‌ها، ساخت/به‌روزرسانی برچسب و انتشار GitHub Release با فایل‌های zip |

اجرای دستی وورکفلو Release (از تب **Actions ← Release ← Run workflow** یا با `gh`):

```bash
# فقط بررسی و ساخت بسته‌ها، بدون انتشار (پیشنهاد می‌شود اول این را بزنید)
gh workflow run release.yml -f mode=dry-run

# انتشار واقعی همین نسخه (نسخه از هدر افزونه خوانده می‌شود)
gh workflow run release.yml -f mode=publish

# به‌روزرسانی ریلیز موجود (مثلاً پیوست دوباره فایل‌ها به v1.0.0)
gh workflow run release.yml -f mode=publish -f target=v1.0.0

# انتشار پیش‌نویس
gh workflow run release.yml -f mode=publish -f draft=true
```

> برای انتشار نسخه جدید: هدر `Version:` افزونه، `Stable tag:` در `readme.txt` و بخش `## <نسخه>` در `CHANGELOG.md` را یکسان کنید، سپس `git tag -a v1.0.1 -m "TaranehYar 1.0.1" && git push origin v1.0.1`.

## ساخت بسته انتشار

```bash
./build.sh
```

خروجی:
- `dist/taranehyar.zip` — افزونه (نصب از پیشخوان وردپرس)
- `dist/taranehyar-hostdl.zip` — فقط اسکریپت‌های هاست دانلود

## پیکربندی سریع

1. افزونه را نصب و فعال کنید.
2. محتوای `hostdl/` را روی هاست دانلود آپلود کنید و در `config.php` یک توکن تصادفی طولانی بگذارید.
3. در «پنل موزیک ← تنظیمات»: آدرس هاست دانلود، آدرس endpointها (`curl-music.php` برای تک آهنگ/ریمیکس/نوحه)، همان توکن، اطلاعات سایت و شناسه دسته‌بندی‌ها را وارد کنید.

جزئیات پروتکل: [`taranehyar/hostdl/PROTOCOL.md`](taranehyar/hostdl/PROTOCOL.md)
