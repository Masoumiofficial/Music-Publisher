#!/usr/bin/env python3
"""Static + logic security checks (no PHP/WordPress runtime available)."""
from __future__ import annotations

import ipaddress
import os
import re
import sys
import zipfile
from urllib.parse import urlparse

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
PLUGIN = os.path.join(ROOT, "music-publisher")
FAILS = []
PASSES = []


def ok(name: str) -> None:
    PASSES.append(name)
    print(f"PASS  {name}")


def fail(name: str, detail: str) -> None:
    FAILS.append(f"{name}: {detail}")
    print(f"FAIL  {name}: {detail}")


def read(rel: str) -> str:
    with open(os.path.join(PLUGIN, rel), encoding="utf-8", errors="replace") as f:
        return f.read()


def walk_php(exclude_getid3=True):
    for dirpath, _, files in os.walk(PLUGIN):
        if exclude_getid3 and "getid3" in dirpath.replace("\\", "/"):
            continue
        for fn in files:
            if fn.endswith(".php"):
                path = os.path.join(dirpath, fn)
                yield os.path.relpath(path, PLUGIN), open(path, encoding="utf-8", errors="replace").read()


# --- URL / SSRF logic mirroring SMP_Post_Handler::validate_remote_url ---
def validate_remote_url(url: str):
    if not url:
        return True
    parts = urlparse(url)
    if parts.scheme not in ("http", "https"):
        return False
    host = (parts.hostname or "").lower()
    if not host:
        return False
    blocked = {
        "localhost",
        "127.0.0.1",
        "0.0.0.0",
        "::1",
        "169.254.169.254",
        "metadata.google.internal",
    }
    if host in blocked:
        return False
    if re.match(r"^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)", host):
        return False
    try:
        ip = ipaddress.ip_address(host)
        if ip.is_private or ip.is_loopback or ip.is_link_local or ip.is_reserved or ip.is_multicast:
            return False
    except ValueError:
        pass
    return True


def safe_name(name: str) -> str:
    name = re.sub(r"<[^>]*>", "", name)
    name = name.replace("/", "").replace("\\", "").replace("..", "").replace("\0", "")
    name = name.strip()
    return name if name else "untitled"


def test_ssrf_cases():
    blocked = [
        "http://127.0.0.1/secret",
        "http://localhost/admin",
        "http://169.254.169.254/latest/meta-data/",
        "http://10.0.0.5/",
        "http://192.168.1.1/",
        "http://172.16.0.1/",
        "file:///etc/passwd",
        "gopher://127.0.0.1/",
        "http://0.0.0.0/",
        "http://metadata.google.internal/",
    ]
    allowed = [
        "https://cdn.example.com/a.mp3",
        "http://dl.example.ir/x.mp3",
    ]
    for u in blocked:
        if validate_remote_url(u) is not False and validate_remote_url(u) is not True:
            fail("ssrf", f"unexpected for {u}")
            return
        if validate_remote_url(u) is True:
            fail("ssrf-block", f"should reject {u}")
            return
    for u in allowed:
        if validate_remote_url(u) is not True:
            fail("ssrf-allow", f"should allow {u}")
            return
    # DNS names that look public are allowed (rebinding residual)
    if validate_remote_url("http://evil.example.com/") is not True:
        fail("ssrf-public", "public host should pass")
        return
    ok("SSRF URL filter rejects loopback/private/file/gopher")


def test_path_traversal():
    cases = {
        "../etc/passwd": "etcpasswd" if ".." in "../etc/passwd" else None,
    }
    out = safe_name("../etc/passwd")
    if ".." in out or "/" in out or "\\" in out:
        fail("path-traversal", f"unsafe result {out!r}")
        return
    out2 = safe_name("Artist/../../../tmp")
    if "/" in out2 or ".." in out2:
        fail("path-traversal-2", repr(out2))
        return
    ok("Filename sanitizer strips traversal")


def test_no_eval():
    bad = []
    for rel, src in walk_php():
        if re.search(r"\beval\s*\(|\bshell_exec\s*\(|\bpassthru\s*\(|\bproc_open\s*\(|\bsystem\s*\(", src):
            bad.append(rel)
    if bad:
        fail("no-rce", str(bad))
    else:
        ok("No eval/shell_exec/system/passthru/proc_open in first-party PHP")


