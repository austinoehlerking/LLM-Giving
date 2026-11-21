# Local research planner

The website queues planning after the existing admin approval. A signed-in local Codex CLI claims the approved research brief, prepares structured tasks, and returns the plan. Server validation installs the plan, the first Ready research tasks, and downstream synthesis and independent review. Until a valid plan arrives, the approved brief is available by its direct URL and stays out of discovery and general volunteer pickup. If the computer is offline, the work waits.

## Setup

1. Confirm `codex login status` reports that Codex is logged in using ChatGPT. If needed, run the official `codex login` on the operator's computer.
2. Generate a dedicated 32-byte random hex token. Store the raw token only in the local runner's `LOCAL_PLANNER_TOKEN` environment. Set `LOCAL_PLANNER_TOKEN_SHA256` to the SHA-256 of that hex string in the site's private environment. The token can claim or return local plans for approved research. ChatGPT credentials are handled by the local Codex CLI.
3. From the installed site run `php bin/console.php local-planner-enable` after applying migrations. This converts existing unreserved planning tasks into private local jobs and invalidates their previous public planning packages. Existing reserved or held planning work can finish through its original path. Released, expired or cleared planning jobs are picked up by the local runner.
4. From the local checkout run `node tools/local-planner.mjs --watch`. For a single queued request use `--once`. The runner uses the LLM.Giving HTTPS origin. An isolated test may set `LOCAL_PLANNER_ORIGIN` to a loopback HTTP origin.

The runner uses only a locally configured Codex ChatGPT sign-in and makes one structured inference call per claimed job. The website selects a compatible Astra or Sol Max configuration under the current planning policy and requester requirements. A job that fails validation or has no compatible configuration is visible to the administrator on its request page. After resolving the reason, use **Retry local planning**. A request edit invalidates the previous lease. Interrupted leases expire after 15 minutes and are reclaimed at most twice.

Launch settings are recorded as runner configuration. Planning is a task decomposition step. Research results still go through the ordinary submission, evidence and moderation workflow.
