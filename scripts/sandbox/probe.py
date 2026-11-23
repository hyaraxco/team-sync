#!/usr/bin/env python3
"""Trusted Docker orchestration and bounded Linux control proofs (stdlib only)."""
import argparse
import errno
import codecs
import json
import os
from pathlib import Path
import resource
import selectors
import signal
import socket
import subprocess
import sys
import time
import threading
import uuid

ENV = {
    "PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
    "HOME": "/scratch/home", "TMPDIR": "/scratch/tmp", "TMP": "/scratch/tmp",
    "TEMP": "/scratch/tmp", "XDG_CACHE_HOME": "/scratch/cache",
    "XDG_CONFIG_HOME": "/scratch/config", "XDG_DATA_HOME": "/scratch/data",
    "BUN_INSTALL_CACHE_DIR": "/scratch/cache/bun",
    "COMPOSER_CACHE_DIR": "/scratch/cache/composer",
    "PLAYWRIGHT_BROWSERS_PATH": "/opt/browsers", "CI": "1", "TZ": "UTC",
    "LANG": "C.UTF-8", "LC_ALL": "C.UTF-8",
}
PHASES = ("be", "fe", "build-be", "build-fe", "a11y", "roles")
CGROUP = Path("/sys/fs/cgroup")
PROOF_LIMITS = (128 * 1024**2, 16, 8 * 1024**2, 128, 0.5)
SUITE_LIMITS = (6 * 1024**3, 256, 4 * 1024**3, 131072, 2)
OUTPUT_LIMIT = 4 * 1024**2


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def emit(event, **values):
    print(json.dumps({"event": event, **values}, ensure_ascii=True), flush=True)


def numbers(name):
    return dict(line.split() for line in (CGROUP / name).read_text().splitlines())


def mount_for(path):
    path = os.path.realpath(path)
    matches = []
    for line in Path("/proc/self/mountinfo").read_text().splitlines():
        fields = line.split()
        mount = fields[4].replace("\\040", " ")
        if path == mount or path.startswith(mount.rstrip("/") + "/"):
            separator = fields.index("-")
            matches.append((len(mount), fields[5].split(","), fields[separator + 1], fields[separator + 3]))
    require(matches, "mount missing: " + path)
    return max(matches)


def blocked_write(path):
    try:
        fd = os.open(path, os.O_WRONLY | os.O_NONBLOCK)
    except OSError as error:
        require(error.errno in (errno.EROFS, errno.EACCES, errno.EPERM), "unexpected write error: " + path)
    else:
        os.close(fd)
        raise RuntimeError("writable immutable file: " + path)


