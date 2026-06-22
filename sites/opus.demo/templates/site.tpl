<div id="demo-app" lang="{tag:htmlLang}" data-site="{tag:siteId}">
    <header class="site-header">
        <div class="brand-row">
            <div>
                <p class="eyebrow">ASAP / PHP 8 Demo</p>
                <h1><a href="{tag:homeUrl}">{tag:siteName}</a></h1>
            </div>
            <div class="header-tools">
                <div class="site-switch">{tag:siteSwitch}</div>
                <div class="lang-switch">{tag:langSwitch}</div>
                <div class="date-pill">{tag:theDate}</div>
            </div>
        </div>
        {tag:menu}
    </header>

    <main class="layout">
        <section class="main-panel">
            <div class="page-heading">
                <p class="eyebrow">{tag:active}</p>
                <h2>{tag:pageTitle}</h2>
                <p>{tag:subtitle}</p>
            </div>
            {tag:contentHtml}
        </section>

        <aside class="side-panel">
            {tag:asideHtml}
        </aside>
    </main>

    <footer class="site-footer">
        <p>© {tag:footerYear} ASAP Framework — package <code>{tag:siteId}</code> servi par Log&amp;Play.</p>
        <p><a href="{tag:accentUrl}">URL accentuée</a> · <a href="{tag:apiUrl}">API site</a></p>
    </footer>
</div>
