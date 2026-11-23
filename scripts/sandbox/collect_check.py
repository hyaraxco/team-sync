#!/usr/bin/env python3
"""Run with Python inside the sandbox; never follows report links."""

import importlib.util
import os
from pathlib import Path
import tempfile

spec = importlib.util.spec_from_file_location("collector", "/source/scripts/sandbox/collect.py")
collector = importlib.util.module_from_spec(spec)
spec.loader.exec_module(collector)
with tempfile.TemporaryDirectory(dir="/scratch") as directory:
    root = Path(directory)
    (root / "report").write_text("{}")
    fd = os.open(root, os.O_RDONLY | os.O_DIRECTORY)
    try:
        assert collector.read_regular(fd, "report", 2) == b"{}"
        os.symlink(root / "report", root / "symlink")
        os.link(root / "report", root / "hardlink")
        os.mkfifo(root / "fifo")
        for name, limit in (("symlink", 2), ("hardlink", 2), ("fifo", 2), ("report", 1)):
            try:
                collector.read_regular(fd, name, limit)
            except (OSError, ValueError):
                pass
            else:
                raise AssertionError(f"Unsafe report accepted: {name}")
    finally:
        os.close(fd)
assert collector.summarize("roles-junit.xml", b'<testsuites><testsuite tests="4" failures="1" skipped="2"/></testsuites>') == {"tests": 4, "failures": 1, "errors": 0, "skipped": 2, "assertions": 0}
assert collector.summarize("fe-coverage-final.json", b'{"never-imported.vue":{"s":{"0":0}},"imported.js":{"s":{"0":1}}}') == {"files": 2, "uncovered_files": 1}
print("collector safety checks: PASS")