def static_proof(limits):
    memory, pids, scratch, inodes, cpus = limits
    require(sys.platform == "linux", "Linux required")
    require(os.getuid() == os.geteuid() == 1000 and os.getgid() == 1000, "uid/gid must be 1000")
    require(dict(os.environ) == ENV, "environment differs from explicit allowlist")
    status = dict(line.split(":", 1) for line in Path("/proc/self/status").read_text().splitlines())
    require(int(status["CapEff"].strip(), 16) == 0, "effective capabilities present")
    require(int(status["CapBnd"].strip(), 16) == 0, "bounding capabilities present")
    require(status["NoNewPrivs"].strip() == "1", "no-new-privileges missing")
    require(status["Seccomp"].strip() == "2", "seccomp filter missing")
    require((CGROUP / "cgroup.controllers").exists(), "cgroup v2 required")
    require(int((CGROUP / "memory.max").read_text()) == memory, "wrong memory.max")
    require((CGROUP / "memory.swap.max").read_text().strip() == "0", "swap available")
    require(int((CGROUP / "pids.max").read_text()) == pids, "wrong pids.max")
    quota, period = map(int, (CGROUP / "cpu.max").read_text().split())
    require(quota / period == cpus, "wrong aggregate CPU quota")
    for kind, expected in ((resource.RLIMIT_NOFILE, 4096), (resource.RLIMIT_FSIZE, 256 * 1024**2), (resource.RLIMIT_CORE, 0)):
        require(resource.getrlimit(kind) == (expected, expected), "wrong process rlimit")
    for path in ("/", "/source", "/source/scripts/sandbox/probe.py", "/opt/be/vendor", "/opt/fe/node_modules", "/opt/be/node_modules", "/opt/browsers", "/usr", "/bin"):
        require(os.path.exists(path), "missing immutable image path: " + path)
        require("ro" in mount_for(path)[1], "writable source/toolchain mount: " + path)
    blocked_write("/source/scripts/sandbox/probe.py")
    for tool in ("/usr/bin/env", sys.executable):
        blocked_write(tool)
    require(os.path.realpath("/tmp") == "/scratch/tmp", "/tmp must resolve inside scratch")
    require(os.path.realpath("/var/tmp") == "/scratch/tmp", "/var/tmp must resolve inside scratch")
    mount = mount_for("/scratch")
    require(mount[2] == "tmpfs" and "rw" in mount[1] and "nosuid" in mount[1] and "nodev" in mount[1], "unsafe scratch mount")
    fs = os.statvfs("/scratch")
    require(fs.f_blocks * fs.f_frsize == scratch and fs.f_files == inodes, "wrong scratch byte/inode bound")
    for path in ("/etc/hosts", "/etc/hostname", "/etc/resolv.conf"):
        require(not os.access(path, os.W_OK), "writable Docker runtime file: " + path)
    for path in ("/dev", "/dev/pts", "/dev/mqueue", "/dev/shm", "/run", "/sys/fs/cgroup"):
        if os.path.exists(path):
            require(not os.access(path, os.W_OK), "writable surface outside scratch: " + path)
    require(not os.path.exists("/var/run/docker.sock"), "Docker socket exposed")
    require({name for _, name in socket.if_nameindex()} == {"lo"}, "non-loopback interface")
    for line in Path("/proc/net/route").read_text().splitlines()[1:]:
        require(line.split()[0] == "lo", "external IPv4 route")
    for line in Path("/proc/net/ipv6_route").read_text().splitlines():
        require(line.split()[-1] == "lo", "external IPv6 route")
    for family, address in ((socket.AF_INET, ("192.0.2.1", 443)), (socket.AF_INET6, ("2001:db8::1", 443, 0, 0))):
        with socket.socket(family, socket.SOCK_STREAM) as client:
            client.settimeout(0.2)
            try:
                client.connect(address)
            except OSError as error:
                require(error.errno in (errno.ENETUNREACH, errno.EHOSTUNREACH, errno.EAFNOSUPPORT), "external socket did not fail at routing boundary")
            else:
                raise RuntimeError("external socket connected")
    for path in ("home", "tmp", "cache", "config", "data"):
        Path("/scratch", path).mkdir(exist_ok=True)
    emit("static-proof", limits=limits, netns=os.readlink("/proc/self/ns/net"), writable="/scratch only; /dev devices are OS-managed, not regular-file storage")


def reap(children):
    for child in children:
        try:
            os.kill(child, signal.SIGKILL)
        except ProcessLookupError:
            pass
    for child in children:
        try:
            os.waitpid(child, 0)
        except ChildProcessError:
            pass


def fork_worker(work):
    child = os.fork()
    if child == 0:
        try:
            work()
        finally:
            os._exit(0)
    return child


def memory_worker():
    Path("/proc/self/oom_score_adj").write_text("1000")
    data = bytearray(72 * 1024**2)
    for offset in range(0, len(data), 4096):
        data[offset] = 1
    time.sleep(4)


def pressure_proof():
    before = int(numbers("memory.events")["oom_kill"])
    children = []
    try:
        children.append(fork_worker(memory_worker))
        children.append(fork_worker(memory_worker))
        deadline = time.monotonic() + 5
        while time.monotonic() < deadline and int(numbers("memory.events")["oom_kill"]) == before:
            time.sleep(0.05)
        require(int(numbers("memory.events")["oom_kill"]) > before, "aggregate memory pressure did not trigger cgroup OOM kill")
    finally:
        reap(children)
    before = int(numbers("pids.events")["max"])
    children = []
    limited = False
    try:
        for _ in range(20):
            try:
                children.append(fork_worker(lambda: time.sleep(5)))
            except OSError as error:
                require(error.errno == errno.EAGAIN, "unexpected fork failure")
                limited = True
                break
        require(limited and int(numbers("pids.events")["max"]) > before, "aggregate PID enforcement missing")
    finally:
        reap(children)
    before = numbers("cpu.stat")
    start = time.monotonic()
    children = []
    def busy():
        end = time.monotonic() + 3
        while time.monotonic() < end:
            pass
    try:
        for _ in range(4):
            children.append(fork_worker(busy))
        for child in children:
            os.waitpid(child, 0)
        children.clear()
    finally:
        reap(children)
    after = numbers("cpu.stat")
    elapsed = time.monotonic() - start
    used = (int(after["usage_usec"]) - int(before["usage_usec"])) / 1e6
    require(int(after["nr_throttled"]) > int(before["nr_throttled"]), "aggregate CPU throttling not observed")
    require(0.1 < used / elapsed < 0.9, "CPU usage inconsistent with 0.5-core proof limit")
    emit("pressure-proof", memory_oom_kill=True, pid_rejected=True, cpu_throttled=True, observed_cores=round(used / elapsed, 3))


