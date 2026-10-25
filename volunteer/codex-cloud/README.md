# Restricted Dot volunteer setup package

**Version 2.0.0 — native browser submission verified for one synthetic task; phone start and multi-task operation untested.**

The active route uses ChatGPT Dot's own cloud browser. No repository, published Codex Cloud environment, connected personal computer, installation or API key is needed. This directory and its links retain the legacy codex-cloud name for compatibility; the former Codex Cloud environment instructions are retired for this route.

Read the [setup guide](../../docs/cloud-volunteer-setup.md), then use:

- [setup.json](setup.json): versioned LLM.Giving instruction/checklist data, not a provider import API, network configuration or sandbox.
- [initialization.txt](initialization.txt): credential-free instructions to check the actual Dot's capabilities before providing a separate authorized handoff.
- [launch-preflight.mjs](launch-preflight.mjs): a pure controller-side consistency diagnostic for native request/admission projections. It performs no I/O or research, authenticates neither record origin nor provider settings, and does not replace website policy.

The recorded [synthetic accepted result](https://llm.giving/research/qa-dot-native-cloud-browser-submission-proof-2026-10-24-dotqa20261024a01-0242a1b38a/results/cec5f4649f0446fc6b7622ddad933f97) demonstrates browser source reading, validation repair, Check without recording, explicit submission and same-receipt recovery without duplicates. It does not verify a handset launch, the multi-task restricted controller, model-derived arithmetic or Dot-to-Codex workers. Actual model/effort are unavailable, so their observed values remain null in this checklist; those values are not a submission packet.

The existing session_profile=restricted_web_v1 remains **Restricted web research**, with the same eligibility and evidence rules. It permits web research, reasoning and directly explained model arithmetic, and excludes research execution, calculators, spreadsheets, scientific software, installation, tool building and reproduction. These restrictions are instructions plus server eligibility, not provider tool sandboxing. General sessions are unchanged.

Keep all per-run session/return credentials private. Do not bake them into this package, reusable Dot settings or public evidence. Use your own or authorized resources; no purchase, installation or personal-computer permission is granted. Account availability and genuine provider approval checks still apply.

Developer verification (no provider execution, network, database or installation):

    node tests/cloud-volunteer-setup.mjs

See [official Dot setup](https://learn.chatgpt.com/docs/dots/getting-started), [its cloud computer](https://learn.chatgpt.com/docs/dots/computers-and-apps) and [controls](https://learn.chatgpt.com/docs/dots/controls). Local contract checks cannot establish phone or end-to-end session acceptance.
