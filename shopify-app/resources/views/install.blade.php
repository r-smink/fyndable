<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fyndable SEO — Install</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f6f6f7; margin: 0; padding: 40px 20px; }
        .container { max-width: 540px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { font-size: 28px; margin: 0; background: linear-gradient(135deg, #379fd3, #8f39ac); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo p { color: #637381; margin: 5px 0 0; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; box-sizing: border-box; }
        .form-group input:focus { outline: none; border-color: #379fd3; box-shadow: 0 0 0 2px rgba(55,159,211,0.2); }
        .btn { width: 100%; padding: 12px; background: linear-gradient(135deg, #379fd3, #8f39ac); color: #fff; border: none; border-radius: 6px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
        .info { background: #f0f5ff; border: 1px solid #cfe0ff; border-radius: 8px; padding: 15px; margin-bottom: 25px; font-size: 13px; color: #454545; line-height: 1.5; }
        .info a { color: #379fd3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Fyndable SEO</h1>
            <p>AI-powered SEO for Shopify</p>
        </div>

        <div class="info">
            Enter your Shopify store domain to install the Fyndable SEO app.
            After installation, you'll need to link your Fyndable license key.
            Don't have a license? <a href="https://portal.fyndable.ai" target="_blank">Get one here</a>.
        </div>

        <form action="{{ route('install') }}" method="GET">
            <div class="form-group">
                <label for="shop">Shopify Store Domain</label>
                <input type="text" id="shop" name="shop" placeholder="your-store.myshopify.com" required>
            </div>
            <button type="submit" class="btn">Install App</button>
        </form>
    </div>
</body>
</html>