def storage_proof():
    path = Path("/scratch/fill")
    try:
        with path.open("wb", buffering=0) as output:
            for _ in range(12):
                output.write(b"x" * 1024**2)
    except OSError as error:
        require(error.errno == errno.ENOSPC, "unexpected scratch capacity failure")
    else:
        raise RuntimeError("scratch byte limit not enforced")
    finally:
        path.unlink(missing_ok=True)
    files = []
    try:
        for index in range(160):
            path = Path("/scratch/inode-" + str(index))
            try:
                path.touch(exist_ok=False)
                files.append(path)
            except OSError as error:
                require(error.errno == errno.ENOSPC, "unexpected inode limit failure")
                break
        else:
            raise RuntimeError("scratch inode bound not enforced")
    finally:
        for path in files:
            path.unlink()
    descriptors = []
    try:
        for _ in range(resource.getrlimit(resource.RLIMIT_NOFILE)[0] + 6):
            try:
                descriptors.append(os.open("/dev/null", os.O_RDONLY))
            except OSError as error:
                require(error.errno == errno.EMFILE, "unexpected descriptor failure")
                break
        else:
            raise RuntimeError("open-file limit not enforced")
    finally:
        for fd in descriptors:
            os.close(fd)
    signal.signal(signal.SIGXFSZ, signal.SIG_IGN)
    path = Path("/scratch/fsize")
    try:
        with path.open("wb") as output:
            try:
                output.truncate(256 * 1024**2 + 1)
            except OSError as error:
                require(error.errno == errno.EFBIG, "unexpected file-size failure")
            else:
                raise RuntimeError("file-size limit not enforced")
    finally:
        path.unlink(missing_ok=True)
    emit("storage-proof", scratch_enospc=True, inode_enospc=True, nofile_emfile=True, fsize_efbig=True)


def container_main(mode, phase):
    static_proof(SUITE_LIMITS if mode == "suite" else PROOF_LIMITS)
    if mode == "proof":
        pressure_proof()
        storage_proof()
    elif mode == "watchdog":
        fork_worker(lambda: time.sleep(300))
        time.sleep(300)
    elif mode == "baseline":
        time.sleep(60)
    elif mode == "suite":
        require(phase in PHASES, "invalid suite phase")
        os.execv("/usr/bin/env", ["env", "-i", *[key + "=" + value for key, value in ENV.items()], "python3", "-I", "/source/scripts/sandbox/collect.py", phase])


