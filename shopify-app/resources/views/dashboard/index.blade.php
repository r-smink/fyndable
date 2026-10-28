<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fyndable SEO</title>
    <meta name="shopify-api-key" content="{{ $apiKey }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <script>
        // Apply the stored theme before first paint to avoid a light flash in dark mode.
        try { if (localStorage.getItem('fyn-theme') === 'dark') document.documentElement.classList.add('fyn-dark'); } catch (e) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('vendor/dashicons/dashicons.css') }}">
    <style>
        /* ---- Theme tokens (light default, html.fyn-dark flips) ---- */
        :root {
            --fyn-purple: #8F39AC; --fyn-blue: #379FD3; --fyn-ink: #333333;
            --fyn-purple-tint: #F4E9F8;
            --fyn-gradient: linear-gradient(135deg, #8F39AC 0%, #379FD3 100%);
            --fyn-gradient-flat: linear-gradient(90deg, #8F39AC, #379FD3);
            --fyn-band: linear-gradient(135deg, #2A1533 0%, #132A38 100%);
            --fyn-success: #10b981; --fyn-success-light: #d1fae5; --fyn-success-ink: #047857;
            --fyn-warning: #f59e0b; --fyn-warning-light: #fef3c7; --fyn-warning-ink: #92700a;
            --fyn-danger: #ef4444; --fyn-danger-light: #fee2e2; --fyn-danger-ink: #b91c1c;
            --fyn-info: #3b82f6; --fyn-info-light: #dbeafe; --fyn-info-ink: #1d4ed8;

            --page: #f1f0f5; --card: #ffffff; --card-border: #e7e6ee; --panel: #ffffff;
            --text-h: #1a1430; --text-b: #413b52; --text-m: #746e82; --text-s: #a39dae;
            --well: #f7f6fa; --well-border: #edecf2; --row-hover: #faf7fe; --divide: #efeef4;
            --input-bg: #ffffff; --input-border: #d8d6e0; --sev-track: #e9e7f0;
            --serp-bg: #f8f8fb; --serp-title: #1a0dab; --serp-url: #0f7a37;
            --shadow: 0 1px 3px rgba(20, 14, 30, .06);
        }
        html.fyn-dark {
            --page: #120c1c; --card: #1d1630; --card-border: rgba(255,255,255,.07); --panel: #19122a;
            --text-h: #f4f1fa; --text-b: #cdc6dd; --text-m: #958da7; --text-s: #6f6784;
            --well: #211a35; --well-border: rgba(255,255,255,.07); --row-hover: rgba(143,57,172,.12); --divide: rgba(255,255,255,.07);
            --input-bg: #211a35; --input-border: rgba(255,255,255,.12); --sev-track: rgba(255,255,255,.1);
            --serp-bg: #17112a; --serp-title: #8ab4f8; --serp-url: #6ee7a8;
            color-scheme: dark;
        }

        /* ---- Base ---- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', system-ui, -apple-system, "Segoe UI", sans-serif; font-size: 14px; background: var(--page); color: var(--text-b); min-height: 100vh; }
        a { color: var(--fyn-purple); text-decoration: none; }
        a:hover { color: var(--fyn-blue); }
        code { font-family: ui-monospace, Menlo, monospace; font-size: 12px; background: var(--well); border: 1px solid var(--well-border); padding: 1px 6px; border-radius: 5px; color: var(--text-b); word-break: break-all; }
        .dashicons { width: 16px; height: 16px; font-size: 16px; line-height: 1; flex: none; }
        .fyn-scroll::-webkit-scrollbar { height: 8px; width: 8px; }
        .fyn-scroll::-webkit-scrollbar-thumb { background: rgba(143,57,172,.35); border-radius: 8px; }
        @keyframes fynFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

        /* ---- Brand band + tab bar ---- */
        .band { background: var(--fyn-band); position: relative; z-index: 100; }
        .band-inner { display: flex; align-items: center; gap: 16px; height: 60px; padding: 0 26px; max-width: 1340px; margin: 0 auto; }
        .band-logo { height: 24px; width: auto; display: block; }
        .band-label { font-size: 11px; font-weight: 600; color: rgba(255,255,255,.55); letter-spacing: .05em; text-transform: uppercase; border-left: 1px solid rgba(255,255,255,.2); padding-left: 14px; white-space: nowrap; }
        .band-spacer { flex: 1; }
        .shop-chip { display: flex; align-items: center; gap: 8px; height: 34px; padding: 0 13px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.14); border-radius: 9px; min-width: 0; }
        .shop-chip .dashicons { font-size: 15px; width: 15px; height: 15px; color: rgba(255,255,255,.7); }
        .shop-chip span:last-child { font-size: 12.5px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .theme-toggle { display: flex; align-items: center; gap: 5px; height: 34px; padding: 3px; background: rgba(0,0,0,.22); border: 1px solid rgba(255,255,255,.14); border-radius: 999px; cursor: pointer; font-family: inherit; }
        .theme-toggle span { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; font-size: 14px; color: rgba(255,255,255,.6); transition: all .2s ease; }
        html:not(.fyn-dark) .theme-toggle .tt-sun { background: rgba(255,255,255,.9); color: var(--fyn-purple); }
        html.fyn-dark .theme-toggle .tt-moon { background: rgba(255,255,255,.18); color: #fff; }
        .lic-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 13px; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); color: rgba(255,255,255,.75); font-size: 12px; font-weight: 700; white-space: nowrap; text-transform: capitalize; }
        .lic-pill .dashicons { font-size: 14px; width: 14px; height: 14px; }
        .lic-pill.active { background: rgba(16,185,129,.2); border-color: rgba(16,185,129,.35); color: #6ee7a8; }
        .tabs { max-width: 1340px; margin: 0 auto; padding: 0 18px; display: flex; gap: 2px; overflow: visible; align-items: stretch; flex-wrap: wrap; }
        .tab { display: flex; align-items: center; gap: 7px; padding: 13px 15px; border: none; background: transparent; font-family: inherit; font-size: 13px; font-weight: 600; white-space: nowrap; cursor: pointer; color: rgba(255,255,255,.62); border-bottom: 3px solid transparent; transition: all .2s ease; }
        .tab .dashicons { font-size: 15px; width: 15px; height: 15px; pointer-events: none; }
        .tab:hover { color: rgba(255,255,255,.9); }
        .tab.active { color: #fff; border-bottom-color: #fff; }

        /* Tab dropdown groups */
        .tab-group { position: relative; display: flex; align-items: stretch; }
        .tab-group-trigger { display: flex; align-items: center; gap: 7px; padding: 13px 15px; border: none; background: transparent; font-family: inherit; font-size: 13px; font-weight: 600; white-space: nowrap; cursor: pointer; color: rgba(255,255,255,.62); border-bottom: 3px solid transparent; transition: all .2s ease; }
        .tab-group-trigger .dashicons { font-size: 15px; width: 15px; height: 15px; pointer-events: none; }
        .tab-group-trigger:hover { color: rgba(255,255,255,.9); }
        .tab-group-trigger.has-active { color: #fff; border-bottom-color: #fff; }
        .tab-group-trigger .caret { font-size: 10px; margin-left: 2px; opacity: .6; pointer-events: none; }
        .tab-dropdown { display: none; position: absolute; top: 100%; left: 0; min-width: 210px; background: var(--card); border: 1px solid var(--card-border); border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.18); padding: 6px; z-index: 100; animation: fynFade .15s ease; }
        .tab-group.open .tab-dropdown { display: block; }
        .tab-dropdown .tab { width: 100%; padding: 10px 14px; border-bottom: none; border-radius: 7px; color: var(--text-b); gap: 9px; }
        .tab-dropdown .tab .dashicons { color: var(--fyn-purple); }
        .tab-dropdown .tab:hover { background: var(--well); color: var(--text-h); }
        .tab-dropdown .tab.active { background: var(--fyn-purple-tint); color: var(--fyn-purple); }
        html.fyn-dark .tab-dropdown .tab.active { background: rgba(143,57,172,.2); color: #c084fc; }

        /* App manage link in header */
        .band-manage { display: flex; align-items: center; gap: 6px; padding: 0 10px; height: 34px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); border-radius: 9px; color: rgba(255,255,255,.7); font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; transition: all .2s ease; font-family: inherit; }
        .band-manage:hover { background: rgba(255,255,255,.14); color: #fff; }
        .band-manage .dashicons { font-size: 14px; width: 14px; height: 14px; }

        /* ---- Content area ---- */
        .app { max-width: 1340px; margin: 0 auto; padding: 26px 26px 48px; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fynFade .3s ease; }
        .page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 22px; }
        .page-head h1 { font-size: 25px; font-weight: 700; letter-spacing: -.02em; color: var(--text-h); }
        .page-head p { margin-top: 6px; font-size: 13.5px; color: var(--text-m); max-width: 640px; line-height: 1.5; }
        .page-actions { display: flex; gap: 10px; }
        .grid { display: grid; gap: 18px; align-items: start; }
        .grid.side-r { grid-template-columns: minmax(0, 1fr) 360px; }
        .grid.side-r-wide { grid-template-columns: minmax(0, 1fr) 380px; }
        .grid.side-l { grid-template-columns: 340px minmax(0, 1fr); }
        .grid.content-split { grid-template-columns: 380px minmax(0, 1fr); }
        .grid.halves { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .stack > * + * { margin-top: 18px; }

        /* ---- Cards ---- */
        .card { background: var(--card); border: 1px solid var(--card-border); border-radius: 14px; box-shadow: var(--shadow); padding: 24px; margin-bottom: 18px; }
        .grid > .card, .stack > .card { margin-bottom: 0; }
        .card.flush { padding: 0; overflow: hidden; }
        .card-head { display: flex; align-items: center; gap: 9px; margin-bottom: 16px; }
        .card-head .dashicons { font-size: 18px; width: 18px; height: 18px; color: var(--fyn-purple); }
        .card-head h2, .card-head h3 { font-size: 16px; font-weight: 700; color: var(--text-h); }
        .card-head + .card-desc { margin-top: -12px; }
        .card-desc { font-size: 13px; color: var(--text-m); line-height: 1.5; margin-bottom: 18px; }
        .card-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 16px 22px; border-bottom: 1px solid var(--divide); }
        .card-bar .card-head { margin-bottom: 0; }
        .card-pad { padding: 20px 22px; }
        .card-pad:empty { display: none; }
        .section { margin-top: 22px; padding-top: 20px; border-top: 1px solid var(--divide); }
        .section h3 { display: flex; align-items: center; gap: 8px; font-size: 14.5px; font-weight: 700; color: var(--text-h); margin-bottom: 12px; }
        .section h3 .dashicons { color: var(--fyn-purple); }

        /* ---- Stat tiles ---- */
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
        .stats.cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        .stats > .loading, .stats > .alert { grid-column: 1 / -1; margin-bottom: 0; }
        .stat { position: relative; background: var(--card); border: 1px solid var(--card-border); border-radius: 13px; box-shadow: var(--shadow); padding: 18px 20px; overflow: hidden; transition: all .2s ease; }
        .stat::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--fyn-gradient-flat); }
        .stat:hover { transform: translateY(-2px); border-color: var(--fyn-purple); box-shadow: 0 6px 18px rgba(143,57,172,.2); }
        .stat .label { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 9px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--text-m); }
        .stat .label .dashicons { font-size: 17px; width: 17px; height: 17px; color: var(--fyn-purple); }
        .stat .value { font-size: 28px; font-weight: 700; letter-spacing: -.02em; line-height: 1; color: var(--text-h); text-transform: capitalize; }
        .stat .value.green { color: var(--fyn-success); }
        .stat .value.blue { color: var(--fyn-blue); }
        .stat .value.purple { color: var(--fyn-purple); }
        .stat .value.red { color: var(--fyn-danger); }
        .stat .sub { margin-top: 8px; font-size: 12px; color: var(--text-m); }

        /* ---- Forms ---- */
        .form-group { margin-bottom: 16px; }
        .form-group > label, .field-label { display: block; font-size: 12px; font-weight: 700; color: var(--text-b); margin-bottom: 7px; }
        .field-label .hint { color: var(--text-s); font-weight: 500; }
        .form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .form-row > .form-group { margin-bottom: 0; flex: 1; min-width: 200px; }
        .form-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .fyn-inp, .fyn-ta, .form-group input:not([type=checkbox]), .form-group textarea, .form-group select {
            width: 100%; border-radius: 9px; border: 2px solid var(--input-border); background: var(--input-bg); color: var(--text-b);
            font-family: inherit; font-size: 13.5px; outline: none; transition: border-color .2s ease;
        }
        .fyn-inp, .form-group input:not([type=checkbox]), .form-group select { height: 44px; padding: 0 14px; }
        .fyn-ta, .form-group textarea { min-height: 90px; padding: 11px 14px; line-height: 1.5; resize: vertical; }
        .form-group select:not([size]), select.fyn-inp { appearance: none; -webkit-appearance: none; padding-right: 36px; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 20 20'%3E%3Cpath fill='%23746e82' d='M5 6l5 5 5-5 2 1-7 7-7-7z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; }
        .fyn-inp:focus, .fyn-ta:focus, .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--fyn-purple); }
        ::placeholder { color: var(--text-s); }
        textarea.mono, .form-group textarea.mono { font-family: ui-monospace, Menlo, monospace; font-size: 12px; }
        .inline-check { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-b); cursor: pointer; }
        input[type=checkbox] { accent-color: var(--fyn-purple); width: 16px; height: 16px; }

        /* Toggle rows (checkbox rendered as a switch) */
        .switch-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 15px; border-radius: 10px; background: var(--well); border: 1px solid var(--well-border); margin-bottom: 10px; cursor: pointer; }
        .switch-row .t { font-size: 13.5px; font-weight: 600; color: var(--text-h); }
        .switch-row .s { font-size: 11.5px; color: var(--text-m); }
        .switch-row input { position: absolute; opacity: 0; pointer-events: none; }
        .switch { position: relative; width: 44px; height: 24px; border-radius: 999px; background: var(--sev-track); flex: none; transition: all .2s ease; }
        .switch::after { content: ""; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; border-radius: 50%; background: #fff; transition: all .2s ease; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
        .switch-row input:checked + .switch { background: var(--fyn-purple); }
        .switch-row input:checked + .switch::after { left: 22px; }
        .switch-row input:focus-visible + .switch { box-shadow: 0 0 0 3px rgba(143,57,172,.25); }

        /* Chip checkboxes */
        .chip-group { display: flex; flex-wrap: wrap; gap: 9px; }
        .chip-check { display: inline-flex; align-items: center; gap: 6px; padding: 6px 13px; border-radius: 999px; border: 1px solid var(--input-border); background: var(--card); color: var(--text-m); font-size: 12px; font-weight: 600; cursor: pointer; transition: all .2s ease; }
        .chip-check input { position: absolute; opacity: 0; pointer-events: none; }
        .chip-check .dashicons { font-size: 14px; width: 14px; height: 14px; visibility: hidden; }
        .chip-check:has(input:checked) { background: var(--fyn-purple-tint); border-color: transparent; color: var(--fyn-purple); }
        .chip-check:has(input:checked) .dashicons { visibility: visible; }
        .chip-check:has(input:focus-visible) { box-shadow: 0 0 0 3px rgba(143,57,172,.25); }

        /* ---- Buttons ---- */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 42px; padding: 0 18px; border-radius: 9px; border: none; background: var(--fyn-gradient); color: #fff; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: all .2s ease; box-shadow: 0 4px 14px rgba(143,57,172,.25); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(143,57,172,.35); }
        .btn.secondary { background: var(--card); color: var(--text-b); border: 2px solid var(--input-border); box-shadow: none; }
        .btn.secondary:hover { border-color: var(--fyn-purple); background: var(--well); color: var(--text-h); }
        .btn.danger { background: transparent; color: var(--fyn-danger); border: 2px solid var(--fyn-danger); box-shadow: none; }
        .btn.danger:hover { background: var(--fyn-danger-light); color: var(--fyn-danger-ink); }
        .btn.sm { height: 34px; padding: 0 14px; border-radius: 8px; font-size: 12.5px; }
        .btn.input-h { height: 44px; }
        .btn.block { width: 100%; }
        .btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: none; background: transparent; color: var(--fyn-danger); cursor: pointer; transition: all .2s ease; }
        .icon-btn:hover { background: var(--fyn-danger-light); }

        /* AI action tiles + quick actions */
        .action-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 9px; }
        .tile-btn { display: flex; align-items: center; gap: 9px; padding: 11px 12px; border-radius: 9px; border: 2px solid var(--input-border); background: var(--card); color: var(--text-b); font-family: inherit; font-size: 12.5px; font-weight: 600; text-align: left; cursor: pointer; transition: all .2s ease; }
        .tile-btn .dashicons { color: var(--fyn-purple); }
        .tile-btn:hover { border-color: var(--fyn-purple); background: var(--well); transform: translateY(-2px); }
        .quick { width: 100%; display: flex; align-items: center; gap: 12px; padding: 12px 13px; margin-bottom: 9px; border-radius: 10px; border: 2px solid var(--input-border); background: var(--card); font-family: inherit; text-align: left; cursor: pointer; transition: all .2s ease; }
        .quick:hover { border-color: var(--fyn-purple); background: var(--well); transform: translateY(-2px); }
        .quick .t { font-size: 13px; font-weight: 600; color: var(--text-h); }
        .quick .s { font-size: 11.5px; color: var(--text-m); }
        .quick .grow { flex: 1; min-width: 0; }
        .quick .dashicons-arrow-right-alt2 { font-size: 14px; width: 14px; height: 14px; color: var(--text-s); }
        .icon-chip { width: 34px; height: 34px; border-radius: 9px; background: var(--fyn-purple-tint); display: flex; align-items: center; justify-content: center; flex: none; }
        .icon-chip .dashicons { color: var(--fyn-purple); }
        .icon-chip.well { background: var(--well); border: 1px solid var(--well-border); }
        .icon-chip.lg { width: 46px; height: 46px; border-radius: 12px; }
        .icon-chip.lg .dashicons { font-size: 22px; width: 22px; height: 22px; }

        /* Content type pills */
        .pills { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .pill { display: flex; align-items: center; gap: 8px; padding: 9px 15px; border-radius: 9px; border: 2px solid var(--input-border); background: var(--card); color: var(--text-b); font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s ease; }
        .pill .dashicons { font-size: 15px; width: 15px; height: 15px; pointer-events: none; }
        .pill:hover { border-color: var(--fyn-purple); }
        .pill.active { background: var(--fyn-gradient); border-color: transparent; color: #fff; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        /* Searchable item list */
        .search-bar { display: flex; align-items: center; gap: 9px; height: 48px; padding: 0 8px 0 16px; border-bottom: 1px solid var(--divide); background: var(--well); }
        .search-bar .dashicons { font-size: 15px; width: 15px; height: 15px; color: var(--text-s); }
        .search-bar input { flex: 1; min-width: 0; height: 100%; background: transparent; border: none; outline: none; color: var(--text-b); font-family: inherit; font-size: 13px; }
        .item-list { display: block; width: 100%; min-height: 360px; border: none; outline: none; background: var(--card); color: var(--text-b); font-family: inherit; font-size: 13.5px; padding: 6px 0; }
        .item-list option { padding: 11px 16px; border-bottom: 1px solid var(--divide); white-space: normal; }
        .item-list option:checked { background: var(--row-hover); color: var(--fyn-purple); font-weight: 600; }
        .empty { padding: 28px 16px; text-align: center; color: var(--text-m); font-size: 13px; }
        .empty .dashicons { display: block; margin: 0 auto 10px; font-size: 28px; width: 28px; height: 28px; color: var(--text-s); }
        .item-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .item-head .t { font-size: 15px; font-weight: 700; color: var(--text-h); }
        .item-head .s { font-size: 12px; color: var(--text-m); word-break: break-all; }

        /* ---- Badges / alerts ---- */
        .badge { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
        .badge.green { background: var(--fyn-success-light); color: var(--fyn-success-ink); }
        .badge.red { background: var(--fyn-danger-light); color: var(--fyn-danger-ink); }
        .badge.blue { background: var(--fyn-info-light); color: var(--fyn-info-ink); }
        .badge.amber { background: var(--fyn-warning-light); color: var(--fyn-warning-ink); }
        .badge.gray { background: var(--sev-track); color: var(--text-m); }
        .alert { display: flex; align-items: flex-start; gap: 9px; padding: 11px 15px; border-radius: 9px; margin-bottom: 15px; font-size: 13px; font-weight: 600; line-height: 1.5; }
        .alert::before { font-family: dashicons; font-size: 16px; line-height: 1.3; flex: none; }
        .alert.info { background: var(--fyn-info-light); border: 1px solid rgba(59,130,246,.25); color: var(--fyn-info-ink); }
        .alert.info::before { content: "\f348"; color: var(--fyn-info); }
        .alert.error { background: var(--fyn-danger-light); border: 1px solid rgba(239,68,68,.25); color: var(--fyn-danger-ink); }
        .alert.error::before { content: "\f534"; color: var(--fyn-danger); }
        .alert.success { background: var(--fyn-success-light); border: 1px solid rgba(16,185,129,.25); color: var(--fyn-success-ink); }
        .alert.success::before { content: "\f12a"; color: var(--fyn-success); }
        .alert a { color: inherit; text-decoration: underline; }
        .alert img { display: block; }

        /* ---- Tables ---- */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 11px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text-m); border-bottom: 2px solid var(--divide); white-space: nowrap; }
        td { padding: 13px 12px; font-size: 13px; color: var(--text-b); border-bottom: 1px solid var(--divide); }
        th:first-child, td:first-child { padding-left: 22px; }
        th:last-child, td:last-child { padding-right: 22px; }
        tbody tr { transition: background .2s ease; }
        tbody tr:hover { background: var(--row-hover); }
        td.kw { font-size: 13.5px; font-weight: 600; color: var(--text-h); }
        td.num, th.num { text-align: center; }
        td.end, th.end { text-align: right; }
        td.muted { color: var(--text-m); font-size: 12.5px; }

        /* ---- Misc ---- */
        .preview, .code { margin: 0; padding: 18px; font-family: ui-monospace, Menlo, monospace; font-size: 11.5px; line-height: 1.6; color: var(--text-b); white-space: pre-wrap; word-break: break-word; background: var(--serp-bg); max-height: 520px; overflow: auto; }
        .code { border-radius: 11px; border: 1px solid var(--well-border); max-height: 400px; }
        .muted { color: var(--text-m); font-size: 13px; }
        .note { font-size: 12.5px; color: var(--text-m); line-height: 1.5; }
        .loading { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 28px; color: var(--text-m); font-size: 13px; }
        .loading::before { content: ""; width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--sev-track); border-top-color: var(--fyn-purple); animation: fynSpin .8s linear infinite; }
        @keyframes fynSpin { to { transform: rotate(360deg); } }
        .progress { height: 9px; border-radius: 999px; background: var(--sev-track); overflow: hidden; }
        .progress > div { height: 100%; background: var(--fyn-gradient-flat); transition: width .3s ease; }
        .progress-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 14px; font-weight: 700; color: var(--text-h); }
        .progress-head span:last-child { font-size: 12.5px; color: var(--fyn-purple); }
        .status-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-top: 1px solid var(--divide); }
        .status-row .grow { flex: 1; min-width: 0; }
        .status-row .t { font-size: 13px; font-weight: 600; color: var(--text-h); }
        .status-row .s { font-size: 11.5px; color: var(--text-m); word-break: break-all; }
        .link-row { display: flex; gap: 10px; align-items: center; padding: 9px 0; border-bottom: 1px solid var(--divide); font-size: 13px; }
        .link-row .grow { flex: 1; min-width: 0; word-break: break-word; }
        .link-row em { color: var(--text-s); }
        .feature { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: 13px; color: var(--text-b); }
        .feature::before { content: "✓"; color: var(--fyn-success); font-weight: 700; }
        .lic-key { display: flex; align-items: center; gap: 10px; height: 46px; padding: 0 15px; border-radius: 9px; background: var(--well); border: 1px solid var(--well-border); font-family: ui-monospace, Menlo, monospace; font-size: 13.5px; letter-spacing: 1px; color: var(--text-b); margin-bottom: 20px; }
        #license-key { font-family: ui-monospace, Menlo, monospace; letter-spacing: 1px; }
        .draft-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        #bw-draft[style*="block"] ~ .bw-empty { display: none; }

        /* ---- Responsive ---- */
        @media (max-width: 1199px) {
            .stats, .stats.cols-5 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 1023px) {
            .stats, .stats.cols-5 { grid-template-columns: minmax(0, 1fr); }
            .grid.side-r, .grid.side-r-wide, .grid.side-l, .grid.content-split, .grid.halves { grid-template-columns: minmax(0, 1fr); }
            .band-label, .shop-chip { display: none; }
        }
        @media (max-width: 600px) {
            .app { padding: 20px 16px 40px; }
            .band-inner { padding: 0 16px; }
            .form-cols, .action-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="band">
        <div class="band-inner">
            <img class="band-logo" src="{{ asset('images/fyndable-logo.png') }}" alt="Fyndable Smart SEO">
            <span class="band-label">Shopify-app</span>
            <div class="band-spacer"></div>
            <div class="shop-chip" title="{{ $shopDomain }}"><span class="dashicons dashicons-store"></span><span>{{ $shopDomain }}</span></div>
            <button type="button" class="theme-toggle" onclick="toggleTheme()" title="Toggle theme" aria-label="Toggle light/dark theme"><span class="tt-sun">☀</span><span class="tt-moon">☾</span></button>
            <span class="lic-pill" id="hdr-license"><span class="dashicons dashicons-admin-network"></span><span id="hdr-license-label">License</span></span>
            <a class="band-manage" href="https://admin.shopify.com/store/{{ str_replace('.myshopify.com', '', $shopDomain) }}/settings/apps" target="_top" title="App beheren in Shopify"><span class="dashicons dashicons-admin-generic"></span>App beheren</a>
        </div>
        <nav class="tabs fyn-scroll">
            <button class="tab active" data-tab="overview" onclick="showTab('overview')"><span class="dashicons dashicons-chart-bar"></span>Dashboard</button>

            <!-- Content group -->
            <div class="tab-group" data-tabs="content,bulk,blogwriter,calendar">
                <button class="tab-group-trigger" onclick="toggleGroup(this)"><span class="dashicons dashicons-edit"></span>Content<span class="caret">▾</span></button>
                <div class="tab-dropdown">
                    <button class="tab" data-tab="content" onclick="showTab('content')"><span class="dashicons dashicons-edit"></span>Content Optimizer</button>
                    <button class="tab" data-tab="bulk" onclick="showTab('bulk')"><span class="dashicons dashicons-yes-alt"></span>Bulk Optimizer</button>
                    <button class="tab" data-tab="blogwriter" onclick="showTab('blogwriter')"><span class="dashicons dashicons-edit-page"></span>Blog Writer</button>
                    <button class="tab" data-tab="calendar" onclick="showTab('calendar')"><span class="dashicons dashicons-calendar-alt"></span>Content Calendar</button>
                </div>
            </div>

            <!-- SEO group -->
            <div class="tab-group" data-tabs="ranktracker,kwexplorer,competitor,backlinks,markets">
                <button class="tab-group-trigger" onclick="toggleGroup(this)"><span class="dashicons dashicons-chart-line"></span>SEO<span class="caret">▾</span></button>
                <div class="tab-dropdown">
                    <button class="tab" data-tab="ranktracker" onclick="showTab('ranktracker')"><span class="dashicons dashicons-chart-line"></span>Rank Tracker</button>
                    <button class="tab" data-tab="kwexplorer" onclick="showTab('kwexplorer')"><span class="dashicons dashicons-search"></span>Keyword Explorer</button>
                    <button class="tab" data-tab="competitor" onclick="showTab('competitor')"><span class="dashicons dashicons-groups"></span>Competitors</button>
                    <button class="tab" data-tab="backlinks" onclick="showTab('backlinks')"><span class="dashicons dashicons-admin-links"></span>Backlinks</button>
                    <button class="tab" data-tab="markets" onclick="showTab('markets')"><span class="dashicons dashicons-admin-site-alt3"></span>Markets SEO</button>
                </div>
            </div>

            <!-- AI group -->
            <div class="tab-group" data-tabs="aivisibility,geoscan,aitracker">
                <button class="tab-group-trigger" onclick="toggleGroup(this)"><span class="dashicons dashicons-visibility"></span>AI<span class="caret">▾</span></button>
                <div class="tab-dropdown">
                    <button class="tab" data-tab="aivisibility" onclick="showTab('aivisibility')"><span class="dashicons dashicons-visibility"></span>AI Visibility</button>
                    <button class="tab" data-tab="geoscan" onclick="showTab('geoscan')"><span class="dashicons dashicons-welcome-view-site"></span>GEO Scan</button>
                    <button class="tab" data-tab="aitracker" onclick="showTab('aitracker')"><span class="dashicons dashicons-trending-up"></span>AI Tracker</button>
                </div>
            </div>

            <!-- Standalone tabs -->
            <button class="tab" data-tab="llmstxt" onclick="showTab('llmstxt')"><span class="dashicons dashicons-media-code"></span>llms.txt</button>
            <button class="tab" data-tab="tools" onclick="showTab('tools')"><span class="dashicons dashicons-admin-tools"></span>Tools</button>
            <button class="tab" data-tab="license" onclick="showTab('license')"><span class="dashicons dashicons-admin-network"></span>License</button>
        </nav>
    </header>

    <main class="app">

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <div class="page-head">
                <div>
                    <h1>Dashboard</h1>
                    <p>An overview of your SEO status, store setup and quick actions for {{ $shopDomain }}.</p>
                </div>
                <div class="page-actions">
                    <button class="btn" onclick="loadOverview()"><span class="dashicons dashicons-update"></span>Refresh</button>
                </div>
            </div>
            <div id="overview-stats" class="stats cols-5">
                <div class="loading">Loading...</div>
            </div>
            <div class="grid side-r">
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-chart-bar"></span><h2>Store status</h2></div>
                    <div id="overview-status"><div class="loading">Loading...</div></div>
                </div>
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-lightbulb"></span><h3>Quick actions</h3></div>
                    <button type="button" class="quick" onclick="goTab('bulk')"><div class="icon-chip"><span class="dashicons dashicons-yes-alt"></span></div><div class="grow"><div class="t">Start bulk optimization</div><div class="s">Meta, titles, alt text or descriptions</div></div><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                    <button type="button" class="quick" onclick="goTab('llmstxt')"><div class="icon-chip"><span class="dashicons dashicons-media-code"></span></div><div class="grow"><div class="t">Refresh llms.txt</div><div class="s">Regenerate what AI crawlers see</div></div><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                    <button type="button" class="quick" onclick="goTab('ranktracker')"><div class="icon-chip"><span class="dashicons dashicons-chart-line"></span></div><div class="grow"><div class="t">Check rankings</div><div class="s">Google positions for tracked keywords</div></div><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                    <button type="button" class="quick" onclick="goTab('blogwriter')"><div class="icon-chip"><span class="dashicons dashicons-edit-page"></span></div><div class="grow"><div class="t">New blog article</div><div class="s">Generate with AI</div></div><span class="dashicons dashicons-arrow-right-alt2"></span></button>
                </div>
            </div>
        </div>

        <!-- Content Tab -->
        <div id="content" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Content Optimizer</h1>
                    <p>Generate AI-powered descriptions, meta tags, image alt text and JSON-LD schema for products, collections, pages and blog articles. Review, edit and save to Shopify.</p>
                </div>
            </div>
            <div class="pills" id="content-type-pills">
                <button type="button" class="pill active" data-type="product" onclick="setContentType('product')"><span class="dashicons dashicons-products"></span>Products</button>
                <button type="button" class="pill" data-type="collection" onclick="setContentType('collection')"><span class="dashicons dashicons-category"></span>Collections</button>
                <button type="button" class="pill" data-type="page" onclick="setContentType('page')"><span class="dashicons dashicons-admin-page"></span>Pages</button>
                <button type="button" class="pill" data-type="article" onclick="setContentType('article')"><span class="dashicons dashicons-media-document"></span>Blog articles</button>
            </div>
            <label for="content-type" class="sr-only">Content type</label>
            <select id="content-type" class="sr-only" onchange="onContentTypeChange()" tabindex="-1">
                <option value="product">Products</option>
                <option value="collection">Collections</option>
                <option value="page">Pages</option>
                <option value="article">Blog articles</option>
            </select>
            <div class="grid content-split">
                <div class="card flush">
                    <div class="search-bar">
                        <span class="dashicons dashicons-search"></span>
                        <label for="content-search" class="sr-only">Search</label>
                        <input type="text" id="content-search" placeholder="Search by title..." onkeydown="if(event.key==='Enter'){event.preventDefault();searchContent();}">
                        <button class="btn secondary sm" onclick="searchContent()">Search</button>
                    </div>
                    <select id="content-list" class="item-list fyn-scroll" size="12" onchange="onContentSelect()" aria-label="Items"></select>
                </div>

                <div class="card">
                    <div id="product-result"></div>
                    <div id="content-empty" class="empty"><span class="dashicons dashicons-edit"></span>Select an item to load the editor.</div>

                    <div id="content-editor" style="display: none;">
                        <div class="item-head">
                            <div class="icon-chip lg"><span class="dashicons dashicons-products"></span></div>
                            <div><div class="t" id="content-selected"></div><div class="s">Edit, generate with AI and save to Shopify</div></div>
                        </div>

                        <div class="form-group">
                            <label for="product-context">Additional context for AI <span class="hint">· optional — keywords, target audience, tone</span></label>
                            <textarea id="product-context" rows="2" placeholder="e.g. Target audience: outdoor enthusiasts. Tone: adventurous and eco-friendly."></textarea>
                        </div>
                        <div class="field-label">AI actions</div>
                        <div class="action-grid" style="margin-bottom: 20px;">
                            <button class="tile-btn" onclick="generateMeta()"><span class="dashicons dashicons-tag"></span>Generate Meta Tags</button>
                            <button class="tile-btn" onclick="generateDescription('long')"><span class="dashicons dashicons-edit"></span>Generate Description</button>
                            <button class="tile-btn" onclick="generateDescription('short')"><span class="dashicons dashicons-editor-paragraph"></span>Short Description</button>
                            <button class="tile-btn" onclick="generateSchema()"><span class="dashicons dashicons-media-code"></span>Generate Schema</button>
                        </div>

                        <div class="form-group">
                            <label for="edit-meta-title">Meta title</label>
                            <input type="text" id="edit-meta-title">
                        </div>
                        <div class="form-group">
                            <label for="edit-meta-desc">Meta description</label>
                            <textarea id="edit-meta-desc" rows="2"></textarea>
                        </div>
                        <button class="btn" onclick="saveMeta()"><span class="dashicons dashicons-upload"></span>Save Meta</button>

                        <div class="section">
                            <div class="form-group">
                                <label for="edit-description">Description / body</label>
                                <textarea id="edit-description" rows="8"></textarea>
                            </div>
                            <button class="btn" onclick="saveDescription()"><span class="dashicons dashicons-upload"></span>Save Description</button>
                        </div>

                        <div class="section" id="alt-text-section">
                            <h3><span class="dashicons dashicons-format-image"></span>Image alt text</h3>
                            <div class="form-group">
                                <label for="edit-image">Image</label>
                                <select id="edit-image"></select>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit-alt-text">Alt text</label>
                                    <input type="text" id="edit-alt-text" placeholder="Alt text...">
                                </div>
                                <button class="btn secondary input-h" onclick="generateAltText()">Generate</button>
                                <button class="btn input-h" onclick="saveAltText()">Save</button>
                            </div>
                        </div>

                        <div class="section">
                            <h3><span class="dashicons dashicons-media-code"></span>JSON-LD schema</h3>
                            <div class="form-group">
                                <label for="edit-schema">Schema <span class="hint">· editable</span></label>
                                <textarea id="edit-schema" class="mono" rows="10"></textarea>
                            </div>
                            <button class="btn" onclick="saveSchema()"><span class="dashicons dashicons-upload"></span>Save Schema</button>
                        </div>

                        <div class="section">
                            <h3><span class="dashicons dashicons-editor-help"></span>FAQ schema</h3>
                            <div class="btn-row" style="margin-bottom: 14px;">
                                <label for="faq-count" class="sr-only">Number of questions</label>
                                <input type="number" id="faq-count" class="fyn-inp" value="5" min="2" max="10" style="width: 80px;">
                                <button class="btn secondary" onclick="generateFaq()">Generate FAQ</button>
                                <button class="btn" onclick="saveFaq()">Save FAQ Schema</button>
                            </div>
                            <div class="form-group">
                                <label for="edit-faq">FAQ items <span class="hint">· JSON, editable — question/answer pairs</span></label>
                                <textarea id="edit-faq" class="mono" rows="6" placeholder='[{"question": "...", "answer": "..."}]'></textarea>
                            </div>
                        </div>

                        <div class="section">
                            <h3><span class="dashicons dashicons-admin-links"></span>Internal links</h3>
                            <button class="btn secondary" onclick="findLinkSuggestions()">Find link suggestions</button>
                            <div id="link-suggestions" style="margin-top: 10px;"></div>
                        </div>

                        <div class="section" id="ai-image-section" style="display: none;">
                            <h3><span class="dashicons dashicons-format-image"></span>AI product image</h3>
                            <div class="form-group">
                                <label for="ai-image-prompt">Image prompt <span class="hint">· optional — defaults to product title</span></label>
                                <input type="text" id="ai-image-prompt" placeholder="e.g. product on a wooden table, warm light">
                            </div>
                            <button class="btn secondary" onclick="generateAiImage()">Generate &amp; attach image</button>
                        </div>

                        <div class="section" id="push-all-section">
                            <div class="btn-row">
                                <button class="btn" onclick="pushAll()"><span class="dashicons dashicons-upload"></span>Push All to Shopify</button>
                                <label class="inline-check"><input type="checkbox" id="product-overwrite"> Overwrite existing product content</label>
                            </div>
                        </div>

                        <p class="note" style="margin-top: 18px;">
                            Note: to output the generated JSON-LD on your storefront, enable the
                            <strong>Fyndable SEO Schema</strong> app embed in the Shopify theme editor
                            (Online Store → Themes → Customize → App embeds).
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- llms.txt Tab -->
        <div id="llmstxt" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>llms.txt</h1>
                    <p>Help AI assistants (ChatGPT, Claude, Gemini, Perplexity) understand your store through a structured llms.txt file.</p>
                </div>
            </div>
            <div class="grid side-r-wide">
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-media-code"></span><h2>llms.txt generator</h2></div>
                    <p class="card-desc">Serves <code>/llms.txt</code> (markdown summary) and <code>/llms-full.txt</code> (full content) for AI crawlers.</p>
                    <div id="llmstxt-status"></div>
                    <div id="llmstxt-settings">
                        <label class="switch-row">
                            <div><div class="t">Enable /llms.txt</div><div class="s">Markdown summary of your store</div></div>
                            <input type="checkbox" id="llmstxt-enabled"><span class="switch"></span>
                        </label>
                        <label class="switch-row">
                            <div><div class="t">Enable /llms-full.txt</div><div class="s">Full content for AI crawlers</div></div>
                            <input type="checkbox" id="llmstxt-full-enabled"><span class="switch"></span>
                        </label>
                        <div class="form-group" style="margin-top: 18px;">
                            <div class="field-label">Content types to include</div>
                            <div class="chip-group">
                                <label class="chip-check"><input type="checkbox" id="llmstxt-products" checked><span class="dashicons dashicons-yes"></span>Products</label>
                                <label class="chip-check"><input type="checkbox" id="llmstxt-collections" checked><span class="dashicons dashicons-yes"></span>Collections</label>
                                <label class="chip-check"><input type="checkbox" id="llmstxt-pages" checked><span class="dashicons dashicons-yes"></span>Pages</label>
                                <label class="chip-check"><input type="checkbox" id="llmstxt-blogs" checked><span class="dashicons dashicons-yes"></span>Blog articles</label>
                            </div>
                        </div>
                        <div class="form-cols">
                            <div class="form-group">
                                <label for="llmstxt-max-products">Max products</label>
                                <input type="number" id="llmstxt-max-products" value="100" min="1" max="1000">
                            </div>
                            <div class="form-group">
                                <label for="llmstxt-max-chars">Max chars per item (full)</label>
                                <input type="number" id="llmstxt-max-chars" value="50000" min="100" max="500000" step="500">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="llmstxt-description">Site description <span class="hint">· optional</span></label>
                            <input type="text" id="llmstxt-description" placeholder="Defaults to shop description">
                        </div>
                        <div class="form-group">
                            <label for="llmstxt-custom">Custom sections <span class="hint">· markdown, optional</span></label>
                            <textarea id="llmstxt-custom" class="mono" rows="4" placeholder="## Additional info&#10;- [About](/pages/about)"></textarea>
                        </div>
                        <div class="btn-row">
                            <button class="btn" onclick="saveLlmsTxtSettings()">Save Settings</button>
                            <button class="btn secondary" onclick="regenerateLlmsTxt()">Regenerate</button>
                            <button class="btn secondary" onclick="previewLlmsTxt()">Preview</button>
                            <button class="btn secondary" onclick="setupLlmsTxtRedirects()">Create root /llms.txt redirects</button>
                        </div>
                        <div id="llmstxt-redirect-status" class="note" style="margin-top: 10px; white-space: pre-line;"></div>
                    </div>
                </div>
                <div class="card flush" id="llmstxt-preview-card">
                    <div class="card-bar">
                        <div class="card-head"><span class="dashicons dashicons-visibility"></span><h3>Preview — /llms.txt</h3></div>
                    </div>
                    <div id="llmstxt-preview" class="preview fyn-scroll">Click "Preview" to load the generated llms.txt.</div>
                </div>
            </div>
        </div>

        <!-- Bulk Optimize Tab -->
        <div id="bulk" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Bulk Optimizer</h1>
                    <p>Optimize all products at once. Processing happens in the background.</p>
                </div>
            </div>
            <div class="grid side-l">
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-yes-alt"></span><h3>New run</h3></div>
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
                    <button class="btn block" style="margin-bottom: 10px;" onclick="startBulkOptimize()">Start Bulk Optimize</button>
                    <div class="btn-row" style="flex-wrap: nowrap;">
                        <button class="btn secondary sm" style="flex: 1;" onclick="checkBulkProgress()">Check Progress</button>
                        <button class="btn danger sm" style="flex: 1;" onclick="cancelBulkOptimize()">Cancel</button>
                    </div>
                </div>
                <div class="card flush">
                    <div class="card-bar">
                        <div class="card-head"><span class="dashicons dashicons-update"></span><h3>Run status</h3></div>
                    </div>
                    <div class="card-pad">
                        <div id="bulk-result"><div class="empty"><span class="dashicons dashicons-clock"></span>Start a run or check progress for the selected action.</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rank Tracker Tab -->
        <div id="ranktracker" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Rank Tracker</h1>
                    <p>Track keyword rankings in Google SERP for your product and collection pages.</p>
                </div>
                <div class="page-actions">
                    <button class="btn" onclick="checkRankings()"><span class="dashicons dashicons-update"></span>Check Rankings Now</button>
                </div>
            </div>
            <div id="rank-stats" class="stats cols-5">
                <div class="loading">Loading...</div>
            </div>
            <div class="card" style="padding: 18px 22px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="rt-keyword">Keyword</label>
                        <input type="text" id="rt-keyword" placeholder="e.g. blue sneakers">
                    </div>
                    <div class="form-group">
                        <label for="rt-url">URL to track <span class="hint">· optional</span></label>
                        <input type="text" id="rt-url" placeholder="https://your-store.com/products/...">
                    </div>
                    <button class="btn input-h" onclick="addKeyword()">+ Add Keyword</button>
                </div>
            </div>
            <div class="card flush">
                <div class="card-bar">
                    <div class="card-head"><span class="dashicons dashicons-chart-line"></span><h3>Tracked keywords</h3></div>
                </div>
                <div id="rank-keywords"></div>
            </div>
        </div>

        <!-- Backlinks Tab -->
        <div id="backlinks" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Backlinks</h1>
                    <p>Analyze the backlink profile of your store (or any domain) via DataForSEO.</p>
                </div>
            </div>
            <div class="card flush">
                <div class="card-bar">
                    <div class="card-head"><span class="dashicons dashicons-admin-links"></span><h3>Backlink profile</h3></div>
                </div>
                <div class="card-pad">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bl-target">Domain or URL</label>
                            <input type="text" id="bl-target" placeholder="your-store.com">
                        </div>
                        <button class="btn input-h" onclick="loadBacklinks(false)">Summary</button>
                        <button class="btn secondary input-h" onclick="loadBacklinks(true)">Live backlinks</button>
                    </div>
                </div>
                <div id="backlinks-result" class="card-pad" style="padding-top: 0;"></div>
            </div>
        </div>

        <!-- AI Visibility Tab -->
        <div id="aivisibility" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>AI Visibility</h1>
                    <p>Check whether AI assistants mention your brand, and inspect live LLM answers.</p>
                </div>
            </div>
            <div class="grid side-r">
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-format-chat"></span><h2>Live LLM answer</h2></div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="llm-prompt">Question to ask</label>
                                <input type="text" id="llm-prompt" placeholder="e.g. What are the best shops for outdoor gear?">
                            </div>
                            <div class="form-group" style="flex: none; min-width: 150px;">
                                <label for="llm-provider">Provider</label>
                                <select id="llm-provider">
                                    <option value="chatgpt">ChatGPT</option>
                                    <option value="claude">Claude</option>
                                    <option value="gemini">Gemini</option>
                                    <option value="perplexity">Perplexity</option>
                                </select>
                            </div>
                            <button class="btn input-h" onclick="runLlmCheck()">Ask</button>
                        </div>
                        <div id="llm-result" style="margin-top: 15px;"></div>
                    </div>
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-chart-area"></span><h3>Keyword data</h3></div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="kw-data-input">Keywords <span class="hint">· comma separated</span></label>
                                <input type="text" id="kw-data-input" placeholder="e.g. snowboard, splitboard, snow gear">
                            </div>
                            <button class="btn secondary input-h" onclick="loadKeywordData()">Get volumes</button>
                        </div>
                        <div id="kw-data-result" style="margin-top: 15px;"></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-search"></span><h3>AI mentions</h3></div>
                    <div class="form-group">
                        <label for="llm-mentions-target">Brand or domain</label>
                        <input type="text" id="llm-mentions-target" placeholder="your-store.com">
                    </div>
                    <button class="btn secondary block" onclick="loadLlmMentions()">Search mentions</button>
                    <div id="llm-mentions-result" style="margin-top: 15px;"></div>
                </div>
            </div>
        </div>

        <!-- Blog Writer Tab -->
        <div id="blogwriter" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Blog Writer</h1>
                    <p>Generate an SEO-optimized article with AI, review it, and publish it to a blog.</p>
                </div>
            </div>
            <div class="grid side-l">
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-edit-page"></span><h3>New article</h3></div>
                    <div class="form-group">
                        <label for="bw-blog">Blog</label>
                        <select id="bw-blog"></select>
                    </div>
                    <div class="form-group">
                        <label for="bw-topic">Topic</label>
                        <input type="text" id="bw-topic" placeholder="e.g. How to choose the right snowboard size">
                    </div>
                    <div class="form-group">
                        <label for="bw-keywords">Keywords <span class="hint">· optional, comma separated</span></label>
                        <input type="text" id="bw-keywords" placeholder="e.g. snowboard size chart, beginner snowboard">
                    </div>
                    <div class="form-cols">
                        <div class="form-group">
                            <label for="bw-words">Word count</label>
                            <input type="number" id="bw-words" value="800" min="200" max="2500">
                        </div>
                        <div class="form-group">
                            <label for="bw-lang">Language</label>
                            <select id="bw-lang">
                                <option value="en">English</option>
                                <option value="nl">Nederlands</option>
                                <option value="de">Deutsch</option>
                                <option value="fr">Français</option>
                                <option value="es">Español</option>
                                <option value="it">Italiano</option>
                                <option value="pt">Português</option>
                                <option value="da">Dansk</option>
                                <option value="sv">Svenska</option>
                                <option value="no">Norsk</option>
                                <option value="fi">Suomi</option>
                                <option value="pl">Polski</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn block" onclick="generateArticle()"><span class="dashicons dashicons-superhero-alt"></span>Generate draft</button>
                </div>
                <div class="card flush">
                    <div class="card-bar">
                        <div class="card-head"><span class="dashicons dashicons-media-document"></span><h3>Draft</h3></div>
                        <span class="badge amber">Not published</span>
                    </div>
                    <div class="card-pad">
                        <div id="bw-result"></div>
                        <div id="bw-draft" style="display: none;">
                            <div class="form-group">
                                <label for="bw-title">Title</label>
                                <input type="text" id="bw-title">
                            </div>
                            <div class="form-group">
                                <label for="bw-body">Body <span class="hint">· HTML</span></label>
                                <textarea id="bw-body" class="mono" rows="14"></textarea>
                            </div>
                            <div class="form-cols">
                                <div class="form-group">
                                    <label for="bw-author">Author <span class="hint">· optional — defaults to shop name</span></label>
                                    <input type="text" id="bw-author" placeholder="e.g. Jane Doe">
                                </div>
                                <div class="form-group">
                                    <label for="bw-tags">Tags <span class="hint">· optional, comma separated</span></label>
                                    <input type="text" id="bw-tags">
                                </div>
                            </div>
                            <div class="btn-row" style="padding-top: 18px; border-top: 1px solid var(--divide);">
                                <button class="btn" onclick="createArticle(true)"><span class="dashicons dashicons-upload"></span>Publish article</button>
                                <button class="btn secondary" onclick="createArticle(false)">Save as draft</button>
                            </div>
                        </div>
                        <div class="empty bw-empty"><span class="dashicons dashicons-media-document"></span>Your generated draft appears here.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GEO Scan Tab -->
        <div id="geoscan" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>GEO Scan</h1>
                    <p>Scan your store for AI search visibility — see how well ChatGPT, Gemini, Claude and Perplexity cite your content.</p>
                </div>
            </div>
            <div class="grid side-r">
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-welcome-view-site"></span><h2>Start a scan</h2></div>
                        <div class="form-group">
                            <label for="geo-keywords">Keywords <span class="hint">· 1-2, comma-separated</span></label>
                            <input type="text" id="geo-keywords" placeholder="e.g. sustainable fashion, organic clothing">
                        </div>
                        <div class="btn-row">
                            <button class="btn" onclick="startGeoScan()">Start GEO Scan</button>
                        </div>
                        <div id="geo-result" style="margin-top:15px;"></div>
                    </div>
                    <div class="card" id="geo-detail-card" style="display:none;">
                        <div class="card-head"><span class="dashicons dashicons-chart-pie"></span><h2>Scan results</h2></div>
                        <div id="geo-detail"></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-backup"></span><h3>Recent scans</h3></div>
                    <div id="geo-recent"><div class="loading">Loading...</div></div>
                </div>
            </div>
        </div>

        <!-- Keyword Explorer Tab -->
        <div id="kwexplorer" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Keyword Explorer</h1>
                    <p>Discover related keywords, search volume, difficulty and trends for any seed keyword.</p>
                </div>
            </div>
            <div class="grid side-r">
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-search"></span><h2>Explore keywords</h2></div>
                        <div class="form-group">
                            <label for="kwe-seed">Seed keyword</label>
                            <input type="text" id="kwe-seed" placeholder="e.g. organic skincare">
                        </div>
                        <div style="display:flex;gap:10px;">
                            <div class="form-group" style="flex:1;">
                                <label for="kwe-country">Country</label>
                                <select id="kwe-country"><option value="us">United States</option><option value="gb">United Kingdom</option><option value="nl">Netherlands</option><option value="de">Germany</option><option value="fr">France</option><option value="es">Spain</option><option value="it">Italy</option><option value="au">Australia</option><option value="ca">Canada</option></select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label for="kwe-language">Language</label>
                                <select id="kwe-language"><option value="en">English</option><option value="nl">Dutch</option><option value="de">German</option><option value="fr">French</option><option value="es">Spanish</option><option value="it">Italian</option></select>
                            </div>
                        </div>
                        <div class="btn-row">
                            <button class="btn" onclick="exploreKeywords()">Explore</button>
                            <button class="btn secondary" onclick="clusterKeywords()">Cluster Results</button>
                        </div>
                    </div>
                    <div class="card" id="kwe-results-card" style="display:none;">
                        <div class="card-head"><span class="dashicons dashicons-list-view"></span><h2>Results</h2></div>
                        <div id="kwe-results"></div>
                    </div>
                </div>
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-chart-area"></span><h3>Volume lookup</h3></div>
                        <div class="form-group">
                            <label for="kwe-volume-input">Keywords <span class="hint">· comma-separated</span></label>
                            <textarea id="kwe-volume-input" rows="3" placeholder="keyword1, keyword2, keyword3"></textarea>
                        </div>
                        <button class="btn secondary" onclick="lookupVolume()">Get volume</button>
                        <div id="kwe-volume-result" style="margin-top:12px;"></div>
                    </div>
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-chart-line"></span><h3>Google Trends</h3></div>
                        <div class="form-group">
                            <label for="kwe-trends-input">Keywords <span class="hint">· comma-separated</span></label>
                            <input type="text" id="kwe-trends-input" placeholder="keyword1, keyword2">
                        </div>
                        <button class="btn secondary" onclick="lookupTrends()">Get trends</button>
                        <div id="kwe-trends-result" style="margin-top:12px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Competitor Analysis Tab -->
        <div id="competitor" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>SERP Competitor Analysis</h1>
                    <p>See who ranks for your target keywords, with backlink metrics and competitive insights.</p>
                </div>
            </div>
            <div class="grid side-r">
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-groups"></span><h2>Analyze competitors</h2></div>
                        <div class="form-group">
                            <label for="comp-keyword">Target keyword</label>
                            <input type="text" id="comp-keyword" placeholder="e.g. best running shoes">
                        </div>
                        <div class="form-group">
                            <label for="comp-target">Your URL <span class="hint">· optional, defaults to your shop</span></label>
                            <input type="text" id="comp-target" placeholder="https://your-store.com">
                        </div>
                        <div style="display:flex;gap:10px;">
                            <div class="form-group" style="flex:1;"><label for="comp-country">Country</label><select id="comp-country"><option value="us">US</option><option value="gb">UK</option><option value="nl">NL</option><option value="de">DE</option><option value="fr">FR</option></select></div>
                            <div class="form-group" style="flex:1;"><label for="comp-lang">Language</label><select id="comp-lang"><option value="en">EN</option><option value="nl">NL</option><option value="de">DE</option><option value="fr">FR</option></select></div>
                        </div>
                        <button class="btn" onclick="analyzeCompetitors()">Analyze SERP</button>
                    </div>
                    <div class="card" id="comp-results-card" style="display:none;">
                        <div class="card-head"><span class="dashicons dashicons-editor-ol"></span><h2>SERP Results</h2></div>
                        <div id="comp-results"></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-image-flip-horizontal"></span><h3>Compare domains</h3></div>
                    <div class="form-group"><label for="comp-domain-a">Domain A</label><input type="text" id="comp-domain-a" placeholder="your-store.com"></div>
                    <div class="form-group"><label for="comp-domain-b">Domain B</label><input type="text" id="comp-domain-b" placeholder="competitor.com"></div>
                    <button class="btn secondary" onclick="compareDomains()">Compare</button>
                    <div id="comp-compare-result" style="margin-top:12px;"></div>
                </div>
            </div>
        </div>

        <!-- AI Tracker Tab -->
        <div id="aitracker" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>AI Optimization Tracker</h1>
                    <p>Track your brand visibility across AI search engines over time — mentions, trends, and actionable recommendations.</p>
                </div>
                <div class="page-actions">
                    <button class="btn" onclick="loadAiTrackerOverview()"><span class="dashicons dashicons-update"></span>Refresh</button>
                </div>
            </div>
            <div id="ait-stats" class="stats cols-4"><div class="loading">Loading...</div></div>
            <div class="grid side-r">
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-chart-area"></span><h2>Visibility trends</h2></div>
                        <div id="ait-timeseries"><div class="loading">Loading...</div></div>
                    </div>
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-megaphone"></span><h2>Multi-provider brand check</h2></div>
                        <div class="form-group">
                            <label for="ait-prompt">Ask all 4 AI models about your brand</label>
                            <input type="text" id="ait-prompt" placeholder="e.g. What are the best stores for organic skincare?">
                        </div>
                        <button class="btn" onclick="runAiTrackerCheck()">Check all providers</button>
                        <div id="ait-check-result" style="margin-top:12px;"></div>
                    </div>
                </div>
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-lightbulb"></span><h3>AI recommendations</h3></div>
                        <div id="ait-recommendations"><div class="loading">Loading...</div></div>
                    </div>
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-star-filled"></span><h3>Top pages</h3></div>
                        <div id="ait-top-pages"><div class="loading">Loading...</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Calendar Tab -->
        <div id="calendar" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Content Calendar</h1>
                    <p>Schedule blog articles for future publishing. Articles are automatically published to Shopify every 15 minutes.</p>
                </div>
                <div class="page-actions">
                    <button class="btn" onclick="loadCalendar()"><span class="dashicons dashicons-update"></span>Refresh</button>
                </div>
            </div>
            <div class="grid side-r">
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-calendar-alt"></span><h2>Scheduled articles</h2></div>
                        <div style="display:flex;gap:10px;margin-bottom:14px;">
                            <select id="cal-status" onchange="loadCalendarArticles()" style="flex:1;"><option value="">All statuses</option><option value="scheduled">Scheduled</option><option value="published">Published</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option></select>
                            <input type="month" id="cal-month" onchange="loadCalendarArticles()" style="flex:1;">
                        </div>
                        <div id="cal-articles"><div class="loading">Loading...</div></div>
                    </div>
                </div>
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-plus-alt2"></span><h3>Schedule new article</h3></div>
                        <div class="form-group"><label for="cal-blog">Blog</label><select id="cal-blog"><option>Loading...</option></select></div>
                        <div class="form-group"><label for="cal-title">Title</label><input type="text" id="cal-title" placeholder="Article title"></div>
                        <div class="form-group"><label for="cal-body">Body HTML</label><textarea id="cal-body" rows="6" placeholder="Article body..."></textarea></div>
                        <div class="form-group"><label for="cal-tags">Tags <span class="hint">· comma-separated</span></label><input type="text" id="cal-tags" placeholder="seo, tips"></div>
                        <div class="form-group"><label for="cal-date">Publish date &amp; time</label><input type="datetime-local" id="cal-date"></div>
                        <div class="form-group"><label for="cal-author">Author <span class="hint">· optional</span></label><input type="text" id="cal-author" placeholder="Author name"></div>
                        <button class="btn" onclick="scheduleArticle()">Schedule</button>
                        <div id="cal-result" style="margin-top:12px;"></div>
                    </div>
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-media-text"></span><h3>Quick tip</h3></div>
                        <p class="muted" style="line-height:1.6;">Use the <strong>Blog Writer</strong> tab to generate an article with AI, then come here to schedule it for later. You can also generate the body directly via the Blog Writer and paste it above.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Markets SEO Tab -->
        <div id="markets" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Markets SEO Check</h1>
                    <p>Audit your Shopify Markets setup for hreflang consistency, locale coverage, and translation gaps.</p>
                </div>
                <div class="page-actions">
                    <button class="btn" onclick="loadMarketsAudit()"><span class="dashicons dashicons-update"></span>Run audit</button>
                </div>
            </div>
            <div id="markets-stats" class="stats cols-4"><div class="loading">Loading...</div></div>
            <div class="grid side-r">
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-admin-site-alt3"></span><h2>Audit checks</h2></div>
                        <div id="markets-checks"><div class="loading">Loading...</div></div>
                    </div>
                </div>
                <div class="stack">
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-lightbulb"></span><h3>Recommendations</h3></div>
                        <div id="markets-recommendations"><div class="loading">Loading...</div></div>
                    </div>
                    <div class="card">
                        <div class="card-head"><span class="dashicons dashicons-translation"></span><h3>Markets overview</h3></div>
                        <div id="markets-overview"><div class="loading">Loading...</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tools Tab -->
        <div id="tools" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>Tools</h1>
                    <p>IndexNow and technical SEO helpers for your store.</p>
                </div>
            </div>
            <div class="grid halves">
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-update"></span><h2>IndexNow</h2></div>
                    <p class="card-desc">
                        Instantly notify Bing and other IndexNow search engines when URLs change.
                        One-time setup creates a key file on your domain via a redirect to the app proxy.
                    </p>
                    <div class="form-group">
                        <label for="indexnow-urls">URLs to submit <span class="hint">· one per line</span></label>
                        <textarea id="indexnow-urls" rows="5" placeholder="https://your-store.com/products/example"></textarea>
                    </div>
                    <div class="btn-row">
                        <button class="btn" onclick="submitIndexNow()">Submit URLs</button>
                        <button class="btn secondary" onclick="setupIndexNow()">Setup key redirect</button>
                    </div>
                    <div id="indexnow-result" style="margin-top: 15px;"></div>
                </div>
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-admin-tools"></span><h2>Technical status</h2></div>
                    <div id="indexnow-status"><div class="loading">Loading...</div></div>
                </div>
            </div>
        </div>

        <!-- License Tab -->
        <div id="license" class="tab-content">
            <div class="page-head">
                <div>
                    <h1>License</h1>
                    <p>Manage your Fyndable license.</p>
                </div>
            </div>
            <div class="grid side-r">
                <div class="card" style="padding: 26px;">
                    <div id="license-status"><div class="loading">Loading...</div></div>
                    <div id="license-form">
                        <div class="form-group">
                            <label for="license-key">Fyndable License Key</label>
                            <input type="text" id="license-key" placeholder="FYND-XXXX-XXXX-XXXX">
                        </div>
                        <button class="btn" onclick="activateLicense()">Activate License</button>
                    </div>
                    <div id="license-active" style="display:none;">
                        <button class="btn danger" onclick="deactivateLicense()">Deactivate License</button>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><span class="dashicons dashicons-star-filled"></span><h3>Fyndable SEO modules</h3></div>
                    <div class="feature">Content Optimizer</div>
                    <div class="feature">Bulk optimization</div>
                    <div class="feature">llms.txt generator</div>
                    <div class="feature">Rank tracker</div>
                    <div class="feature">Backlink analysis</div>
                    <div class="feature">AI visibility checks</div>
                    <div class="feature">Blog Writer</div>
                    <div class="feature">IndexNow</div>
                    <div class="feature">Schema &amp; FAQ markup</div>
                    <div class="feature">GEO Scan (AI visibility)</div>
                    <div class="feature">Keyword Explorer</div>
                    <div class="feature">SERP Competitor Analysis</div>
                    <div class="feature">AI Optimization Tracker</div>
                    <div class="feature">Content Calendar</div>
                    <div class="feature">Product Variant SEO</div>
                    <div class="feature">Review Schema</div>
                    <div class="feature">Markets SEO Check</div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const API_BASE = '/api';
        const shopDomain = @json($shopDomain);
        const apiKey = @json($apiKey);

        // UI helpers (presentation only)
        function toggleTheme() {
            const dark = document.documentElement.classList.toggle('fyn-dark');
            try { localStorage.setItem('fyn-theme', dark ? 'dark' : 'light'); } catch (e) {}
        }

        // Dropdown group toggle
        function toggleGroup(trigger) {
            const group = trigger.closest('.tab-group');
            const wasOpen = group.classList.contains('open');
            closeAllGroups();
            if (!wasOpen) group.classList.add('open');
        }

        function closeAllGroups() {
            document.querySelectorAll('.tab-group').forEach(g => g.classList.remove('open'));
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.tab-group')) closeAllGroups();
        });

        // Route through the real tab button so showTab() gets it as event.target.
        function goTab(tabId) {
            const btn = document.querySelector(`.tab[data-tab="${tabId}"]`);
            if (btn) btn.click();
        }

        function setContentType(type) {
            document.querySelectorAll('#content-type-pills .pill').forEach(el => el.classList.toggle('active', el.dataset.type === type));
            document.getElementById('content-type').value = type;
            onContentTypeChange();
        }

        function statTile(label, value, icon, cls = '', sub = '') {
            return `<div class="stat"><div class="label">${label}<span class="dashicons dashicons-${icon}"></span></div>`
                + `<div class="value ${cls}">${value}</div>${sub ? `<div class="sub">${sub}</div>` : ''}</div>`;
        }

        function statusRow(icon, title, sub, badge) {
            return `<div class="status-row"><div class="icon-chip well"><span class="dashicons dashicons-${icon}"></span></div>`
                + `<div class="grow"><div class="t">${title}</div><div class="s">${sub}</div></div>${badge}</div>`;
        }

        function setLicensePill(tier, valid) {
            document.getElementById('hdr-license-label').textContent = tier || 'free';
            document.getElementById('hdr-license').classList.toggle('active', !!valid);
        }

        // App Bridge v4 (loaded via cdn.shopify.com/shopifycloud/app-bridge.js)
        // automatically intercepts the global fetch() function and adds the
        // Authorization: Bearer <id-token> header for requests to the app's
        // own domain. No createApp() or authenticatedFetch() needed.

        // Tab switching
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-group-trigger').forEach(el => el.classList.remove('has-active'));
            document.getElementById(tabId).classList.add('active');

            // Highlight the clicked tab
            const tabBtn = document.querySelector(`.tab[data-tab="${tabId}"]`);
            if (tabBtn) tabBtn.classList.add('active');

            // Highlight parent group trigger if tab is inside a dropdown
            const group = tabBtn?.closest('.tab-group');
            if (group) group.querySelector('.tab-group-trigger')?.classList.add('has-active');

            // Close all dropdowns
            closeAllGroups();

            // Load tab data
            if (tabId === 'overview') loadOverview();
            if (tabId === 'content' && !contentListLoaded) { contentListLoaded = true; searchContent(); }
            if (tabId === 'llmstxt') loadLlmsTxtStatus();
            if (tabId === 'ranktracker') { loadRankStats(); loadKeywords(); }
            if (tabId === 'blogwriter') loadBlogs();
            if (tabId === 'tools') loadIndexNowStatus();
            if (tabId === 'license') loadLicenseStatus();
            if (tabId === 'geoscan') loadGeoRecent();
            if (tabId === 'aitracker') loadAiTrackerOverview();
            if (tabId === 'calendar') loadCalendar();
            if (tabId === 'markets') loadMarketsAudit();
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
                document.getElementById('overview-status').innerHTML = '';
                return;
            }
            const valid = !!data.license?.valid;
            setLicensePill(data.license?.tier, valid);
            document.getElementById('overview-stats').innerHTML =
                statTile('License Tier', data.license?.tier || 'free', 'admin-network', 'purple', valid ? 'Active plan' : 'No active license')
                + statTile('Products', data.product_count ?? 0, 'products', 'blue', 'in your store')
                + statTile('Tracked Keywords', data.tracked_keywords ?? 0, 'search', '', 'in Rank Tracker')
                + statTile('Top 10 Rankings', data.top_10_keywords ?? 0, 'awards', 'green', 'Google positions 1–10')
                + statTile('llms.txt', data.llms_txt_enabled ? 'Enabled' : 'Disabled', 'media-code', data.llms_txt_enabled ? 'green' : 'red', data.llms_txt_full_enabled ? 'llms-full.txt enabled' : 'llms-full.txt disabled');
            const on = (ok, yes, no) => `<span class="badge ${ok ? 'green' : 'gray'}">${ok ? yes : no}</span>`;
            document.getElementById('overview-status').innerHTML =
                statusRow('admin-network', 'License', `Tier: ${data.license?.tier || 'free'}`, on(valid, 'Active', 'Inactive'))
                + statusRow('media-code', '/llms.txt', 'Markdown summary for AI crawlers', on(data.llms_txt_enabled, 'Enabled', 'Disabled'))
                + statusRow('media-text', '/llms-full.txt', 'Full content for AI crawlers', on(data.llms_txt_full_enabled, 'Enabled', 'Disabled'))
                + statusRow('lock', 'App permissions', data.missing_scopes?.length ? `Missing: ${data.missing_scopes.join(', ')}` : 'All required scopes granted',
                    data.missing_scopes?.length ? '<span class="badge red">Action needed</span>' : '<span class="badge green">OK</span>');
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
            const bar = () => {
                const pct = data.total ? Math.round(data.processed / data.total * 100) : 0;
                return `<div class="progress-head"><span>${data.processed} of ${data.total} products</span><span>${pct}%</span></div>
                    <div class="progress" style="margin-bottom: 14px;"><div style="width: ${pct}%;"></div></div>`;
            };
            if (data.status === 'running') {
                document.getElementById('bulk-result').innerHTML = `${bar()}<div class="alert info">Progress: ${data.processed}/${data.total} (Succeeded: ${data.succeeded}, Failed: ${data.failed})</div>`;
            } else if (data.status === 'completed') {
                document.getElementById('bulk-result').innerHTML = `${bar()}<div class="alert success">Completed: ${data.processed}/${data.total} (Succeeded: ${data.succeeded}, Failed: ${data.failed})</div>`;
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
            document.getElementById('rank-stats').innerHTML =
                statTile('Total Keywords', data.total_keywords, 'search', '', 'tracked')
                + statTile('Top 3', data.top_3, 'awards', 'green', 'positions 1–3')
                + statTile('Top 10', data.top_10, 'star-filled', 'green', 'positions 1–10')
                + statTile('Top 100', data.top_100, 'chart-line', 'blue', 'positions 1–100')
                + statTile('Not Ranked', data.not_ranked, 'dismiss', '', 'outside top 100');
        }

        function posBadge(pos) {
            if (!pos) return '<span class="badge gray">—</span>';
            return `<span class="badge ${pos <= 3 ? 'green' : pos <= 10 ? 'blue' : 'amber'}">${pos}</span>`;
        }

        async function loadKeywords() {
            const data = await api('/rank-tracker/keywords');
            if (data.error || !data.keywords) return;
            const html = data.keywords.length === 0
                ? '<div class="empty"><span class="dashicons dashicons-chart-line"></span>No keywords tracked yet.</div>'
                : `<div class="table-wrap fyn-scroll"><table style="min-width: 720px;"><thead><tr><th>Keyword</th><th>URL</th><th class="num">Last Position</th><th class="num">Best</th><th>Last Checked</th><th class="end">Action</th></tr></thead><tbody>
                ${data.keywords.map(k => `<tr>
                    <td class="kw">${k.keyword}</td>
                    <td class="muted">${k.url ? k.url.substring(0, 40) + '...' : '—'}</td>
                    <td class="num">${posBadge(k.last_position)}</td>
                    <td class="num">${posBadge(k.best_position)}</td>
                    <td class="muted">${k.last_checked_at ? new Date(k.last_checked_at).toLocaleDateString() : '—'}</td>
                    <td class="end"><button class="icon-btn" title="Delete" aria-label="Delete keyword" onclick="deleteKeyword(${k.id})"><span class="dashicons dashicons-trash"></span></button></td>
                </tr>`).join('')}
                </tbody></table></div>`;
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
            setLicensePill(data.tier, data.valid);
            const head = (ok, title, sub) => `<div style="display:flex;align-items:center;gap:12px;padding-bottom:18px;border-bottom:1px solid var(--divide);margin-bottom:20px;">
                    <div class="icon-chip lg" style="background: var(${ok ? '--fyn-success-light' : '--fyn-danger-light'});"><span class="dashicons dashicons-${ok ? 'yes-alt' : 'warning'}" style="color: var(${ok ? '--fyn-success' : '--fyn-danger'});"></span></div>
                    <div><div style="font-size:17px;font-weight:700;color:var(--text-h);">${title}</div><div class="muted">${sub}</div></div></div>`;
            const statusHtml = data.valid
                ? head(true, 'License active', `Tier: <strong style="text-transform:capitalize;">${data.tier}</strong>`)
                    + `<div class="field-label">Fyndable license key</div><div class="lic-key"><span style="flex:1;">${data.license_key}</span><span class="dashicons dashicons-admin-network" style="color:var(--fyn-purple);"></span></div>`
                : head(false, 'No active license', 'Enter your Fyndable license key below.');
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
                box.innerHTML = '<p class="muted">No linkable titles found in the body text.</p>';
                return;
            }
            box.innerHTML = data.suggestions.map((s, i) => `
                <div class="link-row">
                    <span class="grow"><strong>${escHtml(s.anchor)}</strong> → ${escHtml(s.url)} <em>(${s.target_type})</em></span>
                    <button class="btn secondary sm" onclick="applyLink(${i})">Apply</button>
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
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
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
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
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
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
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
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.data ?? data, null, 2))}</pre>`;
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
                    language: document.getElementById('bw-lang').value,
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
                    author: document.getElementById('bw-author').value.trim(),
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
            box.innerHTML =
                statusRow('admin-network', 'IndexNow key', `<code>${data.key}</code>`, '<span class="badge green">Generated</span>')
                + statusRow('media-text', 'Key file', `<code>${data.key_location}</code>`, '')
                + statusRow('admin-links', 'App proxy target', `→ redirects to <code>${data.proxy_path}</code>`, '');
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

        // -----------------------------------------------------------------------
        //  GEO Scan
        // -----------------------------------------------------------------------
        async function startGeoScan() {
            const keywords = document.getElementById('geo-keywords').value.trim();
            if (!keywords) { alert('Enter at least one keyword'); return; }
            const box = document.getElementById('geo-result');
            box.innerHTML = '<div class="loading">Starting GEO scan...</div>';
            const data = await api('/geo-scan/start', {
                method: 'POST',
                body: JSON.stringify({ keywords }),
            });
            if (data.error) {
                box.innerHTML = `<div class="alert error">Error: ${data.error}${data.message ? ' — ' + data.message : ''}</div>`;
                return;
            }
            const scanId = data.scan_id || data.id;
            if (!scanId) {
                box.innerHTML = `<div class="alert success">Scan queued.</div>`;
                return;
            }
            box.innerHTML = `<div class="alert info">Scan started (ID: ${scanId}). Polling for results...</div>`;
            pollGeoScan(scanId);
        }

        async function pollGeoScan(scanId) {
            const box = document.getElementById('geo-result');
            const detailBox = document.getElementById('geo-detail');
            const detailCard = document.getElementById('geo-detail-card');
            let attempts = 0;
            const poll = async () => {
                if (attempts++ > 60) { box.innerHTML = '<div class="alert error">Scan timed out.</div>'; return; }
                const data = await api(`/geo-scan/${scanId}/status`);
                if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
                const status = data.status || 'unknown';
                const progress = data.progress || 0;
                box.innerHTML = `<div class="alert info">Status: <strong>${status}</strong> — ${progress}%${data.progress_label ? ' · ' + data.progress_label : ''}</div>
                    <div class="progress"><div style="width:${progress}%;"></div></div>`;
                if (status === 'completed') {
                    box.innerHTML = `<div class="alert success">Scan complete! Score: <strong>${data.score ?? data.result?.score ?? '—'}</strong></div>`;
                    detailCard.style.display = 'block';
                    const r = data.result || data;
                    detailBox.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(r, null, 2))}</pre>`;
                    loadGeoRecent();
                    return;
                }
                if (status === 'failed') {
                    box.innerHTML = `<div class="alert error">Scan failed: ${data.error || 'Unknown error'}</div>`;
                    return;
                }
                setTimeout(poll, 5000);
            };
            poll();
        }

        async function loadGeoRecent() {
            const box = document.getElementById('geo-recent');
            const data = await api('/geo-scan/recent?limit=10');
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            const scans = data.scans || data.data || (Array.isArray(data) ? data : []);
            if (!scans.length) { box.innerHTML = '<p class="muted">No scans yet. Start your first GEO scan!</p>'; return; }
            box.innerHTML = scans.map(s => `
                <div class="status-row" style="cursor:pointer;" onclick="pollGeoScan(${s.id})">
                    <div class="icon-chip well"><span class="dashicons dashicons-${s.status === 'completed' ? 'yes-alt' : s.status === 'running' ? 'update' : 'clock'}"></span></div>
                    <div class="grow"><div class="t">${escHtml(s.keywords || s.url || 'Scan #' + s.id)}</div>
                    <div class="s">${s.status}${s.score ? ' · Score: ' + s.score : ''}</div></div>
                    <span class="badge ${s.status === 'completed' ? 'green' : s.status === 'failed' ? 'red' : 'gray'}">${s.status}</span>
                </div>`).join('');
        }

        // -----------------------------------------------------------------------
        //  Keyword Explorer
        // -----------------------------------------------------------------------
        let _kweLastKeywords = [];

        async function exploreKeywords() {
            const seed = document.getElementById('kwe-seed').value.trim();
            if (!seed) { alert('Enter a seed keyword'); return; }
            const card = document.getElementById('kwe-results-card');
            const box = document.getElementById('kwe-results');
            card.style.display = 'block';
            box.innerHTML = '<div class="loading">Exploring keywords...</div>';
            const data = await api('/keywords/explore', {
                method: 'POST',
                body: JSON.stringify({
                    seed,
                    country: document.getElementById('kwe-country').value,
                    language: document.getElementById('kwe-language').value,
                }),
            });
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}${data.message ? ' — ' + data.message : ''}</div>`; return; }
            const keywords = data.keywords || data.data || [];
            _kweLastKeywords = Array.isArray(keywords) ? keywords.map(k => typeof k === 'string' ? k : (k.keyword || k.term || '')) : [];
            if (!keywords.length) { box.innerHTML = '<p class="muted">No related keywords found.</p>'; return; }
            box.innerHTML = `<div class="table-wrap fyn-scroll"><table><thead><tr><th>Keyword</th><th class="num">Volume</th><th class="num">CPC</th><th class="num">Difficulty</th></tr></thead><tbody>
                ${(Array.isArray(keywords) ? keywords : []).map(k => {
                    const kw = typeof k === 'string' ? k : k;
                    return `<tr><td class="kw">${escHtml(kw.keyword || kw.term || kw)}</td>
                        <td class="num">${kw.search_volume ?? kw.volume ?? '—'}</td>
                        <td class="num">${kw.cpc ? '$' + Number(kw.cpc).toFixed(2) : '—'}</td>
                        <td class="num">${kw.difficulty ?? kw.keyword_difficulty ?? '—'}</td></tr>`;
                }).join('')}</tbody></table></div>`;
        }

        async function clusterKeywords() {
            if (_kweLastKeywords.length < 2) { alert('Explore keywords first (need at least 2)'); return; }
            const box = document.getElementById('kwe-results');
            box.innerHTML = '<div class="loading">Clustering keywords...</div>';
            const data = await api('/keywords/cluster', {
                method: 'POST',
                body: JSON.stringify({ keywords: _kweLastKeywords }),
            });
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.clusters || data, null, 2))}</pre>`;
        }

        async function lookupVolume() {
            const raw = document.getElementById('kwe-volume-input').value;
            const keywords = raw.split(',').map(k => k.trim()).filter(Boolean);
            if (!keywords.length) { alert('Enter at least one keyword'); return; }
            const box = document.getElementById('kwe-volume-result');
            box.innerHTML = '<div class="loading">Looking up volume...</div>';
            const data = await api('/keywords/volume', {
                method: 'POST',
                body: JSON.stringify({ keywords }),
            });
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.data || data, null, 2))}</pre>`;
        }

        async function lookupTrends() {
            const raw = document.getElementById('kwe-trends-input').value;
            const keywords = raw.split(',').map(k => k.trim()).filter(Boolean);
            if (!keywords.length) { alert('Enter at least one keyword'); return; }
            const box = document.getElementById('kwe-trends-result');
            box.innerHTML = '<div class="loading">Loading trends...</div>';
            const data = await api('/keywords/trends', {
                method: 'POST',
                body: JSON.stringify({ keywords }),
            });
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.data || data, null, 2))}</pre>`;
        }

        // -----------------------------------------------------------------------
        //  Competitor Analysis
        // -----------------------------------------------------------------------
        async function analyzeCompetitors() {
            const keyword = document.getElementById('comp-keyword').value.trim();
            if (!keyword) { alert('Enter a target keyword'); return; }
            const card = document.getElementById('comp-results-card');
            const box = document.getElementById('comp-results');
            card.style.display = 'block';
            box.innerHTML = '<div class="loading">Analyzing SERP competitors (this may take a moment)...</div>';
            const data = await api('/competitor/analyze', {
                method: 'POST',
                body: JSON.stringify({
                    keyword,
                    target_url: document.getElementById('comp-target').value.trim() || undefined,
                    country: document.getElementById('comp-country').value,
                    language: document.getElementById('comp-lang').value,
                }),
            });
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}${data.message ? ' — ' + data.message : ''}</div>`; return; }
            const pos = data.target_position;
            let html = `<div class="alert ${pos ? (pos <= 10 ? 'success' : 'info') : 'info'}">Your position: <strong>${pos || 'Not found in top results'}</strong></div>`;
            html += `<div class="table-wrap fyn-scroll"><table><thead><tr><th>#</th><th>Domain</th><th>Title</th><th class="num">Backlinks</th><th class="num">Ref. Domains</th><th class="num">DR</th></tr></thead><tbody>`;
            (data.competitors || []).forEach((c, i) => {
                const bl = c.backlinks || {};
                html += `<tr><td>${c.position || i + 1}</td><td class="kw">${escHtml(c.domain)}</td>
                    <td>${escHtml((c.title || '').substring(0, 60))}</td>
                    <td class="num">${bl.total ?? '—'}</td><td class="num">${bl.referring_domains ?? '—'}</td>
                    <td class="num">${bl.domain_rank ?? '—'}</td></tr>`;
            });
            html += '</tbody></table></div>';
            if (data.serp_features && Object.keys(data.serp_features).length) {
                html += `<div style="margin-top:14px;"><strong>SERP Features:</strong> ${Object.keys(data.serp_features).map(f => `<span class="badge gray">${escHtml(f)}</span>`).join(' ')}</div>`;
            }
            box.innerHTML = html;
        }

        async function compareDomains() {
            const a = document.getElementById('comp-domain-a').value.trim();
            const b = document.getElementById('comp-domain-b').value.trim();
            if (!a || !b) { alert('Enter both domains'); return; }
            const box = document.getElementById('comp-compare-result');
            box.innerHTML = '<div class="loading">Comparing...</div>';
            const data = await api('/competitor/compare', {
                method: 'POST',
                body: JSON.stringify({ domain_a: a, domain_b: b }),
            });
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.domains || data, null, 2))}</pre>`;
        }

        // -----------------------------------------------------------------------
        //  AI Optimization Tracker
        // -----------------------------------------------------------------------
        async function loadAiTrackerOverview() {
            const statsBox = document.getElementById('ait-stats');
            statsBox.innerHTML = '<div class="loading">Loading AI visibility data...</div>';
            const data = await api('/ai-tracker/overview');
            if (data.error) { statsBox.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            const m = data.metrics || {};
            statsBox.innerHTML =
                statTile('Total Mentions', m.total_mentions ?? m.mentions_count ?? '—', 'megaphone', 'purple', 'across AI models')
                + statTile('Top Domains', (data.top_brands?.data || []).length || '—', 'groups', 'blue', 'mentioned brands')
                + statTile('Top Pages', (data.top_pages?.data || []).length || '—', 'admin-page', '', 'cited pages')
                + statTile('Target', escHtml(data.target || shopDomain), 'admin-site-alt3', '', '');

            // Top pages
            const pages = data.top_pages?.data || [];
            document.getElementById('ait-top-pages').innerHTML = pages.length
                ? pages.slice(0, 10).map(p => `<div class="status-row"><div class="grow"><div class="t">${escHtml(p.url || p.page || p.title || '—')}</div><div class="s">${p.mentions || p.count || '—'} mentions</div></div></div>`).join('')
                : '<p class="muted">No top pages data yet.</p>';

            // Timeseries
            loadAiTrackerTimeseries();
            loadAiTrackerRecommendations();
        }

        async function loadAiTrackerTimeseries() {
            const box = document.getElementById('ait-timeseries');
            const data = await api('/ai-tracker/timeseries');
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            box.innerHTML = `<pre class="code fyn-scroll">${escHtml(JSON.stringify(data.delta || data, null, 2))}</pre>`;
        }

        async function loadAiTrackerRecommendations() {
            const box = document.getElementById('ait-recommendations');
            const data = await api('/ai-tracker/recommendations');
            if (data.error) { box.innerHTML = `<p class="muted">No recommendations available yet. Run a GEO scan first.</p>`; return; }
            const recs = data.recommendations || [];
            if (!recs.length) { box.innerHTML = '<p class="muted">No recommendations available.</p>'; return; }
            box.innerHTML = recs.map(r => `
                <div class="status-row">
                    <div class="icon-chip well"><span class="dashicons dashicons-${r.priority === 'high' ? 'warning' : r.priority === 'medium' ? 'info' : 'marker'}"></span></div>
                    <div class="grow"><div class="t">${escHtml(r.title)}</div><div class="s">${escHtml(r.description)}</div></div>
                    <span class="badge ${r.priority === 'high' ? 'red' : r.priority === 'medium' ? 'amber' : 'gray'}">${r.priority}</span>
                </div>`).join('');
        }

        async function runAiTrackerCheck() {
            const prompt = document.getElementById('ait-prompt').value.trim();
            if (!prompt) { alert('Enter a question'); return; }
            const box = document.getElementById('ait-check-result');
            box.innerHTML = '<div class="loading">Checking all 4 AI providers (this takes ~30s)...</div>';
            const data = await api('/ai-tracker/check', {
                method: 'POST',
                body: JSON.stringify({ prompt }),
            });
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            const results = data.results || {};
            let html = '';
            for (const [provider, result] of Object.entries(results)) {
                const icon = provider === 'chatgpt' ? '💬' : provider === 'claude' ? '🤖' : provider === 'gemini' ? '✨' : '🔍';
                const hasError = result.error;
                html += `<div class="card" style="margin-top:10px;padding:16px;">
                    <div style="font-weight:700;margin-bottom:8px;">${icon} ${provider.charAt(0).toUpperCase() + provider.slice(1)}</div>
                    ${hasError ? `<div class="alert error">${result.error}</div>` : `<pre class="code fyn-scroll" style="max-height:200px;">${escHtml(JSON.stringify(result, null, 2))}</pre>`}
                </div>`;
            }
            box.innerHTML = html;
        }

        // -----------------------------------------------------------------------
        //  Content Calendar
        // -----------------------------------------------------------------------
        function loadCalendar() {
            loadCalendarBlogs();
            loadCalendarArticles();
        }

        async function loadCalendarBlogs() {
            const sel = document.getElementById('cal-blog');
            const data = await api('/blogs');
            if (data.error || !data.blogs) { sel.innerHTML = '<option>Error loading blogs</option>'; return; }
            sel.innerHTML = data.blogs.map(b => `<option value="${b.id.split('/').pop()}">${escHtml(b.title)}</option>`).join('') || '<option>No blogs</option>';
        }

        async function loadCalendarArticles() {
            const box = document.getElementById('cal-articles');
            const status = document.getElementById('cal-status').value;
            const month = document.getElementById('cal-month').value;
            let url = '/calendar/articles?';
            if (status) url += `status=${status}&`;
            if (month) url += `month=${month}&`;
            const data = await api(url);
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            const articles = data.articles || [];
            if (!articles.length) { box.innerHTML = '<div class="empty"><span class="dashicons dashicons-calendar-alt"></span>No scheduled articles found.</div>'; return; }
            box.innerHTML = `<div class="table-wrap fyn-scroll"><table><thead><tr><th>Title</th><th>Scheduled</th><th>Status</th><th class="end">Action</th></tr></thead><tbody>
                ${articles.map(a => `<tr>
                    <td class="kw">${escHtml(a.title)}</td>
                    <td class="muted">${new Date(a.scheduled_at).toLocaleString()}</td>
                    <td><span class="badge ${a.status === 'published' ? 'green' : a.status === 'failed' ? 'red' : a.status === 'cancelled' ? 'gray' : 'amber'}">${a.status}</span></td>
                    <td class="end">${a.status === 'scheduled' ? `<button class="icon-btn" title="Cancel" onclick="cancelScheduled(${a.id})"><span class="dashicons dashicons-dismiss"></span></button>` : ''}</td>
                </tr>`).join('')}</tbody></table></div>`;
        }

        async function scheduleArticle() {
            const blogId = document.getElementById('cal-blog').value;
            const title = document.getElementById('cal-title').value.trim();
            const body = document.getElementById('cal-body').value.trim();
            const date = document.getElementById('cal-date').value;
            if (!title || !body || !date) { alert('Title, body and date are required'); return; }
            const box = document.getElementById('cal-result');
            box.innerHTML = '<div class="loading">Scheduling...</div>';
            const data = await api('/calendar/schedule', {
                method: 'POST',
                body: JSON.stringify({
                    blog_id: blogId,
                    title,
                    body_html: body,
                    tags: document.getElementById('cal-tags').value,
                    scheduled_at: date,
                    author: document.getElementById('cal-author').value.trim(),
                }),
            });
            if (data.success) {
                box.innerHTML = '<div class="alert success">Article scheduled!</div>';
                document.getElementById('cal-title').value = '';
                document.getElementById('cal-body').value = '';
                loadCalendarArticles();
            } else {
                box.innerHTML = `<div class="alert error">${data.error}</div>`;
            }
        }

        async function cancelScheduled(id) {
            if (!confirm('Cancel this scheduled article?')) return;
            await api(`/calendar/${id}`, { method: 'DELETE' });
            loadCalendarArticles();
        }

        // -----------------------------------------------------------------------
        //  Markets SEO Check
        // -----------------------------------------------------------------------
        async function loadMarketsAudit() {
            const statsBox = document.getElementById('markets-stats');
            const checksBox = document.getElementById('markets-checks');
            const recsBox = document.getElementById('markets-recommendations');
            const overviewBox = document.getElementById('markets-overview');
            statsBox.innerHTML = '<div class="loading">Running markets SEO audit...</div>';
            checksBox.innerHTML = '<div class="loading">Loading...</div>';

            // Load audit
            const data = await api('/markets/audit');
            if (data.error) {
                statsBox.innerHTML = `<div class="alert error">${data.error}${data.message ? ' — ' + data.message : ''}</div>`;
                checksBox.innerHTML = '';
                return;
            }

            const score = data.score ?? 0;
            const scoreColor = score >= 80 ? 'green' : score >= 50 ? 'amber' : 'red';
            statsBox.innerHTML =
                statTile('SEO Score', score + '%', 'awards', scoreColor, score >= 80 ? 'Good' : score >= 50 ? 'Needs work' : 'Issues found')
                + statTile('Markets', data.markets_count ?? 0, 'admin-site-alt3', 'blue', 'configured')
                + statTile('Locales', (data.locales || []).length || 0, 'translation', '', (data.locales || []).join(', ') || 'none')
                + statTile('Checks', (data.checks || []).length, 'yes-alt', '', 'audit checks run');

            // Checks table
            const checks = data.checks || [];
            checksBox.innerHTML = checks.length
                ? checks.map(c => `<div class="status-row">
                    <div class="icon-chip well"><span class="dashicons dashicons-${c.passed ? 'yes-alt' : 'warning'}" style="color:var(${c.passed ? '--fyn-success' : '--fyn-danger'});"></span></div>
                    <div class="grow"><div class="t">${escHtml(c.name)}${c.market ? ' · ' + escHtml(c.market) : ''}${c.locale ? ' · ' + escHtml(c.locale) : ''}</div>
                    <div class="s">${escHtml(c.detail)}</div></div>
                    <span class="badge ${c.passed ? 'green' : 'red'}">${c.passed ? 'Pass' : 'Fail'}</span>
                </div>`).join('')
                : '<p class="muted">No checks run.</p>';

            // Recommendations
            const recs = data.recommendations || [];
            recsBox.innerHTML = recs.length
                ? recs.map(r => `<div class="status-row"><div class="icon-chip well"><span class="dashicons dashicons-lightbulb"></span></div><div class="grow"><div class="s">${escHtml(r)}</div></div></div>`).join('')
                : '<p class="muted">No issues found — your markets setup looks good!</p>';

            // Markets overview
            loadMarketsOverview(overviewBox);
        }

        async function loadMarketsOverview(box) {
            const data = await api('/markets/overview');
            if (data.error) { box.innerHTML = `<div class="alert error">${data.error}</div>`; return; }
            const markets = data.markets || [];
            if (!markets.length) { box.innerHTML = '<p class="muted">No markets configured in your Shopify store.</p>'; return; }
            box.innerHTML = markets.map(m => {
                const locales = (m.webPresences || []).flatMap(wp => [wp.defaultLocale, ...(wp.alternateLocales || [])]).filter(Boolean);
                const regions = (m.regions || []).map(r => r.name || r.code).join(', ');
                return `<div class="status-row">
                    <div class="icon-chip well"><span class="dashicons dashicons-${m.primary ? 'star-filled' : 'admin-site-alt3'}" style="color:${m.primary ? 'var(--fyn-warning)' : 'var(--fyn-purple)'};"></span></div>
                    <div class="grow"><div class="t">${escHtml(m.name)}${m.primary ? ' (Primary)' : ''}</div>
                    <div class="s">Locales: ${locales.join(', ') || 'none'} · Regions: ${regions || 'none'}</div></div>
                    <span class="badge ${m.enabled ? 'green' : 'gray'}">${m.enabled ? 'Active' : 'Disabled'}</span>
                </div>`;
            }).join('');
        }

        // Load overview on page load
        loadOverview();
    </script>
</body>
</html>
