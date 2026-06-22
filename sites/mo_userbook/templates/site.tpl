<div class="site-shell site-{tag:siteId}" lang="{tag:htmlLang}">
    <header class="hero">
        <nav class="topbar">
            <a class="brand" href="{tag:homeUrl}">
                <span class="brand-mark">{tag:brandMark}</span>
                <span>{tag:siteName}</span>
            </a>
            <div class="site-switch">{tag:siteSwitch}</div>
            <div class="lang-switch">{tag:langSwitch}</div>
        </nav>
        <div class="hero-body">
            <p class="eyebrow">{tag:siteKind}</p>
            <h1>{tag:pageTitle}</h1>
            <p>{tag:subtitle}</p>
        </div>
        {tag:menu}
    </header>

    <main class="layout">
        <section class="content-card">
            {tag:contentHtml}
        </section>
        <aside class="aside-card">
            {tag:asideHtml}
        </aside>
    </main>

    <footer class="footer">
        <p>© {tag:footerYear} Log&amp;Play — package <code>{tag:siteId}</code> servi par ASAP.</p>
        <p><a href="{tag:accentUrl}">URL accentuée</a> · <a href="{tag:apiUrl}">API site</a></p>
    </footer>
</div>