class Docker:
    def __init__(self):
        self.active = set()
        self.env = {"PATH": os.environ.get("PATH", "/usr/local/bin:/usr/bin:/bin")}
        # Docker's local client configuration is trusted orchestration, never container input.
        for key in ("HOME", "DOCKER_HOST", "DOCKER_CONTEXT", "DOCKER_CONFIG", "DOCKER_TLS_VERIFY", "DOCKER_CERT_PATH"):
            if key in os.environ:
                self.env[key] = os.environ[key]

    def call(self, args, timeout=15):
        result = subprocess.run(["docker", *args], env=self.env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=timeout)
        require(result.returncode == 0, "Docker command failed: " + " ".join(args[:2]) + ": " + result.stderr[:2048].decode("utf-8", "replace"))
        return result.stdout.decode("utf-8")

    def cleanup(self, name):
        self.call(["rm", "--force", "--volumes", name], timeout=20)
        listed = json.loads(self.call(["ps", "--all", "--filter", "name=^/" + name + "$", "--format", "{{json .ID}}"] ) or '""')
        require(not listed, "container remains after forced removal")
        self.active.discard(name)

    def create(self, image, mode, phase=""):
        name = "tea13-" + uuid.uuid4().hex
        self.active.add(name)
        memory, pids, scratch, inodes, cpus = SUITE_LIMITS if mode == "suite" else PROOF_LIMITS
        args = ["create", "--pull=never", "--name", name, "--network", "none", "--read-only", "--cap-drop", "ALL", "--security-opt", "no-new-privileges", "--user", "1000:1000", "--cpus", str(cpus), "--memory", str(memory), "--memory-swap", str(memory), "--pids-limit", str(pids), "--ulimit", "nofile=4096:4096", "--ulimit", "fsize=268435456:268435456", "--ulimit", "core=0:0", "--ipc=none", "--log-driver", "none", "--restart", "no", "--stop-timeout", "1", "--tmpfs", f"/scratch:rw,nosuid,nodev,size={scratch},nr_inodes={inodes},mode=0700,uid=1000,gid=1000", "--workdir", "/source", "--entrypoint", "/usr/bin/env", image, "-i", *[key + "=" + value for key, value in ENV.items()], "python3", "-I", "/source/scripts/sandbox/probe.py", "--container", mode]
        args[1:1] = ["--tmpfs", "/dev/mqueue:ro,nosuid,nodev,noexec,size=4096,mode=0000"]
        if phase:
            args += ["--phase", phase]
        emit("command", phase=phase or mode, argv=["docker", *args])
        self.call(args)
        return name

    def run(self, image, mode, seconds, phase=""):
        name = self.create(image, mode, phase)
        process = None
        watchdog = None
        expired = threading.Event()
        cleanup_errors = []
        try:
            process = subprocess.Popen(["docker", "start", "--attach", name], env=self.env, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
            def enforce_deadline():
                expired.set()
                try:
                    self.cleanup(name)
                except Exception as error:
                    cleanup_errors.append(str(error))
                finally:
                    process.kill()
            # Independent of stdout handling: even a blocked host output sink cannot keep the container alive.
            watchdog = threading.Timer(seconds, enforce_deadline)
            watchdog.daemon = True
            watchdog.start()
            selector = selectors.DefaultSelector()
            selector.register(process.stdout, selectors.EVENT_READ)
            total = 0
            pending = b""
            netns = None
            decoder = codecs.getincrementaldecoder("utf-8")("replace")
            while selector.get_map() and not expired.is_set():
                for key, _ in selector.select(0.2):
                    data = os.read(key.fd, 16384)
                    if not data:
                        selector.unregister(key.fileobj)
                        tail = decoder.decode(b"", final=True)
                        if tail:
                            emit("output", phase=phase or mode, data=tail)
                    else:
                        total += len(data)
                        require(total <= OUTPUT_LIMIT, "container output exceeded 4MiB bound")
                        if mode != "suite":
                            pending += data
                            while b"\n" in pending:
                                line, pending = pending.split(b"\n", 1)
                                report = json.loads(line)
                                if report.get("event") == "static-proof":
                                    netns = report["netns"]
                        emit("output", phase=phase or mode, data=decoder.decode(data))
            selector.close()
            process.wait(timeout=25 if expired.is_set() else seconds)
            if expired.is_set():
                watchdog.join()
                require(not cleanup_errors, "watchdog cleanup failed: " + "; ".join(cleanup_errors))
                emit("status", phase=phase or mode, exit_code=124, watchdog=True, cleanup=True)
                return 124, netns
            state = json.loads(self.call(["inspect", "--format", "{{json .State}}", name]))
            require(not state["Running"], "attached Docker client exited but container still running")
            require(process.returncode == state["ExitCode"], "Docker attach/container status mismatch")
            code = state["ExitCode"]
            emit("status", phase=phase or mode, exit_code=code, oom_killed=state["OOMKilled"], watchdog=False)
            require(not state["OOMKilled"] or (mode == "proof" and code == 0), "unexpected cgroup OOM kill")
            require(mode == "suite" or netns is not None, "control proof missing network namespace evidence")
            return code, netns
        finally:
            if watchdog is not None:
                watchdog.cancel()
                watchdog.join()
            if name in self.active:
                self.cleanup(name)
            if process is not None and process.poll() is None:
                process.kill()
                process.wait(timeout=5)


def self_check():
    require(PHASES == ("be", "fe", "build-be", "build-fe", "a11y", "roles"), "phase allowlist changed")
    require(all(value.startswith("/scratch/") for key, value in ENV.items() if key in ("HOME", "TMPDIR", "XDG_CACHE_HOME")), "unsafe environment")
    class FakeDocker(Docker):
        def __init__(self):
            self.active = set()
            self.commands = []
        def call(self, args, timeout=15):
            self.commands.append(args)
            return "id"
    fake = FakeDocker()
    fake.create("sha256:" + "a" * 64, "suite", "be")
    args = fake.commands[0]
    for item in ("--pull=never", "--read-only", "--ipc=none", "--cap-drop", "--security-opt", "--memory-swap", "--pids-limit"):
        require(item in args, "missing control flag: " + item)
    require("--volume" not in args and "--mount" not in args and "--publish" not in args, "host exposure")
    require(args[args.index("--memory") + 1] == args[args.index("--memory-swap") + 1], "swap mismatch")
    require(args[args.index("--entrypoint") + 1] == "/usr/bin/env", "unsafe entrypoint")
    # Exercise the watchdog path against a trusted sleeping host child, never target code.
    real_popen = subprocess.Popen
    child = None
    removed = []
    def sleeping_child(*_args, **kwargs):
        nonlocal child
        child = real_popen([sys.executable, "-I", "-c", "import time; time.sleep(60)"], **kwargs)
        return child
    class WatchdogDocker(FakeDocker):
        def __init__(self):
            super().__init__()
            self.env = {}
        def cleanup(self, name):
            removed.append(name)
            self.active.discard(name)
    subprocess.Popen = sleeping_child
    try:
        code, _ = WatchdogDocker().run("sha256:" + "a" * 64, "watchdog", 0.1)
        require(code == 124 and len(removed) == 1, "watchdog did not remove its container")
        require(child is not None and child.poll() is not None, "watchdog child remains alive")
    finally:
        subprocess.Popen = real_popen
        if child is not None and child.poll() is None:
            child.kill()
            child.wait()
    emit("self-check", passed=True, scope="command construction and trusted watchdog child; no Docker or target execution")


def host_main(image, phase, seconds):
    require(image and not image.startswith("-"), "explicit local image required")
    docker = Docker()
    def interrupted(signum, _frame):
        raise RuntimeError("host interrupted by signal " + str(signum))
    signal.signal(signal.SIGTERM, interrupted)
    signal.signal(signal.SIGINT, interrupted)
    try:
        facts = json.loads(docker.call(["image", "inspect", image]))[0]
        require(facts.get("Os") == "linux" and facts.get("Architecture") == "arm64", "Linux ARM64 image required")
        require(not facts["Config"].get("Volumes"), "image-declared volumes forbidden")
        image_id = facts["Id"]
        require(image_id.startswith("sha256:") and len(image_id) == 71, "invalid immutable image ID")
        revision = (facts["Config"].get("Labels") or {}).get("org.opencontainers.image.revision", "")
        require(len(revision) in (40, 64) and all(c in "0123456789abcdef" for c in revision), "image must declare source revision label")
        emit("provenance", image=image_id, labeled_source_revision=revision, source_provenance="image label is a baseline; image content and collector manifest identify tested content", phase=phase, wall_seconds=seconds, output_limit=OUTPUT_LIMIT, artifact_promotion=False)
        # Compare simultaneously live Linux namespace inodes, including on a macOS Docker client.
        baseline = docker.create(image_id, "baseline")
        docker.call(["start", baseline])
        baseline_netns = docker.call(["exec", baseline, "/usr/bin/readlink", "/proc/self/ns/net"]).strip()
        require(baseline_netns.startswith("net:["), "trusted namespace baseline unavailable")
        code, proof_netns = docker.run(image_id, "proof", 40)
        require(code == 0, "OS control proof failed; suites forbidden")
        require(proof_netns != baseline_netns, "network namespace shared with trusted baseline")
        emit("namespace-proof", baseline=baseline_netns, sandbox=proof_netns, distinct=True)
        docker.cleanup(baseline)
        code, _ = docker.run(image_id, "watchdog", 2)
        require(code == 124, "host watchdog did not terminate bounded descendant proof")
        failed = False
        for selected in PHASES if phase == "all" else (phase,):
            code, _ = docker.run(image_id, "suite", seconds, selected)
            failed = failed or code != 0
        return 1 if failed else 0
    finally:
        errors = []
        for name in tuple(docker.active):
            try:
                docker.cleanup(name)
            except Exception as error:
                errors.append(str(error))
        require(not errors, "container cleanup failed: " + "; ".join(errors))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--host", action="store_true")
    parser.add_argument("--self-check", action="store_true")
    parser.add_argument("--container", choices=("proof", "baseline", "watchdog", "suite"))
    parser.add_argument("--phase", choices=(*PHASES, "all"), default="all")
    parser.add_argument("--seconds", type=int, default=1800)
    parser.add_argument("image", nargs="?")
    args = parser.parse_args()
    if args.self_check:
        self_check()
        return 0
    if args.container:
        container_main(args.container, args.phase)
        return 0
    require(args.host and args.image, "usage: run.sh LOCAL_IMAGE [--phase all|be|fe|build-be|build-fe|a11y|roles] [--seconds N]")
    require(1 <= args.seconds <= 7200, "wall limit must be 1..7200 seconds per suite")
    return host_main(args.image, args.phase, args.seconds)


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        emit("error", message=str(error))
        sys.exit(1)
