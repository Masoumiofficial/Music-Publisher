#!/usr/bin/env bash
# انتشار نسخه «ترانه‌یار» روی گیت‌هاب
# ---------------------------------------------------------------
# ۱) نسخه را از هدر افزونه می‌خواند و با CHANGELOG و readme.txt چک می‌کند
# ۲) بسته‌های انتشار را با build.sh می‌سازد
# ۳) برچسب git (مثل v1.0.0) را می‌سازد و push می‌کند
# ۴) GitHub Release را با فایل‌های zip منتشر می‌کند
#
# استفاده:
#   ./publish-release.sh                 # انتشار همین نسخه (v1.0.0)
#   ./publish-release.sh 1.0.1           # انتشار یک نسخه مشخص
#   ./publish-release.sh --target main   # برچسب روی commit دیگری (پیش‌فرض: HEAD)
#   ./publish-release.sh --draft         # انتشار به‌صورت پیش‌نویس
#   ./publish-release.sh --dry-run       # فقط بررسی و ساخت بسته‌ها، بدون هیچ انتشار
#
# پیش‌نیازها: git، zip، gh (احراز هویت‌شده روی مخزن)
set -euo pipefail
cd "$(dirname "$0")"

readonly PLUGIN_FILE="taranehyar/taranehyar.php"
readonly README_FILE="taranehyar/readme.txt"
readonly CHANGELOG_FILE="CHANGELOG.md"
readonly DIST_DIR="dist"

DRAFT=0
DRY_RUN=0
VERSION_OVERRIDE=""
TAG_TARGET=""
ALLOW_DIRTY=0
MISSING_ASSETS=0

usage() {
    sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

log()  { printf '\033[1;34m▸\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m✔\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m✖\033[0m %s\n' "$*" >&2; exit 1; }

# --- پارس آرگومان‌ها -------------------------------------------------
while [[ $# -gt 0 ]]; do
    case "$1" in
        --draft)        DRAFT=1 ;;
        --dry-run)      DRY_RUN=1 ;;
        --target)       [[ $# -ge 2 ]] || die "بعد از --target یک ref لازم است."
                        TAG_TARGET="$2"; shift ;;
        --allow-dirty)  ALLOW_DIRTY=1 ;;
        -h|--help)      usage 0 ;;
        -*)             die "گزینه ناشناخته: $1 (برای راهنما: --help)" ;;
        *)              [[ -z "$VERSION_OVERRIDE" ]] || die "فقط یک نسخه می‌توانید بدهید."
                        VERSION_OVERRIDE="$1" ;;
    esac
    shift
done

# --- ۱. تشخیص نسخه ---------------------------------------------------
PLUGIN_VERSION="$(grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" | awk '{print $3}')"
[[ -n "$PLUGIN_VERSION" ]] || die "نسخه در $PLUGIN_FILE پیدا نشد."
VERSION="${VERSION_OVERRIDE:-$PLUGIN_VERSION}"
TAG="v${VERSION}"

log "نسخه افزونه: $PLUGIN_VERSION | نسخه هدف: $VERSION (برچسب: $TAG)"

if [[ "$VERSION" != "$PLUGIN_VERSION" ]]; then
    warn "نسخه هدف با نسخه هدر افزونه ($PLUGIN_VERSION) فرق دارد."
    [[ -n "$VERSION_OVERRIDE" ]] || die "برای انتشار نسخه دیگر، ابتدا هدر افزونه را به‌روز کنید."
fi

# --- ۲. بررسی سازگاری نسخه‌ها و وضعیت مخزن ---------------------------
STABLE_TAG="$(grep -m1 -E '^Stable tag:' "$README_FILE" | awk '{print $3}')"
[[ "$STABLE_TAG" == "$VERSION" ]] \
    || die "«Stable tag» در $README_FILE برابر «$STABLE_TAG» است، نه «$VERSION»."

grep -qE "^##[[:space:]]+${VERSION}([[:space:]]|—|-|$)" "$CHANGELOG_FILE" \
    || die "بخش نسخه $VERSION در $CHANGELOG_FILE پیدا نشد."

if [[ "$ALLOW_DIRTY" -eq 0 ]] && ! git diff --quiet; then
    die "تغییرات commit‌نشده دارید؛ اول commit کنید یا از --allow-dirty استفاده کنید."
fi
ok "نسخه در هدر افزونه، readme.txt و CHANGELOG سازگار است."

# --- ۳. ساخت بسته‌های انتشار -----------------------------------------
log "ساخت بسته‌ها با build.sh ..."
./build.sh >/dev/null
ASSETS=("$DIST_DIR/taranehyar.zip" "$DIST_DIR/taranehyar-hostdl.zip")
for f in "${ASSETS[@]}"; do
    [[ -s "$f" ]] || die "بسته $f ساخته نشد."
done
ok "بسته‌ها ساخته شد: ${ASSETS[*]}"

# --- ۴. استخراج یادداشت‌های انتشار از CHANGELOG -----------------------
NOTES_FILE="$(mktemp)"
trap 'rm -f "$NOTES_FILE"' EXIT

