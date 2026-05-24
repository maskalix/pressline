<?php
require_once __DIR__ . "/pl-load.php";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/session.php";

if ($_SESSION['user_level'] < $admin_level) {
    header("Location: chyba.php?err=403&msg=" . rawurlencode(t('error.no_permission_view')));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['act'] ?? '';
    $pid = $_POST['plugin_id'] ?? '';
    if ($pid && in_array($act, ['activate', 'disable', 'uninstall'], true)) {
        switch ($act) {
            case 'activate':
                PL_Plugins::activate($connection, $pid);
                $msg = t('ext.flash.activated');
                break;
            case 'disable':
                PL_Plugins::disable($connection, $pid);
                $msg = t('ext.flash.disabled');
                break;
            case 'uninstall':
                // Route through the marketplace helper so the plugin folder
                // is also removed (and the registry gets a ping when
                // telemetry is on). The DB-only PL_Plugins::uninstall left
                // the folder behind, so the plugin re-appeared on the next
                // page render and the action looked broken.
                try {
                    PL_Marketplace::uninstall($pid);
                    $msg = t('ext.flash.uninstalled');
                } catch (Throwable $e) {
                    header("Location: rozsireni.php?type=negative&message=" . urlencode($e->getMessage()));
                    exit;
                }
                break;
        }
        header("Location: rozsireni.php?type=positive&message=" . urlencode($msg ?? 'OK'));
        exit;
    }
}

$records = PL_Plugins::records($connection);

$updates = [];
try {
    $updates = PL_Marketplace::checkUpdates($records);
} catch (Throwable $_e) {}
$updateMap = [];
foreach ($updates as $u) $updateMap[$u['plugin_id']] = $u;

$tab = $_GET['tab'] ?? 'installed';
if (!in_array($tab, ['installed', 'marketplace'], true)) $tab = 'installed';

$marketplaceUrl = PL_Marketplace::baseUrl();
$sitename = t('ext.title');
?>
<!DOCTYPE html>
<html lang="<?= esc($GLOBALS['__pl_active_lang']) ?>">
<head>
    <?php include __DIR__ . "/includes/head.php"; ?>
