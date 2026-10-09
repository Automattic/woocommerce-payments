#!/usr/bin/env python3
"""Check literal WooCommerce documentation links without executing plugin code."""

import argparse
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass
from html import escape, unescape
from html.parser import HTMLParser
from http.client import HTTPException
import os
from pathlib import Path
import re
import subprocess
import time
from urllib.error import HTTPError, URLError
from urllib.parse import unquote, urldefrag, urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener


SOURCE_DIRECTORIES = {"assets", "client", "includes", "src", "templates"}
SOURCE_EXTENSIONS = {".php", ".js", ".jsx", ".ts", ".tsx"}
EXCLUDED_DIRECTORIES = {"test", "tests", "__tests__", "__snapshots__", "node_modules", "vendor", "dist"}
DOC_HOSTS = {"woocommerce.com", "www.woocommerce.com", "docs.woocommerce.com", "developer.woocommerce.com"}
URL_PATTERN = re.compile(
    r"https?://(?:(?:www\.)?woocommerce\.com/(?:document(?:ation)?|docs)(?=[/?#]|$)"
    r"|docs\.woocommerce\.com/)[^\s\"'`<>\\]*",
    re.IGNORECASE,
)
COMMENT_MARKER = "<!-- woopayments-documentation-links -->"
MAX_HTML_BYTES = 10 * 1024 * 1024


@dataclass
class Page:
    url: str
    anchors: set[str]
    error: str = ""
    # None means redirects left the original fragment intact; "" clears it.
    fragment: str | None = None


class AnchorParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.anchors = set()

    def handle_starttag(self, tag, attrs):
        for name, value in attrs:
            if value is not None and name == "id":
                self.anchors.add(value)


def validate_destination(url):
    parts = urlsplit(url)
    if (
        parts.scheme not in {"http", "https"}
        or parts.hostname not in DOC_HOSTS
        or parts.username is not None
        or parts.password is not None
    ):
        raise ValueError("Destination is not a WooCommerce documentation host: " + url)


class DocumentationRedirects(HTTPRedirectHandler):
    def __init__(self):
        super().__init__()
        self.fragment = None

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        validate_destination(newurl)
        location = headers.get("Location", headers.get("URI", ""))
        if "#" in location:
            self.fragment = unquote(urlsplit(location).fragment)
        return super().redirect_request(req, fp, code, msg, headers, newurl)


def is_source(path):
    return (
        path.suffix in SOURCE_EXTENSIONS
        and (path.parts[0] in SOURCE_DIRECTORIES or len(path.parts) == 1)
        and not EXCLUDED_DIRECTORIES.intersection(path.parts)
        and not re.search(r"\.(?:test|spec|min)\.", path.name)
    )


def discover_links(root):
    """Return unique URLs and every source location from Git-tracked plugin files."""
    tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).decode()
    links = {}
    for filename in sorted(filter(None, tracked.split("\0"))):
        path = Path(filename)
        if not is_source(path) or (root / path).is_symlink():
            continue
        for line_number, line in enumerate((root / path).read_text(encoding="utf-8").splitlines(), 1):
            # JSON-style escaped slashes can also occur in PHP/JS string literals.
            line = line.replace(r"\/", "/")
            for match in URL_PATTERN.finditer(line):
                url = unescape(match.group())
                if line[match.end():match.end() + 1] not in {"'", '"', "`", "\\"}:
                    url = url.rstrip(".,;)]")
                links.setdefault(url, []).append(f"{filename}:{line_number}")
    return links


def fetch_page(url, timeout=20, attempts=3):
    """GET the final HTML, retrying only network errors and transient HTTP statuses."""
    error = ""
    for attempt in range(attempts):
        redirects = DocumentationRedirects()
        try:
            validate_destination(url)
            request = Request(url, headers={
                "User-Agent": "WooPayments-documentation-link-checker/1.0",
                "Accept": "text/html,application/xhtml+xml",
            })
            with build_opener(redirects).open(request, timeout=timeout) as response:
                final_url = response.geturl()
                if not 200 <= response.status < 300:
                    return Page(final_url, set(), f"HTTP {response.status}")
                if response.headers.get_content_type() not in {"text/html", "application/xhtml+xml"}:
                    return Page(final_url, set(), "Response is not HTML")
                body = response.read(MAX_HTML_BYTES + 1)
                if len(body) > MAX_HTML_BYTES:
                    return Page(final_url, set(), "HTML exceeds the 10 MiB limit")
                parser = AnchorParser()
                parser.feed(body.decode(response.headers.get_content_charset() or "utf-8", errors="replace"))
                parser.close()
                return Page(final_url, parser.anchors, fragment=redirects.fragment)
        except HTTPError as exc:
            error = f"HTTP {exc.code} ({exc.reason})"
            exc.close()
            if exc.code not in {408, 429} and exc.code < 500:
                break
        except (URLError, OSError, HTTPException) as exc:
            error = f"Request failed: {exc}"
        except (ValueError, LookupError) as exc:
            error = str(exc)
            break
        if attempt + 1 < attempts:
            time.sleep(2 ** attempt)
    return Page(url, set(), " ".join(error.split()))


