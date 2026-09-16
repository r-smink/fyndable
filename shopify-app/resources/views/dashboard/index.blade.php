<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fyndable SEO</title>
    <meta name="shopify-api-key" content="{{ $apiKey }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
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
            <button class="tab" onclick="showTab('content')">Content</button>
            <button class="tab" onclick="showTab('bulk')">Bulk Optimize</button>
            <button class="tab" onclick="showTab('ranktracker')">Rank Tracker</button>
            <button class="tab" onclick="showTab('backlinks')">Backlinks</button>
            <button class="tab" onclick="showTab('aivisibility')">AI Visibility</button>
            <button class="tab" onclick="showTab('blogwriter')">Blog Writer</button>
            <button class="tab" onclick="showTab('tools')">Tools</button>
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
                    <button class="btn secondary" onclick="setupLlmsTxtRedirects()">Create root /llms.txt redirects</button>
                    <div id="llmstxt-redirect-status" style="margin-top: 10px; font-size: 13px; color: #637381;"></div>
                </div>
            </div>
            <div class="card" id="llmstxt-preview-card" style="display:none;">
                <h2>llms.txt Preview</h2>
                <div id="llmstxt-preview" class="preview"></div>
            </div>
        </div>

        <!-- Content Tab -->
        <div id="content" class="tab-content">
            <div class="card">
                <h2>Content SEO</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Generate AI-powered descriptions, meta tags, image alt text, and JSON-LD schema
                    for products, collections, pages and blog articles. Review, edit and save to Shopify.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="content-type">Content type</label>
                        <select id="content-type" onchange="onContentTypeChange()" style="min-width: 160px;">
                            <option value="product">Products</option>
                            <option value="collection">Collections</option>
                            <option value="page">Pages</option>
                            <option value="article">Blog articles</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 220px;">
                        <label for="content-search">Search</label>
                        <input type="text" id="content-search" placeholder="Search by title..." onkeydown="if(event.key==='Enter'){event.preventDefault();searchContent();}">
                    </div>
                    <button class="btn secondary" onclick="searchContent()">Search</button>
                </div>
                <div class="form-group" style="margin-top: 12px;">
                    <select id="content-list" size="6" onchange="onContentSelect()" style="width: 100%;"></select>
                </div>
                <div id="content-empty" style="color: #637381; font-size: 13px;">Select an item to load the editor.</div>

                <div id="content-editor" style="display: none;">
                    <div id="content-selected" style="margin: 15px 0; font-weight: 600;"></div>
                    <div class="form-group">
                        <label for="product-context">Additional context for AI (optional — keywords, target audience, tone)</label>
                        <textarea id="product-context" rows="2" placeholder="e.g. Target audience: outdoor enthusiasts. Tone: adventurous and eco-friendly."></textarea>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px;">
                        <button class="btn secondary" onclick="generateDescription('long')">Generate Description</button>
                        <button class="btn secondary" onclick="generateDescription('short')">Short Description</button>
                        <button class="btn secondary" onclick="generateMeta()">Generate Meta Tags</button>
                        <button class="btn secondary" onclick="generateSchema()">Generate Schema</button>
                    </div>

                    <div class="form-group">
                        <label for="edit-meta-title">Meta title</label>
                        <input type="text" id="edit-meta-title">
                    </div>
                    <div class="form-group">
                        <label for="edit-meta-desc">Meta description</label>
                        <textarea id="edit-meta-desc" rows="2"></textarea>
                    </div>
                    <button class="btn" onclick="saveMeta()">Save Meta</button>

                    <div class="form-group" style="margin-top: 20px;">
                        <label for="edit-description">Description / body</label>
                        <textarea id="edit-description" rows="8"></textarea>
                    </div>
                    <button class="btn" onclick="saveDescription()">Save Description</button>

                    <div class="form-group" id="alt-text-section" style="margin-top: 20px;">
                        <label for="edit-image">Image alt text</label>
                        <select id="edit-image" style="margin-bottom: 8px;"></select>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="edit-alt-text" placeholder="Alt text...">
                            <button class="btn secondary" onclick="generateAltText()">Generate</button>
                            <button class="btn" onclick="saveAltText()">Save</button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label for="edit-schema">JSON-LD schema (editable)</label>
                        <textarea id="edit-schema" rows="10" style="font-family: monospace; font-size: 12px;"></textarea>
                    </div>
                    <button class="btn" onclick="saveSchema()">Save Schema</button>

                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <h3 style="font-size: 15px; margin-bottom: 10px;">FAQ schema</h3>
                        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 10px;">
                            <input type="number" id="faq-count" value="5" min="2" max="10" style="width: 70px;">
                            <button class="btn secondary" onclick="generateFaq()">Generate FAQ</button>
                            <button class="btn" onclick="saveFaq()">Save FAQ Schema</button>
                        </div>
                        <div class="form-group">
                            <label for="edit-faq">FAQ items (JSON, editable — question/answer pairs)</label>
                            <textarea id="edit-faq" rows="6" style="font-family: monospace; font-size: 12px;" placeholder='[{"question": "...", "answer": "..."}]'></textarea>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <h3 style="font-size: 15px; margin-bottom: 10px;">Internal links</h3>
                        <button class="btn secondary" onclick="findLinkSuggestions()">Find link suggestions</button>
                        <div id="link-suggestions" style="margin-top: 10px;"></div>
                    </div>

                    <div id="ai-image-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: none;">
                        <h3 style="font-size: 15px; margin-bottom: 10px;">AI product image</h3>
                        <div class="form-group">
                            <label for="ai-image-prompt">Image prompt (optional — defaults to product title)</label>
                            <input type="text" id="ai-image-prompt" placeholder="e.g. product on a wooden table, warm light">
                        </div>
                        <button class="btn secondary" onclick="generateAiImage()">Generate &amp; attach image</button>
                    </div>

                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;" id="push-all-section">
                        <button class="btn" onclick="pushAll()">Push All to Shopify</button>
                        <label style="margin-left: 10px; font-size: 13px;"><input type="checkbox" id="product-overwrite"> Overwrite existing product content</label>
                    </div>

                    <p style="margin-top: 15px; color: #637381; font-size: 13px;">
                        Note: to output the generated JSON-LD on your storefront, enable the
                        <strong>Fyndable SEO Schema</strong> app embed in the Shopify theme editor
                        (Online Store → Themes → Customize → App embeds).
                    </p>
                </div>
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

        <!-- Backlinks Tab -->
        <div id="backlinks" class="tab-content">
            <div class="card">
                <h2>Backlinks</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Analyze the backlink profile of your store (or any domain) via DataForSEO.
                </p>
                <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 240px;">
                        <label for="bl-target">Domain or URL</label>
                        <input type="text" id="bl-target" placeholder="your-store.com">
                    </div>
                    <button class="btn" onclick="loadBacklinks(false)">Summary</button>
                    <button class="btn secondary" onclick="loadBacklinks(true)">Live backlinks</button>
                </div>
                <div id="backlinks-result" style="margin-top: 20px;"></div>
            </div>
        </div>

        <!-- AI Visibility Tab -->
        <div id="aivisibility" class="tab-content">
            <div class="card">
                <h2>AI / LLM Visibility</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Check whether AI assistants mention your brand, and inspect live LLM answers.
                </p>
                <h3 style="font-size: 15px; margin-bottom: 10px;">Live LLM answer</h3>
                <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 240px;">
                        <label for="llm-prompt">Question to ask</label>
                        <input type="text" id="llm-prompt" placeholder="e.g. What are the best shops for outdoor gear?">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="llm-provider">Provider</label>
                        <select id="llm-provider">
                            <option value="chatgpt">ChatGPT</option>
                            <option value="claude">Claude</option>
                            <option value="gemini">Gemini</option>
                            <option value="perplexity">Perplexity</option>
                        </select>
                    </div>
                    <button class="btn" onclick="runLlmCheck()">Ask</button>
                </div>
                <div id="llm-result" style="margin-top: 15px;"></div>
                <h3 style="font-size: 15px; margin: 25px 0 10px;">AI mentions</h3>
                <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 240px;">
                        <label for="llm-mentions-target">Brand or domain</label>
                        <input type="text" id="llm-mentions-target" placeholder="your-store.com">
                    </div>
                    <button class="btn secondary" onclick="loadLlmMentions()">Search mentions</button>
                </div>
                <div id="llm-mentions-result" style="margin-top: 15px;"></div>
                <h3 style="font-size: 15px; margin: 25px 0 10px;">Keyword data</h3>
                <div style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 240px;">
                        <label for="kw-data-input">Keywords (comma separated)</label>
                        <input type="text" id="kw-data-input" placeholder="e.g. snowboard, splitboard, snow gear">
                    </div>
                    <button class="btn secondary" onclick="loadKeywordData()">Get volumes</button>
                </div>
                <div id="kw-data-result" style="margin-top: 15px;"></div>
            </div>
        </div>

        <!-- Blog Writer Tab -->
        <div id="blogwriter" class="tab-content">
            <div class="card">
                <h2>Blog Writer</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Generate an SEO-optimized article with AI, review it, and publish it to a blog.
                </p>
                <div class="form-group">
                    <label for="bw-blog">Blog</label>
                    <select id="bw-blog" style="min-width: 220px;"></select>
                </div>
                <div class="form-group">
                    <label for="bw-topic">Topic</label>
                    <input type="text" id="bw-topic" placeholder="e.g. How to choose the right snowboard size">
                </div>
                <div class="form-group">
                    <label for="bw-keywords">Keywords (optional, comma separated)</label>
                    <input type="text" id="bw-keywords" placeholder="e.g. snowboard size chart, beginner snowboard">
                </div>
                <div class="form-group">
                    <label for="bw-words">Word count</label>
                    <input type="number" id="bw-words" value="800" min="200" max="2500" style="width: 110px;">
                </div>
                <button class="btn" onclick="generateArticle()">Generate draft</button>
                <div id="bw-draft" style="display: none; margin-top: 20px;">
                    <div class="form-group">
                        <label for="bw-title">Title</label>
                        <input type="text" id="bw-title">
                    </div>
                    <div class="form-group">
                        <label for="bw-body">Body (HTML)</label>
                        <textarea id="bw-body" rows="14" style="font-family: monospace; font-size: 12px;"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="bw-tags">Tags (optional, comma separated)</label>
                        <input type="text" id="bw-tags">
                    </div>
                    <button class="btn" onclick="createArticle(true)">Publish article</button>
                    <button class="btn secondary" onclick="createArticle(false)">Save as draft</button>
                </div>
                <div id="bw-result" style="margin-top: 20px;"></div>
            </div>
        </div>

        <!-- Tools Tab -->
        <div id="tools" class="tab-content">
            <div class="card">
                <h2>IndexNow</h2>
                <p style="margin-bottom: 15px; color: #637381; font-size: 14px;">
                    Instantly notify Bing and other IndexNow search engines when URLs change.
                    One-time setup creates a key file on your domain via a redirect to the app proxy.
                </p>
                <div id="indexnow-status" style="margin-bottom: 15px;"></div>
                <button class="btn secondary" onclick="setupIndexNow()">Setup key redirect</button>
                <div class="form-group" style="margin-top: 20px;">
                    <label for="indexnow-urls">URLs to submit (one per line)</label>
                    <textarea id="indexnow-urls" rows="5" placeholder="https://your-store.com/products/example"></textarea>
                </div>
                <button class="btn" onclick="submitIndexNow()">Submit URLs</button>
                <div id="indexnow-result" style="margin-top: 15px;"></div>
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

        // App Bridge v4 (loaded via cdn.shopify.com/shopifycloud/app-bridge.js)
        // automatically intercepts the global fetch() function and adds the
        // Authorization: Bearer <id-token> header for requests to the app's
        // own domain. No createApp() or authenticatedFetch() needed.

        // Tab switching
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');

            // Load tab data
            if (tabId === 'overview') loadOverview();
            if (tabId === 'content' && !contentListLoaded) { contentListLoaded = true; searchContent(); }
            if (tabId === 'llmstxt') loadLlmsTxtStatus();
            if (tabId === 'ranktracker') { loadRankStats(); loadKeywords(); }
            if (tabId === 'blogwriter') loadBlogs();
            if (tabId === 'tools') loadIndexNowStatus();
            if (tabId === 'license') loadLicenseStatus();
        }

        // API helper — App Bridge v4 automatically intercepts window.fetch
        // and adds the Authorization: Bearer <id-token> header.
        async function api(path, options = {}) {
            const url = `${API_BASE}${path}${path.includes('?') ? '&' : '?'}shop=${encodeURIComponent(shopDomain)}`;
            try {
                const response = await fetch(url, {
                    ...options,
                    headers: { 'Content-Type': 'application/json', ...options.headers },
                });
                // Handle non-JSON responses (e.g. 500 HTML error pages)
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const text = await response.text();
                    return { error: 'server_error', message: `HTTP ${response.status}: ${text.substring(0, 200)}` };
                }
                return response.json();
            } catch (e) {
                return { error: 'request_failed', message: e.message };
            }
        }

        // Overview
        async function loadOverview() {
            const data = await api('/dashboard/overview');
            if (data.error) {
                document.getElementById('overview-stats').innerHTML = `<div class="alert error">Error: ${data.error} — ${data.message || ''}</div>`;
                return;
            }
            document.getElementById('overview-stats').innerHTML = `
                <div class="stat"><div class="label">License Tier</div><div class="value purple">${data.license?.tier || 'free'}</div></div>
                <div class="stat"><div class="label">Products</div><div class="value blue">${data.product_count ?? 0}</div></div>
                <div class="stat"><div class="label">Tracked Keywords</div><div class="value">${data.tracked_keywords ?? 0}</div></div>
                <div class="stat"><div class="label">Top 10 Rankings</div><div class="value green">${data.top_10_keywords ?? 0}</div></div>
                <div class="stat"><div class="label">llms.txt</div><div class="value ${data.llms_txt_enabled ? 'green' : 'red'}">${data.llms_txt_enabled ? 'Enabled' : 'Disabled'}</div></div>
            `;
            if (data.missing_scopes && data.missing_scopes.length) {
                document.getElementById('overview-stats').insertAdjacentHTML('beforeend', `
                    <div class="alert error" style="grid-column: 1 / -1;">
                        The app is missing permissions: <strong>${data.missing_scopes.join(', ')}</strong>.
                        Saving to Shopify will fail until you
                        <a href="${data.reauth_url}" target="_top" style="font-weight:600;">re-authorize the app</a>.
                    </div>
                `);
            }
        }

        // llms.txt
        async function loadLlmsTxtStatus() {
            const data = await api('/llmstxt/status');
            if (data.error) {
                document.getElementById('llmstxt-status').innerHTML = `<div class="alert error">Error: ${data.error} — ${data.message || ''}</div>`;
                return;
            }
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
            } else {
                alert('Error: ' + (data.error || data.message || 'Unknown error'));
            }
        }

        async function regenerateLlmsTxt() {
            const data = await api('/llmstxt/regenerate', { method: 'POST' });
            if (data.success) {
                alert(`Regenerated! Summary: ${(data.summary_size / 1024).toFixed(1)} KB, Full: ${(data.full_size / 1024).toFixed(1)} KB`);
                loadLlmsTxtStatus();
            } else {
                alert('Error: ' + (data.error || data.message || 'Unknown error'));
            }
        }

        async function previewLlmsTxt() {
            const data = await api('/llmstxt/preview');
            if (data.content) {
                document.getElementById('llmstxt-preview').textContent = data.content;
                document.getElementById('llmstxt-preview-card').style.display = 'block';
            } else {
                alert('Error: ' + (data.error || data.message || 'No content returned'));
            }
        }

        async function setupLlmsTxtRedirects() {
            const el = document.getElementById('llmstxt-redirect-status');
            el.textContent = 'Creating redirects...';
            const data = await api('/llmstxt/setup-redirects', { method: 'POST' });
            if (data.error) {
                el.textContent = 'Error: ' + data.error;
                return;
            }
            const lines = data.redirects.map(r => `${r.path}: ${r.status}`).join('\n');
            el.textContent = 'Redirects:\n' + lines;
        }

        // Content SEO editor
        let currentType = 'product';
        let currentId = null;
        let contentListLoaded = false;

        function contentResult(html) {
            document.getElementById('product-result').innerHTML = html;
        }

        function showApiError(data) {
            if (data.error === 'missing_scopes' && data.reauth_url) {
                contentResult(`<div class="alert error">${data.message || 'Missing permissions.'}
                    <br><a href="${data.reauth_url}" target="_top" style="font-weight:600;">Re-authorize the app</a></div>`);
                return;
            }
            contentResult(`<div class="alert error">Error: ${data.error || 'Unknown'}${data.message ? ' — ' + data.message : ''}</div>`);
        }

        function numericId() {
            return currentId ? currentId.split('/').pop() : null;
        }

        async function searchContent() {
            const search = document.getElementById('content-search').value;
            const list = document.getElementById('content-list');
            list.innerHTML = '<option>Loading...</option>';
            const data = await api(`/content/${currentType}?search=${encodeURIComponent(search)}&limit=50`);
            if (data.error) {
                list.innerHTML = '';
                showApiError(data);
                return;
            }
            if (!data.items.length) {
                list.innerHTML = '';
                contentResult('<div class="alert info">No items found.</div>');
                return;
            }
            list.innerHTML = data.items
                .map(i => `<option value="${i.id}">${i.title}${i.subtitle ? ' — ' + i.subtitle : ''}</option>`)
                .join('');
        }

        function onContentTypeChange() {
            currentType = document.getElementById('content-type').value;
            currentId = null;
            document.getElementById('content-editor').style.display = 'none';
            document.getElementById('content-empty').style.display = 'block';
            searchContent();
        }

        async function onContentSelect() {
            currentId = document.getElementById('content-list').value;
            await loadContentDetail();
        }

        async function loadContentDetail() {
            contentResult('<div class="loading">Loading item...</div>');
            const data = await api(`/content/${currentType}/${numericId()}`);
            if (data.error) {
                showApiError(data);
                return;
            }
            const item = data.item;
            document.getElementById('content-selected').textContent =
                item.title + (item.url ? ' — ' + item.url : '');
            document.getElementById('edit-meta-title').value = item.seo_title || '';
            document.getElementById('edit-meta-desc').value = item.seo_description || '';
            const descEl = document.getElementById('edit-description');
            descEl.value = item.body || '';
            descEl.dataset.format = 'html';
            document.getElementById('edit-schema').value =
                item.schema ? JSON.stringify(item.schema, null, 2) : '';
            const imgSel = document.getElementById('edit-image');
            imgSel.innerHTML = (item.images || [])
                .map(i => `<option value="${i.id}" data-url="${i.url}">${i.alt || i.url.split('/').pop()}</option>`)
                .join('');
            document.getElementById('alt-text-section').style.display = currentType === 'product' ? 'block' : 'none';
            document.getElementById('push-all-section').style.display = currentType === 'product' ? 'block' : 'none';
            document.getElementById('ai-image-section').style.display = currentType === 'product' ? 'block' : 'none';
            document.getElementById('link-suggestions').innerHTML = '';
            document.getElementById('content-editor').style.display = 'block';
            document.getElementById('content-empty').style.display = 'none';
            contentResult('');
        }

        async function generateDescription(type) {
            if (!currentId) { alert('Select an item first'); return; }
            contentResult('<div class="loading">Generating...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/generate-description`, {
                method: 'POST',
                body: JSON.stringify({ type, context: document.getElementById('product-context').value }),
            });
            if (data.success) {
                const el = document.getElementById('edit-description');
                el.value = data.description;
                el.dataset.format = 'text';
                contentResult('<div class="alert success">Description generated — review it in the field and click "Save Description".</div>');
            } else {
                showApiError(data);
            }
        }

        async function saveDescription() {
            if (!currentId) { alert('Select an item first'); return; }
            const el = document.getElementById('edit-description');
            if (!el.value.trim()) { alert('Nothing to save'); return; }
            contentResult('<div class="loading">Saving...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/save-description`, {
                method: 'POST',
                body: JSON.stringify({ description: el.value, format: el.dataset.format || 'text' }),
            });
            if (data.success) {
                contentResult('<div class="alert success">Description saved to Shopify!</div>');
            } else {
                showApiError(data);
            }
        }

        async function generateMeta() {
            if (!currentId) { alert('Select an item first'); return; }
            contentResult('<div class="loading">Generating...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/generate-meta`, {
                method: 'POST',
                body: JSON.stringify({ context: document.getElementById('product-context').value }),
            });
            if (data.success) {
                document.getElementById('edit-meta-title').value = data.title || '';
                document.getElementById('edit-meta-desc').value = data.description || '';
                contentResult('<div class="alert success">Meta tags generated — review and click "Save Meta".</div>');
            } else {
                showApiError(data);
            }
        }

        async function saveMeta() {
            if (!currentId) { alert('Select an item first'); return; }
            contentResult('<div class="loading">Saving...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/save-meta`, {
                method: 'POST',
                body: JSON.stringify({
                    title: document.getElementById('edit-meta-title').value,
                    description: document.getElementById('edit-meta-desc').value,
                }),
            });
            if (data.success) {
                contentResult('<div class="alert success">Meta tags saved to Shopify!</div>');
            } else {
                showApiError(data);
            }
        }

        async function generateAltText() {
            if (!currentId) { alert('Select an item first'); return; }
            const sel = document.getElementById('edit-image');
            const imageUrl = sel.selectedOptions[0]?.dataset.url;
            if (!imageUrl) { alert('No image selected'); return; }
            contentResult('<div class="loading">Generating alt text...</div>');
            const data = await api(`/products/${numericId()}/generate-alt-text`, {
                method: 'POST',
                body: JSON.stringify({ image_url: imageUrl }),
            });
            if (data.success) {
                document.getElementById('edit-alt-text').value = data.alt_text;
                contentResult('<div class="alert success">Alt text generated — review and click "Save".</div>');
            } else {
                showApiError(data);
            }
        }

        async function saveAltText() {
            if (!currentId) { alert('Select an item first'); return; }
            const imageId = document.getElementById('edit-image').value;
            const altText = document.getElementById('edit-alt-text').value;
            if (!imageId || !altText) { alert('Select an image and generate alt text first'); return; }
            const data = await api(`/products/${numericId()}/push-alt-text`, {
                method: 'POST',
                body: JSON.stringify({ image_id: imageId, alt_text: altText }),
            });
            if (data.success) {
                contentResult('<div class="alert success">Alt text saved to Shopify!</div>');
            } else {
                showApiError(data);
            }
        }

        async function generateSchema() {
            if (!currentId) { alert('Select an item first'); return; }
            contentResult('<div class="loading">Generating schema...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/generate-schema`, { method: 'POST' });
            if (data.success) {
                document.getElementById('edit-schema').value = JSON.stringify(data.schema, null, 2);
                contentResult('<div class="alert success">Schema generated — review/edit it and click "Save Schema".</div>');
            } else {
                showApiError(data);
            }
        }

        async function saveSchema() {
            if (!currentId) { alert('Select an item first'); return; }
            const raw = document.getElementById('edit-schema').value.trim();
            if (!raw) { alert('Generate or paste a schema first'); return; }
            let schema;
            try {
                schema = JSON.parse(raw);
            } catch (e) {
                contentResult(`<div class="alert error">Invalid JSON: ${e.message}</div>`);
                return;
            }
            contentResult('<div class="loading">Saving schema...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/save-schema`, {
                method: 'POST',
                body: JSON.stringify({ schema }),
            });
            if (data.success) {
                contentResult('<div class="alert success">Schema saved to Shopify metafield.</div>');
            } else {
                showApiError(data);
            }
        }

        async function pushAll() {
            if (!currentId || currentType !== 'product') { alert('Select a product first'); return; }
            if (!document.getElementById('product-overwrite').checked) {
                alert('Please check "Overwrite existing product content" to confirm.');
                return;
            }
            const descEl = document.getElementById('edit-description');
            let schema = null;
            const rawSchema = document.getElementById('edit-schema').value.trim();
            if (rawSchema) {
                try {
                    schema = JSON.parse(rawSchema);
                } catch (e) {
                    contentResult(`<div class="alert error">Invalid schema JSON: ${e.message}</div>`);
                    return;
                }
            }
            const data = await api(`/products/${numericId()}/push-all`, {
                method: 'POST',
                body: JSON.stringify({
                    description: descEl.value,
                    description_format: descEl.dataset.format || 'text',
                    meta_title: document.getElementById('edit-meta-title').value,
                    meta_description: document.getElementById('edit-meta-desc').value,
                    image_id: document.getElementById('edit-image').value,
                    alt_text: document.getElementById('edit-alt-text').value,
                    schema,
                    overwrite: true,
                }),
            });
            if (data.success) {
                contentResult(`<div class="alert success">Pushed to Shopify: ${JSON.stringify(data.saved)}</div>`);
            } else {
                showApiError(data);
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

        // FAQ schema
        async function generateFaq() {
            if (!currentId) { alert('Select an item first'); return; }
            contentResult('<div class="loading">Generating FAQ...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/generate-faq`, {
                method: 'POST',
                body: JSON.stringify({
                    count: parseInt(document.getElementById('faq-count').value) || 5,
                    context: document.getElementById('product-context').value,
                }),
            });
            if (data.success) {
                document.getElementById('edit-faq').value = JSON.stringify(data.faq, null, 2);
                document.getElementById('edit-faq').dataset.schema = JSON.stringify(data.schema);
                contentResult('<div class="alert success">FAQ generated — review and click "Save FAQ Schema".</div>');
            } else {
                showApiError(data);
            }
        }

        async function saveFaq() {
            if (!currentId) { alert('Select an item first'); return; }
            let pairs;
            try {
                pairs = JSON.parse(document.getElementById('edit-faq').value);
            } catch (e) {
                alert('FAQ JSON is invalid');
                return;
            }
            const schema = {
                '@@context': 'https://schema.org/',
                '@@type': 'FAQPage',
                'mainEntity': (Array.isArray(pairs) ? pairs : []).map(p => ({
                    '@@type': 'Question',
                    'name': p.question,
                    'acceptedAnswer': { '@@type': 'Answer', 'text': p.answer },
                })),
            };
            contentResult('<div class="loading">Saving...</div>');
            const data = await api(`/content/${currentType}/${numericId()}/save-faq`, {
                method: 'POST',
                body: JSON.stringify({ schema }),
            });
            if (data.success) {
                contentResult('<div class="alert success">FAQ schema saved to Shopify!</div>');
            } else {
                showApiError(data);
            }
        }

        // Internal links
        async function findLinkSuggestions() {
            if (!currentId) { alert('Select an item first'); return; }
            const box = document.getElementById('link-suggestions');
            box.innerHTML = '<div class="loading">Scanning content...</div>';
            const data = await api(`/content/${currentType}/${numericId()}/link-suggestions`, { method: 'POST' });
            if (data.error) {
                showApiError(data);
                box.innerHTML = '';
                return;
            }
            if (!data.suggestions.length) {
                box.innerHTML = '<p style="color:#637381;font-size:13px;">No linkable titles found in the body text.</p>';
                return;
            }
            box.innerHTML = data.suggestions.map((s, i) => `
                <div style="display:flex;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:13px;">
                    <span style="flex:1;"><strong>${escHtml(s.anchor)}</strong> → ${escHtml(s.url)} <em>(${s.target_type})</em></span>
                    <button class="btn secondary" style="padding:4px 10px;font-size:12px;" onclick="applyLink(${i})">Apply</button>
                </div>`).join('');
            window._linkSuggestions = data.suggestions;
        }

        async function applyLink(index) {
            const s = window._linkSuggestions[index];
            if (!s) return;
            const data = await api(`/content/${currentType}/${numericId()}/apply-link`, {
                method: 'POST',
                body: JSON.stringify({ anchor: s.anchor, url: s.url }),
            });
            if (data.success) {
                contentResult('<div class="alert success">Link inserted into the body content!</div>');
                window._linkSuggestions.splice(index, 1);
                document.getElementById('link-suggestions').querySelectorAll('div').forEach(el => el.remove());
                findLinkSuggestions();
            } else {
                showApiError(data);
            }
        }

        // AI product image
        async function generateAiImage() {
            if (!currentId || currentType !== 'product') { alert('Select a product first'); return; }
            contentResult('<div class="loading">Generating image (can take ~30s)...</div>');
            const data = await api(`/content/product/${numericId()}/generate-image`, {
                method: 'POST',
                body: JSON.stringify({ prompt: document.getElementById('ai-image-prompt').value }),
            });
            if (data.success) {
                contentResult(`<div class="alert success">Image generated and attached to the product!
                    <br><img src="${data.image_url}" style="max-width:200px;margin-top:8px;border-radius:6px;"></div>`);
            } else {
                showApiError(data);
            }
        }

        function escHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        // Backlinks
        async function loadBacklinks(live) {
            const target = document.getElementById('bl-target').value.trim();
            const box = document.getElementById('backlinks-result');
            box.innerHTML = '<div class="loading">Loading backlink data...</div>';
            const data = await api(`/insights/backlinks?target=${encodeURIComponent(target)}&live=${live ? 1 : 0}&limit=50`);
            if (data.error || data.success === false) {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
                return;
            }
            box.innerHTML = `<pre style="background:#f8fafc;padding:12px;border-radius:6px;font-size:12px;overflow:auto;max-height:400px;">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
        }

        // AI visibility
        async function runLlmCheck() {
            const prompt = document.getElementById('llm-prompt').value.trim();
            if (!prompt) { alert('Enter a question'); return; }
            const provider = document.getElementById('llm-provider').value;
            const box = document.getElementById('llm-result');
            box.innerHTML = '<div class="loading">Asking ' + provider + '...</div>';
            const data = await api('/insights/llm-check', {
                method: 'POST',
                body: JSON.stringify({ prompt, provider }),
            });
            if (data.error || data.success === false) {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
                return;
            }
            box.innerHTML = `<pre style="background:#f8fafc;padding:12px;border-radius:6px;font-size:12px;overflow:auto;max-height:400px;">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
        }

        async function loadLlmMentions() {
            const target = document.getElementById('llm-mentions-target').value.trim();
            const box = document.getElementById('llm-mentions-result');
            box.innerHTML = '<div class="loading">Searching AI mentions...</div>';
            const data = await api('/insights/llm-mentions', {
                method: 'POST',
                body: JSON.stringify({ action: 'search_mentions', params: { target } }),
            });
            if (data.error || data.success === false) {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
                return;
            }
            box.innerHTML = `<pre style="background:#f8fafc;padding:12px;border-radius:6px;font-size:12px;overflow:auto;max-height:400px;">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
        }

        async function loadKeywordData() {
            const raw = document.getElementById('kw-data-input').value;
            const keywords = raw.split(',').map(k => k.trim()).filter(Boolean);
            if (!keywords.length) { alert('Enter at least one keyword'); return; }
            const box = document.getElementById('kw-data-result');
            box.innerHTML = '<div class="loading">Loading keyword data...</div>';
            const data = await api('/insights/keyword-data', {
                method: 'POST',
                body: JSON.stringify({ keywords }),
            });
            if (data.error || data.success === false) {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
                return;
            }
            box.innerHTML = `<pre style="background:#f8fafc;padding:12px;border-radius:6px;font-size:12px;overflow:auto;max-height:400px;">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
        }

        // Blog writer
        async function loadBlogs() {
            const sel = document.getElementById('bw-blog');
            const data = await api('/blogs');
            if (data.error) {
                sel.innerHTML = '<option>Error loading blogs</option>';
                return;
            }
            sel.innerHTML = (data.blogs || [])
                .map(b => `<option value="${b.id.split('/').pop()}">${escHtml(b.title)}</option>`)
                .join('') || '<option>No blogs found</option>';
        }

        async function generateArticle() {
            const topic = document.getElementById('bw-topic').value.trim();
            if (!topic) { alert('Enter a topic'); return; }
            const box = document.getElementById('bw-result');
            box.innerHTML = '<div class="loading">Writing article (can take ~30s)...</div>';
            const data = await api('/articles/generate', {
                method: 'POST',
                body: JSON.stringify({
                    topic,
                    keywords: document.getElementById('bw-keywords').value,
                    word_count: parseInt(document.getElementById('bw-words').value) || 800,
                }),
            });
            if (data.success) {
                document.getElementById('bw-title').value = data.title;
                document.getElementById('bw-body').value = data.body_html;
                document.getElementById('bw-draft').style.display = 'block';
                box.innerHTML = '<div class="alert success">Draft generated — review below, then publish or save as draft.</div>';
            } else {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
            }
        }

        async function createArticle(publish) {
            const blogId = document.getElementById('bw-blog').value;
            const title = document.getElementById('bw-title').value.trim();
            const body = document.getElementById('bw-body').value;
            if (!title || !body) { alert('Title and body required'); return; }
            const box = document.getElementById('bw-result');
            box.innerHTML = '<div class="loading">Creating article...</div>';
            const data = await api(`/blogs/${blogId}/articles`, {
                method: 'POST',
                body: JSON.stringify({
                    title,
                    body_html: body,
                    publish,
                    tags: document.getElementById('bw-tags').value.split(',').map(t => t.trim()).filter(Boolean),
                }),
            });
            if (data.success) {
                box.innerHTML = `<div class="alert success">Article ${publish ? 'published' : 'saved as draft'}!</div>`;
            } else {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
            }
        }

        // IndexNow
        async function loadIndexNowStatus() {
            const data = await api('/indexnow/status');
            const box = document.getElementById('indexnow-status');
            if (data.error) {
                box.innerHTML = `<div class="alert error">Error: ${data.error}</div>`;
                return;
            }
            box.innerHTML = `<div style="font-size:13px;color:#45545f;">
                Key: <code>${data.key}</code><br>
                Key file: <code>${data.key_location}</code><br>
                → redirects to <code>${data.proxy_path}</code></div>`;
        }

        async function setupIndexNow() {
            const box = document.getElementById('indexnow-result');
            box.innerHTML = '<div class="loading">Creating redirect...</div>';
            const data = await api('/indexnow/setup', { method: 'POST' });
            if (data.success) {
                box.innerHTML = `<div class="alert success">Key redirect ${escHtml(data.redirect.status)}: ${escHtml(data.redirect.path)} → ${escHtml(data.redirect.target)}</div>`;
            } else {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
            }
        }

        async function submitIndexNow() {
            const urls = document.getElementById('indexnow-urls').value.split('\n').map(u => u.trim()).filter(Boolean);
            if (!urls.length) { alert('Enter at least one URL'); return; }
            const box = document.getElementById('indexnow-result');
            box.innerHTML = '<div class="loading">Submitting to IndexNow...</div>';
            const data = await api('/indexnow/submit', {
                method: 'POST',
                body: JSON.stringify({ urls }),
            });
            if (data.success) {
                box.innerHTML = `<div class="alert success">Submitted ${data.submitted} URL(s) to IndexNow.</div>`;
            } else {
                box.innerHTML = `<div class="alert error">Error: ${data.error || ''}${data.message ? ' — ' + data.message : ''}</div>`;
            }
        }

        // Load overview on page load
        loadOverview();
    </script>
</body>
</html>
