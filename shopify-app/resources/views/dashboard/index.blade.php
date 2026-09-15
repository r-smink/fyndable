<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fyndable SEO</title>
    <meta name="shopify-api-key" content="{{ $apiKey }}">
    <script src="https://unpkg.com/@shopify/app-bridge@4"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f6f6f7; color: #202223; }
        .app { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px; border-radius: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 24px; margin: 0; }
        .header p { margin: 5px 0 0; opacity: 0.85; font-size: 14px; }
        .tabs { display: flex; gap: 2px; margin-bottom: 20px; border-bottom: 2px solid #e1e1e1; }
        .tab { padding: 12px 20px; cursor: pointer; border: none; background: none; font-size: 14px; font-weight: 500; color: #637381; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab.active { color: #379fd3; border-bottom-color: #379fd3; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
        .card h2 { font-size: 18px; margin: 0 0 15px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; }
        .stat .label { font-size: 13px; color: #637381; margin-bottom: 5px; }
        .stat .value { font-size: 28px; font-weight: 700; color: #202223; }
        .stat .value.green { color: #16a34a; }
        .stat .value.blue { color: #379fd3; }
        .stat .value.purple { color: #8f39ac; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 14px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #379fd3; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 12px; }
        .checkbox-group label { display: flex; align-items: center; gap: 6px; font-weight: 400; }
        .btn { padding: 10px 20px; background: linear-gradient(135deg, #379fd3, #8f39ac); color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
        .btn.secondary { background: #fff; color: #379fd3; border: 1px solid #379fd3; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .preview { background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-family: monospace; font-size: 12px; max-height: 400px; overflow: auto; white-space: pre-wrap; word-break: break-word; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge.green { background: #dcfce7; color: #16a34a; }
        .badge.red { background: #fee2e2; color: #dc2626; }
        .badge.gray { background: #f1f5f9; color: #64748b; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
        .alert.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
        .alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { font-weight: 600; color: #637381; }
        .loading { text-align: center; padding: 40px; color: #637381; }
    </style>
</head>
<body>
    <div class="app">
        <div class="header">
            <h1>Fyndable SEO</h1>
            <p>AI-powered SEO optimization for {{ $shopDomain }}</p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showTab('overview')">Overview</button>
            <button class="tab" onclick="showTab('llmstxt')">llms.txt</button>
            <button class="tab" onclick="showTab('products')">Products</button>
            <button class="tab" onclick="showTab('bulk')">Bulk Optimize</button>
            <button class="tab" onclick="showTab('ranktracker')">Rank Tracker</button>
            <button class="tab" onclick="showTab('license')">License</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <div class="card">
                <h2>SEO Overview</h2>
                <div id="overview-stats" class="stats">
                    <div class="loading">Loading...</div>
                </div>
            </div>
        </div>

        <!-- llms.txt Tab -->
        <div id="llmstxt" class="tab-content">
            <div class="card">
                <h2>llms.txt Generator</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Serves <code>/llms.txt</code> (markdown summary) and <code>/llms-full.txt</code> (full content)
                    for AI crawlers (ChatGPT, Claude, Gemini, Perplexity).
                </p>
                <div id="llmstxt-status"></div>
                <div id="llmstxt-settings" style="margin-top: 20px;">
                    <div class="form-group">
                        <label><input type="checkbox" id="llmstxt-enabled"> Enable /llms.txt</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="llmstxt-full-enabled"> Enable /llms-full.txt</label>
                    </div>
                    <div class="form-group">
                        <label>Content types to include:</label>
                        <div class="checkbox-group">
                            <label><input type="checkbox" id="llmstxt-products" checked> Products</label>
                            <label><input type="checkbox" id="llmstxt-collections" checked> Collections</label>
                            <label><input type="checkbox" id="llmstxt-pages" checked> Pages</label>
                            <label><input type="checkbox" id="llmstxt-blogs" checked> Blog articles</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="llmstxt-max-products">Max products</label>
                        <input type="number" id="llmstxt-max-products" value="100" min="1" max="1000">
                    </div>
                    <div class="form-group">
                        <label for="llmstxt-max-chars">Max chars per item (full version)</label>
                        <input type="number" id="llmstxt-max-chars" value="50000" min="100" max="500000" step="500">
                    </div>
                    <div class="form-group">
                        <label for="llmstxt-description">Site description (optional)</label>
                        <input type="text" id="llmstxt-description" placeholder="Defaults to shop description">
                    </div>
                    <div class="form-group">
                        <label for="llmstxt-custom">Custom sections (markdown, optional)</label>
                        <textarea id="llmstxt-custom" rows="4" placeholder="## Additional info&#10;- [About](/pages/about)"></textarea>
                    </div>
                    <button class="btn" onclick="saveLlmsTxtSettings()">Save Settings</button>
                    <button class="btn secondary" onclick="regenerateLlmsTxt()">Regenerate</button>
                    <button class="btn secondary" onclick="previewLlmsTxt()">Preview</button>
                </div>
            </div>
            <div class="card" id="llmstxt-preview-card" style="display:none;">
                <h2>llms.txt Preview</h2>
                <div id="llmstxt-preview" class="preview"></div>
            </div>
        </div>

        <!-- Products Tab -->
        <div id="products" class="tab-content">
            <div class="card">
                <h2>Product SEO</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Generate AI-powered product descriptions, meta tags, image alt text, and JSON-LD schema.
                </p>
                <div class="form-group">
                    <label for="product-id">Product ID (Shopify GID or numeric ID)</label>
                    <input type="text" id="product-id" placeholder="e.g. 123456789">
                </div>
                <button class="btn" onclick="generateDescription('long')">Generate Description</button>
                <button class="btn secondary" onclick="generateDescription('short')">Short Description</button>
                <button class="btn secondary" onclick="generateMeta()">Generate Meta Tags</button>
                <button class="btn secondary" onclick="generateSchema()">Generate Schema</button>
                <p style="margin-top: 10px; color: #637381; font-size: 13px;">
                    Note: to output the generated JSON-LD on your storefront, enable the
                    <strong>Fyndable SEO Schema</strong> app embed in the Shopify theme editor
                    (Online Store → Themes → Customize → App embeds).
                </p>
                <div id="product-result" style="margin-top: 20px;"></div>
            </div>
        </div>

        <!-- Bulk Optimize Tab -->
        <div id="bulk" class="tab-content">
            <div class="card">
                <h2>Bulk SEO Optimizer</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Optimize all products at once. Processing happens in the background.
                </p>
                <div class="form-group">
                    <label for="bulk-action">Action</label>
                    <select id="bulk-action">
                        <option value="meta">Generate meta descriptions</option>
                        <option value="titles">Optimize product titles</option>
                        <option value="alt_text">Generate image alt text</option>
                        <option value="descriptions">Rewrite product descriptions</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="bulk-limit">Max products to process</label>
                    <input type="number" id="bulk-limit" value="100" min="1" max="500">
                </div>
                <button class="btn" onclick="startBulkOptimize()">Start Bulk Optimize</button>
                <button class="btn secondary" onclick="checkBulkProgress()">Check Progress</button>
                <button class="btn secondary" onclick="cancelBulkOptimize()">Cancel</button>
                <div id="bulk-result" style="margin-top: 20px;"></div>
            </div>
        </div>

        <!-- Rank Tracker Tab -->
        <div id="ranktracker" class="tab-content">
            <div class="card">
                <h2>Rank Tracker</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Track keyword rankings in Google SERP for your product and collection pages.
                </p>
                <div id="rank-stats" class="stats" style="margin-bottom: 20px;">
                    <div class="loading">Loading...</div>
                </div>
                <button class="btn" onclick="checkRankings()">Check Rankings Now</button>
                <div style="margin-top: 20px;">
                    <h3 style="font-size: 16px; margin-bottom: 10px;">Add Keyword</h3>
                    <div class="form-group">
                        <label for="rt-keyword">Keyword</label>
                        <input type="text" id="rt-keyword" placeholder="e.g. blue sneakers">
                    </div>
                    <div class="form-group">
                        <label for="rt-url">URL to track (optional)</label>
                        <input type="text" id="rt-url" placeholder="https://your-store.com/products/...">
                    </div>
                    <button class="btn" onclick="addKeyword()">Add Keyword</button>
                </div>
                <div id="rank-keywords" style="margin-top: 20px;"></div>
            </div>
        </div>

        <!-- License Tab -->
        <div id="license" class="tab-content">
            <div class="card">
                <h2>License</h2>
                <div id="license-status" style="margin-bottom: 20px;"></div>
                <div id="license-form">
                    <div class="form-group">
                        <label for="license-key">Fyndable License Key</label>
                        <input type="text" id="license-key" placeholder="FYND-XXXX-XXXX-XXXX">
                    </div>
                    <button class="btn" onclick="activateLicense()">Activate License</button>
                </div>
                <div id="license-active" style="display:none;">
                    <button class="btn secondary" onclick="deactivateLicense()">Deactivate License</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api';
        const shopDomain = @json($shopDomain);
        const apiKey = @json($apiKey);

        // Initialize Shopify App Bridge v4 and set up authenticated fetch.
        // App Bridge automatically injects the session ID token into the
        // Authorization header for requests to the app's own domain.
        let app = null;
        let authFetch = null;
        try {
            const AppBridge = window['app-bridge'] || window.AppBridge;
            if (AppBridge && AppBridge.default) {
                const createApp = AppBridge.default;
                const host = new URLSearchParams(window.location.search).get('host') || btoa(shopDomain);
                app = createApp({ apiKey: apiKey, host: host });
                if (AppBridge.actions && AppBridge.actions.TitleBar) {
                    AppBridge.actions.TitleBar.create(app, { title: 'Fyndable SEO' });
                }
                // authenticatedFetch wraps fetch() so the Bearer token is added automatically
                if (AppBridge.utilities && AppBridge.utilities.authenticatedFetch) {
                    authFetch = AppBridge.utilities.authenticatedFetch(app);
                }
            }
        } catch (e) {
            console.warn('App Bridge init failed, falling back to native fetch', e);
        }

        // Tab switching
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');

            // Load tab data
            if (tabId === 'overview') loadOverview();
            if (tabId === 'llmstxt') loadLlmsTxtStatus();
            if (tabId === 'ranktracker') { loadRankStats(); loadKeywords(); }
            if (tabId === 'license') loadLicenseStatus();
        }

        // API helper — uses App Bridge authenticatedFetch when available so the
        // Shopify session ID token is sent as the Authorization: Bearer header.
        async function api(path, options = {}) {
            const url = `${API_BASE}${path}${path.includes('?') ? '&' : '?'}shop=${encodeURIComponent(shopDomain)}`;
            const fetchFn = authFetch || window.fetch;
            const response = await fetchFn(url, {
                ...options,
                headers: { 'Content-Type': 'application/json', ...options.headers },
            });
            return response.json();
        }

        // Overview
        async function loadOverview() {
            const data = await api('/dashboard/overview');
            if (data.error) return;
            document.getElementById('overview-stats').innerHTML = `
                <div class="stat"><div class="label">License Tier</div><div class="value purple">${data.license?.tier || 'free'}</div></div>
                <div class="stat"><div class="label">Products</div><div class="value blue">${data.product_count}</div></div>
                <div class="stat"><div class="label">Tracked Keywords</div><div class="value">${data.tracked_keywords}</div></div>
                <div class="stat"><div class="label">Top 10 Rankings</div><div class="value green">${data.top_10_keywords}</div></div>
                <div class="stat"><div class="label">llms.txt</div><div class="value ${data.llms_txt_enabled ? 'green' : 'red'}">${data.llms_txt_enabled ? 'Enabled' : 'Disabled'}</div></div>
            `;
        }

        // llms.txt
        async function loadLlmsTxtStatus() {
            const data = await api('/llmstxt/status');
            if (data.error) return;
            document.getElementById('llmstxt-enabled').checked = data.enabled;
            document.getElementById('llmstxt-full-enabled').checked = data.full_enabled;
            document.getElementById('llmstxt-products').checked = data.include_products;
            document.getElementById('llmstxt-collections').checked = data.include_collections;
            document.getElementById('llmstxt-pages').checked = data.include_pages;
            document.getElementById('llmstxt-blogs').checked = data.include_blogs;
            document.getElementById('llmstxt-max-products').value = data.max_products;
            document.getElementById('llmstxt-max-chars').value = data.full_max_chars;
            document.getElementById('llmstxt-description').value = data.description || '';
            document.getElementById('llmstxt-custom').value = data.custom_sections || '';
            document.getElementById('llmstxt-status').innerHTML = `
                <div class="alert info">
                    Summary size: ${(data.summary_size / 1024).toFixed(1)} KB |
                    Full size: ${(data.full_size / 1024).toFixed(1)} KB
                </div>
            `;
        }

        async function saveLlmsTxtSettings() {
            const data = await api('/llmstxt/settings', {
                method: 'POST',
                body: JSON.stringify({
                    enabled: document.getElementById('llmstxt-enabled').checked,
                    full_enabled: document.getElementById('llmstxt-full-enabled').checked,
                    include_products: document.getElementById('llmstxt-products').checked,
                    include_collections: document.getElementById('llmstxt-collections').checked,
                    include_pages: document.getElementById('llmstxt-pages').checked,
                    include_blogs: document.getElementById('llmstxt-blogs').checked,
                    max_products: parseInt(document.getElementById('llmstxt-max-products').value),
                    full_max_chars: parseInt(document.getElementById('llmstxt-max-chars').value),
                    description: document.getElementById('llmstxt-description').value,
                    custom_sections: document.getElementById('llmstxt-custom').value,
                }),
            });
            if (data.success) {
                alert('Settings saved!');
                loadLlmsTxtStatus();
            }
        }

        async function regenerateLlmsTxt() {
            const data = await api('/llmstxt/regenerate', { method: 'POST' });
            if (data.success) {
                alert(`Regenerated! Summary: ${(data.summary_size / 1024).toFixed(1)} KB, Full: ${(data.full_size / 1024).toFixed(1)} KB`);
                loadLlmsTxtStatus();
            }
        }

        async function previewLlmsTxt() {
            const data = await api('/llmstxt/preview');
            if (data.content) {
                document.getElementById('llmstxt-preview').textContent = data.content;
                document.getElementById('llmstxt-preview-card').style.display = 'block';
            }
        }

        // Products
        async function generateDescription(type) {
            const productId = document.getElementById('product-id').value;
            if (!productId) { alert('Enter a product ID'); return; }
            document.getElementById('product-result').innerHTML = '<div class="loading">Generating...</div>';
            const data = await api(`/products/${productId}/generate-description`, {
                method: 'POST',
                body: JSON.stringify({ type }),
            });
            if (data.success) {
                document.getElementById('product-result').innerHTML = `<div class="alert success">Generated ${type} description:</div><div class="preview">${data.description}</div>`;
            } else {
                document.getElementById('product-result').innerHTML = `<div class="alert error">Error: ${data.error || data.message || 'Unknown'}</div>`;
            }
        }

        async function generateMeta() {
            const productId = document.getElementById('product-id').value;
            if (!productId) { alert('Enter a product ID'); return; }
            document.getElementById('product-result').innerHTML = '<div class="loading">Generating...</div>';
            const data = await api(`/products/${productId}/generate-meta`, { method: 'POST' });
            if (data.success) {
                document.getElementById('product-result').innerHTML = `<div class="alert success">Meta tags generated:</div><div class="preview">Title: ${data.title}\n\nDescription: ${data.description}</div>`;
            } else {
                document.getElementById('product-result').innerHTML = `<div class="alert error">Error: ${data.error || 'Unknown'}</div>`;
            }
        }

        async function generateSchema() {
            const productId = document.getElementById('product-id').value;
            if (!productId) { alert('Enter a product ID'); return; }
            document.getElementById('product-result').innerHTML = '<div class="loading">Generating schema...</div>';
            const data = await api(`/products/${productId}/generate-schema`, { method: 'POST' });
            if (data.success) {
                document.getElementById('product-result').innerHTML = `<div class="alert success">Schema saved to metafields:</div><div class="preview">${JSON.stringify(data.schema, null, 2)}</div>`;
            } else {
                document.getElementById('product-result').innerHTML = `<div class="alert error">Error: ${data.error || 'Unknown'}</div>`;
            }
        }

        // Bulk Optimize
        async function startBulkOptimize() {
            const action = document.getElementById('bulk-action').value;
            const limit = document.getElementById('bulk-limit').value;
            const data = await api('/bulk/optimize', {
                method: 'POST',
                body: JSON.stringify({ action, limit: parseInt(limit) }),
            });
            if (data.success) {
                document.getElementById('bulk-result').innerHTML = `<div class="alert success">${data.message} (${data.total} products)</div>`;
            } else {
                document.getElementById('bulk-result').innerHTML = `<div class="alert error">Error: ${data.error || data.message || 'Unknown'}</div>`;
            }
        }

        async function checkBulkProgress() {
            const action = document.getElementById('bulk-action').value;
            const data = await api(`/bulk/progress?action=${action}`);
            if (data.status === 'running') {
                document.getElementById('bulk-result').innerHTML = `<div class="alert info">Progress: ${data.processed}/${data.total} (Succeeded: ${data.succeeded}, Failed: ${data.failed})</div>`;
            } else if (data.status === 'completed') {
                document.getElementById('bulk-result').innerHTML = `<div class="alert success">Completed: ${data.processed}/${data.total} (Succeeded: ${data.succeeded}, Failed: ${data.failed})</div>`;
            } else if (data.status === 'cancelled') {
                document.getElementById('bulk-result').innerHTML = `<div class="alert error">Cancelled</div>`;
            } else {
                document.getElementById('bulk-result').innerHTML = `<div class="alert info">${data.message || 'No job running'}</div>`;
            }
        }

        async function cancelBulkOptimize() {
            const action = document.getElementById('bulk-action').value;
            const data = await api('/bulk/cancel', { method: 'POST', body: JSON.stringify({ action }) });
            if (data.success) {
                document.getElementById('bulk-result').innerHTML = `<div class="alert info">${data.message}</div>`;
            }
        }

        // Rank Tracker
        async function loadRankStats() {
            const data = await api('/rank-tracker/stats');
            if (data.error) return;
            document.getElementById('rank-stats').innerHTML = `
                <div class="stat"><div class="label">Total Keywords</div><div class="value">${data.total_keywords}</div></div>
                <div class="stat"><div class="label">Top 3</div><div class="value green">${data.top_3}</div></div>
                <div class="stat"><div class="label">Top 10</div><div class="value green">${data.top_10}</div></div>
                <div class="stat"><div class="label">Top 100</div><div class="value blue">${data.top_100}</div></div>
                <div class="stat"><div class="label">Not Ranked</div><div class="value">${data.not_ranked}</div></div>
            `;
        }

        async function loadKeywords() {
            const data = await api('/rank-tracker/keywords');
            if (data.error || !data.keywords) return;
            const html = data.keywords.length === 0
                ? '<p style="color:#637381;">No keywords tracked yet.</p>'
                : `<table><thead><tr><th>Keyword</th><th>URL</th><th>Last Position</th><th>Best</th><th>Last Checked</th><th></th></tr></thead><tbody>
                ${data.keywords.map(k => `<tr>
                    <td>${k.keyword}</td>
                    <td>${k.url ? k.url.substring(0, 40) + '...' : '—'}</td>
                    <td>${k.last_position || '—'}</td>
                    <td>${k.best_position || '—'}</td>
                    <td>${k.last_checked_at ? new Date(k.last_checked_at).toLocaleDateString() : '—'}</td>
                    <td><button class="btn secondary" onclick="deleteKeyword(${k.id})">Delete</button></td>
                </tr>`).join('')}
                </tbody></table>`;
            document.getElementById('rank-keywords').innerHTML = html;
        }

        async function addKeyword() {
            const keyword = document.getElementById('rt-keyword').value;
            const url = document.getElementById('rt-url').value;
            if (!keyword) { alert('Enter a keyword'); return; }
            const data = await api('/rank-tracker/keywords', {
                method: 'POST',
                body: JSON.stringify({ keyword, url }),
            });
            if (data.success) {
                document.getElementById('rt-keyword').value = '';
                document.getElementById('rt-url').value = '';
                loadKeywords(); loadRankStats();
            } else {
                alert(data.error || data.message || 'Failed to add keyword');
            }
        }

        async function deleteKeyword(id) {
            const data = await api(`/rank-tracker/keywords/${id}`, { method: 'DELETE' });
            if (data.success) { loadKeywords(); loadRankStats(); }
        }

        async function checkRankings() {
            const data = await api('/rank-tracker/check', { method: 'POST' });
            if (data.success) {
                alert('Rank check started. Results will appear after processing.');
            }
        }

        // License
        async function loadLicenseStatus() {
            const data = await api('/license/status');
            if (data.error) return;
            const statusHtml = data.valid
                ? `<div class="alert success">License active — Tier: <strong>${data.tier}</strong> (Key: ${data.license_key})</div>`
                : `<div class="alert error">No active license. Enter your Fyndable license key below.</div>`;
            document.getElementById('license-status').innerHTML = statusHtml;
            if (data.has_license) {
                document.getElementById('license-form').style.display = 'none';
                document.getElementById('license-active').style.display = 'block';
            } else {
                document.getElementById('license-form').style.display = 'block';
                document.getElementById('license-active').style.display = 'none';
            }
        }

        async function activateLicense() {
            const key = document.getElementById('license-key').value;
            if (!key) { alert('Enter a license key'); return; }
            const data = await api('/license/activate', {
                method: 'POST',
                body: JSON.stringify({ license_key: key }),
            });
            if (data.success) {
                alert('License activated! Tier: ' + data.tier);
                loadLicenseStatus();
            } else {
                alert('Activation failed: ' + (data.message || data.error || 'Unknown'));
            }
        }

        async function deactivateLicense() {
            if (!confirm('Deactivate license?')) return;
            const data = await api('/license/deactivate', { method: 'POST' });
            if (data.success) loadLicenseStatus();
        }

        // Load overview on page load
        loadOverview();
    </script>
</body>
</html>