def check_links(links, fetcher=fetch_page):
    pages = {}
    results = {}
    for url in sorted(links):
        if re.search(r"\$|\{|\}|%(?:\d+\$)?s", url):
            results[url] = "Dynamic URL literal needs manual review"
        else:
            pages.setdefault(urldefrag(url)[0], []).append(url)
    with ThreadPoolExecutor(max_workers=4) as executor:
        pending = {executor.submit(fetcher, url): urls for url, urls in pages.items()}
        for future in as_completed(pending):
            page = future.result()
            for url in pending[future]:
                fragment = unquote(urlsplit(url).fragment) if page.fragment is None else page.fragment
                error = page.error
                if not error and fragment and fragment not in page.anchors:
                    error = f"Missing anchor #{fragment} on {urldefrag(page.url)[0]}"
                results[url] = error
                print(f"{'FAIL' if error else 'PASS'} {url}" + (f" — {' '.join(error.split())}" if error else ""), flush=True)
                for location in links[url]:
                    print(f"  {location}", flush=True)
    for url in sorted(set(results) - {url for urls in pages.values() for url in urls}):
        print(f"FAIL {url} — {results[url]}", flush=True)
        for location in links[url]:
            print(f"  {location}", flush=True)
    return results


def code(value):
    return "<code>" + escape(str(value)).replace("|", "&#124;").replace("`", "&#96;") + "</code>"


def make_report(links, results, revision, run_url=""):
    failures = {url: error for url, error in results.items() if error}
    lines = [COMMENT_MARKER, "## WooPayments documentation links", "", f"Checked commit: {code(revision)}", ""]
    if run_url:
        lines += [f"[Workflow run and full logs]({run_url})", ""]
    lines += [f"**{len(results)} unique URLs checked; {len(failures)} failed.**", ""]
    if not links:
        lines += ["**Failed: no documentation URLs were found. Check the scan scope.**", ""]
    lines += [
        "Scope: tracked PHP, JS, JSX, TS and TSX plugin source; tests, dependencies and generated bundles excluded.",
        "Checks complete URL literals, including source comments. Runtime-assembled URLs are not evaluated.",
        "Request errors (including access blocks or timeouts) are failures to verify, not proof of a broken link.",
        "",
    ]
    if failures:
        lines += ["| URL | Problem | Source |", "| --- | --- | --- |"]
        for url, error in sorted(failures.items()):
            row = f"| {code(url)} | {code(error)} | {'<br>'.join(code(loc) for loc in links[url])} |"
            # GitHub issue comments are limited to 65,536 characters.
            if sum(len(line) + 1 for line in lines) + len(row) > 58000:
                lines += ["", "Report truncated; all results and source locations are in the job log."]
                break
            lines.append(row)
    elif links:
        lines.append("All discovered documentation URLs and anchors passed.")
    return "\n".join(lines) + "\n"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--report", type=Path, required=True)
    args = parser.parse_args()
    root = args.root.resolve()
    revision = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
    links = discover_links(root)
    print(f"Found {len(links)} unique documentation URLs at commit {revision}.", flush=True)
    results = check_links(links)
    report = make_report(links, results, revision, os.environ.get("DOC_LINK_RUN_URL", ""))
    args.report.write_text(report, encoding="utf-8")
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as summary:
            summary.write(report)
    print(f"Checked {len(results)} URLs; {sum(bool(error) for error in results.values())} failed.")
    return 1 if not links or any(results.values()) else 0


if __name__ == "__main__":
    raise SystemExit(main())
