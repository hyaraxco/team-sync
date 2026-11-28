#!/usr/bin/env python3
"""Retain only bounded, parsed summaries after the disposable suite stops."""

import hashlib
import json
import os
from pathlib import Path
import signal
import stat
import subprocess
import sys
import xml.etree.ElementTree as ET

LIMITS = {
    "be-clover.xml": 16 << 20,
    "be-junit.xml": 8 << 20,
    "fe-coverage-summary.json": 2 << 20,
    "fe-coverage-final.json": 32 << 20,
    "fe-junit.xml": 8 << 20,
    "roles-junit.xml": 8 << 20,
    "roles-focused-junit.xml": 8 << 20,
    "a11y.json": 4 << 20,
    "runtime.json": 1 << 20,
    "status.json": 1 << 20,
}
REQUIRED = {
    "be": {"be-clover.xml", "be-junit.xml"},
    "fe": {"fe-coverage-summary.json", "fe-coverage-final.json", "fe-junit.xml"},
    "build-be": set(),
    "build-fe": set(),
    "a11y": {"a11y.json"},
    "roles": {"roles-focused-junit.xml", "roles-junit.xml"},
}


def read_regular(directory, name, limit):
    fd = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK, dir_fd=directory)
    try:
        before = os.fstat(fd)
        if not stat.S_ISREG(before.st_mode) or before.st_nlink != 1 or before.st_size > limit:
            raise ValueError(f"Rejected report type, links or size: {name}")
        data = bytearray()
        while len(data) <= before.st_size:
            chunk = os.read(fd, min(65536, before.st_size + 1 - len(data)))
            if not chunk:
                break
            data.extend(chunk)
        after = os.fstat(fd)
        if (before.st_dev, before.st_ino, before.st_mode, before.st_nlink, before.st_size, before.st_mtime_ns, before.st_ctime_ns) != (after.st_dev, after.st_ino, after.st_mode, after.st_nlink, after.st_size, after.st_mtime_ns, after.st_ctime_ns) or len(data) != before.st_size:
            raise ValueError(f"Report changed during collection: {name}")
        return bytes(data)
    finally:
        os.close(fd)


def summarize(name, data):
    if name.endswith(".xml"):
        if b"<!DOCTYPE" in data or b"<!ENTITY" in data:
            raise ValueError("XML declarations are not accepted")
        root = ET.fromstring(data)
        if name == "be-clover.xml":
            project = root.find("project")
            if project is None or project.find("metrics") is None:
                raise ValueError("Missing Clover project metrics")
            return {"metrics": dict(project.find("metrics").attrib), "files": len(project.findall(".//file")), "scope": "app/**/*.php", "exclusions": ["outside app/"], "driver": "Xdebug"}
        suites = [root] if root.tag == "testsuite" else list(root.findall("testsuite"))
        if not suites:
            raise ValueError("Missing JUnit suites")
        return {key: sum(int(suite.get(key, "0")) for suite in suites) for key in ("tests", "failures", "errors", "skipped", "assertions")}
    value = json.loads(data)
    if name == "fe-coverage-final.json":
        return {"files": len(value), "uncovered_files": sum(not any(item.get("s", {}).values()) for item in value.values())}
    if name == "fe-coverage-summary.json":
        return {"total": value["total"], "files": len(value) - 1, "scope": "src/**/*.{js,vue}", "exclusions": ["src/tests/**", "src/**/*.test.js", "src/**/*.spec.js"]}
    if name == "a11y.json":
        return {
            "runner": value.get("runner"), "url": value.get("url", value.get("finalUrl")),
            "scope": value.get("scope"), "browser": value.get("browser"),
            "violations": [{"id": item.get("id"), "impact": item.get("impact"), "nodes": len(item.get("nodes", []))} for item in value.get("violations", [])],
            "passes": len(value.get("passes", [])), "incomplete": len(value.get("incomplete", [])),
        }
    return value


def main():
    phase = sys.argv[1]
    if phase not in ("be", "fe", "build-be", "build-fe", "a11y", "roles"):
        raise SystemExit("Unknown suite phase")
    if os.getpid() != 1:
        raise SystemExit("Collector requires its own PID namespace as PID 1")
    os.makedirs("/scratch/tmp", exist_ok=True)
    os.makedirs("/scratch/reports", exist_ok=True)
    subprocess.run([sys.executable, "-I", "/source/scripts/sandbox/collect_check.py"], check=True)
    directory = os.open("/scratch/reports", os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
    result = {"phase": phase, "reports": {}, "collection_errors": []}
    child = subprocess.Popen(["/bin/bash", "/source/scripts/sandbox/suite.sh", phase], start_new_session=True)
    try:
        result["exit_code"] = child.wait()
        try:
            os.kill(-1, signal.SIGKILL)
        except ProcessLookupError:
            pass
        while True:
            try:
                os.waitpid(-1, 0)
            except ChildProcessError:
                break
        for name, limit in LIMITS.items():
            try:
                data = read_regular(directory, name, limit)
            except FileNotFoundError:
                continue
            except (OSError, ValueError) as error:
                result["collection_errors"].append(str(error))
                continue
            try:
                result["reports"][name] = {"sha256": hashlib.sha256(data).hexdigest(), "bytes": len(data), "summary": summarize(name, data)}
            except (ValueError, TypeError, KeyError, ET.ParseError) as error:
                result["collection_errors"].append(f"{name}: {error}")
        for name in sorted(REQUIRED[phase] | {"runtime.json", "status.json"}):
            if name not in result["reports"]:
                result["collection_errors"].append(f"Missing required report: {name}")
        result["source_manifest_sha256"] = hashlib.sha256(Path("/opt/source-sha256.txt").read_bytes()).hexdigest()
        encoded = json.dumps(result, ensure_ascii=True, separators=(",", ":"))
        if len(encoded) > 262144:
            raise ValueError("Summary exceeds 256 KiB")
        print("TEA13_REPORT:" + encoded, flush=True)
        return result["exit_code"] or bool(result["collection_errors"])
    finally:
        os.close(directory)
        if child.poll() is None:
            os.killpg(child.pid, signal.SIGKILL)
            child.wait()


if __name__ == "__main__":
    sys.exit(main())
