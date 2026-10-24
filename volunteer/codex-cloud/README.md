# Restricted Codex Cloud setup package

**Version 1.0.0 — blocked; no working Cloud submission route is established.**

The [setup guide](../../docs/cloud-volunteer-setup.md) records the capability gap and the preparation/acceptance procedure. The [official Cloud limitations](https://learn.chatgpt.com/docs/environments/cloud-environments#current-limitations), checked 2026-10-24, exclude computer and browser use. Do not start a volunteer session or pass its private handoff to this package while that prerequisite is unresolved.

This small package contains:

- [setup.json](setup.json): a versioned LLM.Giving configuration checklist. It is **not** an OpenAI import format and configures nothing by itself.
- [initialization.txt](initialization.txt): copyable, credential-free instructions for an owner-authorized capability audit in an actual Codex Cloud setup conversation.

No environment image, published environment, installation script, running service, runtime dependency or AI API is supplied. Environment identity, revision, actual model and reasoning effort are deliberately null until observed in a real Cloud task. The reusable package contains no session/return credentials. Its version identifies these instructions, not a tested provider environment.

The additional LLM.Giving profile is session_profile=restricted_web_v1, labelled **Restricted web research**. It allows accessible web research, reasoning and arithmetic worked directly by the model. It excludes research execution, calculators, spreadsheets, scientific software, installation, tool construction and computational reproduction. Server eligibility and instructions do not establish an operating-system sandbox or provider tool enforcement. The general volunteer route remains available independently.

Developer verification (Node 22.16+, no network/database/installation):

    node tests/cloud-volunteer-setup.mjs

This checks the package, credentials/configuration boundaries and local documentation links. It cannot prove Cloud availability, source inspection, browser operation, phone launch or an accepted result.
