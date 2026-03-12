# SSEO AI - Multi-Tenant SaaS SEO Platform

> **Advanced AI-powered WordPress SEO platform with white-label support and 40+ optimization features**

[![Version](https://img.shields.io/badge/version-1.0.8-blue.svg)](https://github.com/yourusername/sseo-ai)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](LICENSE)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)

## 📋 Overview

SSEO AI is a complete **multi-tenant SaaS platform** for WordPress SEO optimization. It consists of two plugins:

1. **SaaS Dashboard Plugin** - Central management platform for licenses, tenants, and API proxying
2. **Client Plugin** - Feature-rich SEO plugin installed on customer WordPress sites

### Key Features

✨ **Multi-Tenant Architecture** - Manage unlimited customers from one dashboard  
🎨 **White-Label Support** - Customize branding, colors, and company name  
🤖 **AI-Powered** - Integrated with OpenAI, Anthropic, and Mistral  
📊 **40+ SEO Features** - Content optimization, rank tracking, schema markup, and more  
💰 **Tiered Pricing** - Free, Starter, Professional, Business, and Agency plans  
🔒 **Secure** - License validation, rate limiting, and tenant isolation  
📈 **Usage Tracking** - Monitor API calls, costs, and feature usage per tenant  

## 📚 Documentation

- **[Architecture Guide](ARCHITECTURE.md)** - System design, data flow, and technical details
- **[Setup Guide](SETUP.md)** - Installation, configuration, and troubleshooting
- **[Feature List](wp-content/plugins/ai-seo-client/README.md)** - Complete list of 40+ SEO features

## 🚀 Quick Start

### For SaaS Providers

1. **Install SaaS Dashboard Plugin**
   ```bash
   cd wp-content/plugins/
   unzip ai-seo-saas-dashboard.zip
   wp plugin activate ai-seo-saas-dashboard
   ```

2. **Configure API Keys**
   - Navigate to: **SSEO AI SaaS → Settings**
   - Add OpenAI API key
   - Add SERP API key (optional)

3. **Generate License**
   - Go to: **SSEO AI SaaS → Licenses**
   - Click **Generate New License**
   - Select tier and expiration
   - Copy license key

4. **Configure White-Label** (Optional)
   - Go to: **SSEO AI SaaS → White-Label**
   - Set company name, logo, colors
   - Settings sync to clients on activation

### For Customers

1. **Install Client Plugin**
   ```bash
   cd wp-content/plugins/
   unzip ai-seo-client.zip
   wp plugin activate ai-seo-client
   ```

2. **Activate License**
   - Navigate to: **SSEO AI → Connection**
   - Enter dashboard URL: `https://saas.yourdomain.com`
   - Enter license key: `SSEO-XXXX-XXXX-XXXX-XXXX`
   - Click **Activate License**

3. **Start Using Features**
   - Features appear based on license tier
   - Access via **SSEO AI** menu in WordPress admin

## 🏗️ System Architecture

```
┌─────────────────────────────────┐
│    SaaS Dashboard Server        │
│  ┌──────────────────────────┐   │
│  │  License Management      │   │
│  │  Tenant Repository       │   │
│  │  API Gateway (AI Proxy)  │   │
│  │  White-Label Settings    │   │
│  └──────────────────────────┘   │
└─────────────────────────────────┘
            ↕ REST API
┌─────────────────────────────────┐
│    Customer WordPress Sites     │
│  ┌──────────────────────────┐   │
│  │  40+ SEO Features        │   │
│  │  Content Optimization    │   │
│  │  Rank Tracking           │   │
│  │  Schema Markup           │   │
│  └──────────────────────────┘   │
└─────────────────────────────────┘
```

See [ARCHITECTURE.md](ARCHITECTURE.md) for detailed system design.

## 💎 Feature Tiers

### Free Tier
- Basic SEO meta fields
- XML sitemap
- Robots.txt editor

### Starter Tier ($19/month)
- All Free features
- Link Assistant
- Redirect Manager
- Image Alt Generator
- Content Rewriter

### Professional Tier ($49/month)
- All Starter features
- Schema Markup
- Local SEO
- Rank Tracker
- Content Optimizer
- SERP Competitor Analysis
- Topic Clusters
- Keyword Explorer
- Google Search Console Integration

### Business Tier ($79/month)
- All Professional features
- AI Content Writer
- Content Repurposer
- Bulk Optimizer
- Content Decay Monitor

### Agency Tier ($99/month)
- All Business features
- SEO Revisions
- AI Plagiarism Checker
- White-label options
- Unlimited API calls
- Multi-site management

## 🔧 Technical Stack

**Backend:**
- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+

**APIs:**
- OpenAI GPT-4 / GPT-3.5
- Anthropic Claude
- Mistral AI
- SERP APIs (SerpAPI, DataForSEO)

**Frontend:**
- WordPress Admin UI
- Gutenberg Blocks
- React (for editor sidebar)
- Custom CSS with modern card design

## 📦 Plugin Structure

### SaaS Dashboard Plugin (`ai-seo-saas-dashboard/`)

```
ai-seo-saas-dashboard/
├── ai-seo-saas-dashboard.php    # Main plugin file
├── includes/
│   ├── dashboard.php             # Main orchestrator
│   ├── tenantrepository.php      # Multi-tenant DB management
│   ├── licensekeygenerator.php   # License generation
│   ├── licenseapi.php            # REST API endpoints
│   ├── licenseadmin.php          # Admin UI
│   ├── apigateway.php            # AI proxy & SERP
│   ├── whitelabeladmin.php       # White-label settings
│   └── saassettings.php          # Global settings
└── assets/
    └── admin.js                  # Admin JavaScript
```

### Client Plugin (`ai-seo-client/`)

```
ai-seo-client/
├── ai-seo-client.php            # Main plugin file
├── includes/
│   ├── client.php               # Main plugin class
│   ├── licensevalidator.php     # License validation
│   ├── dashboardapi.php         # SaaS API client
│   ├── llmclient.php            # AI proxy client
│   ├── settings.php             # Settings management
│   ├── [40+ feature classes]    # Individual features
│   └── ...
├── assets/
│   ├── client-admin.css         # Admin styles
│   ├── client-admin.js          # Admin JavaScript
│   ├── seo-sidebar.js           # Gutenberg sidebar
│   └── editor-sidebar.js        # Classic editor
└── languages/
    └── ai-seo-client-nl_NL.po   # Translations
```

## 🔐 Security

- **License Validation** - Every API call validates license and tenant
- **Rate Limiting** - Per-tenant API call limits based on tier
- **Tenant Isolation** - Complete data separation between customers
- **HTTPS Required** - All API communication over SSL
- **API Key Security** - Keys stored in wp-config.php, never in database
- **Input Sanitization** - All user input sanitized and validated

## 📊 Monitoring & Analytics

### SaaS Dashboard Metrics

- Active licenses count
- API calls per tenant
- API costs per tenant (OpenAI, Anthropic)
- Revenue per tier
- Feature usage statistics
- Error rates and response times

### Client Plugin Metrics

- Content generated (word count)
- Keywords tracked
- Rankings monitored
- Schema markup implemented
- Performance improvements

## 🐛 Troubleshooting

### Common Issues

**"No route found" error**
```bash
wp rewrite flush
```

**License activation fails**
- Verify dashboard URL is correct (include https://)
- Check license status in SaaS Dashboard
- Ensure REST API is enabled

**White-label not applying**
- Deactivate and reactivate license
- Hard refresh browser (Ctrl+Shift+R)

**Division by zero errors**
- Update to v1.0.4 or higher

See [SETUP.md](SETUP.md) for detailed troubleshooting guide.

## 📝 Version History

### v1.0.8 (Current)
- ✅ UI modernization - All pages now use modern card style
- ✅ Permission fixes - All capabilities set to `manage_options`
- ✅ Database error fixes - ContentPerformanceMonitor SQL fix
- ✅ Dashboard card link corrections

### v1.0.7
- ✅ Dashboard card link fixes
- ✅ Content Calendar permission fix

### v1.0.6
- ✅ White-label sync implementation
- ✅ Branding applies to client UI

### v1.0.5
- ✅ SaaS-only features removed from client plugin
- ✅ WhiteLabelAdmin added to SaaS Dashboard

### v1.0.4
- ✅ All division by zero errors fixed (9 files)

### v1.0.3
- ✅ REST API namespace fixes
- ✅ Plugin version sync

See full changelog in [ARCHITECTURE.md](ARCHITECTURE.md#version-history).

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### Development Setup

```bash
# Clone repository
git clone https://github.com/yourusername/sseo-ai.git

# Install dependencies (if any)
composer install

# Set up local WordPress
wp core download
wp core config --dbname=sseo_ai --dbuser=root --dbpass=password
wp core install --url=http://localhost --title="SSEO AI Dev" --admin_user=admin

# Activate plugins
wp plugin activate ai-seo-saas-dashboard
wp plugin activate ai-seo-client
```

## 📄 License

This project is licensed under the GPL-2.0 License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **OpenAI** - GPT models for content generation
- **Anthropic** - Claude AI for advanced analysis
- **WordPress** - Platform foundation
- **SerpAPI** - SERP data provider

## 📞 Support

- **Documentation:** [ARCHITECTURE.md](ARCHITECTURE.md) | [SETUP.md](SETUP.md)
- **Issues:** [GitHub Issues](https://github.com/yourusername/sseo-ai/issues)
- **Email:** support@yourdomain.com (configure in white-label settings)

## 🗺️ Roadmap

### Planned Features

- [ ] Webhook notifications for license events
- [ ] Advanced analytics dashboard with charts
- [ ] Multi-language support (i18n)
- [ ] Custom AI model training per tenant
- [ ] Advanced white-label customization (CSS editor)
- [ ] Client portal for end-users
- [ ] Automated PDF report generation
- [ ] Integration marketplace (Zapier, Make, etc.)
- [ ] Mobile app for rank tracking
- [ ] A/B testing for content optimization

### In Progress

- [x] UI modernization (v1.0.8)
- [x] White-label sync (v1.0.6)
- [x] Permission fixes (v1.0.8)

## 📈 Performance

### Benchmarks

- License validation: < 100ms
- AI content generation: 2-5 seconds (depends on model)
- SERP analysis: 1-3 seconds (cached) / 5-10 seconds (fresh)
- Dashboard load: < 500ms

### Optimization Tips

1. Enable object caching (Redis/Memcached)
2. Use CDN for static assets
3. Cache SERP data for 24 hours
4. Use GPT-3.5 instead of GPT-4 for cost savings
5. Implement request batching where possible

## 🔬 Testing

```bash
# Run PHP tests (if available)
composer test

# Test license activation
wp eval "
\$api = new SSEOAIClient\DashboardAPI(new SSEOAIClient\Settings());
\$result = \$api->activateLicense('SSEO-TEST-KEY', 'https://saas.test');
print_r(\$result);
"

# Test REST API
curl -X POST https://saas.test/wp-json/ai-seo-saas/v1/license/validate \
  -H "Content-Type: application/json" \
  -d '{"license_key":"test","site_url":"https://example.com"}'
```

## 💡 Best Practices

### For SaaS Providers

1. **Monitor API costs** - Set up alerts for unusual spending
2. **Regular backups** - Daily database backups recommended
3. **Update regularly** - Keep WordPress and plugins updated
4. **Use staging** - Test updates on staging before production
5. **Document changes** - Keep changelog updated

### For Customers

1. **Keep plugin updated** - New features and bug fixes
2. **Use target keywords** - Better AI optimization results
3. **Review suggestions** - AI is a tool, not a replacement for human judgment
4. **Monitor rankings** - Track progress over time
5. **Backup before updates** - Always have a restore point

## 🎯 Use Cases

### SEO Agencies
- White-label the platform with your branding
- Manage multiple client sites from one dashboard
- Track all client SEO metrics in one place
- Generate automated reports

### Freelance SEO Consultants
- Offer premium SEO tools to clients
- Charge monthly subscription
- Automate repetitive SEO tasks
- Focus on strategy, not manual work

### SaaS Entrepreneurs
- Launch your own SEO SaaS business
- Customize pricing and features
- Scale to thousands of customers
- Integrate with existing tools

### WordPress Developers
- Add SEO features to client sites
- Offer ongoing SEO maintenance
- Differentiate from competitors
- Recurring revenue stream

---

**Made with ❤️ for the WordPress community**

For detailed technical documentation, see [ARCHITECTURE.md](ARCHITECTURE.md).  
For setup instructions, see [SETUP.md](SETUP.md).
