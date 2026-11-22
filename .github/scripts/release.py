import json
import os
import re
import subprocess
import sys


def git(*args):
    return subprocess.check_output(['git', *args], text=True).strip()


def validate(version, sha, tags, runs):
    pattern = r'v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)'
    match = re.fullmatch(pattern, version)
    if not match or not re.fullmatch(r'[0-9a-f]{40}', sha):
        raise ValueError('Require a stable vMAJOR.MINOR.PATCH and full lowercase commit SHA.')
    if version in tags:
        raise ValueError('Version already exists; never overwrite or republish it.')
    previous = [tuple(map(int, m.groups())) for tag in tags if (m := re.fullmatch(pattern, tag))]
    if previous and tuple(map(int, match.groups())) <= max(previous):
        raise ValueError('Version must exceed existing stable versions.')
    for workflow in ['be-tests.yml', 'fe-tests.yml']:
        candidates = [run for run in runs if run['path'] == f'.github/workflows/{workflow}'
                      and run['head_sha'] == sha and run['head_branch'] == 'main'
                      and run['event'] in ['push', 'workflow_dispatch']]
        latest = max(candidates, key=lambda run: (run['run_number'], run.get('run_attempt', 1)), default=None)
        if not latest or latest['status'] != 'completed' or latest['conclusion'] != 'success':
            raise ValueError(f'{workflow} must succeed on the exact main SHA; dispatch it explicitly if absent.')


def main():
    version, sha = os.environ['VERSION'], os.environ['TARGET_SHA']
    # Validate untrusted dispatch input before using it as a Git argument.
    if not re.fullmatch(r'[0-9a-f]{40}', sha):
        raise ValueError('Require a full lowercase commit SHA.')
    git('fetch', 'origin', 'main', '--tags')
    subprocess.run(['git', 'merge-base', '--is-ancestor', sha, 'origin/main'], check=True)
    repo = os.environ['GITHUB_REPOSITORY']
    raw = subprocess.check_output([
        'gh', 'api', '--paginate', '--slurp',
        f'repos/{repo}/actions/runs?head_sha={sha}&per_page=100',
    ], text=True)
    runs = [run for page in json.loads(raw) for run in page['workflow_runs']]
    validate(version, sha, git('tag', '--list').splitlines(), runs)
    print('publish=true')


if __name__ == '__main__':
    try:
        main()
    except (ValueError, subprocess.CalledProcessError) as error:
        print(f'Release blocked: {error}', file=sys.stderr)
        sys.exit(1)