</head>
<body>
    <?php include __DIR__ . "/includes/sidebar.php"; ?>
    <main>
        <header class="page-header">
            <div class="page-header-text">
                <h1><?= $sitename ?></h1>
                <p>
                    <?= te('ext.subtitle.installed_n', count($records)) ?><?php if (count($updates)): ?> · <?= te('ext.subtitle.updates_n', count($updates)) ?><?php endif; ?>
                </p>
            </div>
            <div class="page-header-actions">
                <button type="button" class="tool" id="check-updates-btn" onclick="MP.checkUpdates(this)">
                    <span class="iconify-inline" data-icon="ic:round-sync"></span>
                    <span><?= te('ext.check_updates') ?></span>
                </button>
                <button type="button" class="tool" onclick="document.getElementById('plugin-help-modal').style.display='block'">
                    <span class="iconify-inline" data-icon="ic:round-help-outline"></span>
                    <span><?= te('ext.help') ?></span>
                </button>
                <a href="<?= esc($marketplaceUrl) ?>" target="_blank" rel="noopener" class="tool">
                    <span class="iconify-inline" data-icon="ic:round-open-in-new"></span>
                    <span><?= te('ext.web_marketplace') ?></span>
                </a>
            </div>
        </header>

        <div class="plugin-tabs">
            <button type="button" class="plugin-tab <?= $tab === 'installed' ? 'is-active' : '' ?>" data-tab="installed" onclick="MP.switchTab('installed')">
                <span class="iconify-inline" data-icon="ic:round-extension"></span>
                <span><?= te('ext.tab.installed') ?></span>
                <span class="count-badge"><?= count($records) ?></span>
            </button>
            <button type="button" class="plugin-tab <?= $tab === 'marketplace' ? 'is-active' : '' ?>" data-tab="marketplace" onclick="MP.switchTab('marketplace')">
                <span class="iconify-inline" data-icon="ic:round-storefront"></span>
                <span><?= te('ext.tab.marketplace') ?></span>
            </button>
        </div>

        <!-- ─── Installed tab ─────────────────────────────────────────────── -->
        <section id="tab-installed" <?= $tab === 'installed' ? '' : 'hidden' ?>>
            <?php if (empty($records)): ?>
            <div class="empty-state">
                <span class="iconify-inline" data-icon="ic:round-extension-off" style="width:48px;height:48px;color:var(--text-disabled);"></span>
                <h3><?= te('ext.empty.title') ?></h3>
                <p><?= t('ext.empty.body') ?></p>
                <button type="button" class="btn-green" onclick="MP.switchTab('marketplace')">
                    <span class="iconify-inline" data-icon="ic:round-storefront"></span>
                    <span><?= te('ext.empty.open_mp') ?></span>
                </button>
            </div>
            <?php else: ?>
            <div class="plugin-grid">
                <?php foreach ($records as $id => $rec):
                    $m = $rec['manifest'];
                    $db = $rec['db'];
                    $orphan = !empty($rec['orphan']);
                    $status = $db['status'] ?? 'installed';
                    $upd = $updateMap[$id] ?? null;
                ?>
                <article class="plugin-card<?= $status === 'active' ? ' is-active' : '' ?><?= $orphan ? ' is-orphan' : '' ?>">
                    <header class="plugin-card-header">
                        <div class="plugin-card-icon">
                            <span class="iconify-inline" data-icon="ic:round-extension"></span>
                        </div>
                        <div class="plugin-card-title">
                            <h3><?= esc($m['name'] ?? $id) ?></h3>
                            <p>
                                <?php if ($m): ?>
                                    v<?= esc($m['version'] ?? '?') ?>
                                    <?php if (!empty($m['author'])): ?> · <?= esc($m['author']) ?><?php endif; ?>
                                <?php else: ?>
                                    <span class="sub"><?= te('ext.missing_folder') ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="plugin-status-badge status-<?= $orphan ? 'orphan' : $status ?>">
                            <?= te($orphan ? 'ext.status.orphan' : ($status === 'active' ? 'ext.status.active' : ($status === 'disabled' ? 'ext.status.disabled' : 'ext.status.installed'))) ?>
                        </span>
                    </header>

                    <?php if ($upd): ?>
                    <div class="plugin-update-row">
                        <span class="iconify-inline" data-icon="ic:round-system-update"></span>
                        <span><?= t('ext.update_available', esc($upd['latest_version'])) ?></span>
                        <button type="button" class="btn-green" style="margin-left:auto;" onclick="MP.update('<?= esc($id) ?>', this)">
                            <span class="iconify-inline" data-icon="ic:round-system-update"></span>
                            <span><?= te('ext.update') ?></span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php if ($m && !empty($m['description'])): ?>
                    <p class="plugin-card-desc"><?= esc($m['description']) ?></p>
                    <?php endif; ?>

                    <?php if ($m && !empty($m['permissions'])): ?>
                    <div class="plugin-card-perms">
                        <span class="aside-label"><?= te('ext.permissions') ?></span>
                        <div class="plugin-perms-list">
                            <?php foreach ($m['permissions'] as $perm): ?>
                                <span class="badge badge-muted"><?= esc($perm) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <footer class="plugin-card-actions">
                        <?php if (!$orphan && $m): ?>
                            <?php if ($status !== 'active'): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="plugin_id" value="<?= esc($id) ?>">
                                <button name="act" value="activate" class="btn-green">
                                    <span class="iconify-inline" data-icon="ic:round-power"></span>
                                    <span><?= te('ext.activate') ?></span>
                                </button>
                            </form>
                            <?php else: ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="plugin_id" value="<?= esc($id) ?>">
                                <button name="act" value="disable" class="tool">
                                    <span class="iconify-inline" data-icon="ic:round-power-off"></span>
                                    <span><?= te('ext.disable') ?></span>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if (!empty($m['homepage'])): ?>
                            <a href="<?= esc($m['homepage']) ?>" target="_blank" rel="noopener" class="tool">
                                <span class="iconify-inline" data-icon="ic:round-open-in-new"></span>
                                <span><?= te('ext.web') ?></span>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <form method="post" style="display:inline;margin-left:auto;"
                              onsubmit="return confirm(<?= htmlspecialchars(json_encode(t('ext.confirm_uninstall', $id)), ENT_QUOTES, 'UTF-8') ?>);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="plugin_id" value="<?= esc($id) ?>">
                            <button name="act" value="uninstall" class="btn-red">
                                <span class="iconify-inline" data-icon="ic:round-delete"></span>
                                <span><?= te('ext.uninstall') ?></span>
                            </button>
                        </form>
                    </footer>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- ─── Marketplace tab ───────────────────────────────────────────── -->
        <section id="tab-marketplace" <?= $tab === 'marketplace' ? '' : 'hidden' ?>>
            <div class="mp-toolbar">
                <div class="mp-search">
                    <span class="iconify-inline" data-icon="ic:round-search"></span>
                    <input type="search" id="mp-search-input" placeholder="<?= te('ext.search_ph') ?>" autocomplete="off">
                </div>
                <select id="mp-sort">
                    <option value="popular"><?= te('ext.sort.popular') ?></option>
                    <option value="recent"><?= te('ext.sort.recent') ?></option>
                    <option value="rating"><?= te('ext.sort.rating') ?></option>
                    <option value="name"><?= te('ext.sort.name') ?></option>
                </select>
            </div>
            <div class="mp-categories" id="mp-categories">
                <button type="button" class="mp-cat-chip is-active" data-cat=""><?= te('ext.cat.all') ?></button>
            </div>
            <div id="mp-grid" class="plugin-grid"></div>
            <div id="mp-status"></div>
        </section>

        <!-- ─── Help modal ────────────────────────────────────────────────── -->
        <div id="plugin-help-modal" class="modal" onclick="if(event.target===this) this.style.display='none'">
            <div class="modal-content" style="max-width:640px;">
                <span class="close" onclick="document.getElementById('plugin-help-modal').style.display='none'">
                    <span class="iconify-inline" data-icon="ic:round-close"></span>
                </span>
                <h2><?= te('ext.help.title') ?></h2>
                <p style="color:var(--text-secondary);"><?= t('ext.help.intro') ?></p>
                <pre style="padding:var(--space-3); background:var(--bg-tertiary); border-radius:var(--radius); overflow-x:auto;"><code>{
    "id": "muj-plugin",
    "name": "Můj plugin",
    "version": "1.0.0",
    "author": "Já",
    "description": "Co plugin dělá.",
    "entry": "plugin.php"
}</code></pre>
                <p style="color:var(--text-secondary); margin-top:var(--space-3);"><?= t('ext.help.hooks_intro') ?></p>
                <pre style="padding:var(--space-3); background:var(--bg-tertiary); border-radius:var(--radius); overflow-x:auto;"><code>&lt;?php
