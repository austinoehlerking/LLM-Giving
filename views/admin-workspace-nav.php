<header class="admin-workspace-header">
    <div class="admin-workspace-bar">
        <a class="brand admin-workspace-brand" href="/admin" aria-label="LLM.Giving administration"><?=brand_wordmark()?></a>
        <nav aria-label="Administration">
            <a href="/admin" <?=($view==='admin'&&($adminSection??'')==='decisions')?'aria-current="page"':''?>>Decisions</a>
            <a href="/admin?tab=catalogue" <?=($view==='admin'&&($adminSection??'')==='catalogue')?'aria-current="page"':''?>>All research</a>
            <details class="admin-tools"><summary>Tools</summary>
                <div>
                    <a href="/admin/taxonomy">Categories &amp; tags</a>
                    <a href="/admin/content">Public pages</a>
                    <a href="/admin/settings">Settings</a>
                    <a href="/admin/system">System</a>
                    <a href="/admin/audit">Audit ledger</a>
                    <a href="/research">View public site</a>
                    <form method="post" action="/admin/logout"><?=csrf()?><button class="link-button">Sign out</button></form>
                </div>
            </details>
        </nav>
    </div>
</header>