{
    echo "# ترانه‌یار | TaranehYar — نسخه ${VERSION}"
    echo
    # بدنه بخش همین نسخه در CHANGELOG (بدون خط عنوان)
    awk -v ver="$VERSION" '
        $0 ~ "^##[[:space:]]+" ver "([[:space:]]|—|-|$)" { inside=1; next }
        inside && /^##[[:space:]]/ { exit }
        inside { print }
    ' "$CHANGELOG_FILE"
    echo
    echo "## بسته‌های نصبی"
    echo
    echo "- \`taranehyar.zip\` — افزونه وردپرس (پیشخوان ← افزونه‌ها ← افزودن ← بارگذاری افزونه)"
    echo "- \`taranehyar-hostdl.zip\` — فقط اسکریپت‌های هاست دانلود (در ریشه هاست دانلود آپلود شود)"
    echo
    echo "راهنمای نصب فارسی: [\`docs/INSTALL-fa.md\`](https://github.com/$(gh repo view --json nameWithOwner -q .nameWithOwner)/blob/main/docs/INSTALL-fa.md)"
} > "$NOTES_FILE"

# بخش SHA-256 فقط وقتی به ریلیز اضافه می‌شود که فایل‌ها واقعاً پیوست شده باشند
hashes_section() {
    echo
    echo "## بررسی سلامت فایل‌ها (SHA-256)"
    echo
    echo '```'
    ( cd "$DIST_DIR" && sha256sum taranehyar.zip taranehyar-hostdl.zip )
    echo '```'
}

if [[ "$DRY_RUN" -eq 1 ]]; then
    ok "اجرای آزمایشی — بسته‌ها آماده‌اند و یادداشت‌ها ساخته شدند، اما چیزی منتشر نشد."
    echo "--- یادداشت‌های انتشار ---"
    { cat "$NOTES_FILE"; hashes_section; } > "$NOTES_FILE"
    cat "$NOTES_FILE"
    exit 0
fi

# --- ۵. بررسی پیش‌نیازهای انتشار -------------------------------------
command -v gh >/dev/null || die "ابزار gh نصب نیست."
gh auth status >/dev/null 2>&1 || die "به GitHub وارد نشده‌اید (gh auth login)."
gh repo view >/dev/null 2>&1 || die "دسترسی به مخزن گیت‌هاب برقرار نشد."
REPO="$(gh repo view --json nameWithOwner -q .nameWithOwner)"

# --- ۶. ساخت و push برچسب --------------------------------------------
TARGET_REF="${TAG_TARGET:-HEAD}"
TARGET_SHA="$(git rev-parse --verify "${TARGET_REF}^{commit}")" \
    || die "ref «$TARGET_REF» پیدا نشد."

if git rev-parse -q --verify "refs/tags/$TAG" >/dev/null; then
    TAG_SHA="$(git rev-list -n1 "$TAG")"
    [[ "$TAG_SHA" == "$TARGET_SHA" ]] \
        || die "برچسب $TAG روی commit دیگری است ($TAG_SHA ≠ $TARGET_SHA)."
    log "برچسب $TAG از قبل وجود دارد و به $TARGET_SHA اشاره می‌کند."
else
    git tag -a "$TAG" -m "TaranehYar ${VERSION}" "$TARGET_SHA"
    ok "برچسب $TAG روی $TARGET_REF ($TARGET_SHA) ساخته شد."
fi

if [[ -z "$(git ls-remote --tags origin "refs/tags/$TAG")" ]]; then
    log "push برچسب به origin ..."
    git push origin "refs/tags/$TAG"
    ok "برچسب $TAG به origin فرستاده شد."
else
    log "برچسب $TAG روی origin موجود است."
fi

# --- ۷. انتشار (یا به‌روزرسانی) Release --------------------------------
RELEASE_ARGS=(--title "ترانه‌یار نسخه ${VERSION}" --notes-file "$NOTES_FILE")
if [[ "$DRAFT" -eq 1 ]]; then
    RELEASE_ARGS+=(--draft)
else
    # صریحاً --draft=false می‌دهیم تا اگر ریلیزِ همین برچسب پیش‌نویس مانده بود، منتشر شود
    RELEASE_ARGS+=(--draft=false)
fi

if gh release view "$TAG" >/dev/null 2>&1; then
    log "ریلیز $TAG از قبل وجود دارد؛ به‌روزرسانی می‌شود ..."
    gh release edit "$TAG" "${RELEASE_ARGS[@]}"
    ok "ریلیز به‌روزرسانی شد."
else
    log "انتشار ریلیز $TAG روی $REPO ..."
    gh release create "$TAG" --verify-tag "${RELEASE_ARGS[@]}"
    ok "ریلیز منتشر شد."
fi

# --- ۸. پیوست فایل‌های بسته (در صورت مسدود نبودن شبکه) -----------------
log "پیوست بسته‌ها به ریلیز ..."
if gh release upload "$TAG" "${ASSETS[@]}" --clobber; then
    ok "فایل‌های بسته پیوست شدند."
    { cat "$NOTES_FILE"; hashes_section; } > "${NOTES_FILE}.tmp"
    mv "${NOTES_FILE}.tmp" "$NOTES_FILE"
    gh release edit "$TAG" "${RELEASE_ARGS[@]}"
else
    warn "آپلود فایل‌ها ناموفق بود (دسترسی به uploads.github.com برقرار نشد)."
    warn "ریلیز منتشر شد، اما دو فایل زیر ضمیمه نشده‌اند. برای پیوست دستی:"
    warn "  gh release upload $TAG ${ASSETS[*]}"
    MISSING_ASSETS=1
fi

RELEASE_KIND="published"
[[ "$DRAFT" -eq 1 ]] && RELEASE_KIND="draft"
echo
log "وضعیت ریلیز: $RELEASE_KIND"
echo "🔗 $(gh release view "$TAG" --json url -q .url)"