PL_Plugin::register('muj-plugin')
    -&gt;onArticleSave(function($article) {
        // tvůj kód
    })
    -&gt;addAdminMenuItem('Můj plugin', pl_url('/plugins/muj-plugin/page.php'), 'ic:round-extension');</code></pre>
                <p style="margin-top:var(--space-3); color:var(--text-secondary);">
                    <span class="aside-label"><?= te('ext.help.available_hooks') ?></span><br>
                    <code>article.beforeSave</code>, <code>article.afterSave</code>, <code>article.beforeDelete</code>,
                    <code>media.afterUpload</code>, <code>user.afterLogin</code>,
                    <code>admin.menu</code>, <code>admin.dashboardCards</code>, <code>editor.toolbarButtons</code>
                </p>
                <p style="margin-top:var(--space-3);">
                    <a href="<?= esc($marketplaceUrl) ?>" target="_blank" rel="noopener" class="btn-green">
                        <span class="iconify-inline" data-icon="ic:round-open-in-new"></span>
                        <span><?= te('ext.help.publish') ?></span>
                    </a>
                </p>
            </div>
        </div>

        <!-- ─── Detail modal ──────────────────────────────────────────────── -->
        <div id="mp-detail-modal" class="modal" onclick="if(event.target===this) MP.closeDetail()">
            <div class="modal-content" id="mp-detail-card"></div>
        </div>
    </main>
    <script>
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
    const I18N = {
        loading:        <?= json_encode(t('ext.js.loading')) ?>,
        unavailable:    <?= json_encode(t('ext.js.unavailable')) ?>,
        noResults:      <?= json_encode(t('ext.js.no_results')) ?>,
        error:          <?= json_encode(t('ext.js.error')) ?>,
        installedBadge: <?= json_encode(t('ext.js.installed_badge')) ?>,
        loadingDetail:  <?= json_encode(t('ext.js.loading_detail')) ?>,
        confirmInstall: <?= json_encode(t('ext.js.confirm_install')) ?>,
        installing:     <?= json_encode(t('ext.js.installing')) ?>,
        installOk:      <?= json_encode(t('ext.js.install_ok')) ?>,
        installFailed:  <?= json_encode(t('ext.js.install_failed')) ?>,
        retry:          <?= json_encode(t('ext.js.retry')) ?>,
        checking:       <?= json_encode(t('ext.js.checking')) ?>,
        foundN:         <?= json_encode(t('ext.js.found_n')) ?>,
        noUpdates:      <?= json_encode(t('ext.js.no_updates')) ?>,
        checkFailed:    <?= json_encode(t('ext.js.check_failed')) ?>,
        confirmUpdate:  <?= json_encode(t('ext.js.confirm_update')) ?>,
        updateOk:       <?= json_encode(t('ext.js.update_ok')) ?>,
        updateFailed:   <?= json_encode(t('ext.js.update_failed')) ?>,
        installVersion: <?= json_encode(t('ext.detail.install_v')) ?>,
        whatsNew:       <?= json_encode(t('ext.detail.whats_new')) ?>,
        reqPerms:       <?= json_encode(t('ext.detail.req_perms')) ?>,
        close:          <?= json_encode(t('ext.detail.close')) ?>,
        alreadyInst:    <?= json_encode(t('ext.detail.installed')) ?>,
        web:            <?= json_encode(t('ext.web')) ?>,
        repo:           "Repo",
    };
    function fmt(s, ...args) { let i = 0; return s.replace(/%(\d\$)?[sd]/g, () => String(args[i++])); }

    const MP = {
        installedIds: <?= json_encode(array_keys($records)) ?>,
        currentCategory: '',
        currentSort: 'popular',
        searchTimeout: null,
        loaded: false,

        switchTab(name) {
            document.querySelectorAll('.plugin-tab').forEach(b => b.classList.toggle('is-active', b.dataset.tab === name));
            document.getElementById('tab-installed').toggleAttribute('hidden', name !== 'installed');
            document.getElementById('tab-marketplace').toggleAttribute('hidden', name !== 'marketplace');
            history.replaceState(null, '', '?tab=' + name);
            if (name === 'marketplace' && !this.loaded) {
                this.loadCategories();
                this.loadGrid();
                this.loaded = true;
            }
        },

        async loadCategories() {
            try {
                const cats = await fetch('./ajax/marketplace_categories.php').then(r => r.json());
                if (!Array.isArray(cats)) return;
                const wrap = document.getElementById('mp-categories');
                wrap.querySelector('.mp-cat-chip').onclick = () => this.selectCategory('');
                cats.forEach(c => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'mp-cat-chip';
                    btn.dataset.cat = c.id;
                    btn.textContent = c.name;
                    btn.onclick = () => this.selectCategory(c.id);
                    wrap.appendChild(btn);
                });
            } catch (_e) {}
        },

        selectCategory(cat) {
            this.currentCategory = cat;
            document.querySelectorAll('.mp-cat-chip').forEach(b => b.classList.toggle('is-active', b.dataset.cat === cat));
            this.loadGrid();
        },

        async loadGrid() {
            const grid = document.getElementById('mp-grid');
            const status = document.getElementById('mp-status');
            grid.innerHTML = '';
            status.innerHTML = '<div class="mp-status-msg">' + escapeHtml(I18N.loading) + '</div>';

            const q = document.getElementById('mp-search-input').value.trim();
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (this.currentCategory) params.set('category', this.currentCategory);
            params.set('sort', this.currentSort);

            try {
                const data = await fetch('./ajax/marketplace_search.php?' + params).then(r => r.json());
                if (data.error) {
                    status.innerHTML = '<div class="mp-status-msg is-error">' + escapeHtml(fmt(I18N.unavailable, data.detail || data.error)) + '</div>';
                    return;
                }
                const items = data.items || [];
                if (!items.length) {
                    status.innerHTML = '<div class="mp-status-msg">' + escapeHtml(I18N.noResults) + '</div>';
                    return;
                }
                status.innerHTML = '';
                items.forEach(item => grid.appendChild(this.renderCard(item)));
            } catch (e) {
                status.innerHTML = '<div class="mp-status-msg is-error">' + escapeHtml(fmt(I18N.error, e.message)) + '</div>';
            }
        },

        renderCard(p) {
            const card = document.createElement('article');
            card.className = 'mp-card';
            const installed = this.installedIds.includes(p.id);
            const badge = p.review_badge || 'unverified';
            card.innerHTML = `
                <div class="mp-card-head">
                    <div class="plugin-card-icon">
                        ${p.icon_url ? `<img src="${escapeHtml(p.icon_url)}" alt="" style="width:32px;height:32px;border-radius:6px;">` : `<span class="iconify-inline" data-icon="ic:round-extension"></span>`}
                    </div>
                    <div style="flex:1; min-width:0;">
                        <h3>${escapeHtml(p.name)}</h3>
                        <div class="mp-card-author">${escapeHtml(p.author || '')}</div>
                    </div>
                    <span class="mp-badge ${badge}">${badge}</span>
                </div>
                <p class="mp-card-desc">${escapeHtml(p.description || '')}</p>
                <div class="mp-card-meta">
                    ${p.install_count ? `<span><span class="iconify-inline" data-icon="ic:round-download"></span> ${p.install_count.toLocaleString()}</span>` : ''}
                    ${p.rating_avg ? `<span><span class="iconify-inline" data-icon="ic:round-star"></span> ${p.rating_avg} (${p.rating_count})</span>` : ''}
                    ${p.latest_version ? `<span class="sub">v${escapeHtml(p.latest_version)}</span>` : ''}
                    ${installed ? `<span class="plugin-status-badge status-active" style="margin-left:auto;">${escapeHtml(I18N.installedBadge)}</span>` : ''}
                </div>
            `;
            card.onclick = () => this.openDetail(p.id);
            return card;
        },

        async openDetail(id) {
            const modal = document.getElementById('mp-detail-modal');
            const card = document.getElementById('mp-detail-card');
            card.innerHTML = '<div class="mp-status-msg">' + escapeHtml(I18N.loadingDetail) + '</div>';
            modal.style.display = 'block';
            try {
                const d = await fetch('./ajax/marketplace_detail.php?id=' + encodeURIComponent(id)).then(r => r.json());
                if (d.error) {
                    card.innerHTML = '<div class="mp-status-msg is-error">' + escapeHtml(d.detail || d.error) + '</div>';
                    return;
                }
                const installed = this.installedIds.includes(d.id);
                const latest = d.latest_version || {};
                card.innerHTML = `
                    <span class="close" onclick="MP.closeDetail()">
                        <span class="iconify-inline" data-icon="ic:round-close"></span>
                    </span>
                    <div class="mp-detail-head">
                        <div class="plugin-card-icon" style="width:56px;height:56px;">
                            ${d.icon_url ? `<img src="${escapeHtml(d.icon_url)}" alt="" style="width:48px;height:48px;border-radius:8px;">` : `<span class="iconify-inline" data-icon="ic:round-extension" style="width:32px;height:32px;"></span>`}
                        </div>
                        <div style="flex:1; min-width:0;">
                            <h2 style="margin:0; font-size:var(--text-xl);">${escapeHtml(d.name)}</h2>
                            <p style="color:var(--text-secondary); margin:var(--space-1) 0 var(--space-2);">${escapeHtml(d.description || '')}</p>
                            <div class="mp-card-meta" style="border:none; padding:0;">
                                ${latest.version ? `<span>v${escapeHtml(latest.version)}</span>` : ''}
                                ${d.license ? `<span>${escapeHtml(d.license)}</span>` : ''}
                                ${d.homepage ? `<a href="${escapeHtml(d.homepage)}" target="_blank" rel="noopener">${escapeHtml(I18N.web)}</a>` : ''}
                                ${d.repository ? `<a href="${escapeHtml(d.repository)}" target="_blank" rel="noopener">${escapeHtml(I18N.repo)}</a>` : ''}
                            </div>
                        </div>
                    </div>
                    ${(d.permissions && d.permissions.length) ? `
                        <span class="aside-label">${escapeHtml(I18N.reqPerms)}</span>
                        <div class="mp-perms-list" style="margin-top:var(--space-1);">${d.permissions.map(p => `<span class="mp-perm-pill">${escapeHtml(p)}</span>`).join('')}</div>
                    ` : ''}
                    ${d.long_description ? `<div class="mp-readme">${renderMarkdown(d.long_description)}</div>` : ''}
                    ${latest.changelog ? `
                        <span class="aside-label" style="margin-top:var(--space-3); display:block;">${escapeHtml(fmt(I18N.whatsNew, latest.version))}</span>
                        <div class="mp-readme">${renderMarkdown(latest.changelog)}</div>
                    ` : ''}
                    <div class="edit-buttons">
                        <button type="button" class="tool" onclick="MP.closeDetail()">${escapeHtml(I18N.close)}</button>
                        ${installed
                            ? `<button type="button" class="tool" disabled><span class="iconify-inline" data-icon="ic:round-check"></span> ${escapeHtml(I18N.alreadyInst)}</button>`
                            : `<button type="button" class="btn-green" onclick="MP.install('${escapeHtml(d.id)}', '${escapeHtml(latest.version)}', this)">
                                   <span class="iconify-inline" data-icon="ic:round-download"></span>
                                   <span>${escapeHtml(fmt(I18N.installVersion, latest.version))}</span>
                               </button>`}
                    </div>
                `;
            } catch (e) {
                card.innerHTML = '<div class="mp-status-msg is-error">' + escapeHtml(fmt(I18N.error, e.message)) + '</div>';
            }
        },

        closeDetail() {
            document.getElementById('mp-detail-modal').style.display = 'none';
        },

        async install(id, version, btn) {
            if (!confirm(fmt(I18N.confirmInstall, id, version))) return;
            btn.disabled = true;
            btn.innerHTML = '<span class="iconify-inline" data-icon="ic:round-hourglass-empty"></span><span>' + escapeHtml(I18N.installing) + '</span>';
            try {
                const fd = new FormData();
                fd.set('csrf_token', CSRF_TOKEN);
                fd.set('id', id);
                fd.set('version', version);
                const r = await fetch('./ajax/marketplace_install.php', { method:'POST', body: fd });
                const d = await r.json();
                if (!r.ok || d.error) throw new Error(d.detail || d.error || 'install_failed');
                location.href = 'rozsireni.php?tab=installed&type=positive&message=' + encodeURIComponent(fmt(I18N.installOk, id));
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<span class="iconify-inline" data-icon="ic:round-download"></span><span>' + escapeHtml(I18N.retry) + '</span>';
                alert(fmt(I18N.installFailed, e.message));
            }
        },

        async checkUpdates(btn) {
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="iconify-inline" data-icon="ic:round-sync"></span><span>' + escapeHtml(I18N.checking) + '</span>';
            try {
                const fd = new FormData();
                fd.set('csrf_token', CSRF_TOKEN);
                const r = await fetch('./ajax/marketplace_check_updates.php', { method:'POST', body: fd });
                const d = await r.json();
                if (!r.ok || d.error) throw new Error(d.detail || d.error || 'check_failed');
                const msg = d.count
                    ? fmt(I18N.foundN, d.count)
                    : I18N.noUpdates;
                if (d.count > 0) {
                    location.href = 'rozsireni.php?type=positive&message=' + encodeURIComponent(msg);
                } else {
                    btn.innerHTML = original;
                    btn.disabled = false;
                    alert(msg);
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = original;
                alert(fmt(I18N.checkFailed, e.message));
            }
        },

        async update(id, btn) {
            if (!confirm(fmt(I18N.confirmUpdate, id))) return;
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.set('csrf_token', CSRF_TOKEN);
                fd.set('id', id);
                const r = await fetch('./ajax/marketplace_update.php', { method:'POST', body: fd });
                const d = await r.json();
                if (!r.ok || d.error) throw new Error(d.detail || d.error || 'update_failed');
                location.href = 'rozsireni.php?type=positive&message=' + encodeURIComponent(fmt(I18N.updateOk, id));
            } catch (e) {
                btn.disabled = false;
                alert(fmt(I18N.updateFailed, e.message));
            }
        },
    };

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function renderMarkdown(s) {
        let h = escapeHtml(s);
        h = h.replace(/^### (.+)$/gm, '<h4>$1</h4>')
             .replace(/^## (.+)$/gm,  '<h3>$1</h3>')
             .replace(/^# (.+)$/gm,   '<h2>$1</h2>')
             .replace(/`([^`]+)`/g,   '<code>$1</code>')
             .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
             .replace(/\*([^*]+)\*/g, '<em>$1</em>')
             .replace(/^- (.+)$/gm,   '<li>$1</li>');
        h = h.replace(/(<li>.*?<\/li>\n?)+/gs, m => '<ul>' + m + '</ul>');
        h = h.split(/\n\n+/).map(p => /^<(h\d|ul|pre|code)/.test(p.trim()) ? p : `<p>${p}</p>`).join('\n');
        return h;
    }

    document.getElementById('mp-search-input').addEventListener('input', () => {
        clearTimeout(MP.searchTimeout);
        MP.searchTimeout = setTimeout(() => MP.loadGrid(), 350);
    });
    document.getElementById('mp-sort').addEventListener('change', e => {
        MP.currentSort = e.target.value;
        MP.loadGrid();
    });

    if (<?= json_encode($tab) ?> === 'marketplace') {
        MP.switchTab('marketplace');
    }
    </script>
    <?php include __DIR__ . "/includes/footer.php"; ?>
</body>
</html>