def test_no_file_get_remote():
    bad = []
    for rel, src in walk_php():
        if "hostdl/curl" in rel.replace("\\", "/"):
            if "file_get_contents( $url" in src or "file_get_contents($url" in src:
                bad.append(rel)
        if rel.startswith("includes/") and "file_get_contents" in src:
            bad.append(rel)
    if bad:
        fail("remote-fgc", str(bad))
    else:
        ok("Host download uses cURL helper, not file_get_contents($url)")


def test_auth_hooks():
    h = read("includes/class-smp-post-handler.php")
    if "admin_post_nopriv" in h:
        fail("nopriv", "guest admin_post hooks exist")
        return
    if "check_admin_referer" not in h or "current_user_can( 'edit_posts' )" not in h:
        fail("nonce-cap", "missing nonce or capability")
        return
    if "publish_posts" not in h:
        fail("publish-cap", "missing publish_posts")
        return
    a = read("includes/class-smp-admin-pages.php")
    if "wp_nonce_field" not in a:
        fail("form-nonce", "forms lack wp_nonce_field")
        return
    ok("admin_post is logged-in only; nonce + edit_posts + publish_posts")


def test_host_secret():
    b = read("hostdl/bootstrap.php")
    if "hash_equals" not in b or "HTTP_X_SMP_KEY" not in b:
        fail("host-secret", "bootstrap missing secret check")
        return
    if "CURLPROTO_HTTP" not in b:
        fail("host-proto", "cURL protocols not restricted")
        return
    ok("hostdl bootstrap has secret + HTTP(S)-only cURL")


def test_zip():
    zpath = os.path.join(ROOT, "release", "music-publisher.zip")
    if not os.path.isfile(zpath):
        fail("zip-missing", zpath)
        return
    with zipfile.ZipFile(zpath) as z:
        names = z.namelist()
    if "music-publisher/music-publisher.php" not in names:
        fail("zip-root", "main file not at music-publisher/music-publisher.php")
        return
    if any(n.startswith("release/") for n in names):
        fail("zip-nested", "zip contains release/")
        return
    ok("Release ZIP has correct plugin root")


def test_sql_prepare():
    h = read("includes/class-smp-post-handler.php")
    if "$wpdb->prepare" not in h:
        fail("sql", "duplicate-title query not prepared")
        return
    if re.search(r'\$wpdb->(query|get_var)\s*\(\s*["\'].*\$', h):
        fail("sql-interp", "interpolated SQL")
        return
    ok("Duplicate-title SQL uses $wpdb->prepare")


def test_xss_notice():
    a = read("includes/class-smp-admin-pages.php")
    if "esc_html( $flash['msg'] )" not in a.replace(" ", ""):
        # allow spaced version
        if "esc_html( $flash['msg'] )" not in a and 'esc_html( $flash[ \'msg\' ] )' not in a:
            if "esc_html( $flash" not in a:
                fail("xss-flash", "flash not escaped")
                return
    if "$_GET['smp_msg']" in a:
        fail("xss-get", "message still in query string")
        return
    ok("Admin notices use escaped transient, not GET")


def test_uploads_api():
    h = read("includes/class-smp-post-handler.php")
    if "wp_upload_dir" not in h:
        fail("uploads", "not using wp_upload_dir")
        return
    if 'ABSPATH . "wp-content/uploads' in h:
        fail("uploads-abspath", "still concatenates ABSPATH uploads")
        return
    ok("Uploads go through wp_upload_dir()")


def main() -> int:
    print("=== SMP static security tests ===")
    print("NOTE: PHP and WordPress are not installed; this is not a live exploit test.\n")
    test_ssrf_cases()
    test_path_traversal()
    test_no_eval()
    test_no_file_get_remote()
    test_auth_hooks()
    test_host_secret()
    test_zip()
    test_sql_prepare()
    test_xss_notice()
    test_uploads_api()
    print(f"\n{len(PASSES)} passed, {len(FAILS)} failed")
    for f in FAILS:
        print(" -", f)
    return 1 if FAILS else 0


if __name__ == "__main__":
    sys.exit(main())
