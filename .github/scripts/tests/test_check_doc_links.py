"""Offline regression tests for discovery, HTTP checks, anchors and reporting."""

from collections import Counter
from contextlib import redirect_stdout
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import importlib.util
import io
import os
from pathlib import Path
import subprocess
import sys
import tempfile
from threading import Thread
import unittest
from unittest.mock import patch
from urllib.error import URLError
from urllib.parse import urlsplit


SCRIPT = Path(__file__).resolve().parents[1] / "check-doc-links.py"
SPEC = importlib.util.spec_from_file_location("checker", SCRIPT)
checker = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = checker
SPEC.loader.exec_module(checker)
DOC_URL = "https://woocommerce.com/document/example/"


class DiscoveryTests(unittest.TestCase):
    def test_readme_links_are_scanned_with_source_locations(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            subprocess.run(["git", "init", "-q", directory], check=True)
            (root / "readme.txt").write_text("\n".join([
                "=== WooPayments ===",
                "[Fees](https://woocommerce.com/document/woopayments/fees/).",
                "[Countries](https://woocommerce.com/document/woopayments/compatibility/countries/#supported-countries)",
                "[Product](https://woocommerce.com/payments/)",
            ]))
            (root / "README.md").write_text(DOC_URL + "#developer-readme")
            (root / "client").mkdir()
            (root / "client/readme.txt").write_text(DOC_URL + "#nested-readme")
            subprocess.run(["git", "add", "."], cwd=root, check=True)
            self.assertEqual(checker.discover_links(root), {
                "https://woocommerce.com/document/woopayments/fees/": ["readme.txt:2"],
                "https://woocommerce.com/document/woopayments/compatibility/countries/#supported-countries": ["readme.txt:3"],
            })

    def test_tracked_source_extensions_locations_and_exclusions(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            subprocess.run(["git", "init", "-q", directory], check=True)
            sources = [
                "woocommerce-payments.php", "includes/example.php", "src/Example.php",
                "client/example.js", "client/example.jsx", "client/example.ts",
                "client/example.tsx", "includes/multi-currency/client/example.js",
                "templates/example.php", "assets/example.js",
            ]
            excluded = [
                "client/__tests__/example.tsx", "client/example.test.js",
                "client/example.spec.ts", "client/example.min.js", "tests/example.php",
                "vendor/example.php", "node_modules/example.js", "dist/example.js",
                "docs/example.js", "lib/packages/example.php", "client/README.md",
            ]
            for name in sources + excluded:
                path = root / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text(f'// Comment\nconst url = "{DOC_URL}#section";\n')
            (root / "client/symlink.js").symlink_to(root / "client/example.js")
            subprocess.run(["git", "add", "."], cwd=root, check=True)
            (root / "client/untracked.js").write_text(DOC_URL + "#untracked")
            links = checker.discover_links(root)
            self.assertEqual(list(links), ["https://woocommerce.com/document/example/#section"])
            self.assertEqual(sorted(links[DOC_URL + "#section"]), sorted(f"{name}:2" for name in sources))

    def test_url_forms_and_domain_boundaries(self):
        source = r'''
            <a href="https://www.woocommerce.com/documentation/products/foo/?a=1&amp;b=2#hello%20world" />
            'http://docs.woocommerce.com/document/old/#section'
            `https://woocommerce.com/docs/example/#section`
            "https:\/\/woocommerce.com\/document\/escaped\/#section"
            // https://woocommerce.com/document/comment/.
            'https://woocommerce.com/products/example/'
            'https://woocommerce.com/documentary/'
            'https://woocommerce.com.evil.example/document/example/'
        '''
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            subprocess.run(["git", "init", "-q", directory], check=True)
            (root / "example.php").write_text(source)
            subprocess.run(["git", "add", "."], cwd=root, check=True)
            self.assertEqual(set(checker.discover_links(root)), {
                "https://www.woocommerce.com/documentation/products/foo/?a=1&b=2#hello%20world",
                "http://docs.woocommerce.com/document/old/#section",
                "https://woocommerce.com/docs/example/#section",
                "https://woocommerce.com/document/escaped/#section",
                "https://woocommerce.com/document/comment/",
            })


class FixtureHandler(BaseHTTPRequestHandler):
    requests = Counter()

    def log_message(self, *args):
        pass

    def do_GET(self):
        path = urlsplit(self.path).path
        self.requests[path] += 1
        redirects = {
            "/redirect": "/ok",
            "/override": "/ok#present",
            "/clear": "/ok#",
            "/offsite": "https://example.com/document/elsewhere/",
            "/loop": "/loop",
        }
        if path in redirects:
            self.send_response(302)
            self.send_header("Location", redirects[path])
            self.end_headers()
            return
        status = 200
        if path in {"/missing", "/document/missing/"}:
            status = 404
        elif path == "/blocked":
            status = 403
        elif path == "/retry" and self.requests[path] == 1:
            status = 429
        elif path == "/unavailable":
            status = 503
        self.send_response(status)
        self.send_header("Content-Type", "application/json" if path == "/json" else "text/html; charset=utf-8")
        self.end_headers()
        self.wfile.write(b'''<html><h2 id="present">Title</h2><div ID='hello world'></div>
            <div id="a&amp;b"></div><div id=unquoted></div><a name="legacy"></a>
            <!-- <div id="comment-only"></div> -->
            <script>const fake = '<div id="script-only"></div>';</script></html>''')


class HttpTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), FixtureHandler)
        cls.thread = Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()
        cls.base = f"http://127.0.0.1:{cls.server.server_port}"

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()
        cls.thread.join()

    def setUp(self):
        FixtureHandler.requests.clear()
        self.hosts = patch.object(checker, "DOC_HOSTS", {"127.0.0.1"})
        self.hosts.start()
        self.addCleanup(self.hosts.stop)

    def check(self, paths):
        links = {self.base + path: ["client/example.tsx:2"] for path in paths}
        with redirect_stdout(io.StringIO()):
            return checker.check_links(links)

    def test_ids_are_exact_decoded_and_not_text_comments_or_named_anchors(self):
        results = self.check([
            "/ok", "/ok#present", "/ok#hello%20world", "/ok#a%26b", "/ok#unquoted",
            "/ok#missing", "/ok#Present", "/ok#legacy", "/ok#comment-only", "/ok#script-only",
        ])
        self.assertEqual(sum(bool(error) for error in results.values()), 5)
        for fragment in ["present", "hello%20world", "a%26b", "unquoted"]:
            self.assertEqual(results[self.base + "/ok#" + fragment], "")
        self.assertEqual(FixtureHandler.requests["/ok"], 1)

    def test_redirects_preserve_replace_and_clear_fragments(self):
        results = self.check(["/redirect#present", "/redirect#missing", "/override#old", "/clear#old"])
        self.assertEqual(results[self.base + "/redirect#present"], "")
        self.assertIn("Missing anchor #missing", results[self.base + "/redirect#missing"])
        self.assertEqual(results[self.base + "/override#old"], "")
        self.assertEqual(results[self.base + "/clear#old"], "")

    def test_404_access_blocks_non_html_and_offsite_redirects_fail(self):
        results = self.check(["/missing", "/blocked", "/json", "/offsite", "/loop"])
        self.assertTrue(all(results.values()))
        self.assertIn("HTTP 404", results[self.base + "/missing"])
        self.assertIn("HTTP 403", results[self.base + "/blocked"])
        self.assertEqual(results[self.base + "/json"], "Response is not HTML")
        self.assertIn("not a WooCommerce documentation host", results[self.base + "/offsite"])
        self.assertEqual(FixtureHandler.requests["/missing"], 1)
        self.assertEqual(FixtureHandler.requests["/blocked"], 1)

    @patch.object(checker.time, "sleep")
    def test_transient_statuses_retry_and_eventually_fail(self, sleep):
        results = self.check(["/retry#present", "/unavailable"])
        self.assertEqual(results[self.base + "/retry#present"], "")
        self.assertIn("HTTP 503", results[self.base + "/unavailable"])
        self.assertEqual(FixtureHandler.requests["/retry"], 2)
        self.assertEqual(FixtureHandler.requests["/unavailable"], 3)

    @patch.object(checker.time, "sleep")
    @patch.object(checker, "build_opener")
    def test_network_errors_exhaust_retries(self, opener, sleep):
        opener.return_value.open.side_effect = URLError("timed out")
        page = checker.fetch_page(self.base + "/ok")
        self.assertIn("Request failed", page.error)
        self.assertEqual(opener.return_value.open.call_count, 3)

    def test_cli_exit_status_summary_and_report(self):
        for suffix, expected_exit in [("#present", 0), ("#missing", 1), (None, 1)]:
            with self.subTest(suffix=suffix), tempfile.TemporaryDirectory() as directory:
                root = Path(directory)
                subprocess.run(["git", "init", "-q", directory], check=True)
                content = "<?php // no URLs" if suffix is None else "http://woocommerce.com/document/example/" + suffix
                (root / "example.php").write_text(content)
                subprocess.run(["git", "add", "."], cwd=root, check=True)
                subprocess.run([
                    "git", "-c", "user.name=Test", "-c", "user.email=test@example.com",
                    "-c", "commit.gpgsign=false", "commit", "-qm", "Fixture",
                ], cwd=root, check=True)
                report = root / "report.md"
                summary = root / "summary.md"
                env = {**os.environ, "http_proxy": self.base, "no_proxy": "", "GITHUB_STEP_SUMMARY": str(summary)}
                result = subprocess.run([
                    sys.executable, "-B", str(SCRIPT), "--root", str(root), "--report", str(report),
                ], env=env, text=True, capture_output=True)
                self.assertEqual(result.returncode, expected_exit, result.stderr + result.stdout)
                self.assertEqual(report.read_text(), summary.read_text())
                self.assertIn("unique URLs checked", report.read_text())
                if suffix == "#missing":
                    self.assertIn("Missing anchor #missing", report.read_text())
                    self.assertIn("example.php:1", report.read_text())
                elif suffix is None:
                    self.assertIn("no documentation URLs were found", report.read_text())


class ReportTests(unittest.TestCase):
    def test_dynamic_literal_is_reported_without_fetching(self):
        links = {DOC_URL + "${path}": ["client/example.ts:1"]}
        with patch.object(checker, "fetch_page") as fetcher, redirect_stdout(io.StringIO()):
            results = checker.check_links(links, fetcher=fetcher)
        fetcher.assert_not_called()
        self.assertEqual(list(results.values()), ["Dynamic URL literal needs manual review"])

    def test_report_keeps_all_failures_and_escapes_source_text(self):
        links = {DOC_URL + str(number): ["client/<script>|`file`.tsx:1"] for number in range(1000)}
        results = {url: "Missing anchor" for url in links}
        report = checker.make_report(links, results, "abc123")
        self.assertEqual(report.count("Missing anchor"), 1000)
        self.assertIn("https://woocommerce.com/document/example/999", report)
        self.assertIn("&lt;script&gt;&#124;&#96;file&#96;", report)
        self.assertTrue(report.startswith("## WooPayments documentation links"))


if __name__ == "__main__":
    unittest.main()
