/**
 * Fyndable I18n — EN/NL translation engine for checkout & customer portal.
 *
 * Detection order for initial language:
 *   1. ?lang= URL parameter
 *   2. window.FyndableI18nConfig.userLang (set by PHP from user meta)
 *   3. localStorage('fyndable_lang')
 *   4. navigator.language (starts with 'nl' → nl, otherwise en)
 *   5. fallback 'en'
 *
 * Persists choice in localStorage('fyndable_lang') and fires a 'langchange' event
 * on document so UI components can re-render.
 */
window.FyndableI18n = (function () {
    var STORAGE_KEY = 'fyndable_lang';
    var currentLang = 'en';

    var dictionaries = {
        en: {
            // Checkout / signup
            choose_your_plan: 'Choose Your Plan',
            trial_subtitle: 'Start free, upgrade anytime. No credit card required.',
            monthly: 'Monthly',
            yearly: 'Yearly',
            months_free: '2 months free',
            most_popular: 'Most Popular',
            back_to_plans: '← Back to plans',
            create_your_account: 'Create Your Account',
            you_selected: 'You selected:',
            your_name: 'Your Name',
            name_placeholder: 'John Doe',
            email_address: 'Email Address',
            email_placeholder: 'john@example.com',
            street_address: 'Street Address',
            street_placeholder: 'Main Street 123',
            postal_code: 'Postal Code',
            postal_placeholder: '1011 AB',
            city: 'City',
            city_placeholder: 'Amsterdam',
            country: 'Country',
            payment_method: 'Payment Method',
            create_account_btn: 'Create Account →',
            creating_account: 'Creating account...',
            fill_all_fields: 'Please fill in all fields.',
            signup_failed: 'Signup failed.',
            network_error: 'Network error. Please try again.',
            failed_to_load_plans: 'Failed to load plans. Please refresh.',
            welcome_to_fyndable: 'Welcome to Fyndable!',
            account_ready: 'Your account is ready. Copy your license key below and paste it into the Fyndable plugin on your WordPress site.',
            next_steps: 'Next steps:',
            next_step_1: '1. Install the Fyndable plugin on your WordPress site',
            next_step_2: '2. Go to Settings → Fyndable',
            next_step_3: '3. Paste your license key and click Activate',
            copy_license_key: 'Copy License Key',
            copied: '✓ Copied!',

            // Customer portal — tabs
            tab_subscription: 'Subscription',
            tab_license: 'License',
            tab_usage: 'Usage',
            tab_download: 'Plugin',
            tab_invoices: 'Invoices',
            tab_account: 'Account',

            // Customer portal — header
            sign_out: 'Sign out',
            welcome_to: 'Welcome to %s',
            sign_in_subtitle: 'Sign in to manage your subscription, view invoices, and download the plugin.',
            sign_in: 'Sign in',

            // Customer portal — loading & status
            loading: 'Loading...',
            loading_subscription: 'Loading subscription...',
            loading_license: 'Loading license...',
            loading_usage: 'Loading usage...',
            loading_invoices: 'Loading invoices...',
            loading_account: 'Loading account...',
            error_generic: 'Something went wrong. Please try again.',
            confirm_cancel: 'Are you sure you want to cancel your subscription? You will retain access until the end of your current billing period.',
            cancelled_success: 'Subscription cancelled successfully.',
            copied_clipboard: 'Copied to clipboard!',
            error_invoice: 'Error loading invoice',

            // Customer portal — admin/agency notices
            admin_notice: 'You are logged in as an administrator.',
            go_to_dashboard: 'Go to SaaS Dashboard',
            agency_notice: 'You are logged in as an agency partner.',
            go_to_agency: 'Go to Agency Portal',
            no_access: 'You do not have access to the customer portal.',
            no_subscription: 'No subscription found for your account. Please contact support.',

            // Customer portal — license tab
            license_label: 'License Key',
            copy: 'Copy',
            status: 'Status',
            tier: 'Tier',
            max_sites: 'Max Sites',
            rate_limit: 'Rate Limit',
            rate_limit_unit: '/hour',
            api_calls_limit: 'API Calls Limit',
            api_calls_limit_unit: '/month',
            expires: 'Expires',
            license_help: 'Use this license key to activate the Fyndable plugin on your WordPress site. Go to Settings → Fyndable, paste the key, and click Activate.',

            // Customer portal — subscription tab
            subscription_details: 'Subscription Details',
            plan: 'Plan',
            billing_period: 'Billing Period',
            amount: 'Amount',
            payment_status: 'Payment Status',
            started: 'Started',
            renews_expires: 'Renews / Expires',
            next_payment: 'Next Payment',
            cancel_subscription: 'Cancel Subscription',
            cancel_subscription_desc: 'Cancelling will stop future payments. You retain access until the end of your current billing period.',
            monthly_label: 'Monthly',
            yearly_label: 'Yearly',

            // Customer portal — usage tab
            usage_for: 'Usage for',
            api_calls: 'API Calls',
            api_cost: 'API Cost',
            serp_requests: 'SERP Requests',
            content_generated: 'Content Generated',
            keywords_tracked: 'Keywords Tracked',
            api_usage: 'API Usage',

            // Customer portal — download tab
            download_plugin: 'Download Plugin',
            latest_version: 'Latest version:',
            download_plugin_version: 'Download Plugin (v%s)',
            your_license_key: 'Your License Key',
            dashboard_url: 'Dashboard URL:',
            installation_instructions: 'Installation Instructions',
            install_step_1: 'Download the zip file above.',
            install_step_2: 'In your WordPress admin, go to Plugins > Add New > Upload Plugin.',
            install_step_3: 'Upload the zip file and click Install Now, then Activate.',
            install_step_4: 'Go to the plugin settings and enter your license key and dashboard URL.',
            install_step_5: 'Save settings — the plugin will validate your license automatically.',

            // Customer portal — invoices tab
            no_invoices: 'No invoices yet.',
            invoice_number: 'Invoice #',
            date: 'Date',
            description: 'Description',
            subscription_default: 'Subscription',
            view: 'View',
            download_pdf: 'Download PDF',
            loading_invoice: 'Loading invoice...',
            allow_popups: 'Please allow pop-ups to download the invoice as PDF.',

            // Customer portal — account tab
            account_settings: 'Account Settings',
            name: 'Name',
            name_placeholder: 'Your name',
            domain: 'Domain',
            domain_placeholder: 'https://yoursite.com',
            save_changes: 'Save Changes',
            password: 'Password',
            password_desc: 'To change your password, use the password reset link.',
            reset_password: 'Reset Password',

            // Language toggle
            lang_en: 'EN',
            lang_nl: 'NL'
        },

        nl: {
            // Checkout / signup
            choose_your_plan: 'Kies je abonnement',
            trial_subtitle: 'Start gratis, upgrade wanneer je wilt. Geen creditcard nodig.',
            monthly: 'Maandelijks',
            yearly: 'Jaarlijks',
            months_free: '2 maanden gratis',
            most_popular: 'Meest gekozen',
            back_to_plans: '← Terug naar abonnementen',
            create_your_account: 'Maak je account aan',
            you_selected: 'Je hebt gekozen:',
            your_name: 'Je naam',
            name_placeholder: 'Jan Jansen',
            email_address: 'E-mailadres',
            email_placeholder: 'jan@voorbeeld.nl',
            street_address: 'Straat en huisnummer',
            street_placeholder: 'Hoofdstraat 123',
            postal_code: 'Postcode',
            postal_placeholder: '1011 AB',
            city: 'Plaats',
            city_placeholder: 'Amsterdam',
            country: 'Land',
            payment_method: 'Betaalmethode',
            create_account_btn: 'Account aanmaken →',
            creating_account: 'Account aanmaken...',
            fill_all_fields: 'Vul alle velden in.',
            signup_failed: 'Aanmelden mislukt.',
            network_error: 'Netwerkfout. Probeer het opnieuw.',
            failed_to_load_plans: 'Abonnementen konden niet worden geladen. Vernieuw de pagina.',
            welcome_to_fyndable: 'Welkom bij Fyndable!',
            account_ready: 'Je account is klaar. Kopieer je licentiesleutel hieronder en plak deze in de Fyndable-plugin op je WordPress-site.',
            next_steps: 'Volgende stappen:',
            next_step_1: '1. Installeer de Fyndable-plugin op je WordPress-site',
            next_step_2: '2. Ga naar Instellingen → Fyndable',
            next_step_3: '3. Plak je licentiesleutel en klik op Activeren',
            copy_license_key: 'Licentiesleutel kopiëren',
            copied: '✓ Gekopieerd!',

            // Customer portal — tabs
            tab_subscription: 'Abonnement',
            tab_license: 'Licentie',
            tab_usage: 'Gebruik',
            tab_download: 'Plugin',
            tab_invoices: 'Facturen',
            tab_account: 'Account',

            // Customer portal — header
            sign_out: 'Uitloggen',
            welcome_to: 'Welkom bij %s',
            sign_in_subtitle: 'Log in om je abonnement te beheren, facturen te bekijken en de plugin te downloaden.',
            sign_in: 'Inloggen',

            // Customer portal — loading & status
            loading: 'Laden...',
            loading_subscription: 'Abonnement laden...',
            loading_license: 'Licentie laden...',
            loading_usage: 'Gebruik laden...',
            loading_invoices: 'Facturen laden...',
            loading_account: 'Account laden...',
            error_generic: 'Er ging iets mis. Probeer het opnieuw.',
            confirm_cancel: 'Weet je zeker dat je je abonnement wilt opzeggen? Je behoudt toegang tot het einde van de huidige factureringsperiode.',
            cancelled_success: 'Abonnement succesvol opgezegd.',
            copied_clipboard: 'Naar klembord gekopieerd!',
            error_invoice: 'Fout bij laden van factuur',

            // Customer portal — admin/agency notices
            admin_notice: 'Je bent ingelogd als beheerder.',
            go_to_dashboard: 'Naar SaaS Dashboard',
            agency_notice: 'Je bent ingelogd als agency-partner.',
            go_to_agency: 'Naar Agency Portal',
            no_access: 'Je hebt geen toegang tot het klantportaal.',
            no_subscription: 'Geen abonnement gevonden voor je account. Neem contact op met support.',

            // Customer portal — license tab
            license_label: 'Licentiesleutel',
            copy: 'Kopiëren',
            status: 'Status',
            tier: 'Tier',
            max_sites: 'Max. sites',
            rate_limit: 'Rate limit',
            rate_limit_unit: '/uur',
            api_calls_limit: 'API-calls limiet',
            api_calls_limit_unit: '/maand',
            expires: 'Verloopt',
            license_help: 'Gebruik deze licentiesleutel om de Fyndable-plugin te activeren op je WordPress-site. Ga naar Instellingen → Fyndable, plak de sleutel en klik op Activeren.',

            // Customer portal — subscription tab
            subscription_details: 'Abonnementsgegevens',
            plan: 'Abonnement',
            billing_period: 'Factureringsperiode',
            amount: 'Bedrag',
            payment_status: 'Betaalstatus',
            started: 'Gestart op',
            renews_expires: 'Vernieuwt / Verloopt',
            next_payment: 'Volgende betaling',
            cancel_subscription: 'Abonnement opzeggen',
            cancel_subscription_desc: 'Opzeggen stopt toekomstige betalingen. Je behoudt toegang tot het einde van de huidige factureringsperiode.',
            monthly_label: 'Maandelijks',
            yearly_label: 'Jaarlijks',

            // Customer portal — usage tab
            usage_for: 'Gebruik voor',
            api_calls: 'API-calls',
            api_cost: 'API-kosten',
            serp_requests: 'SERP-verzoeken',
            content_generated: 'Content gegenereerd',
            keywords_tracked: 'Keywords bijgehouden',
            api_usage: 'API-gebruik',

            // Customer portal — download tab
            download_plugin: 'Plugin downloaden',
            latest_version: 'Laatste versie:',
            download_plugin_version: 'Plugin downloaden (v%s)',
            your_license_key: 'Jouw licentiesleutel',
            dashboard_url: 'Dashboard URL:',
            installation_instructions: 'Installatie-instructies',
            install_step_1: 'Download het zip-bestand hierboven.',
            install_step_2: 'Ga in je WordPress-admin naar Plugins > Nieuwe toevoegen > Plugin uploaden.',
            install_step_3: 'Upload het zip-bestand en klik op Nu installeren, daarna op Activeren.',
            install_step_4: 'Ga naar de plugin-instellingen en vul je licentiesleutel en dashboard-URL in.',
            install_step_5: 'Sla op — de plugin valideert je licentie automatisch.',

            // Customer portal — invoices tab
            no_invoices: 'Nog geen facturen.',
            invoice_number: 'Factuur #',
            date: 'Datum',
            description: 'Omschrijving',
            subscription_default: 'Abonnement',
            view: 'Bekijken',
            download_pdf: 'Download PDF',
            loading_invoice: 'Factuur laden...',
            allow_popups: 'Sta pop-ups toe om de factuur als PDF te downloaden.',

            // Customer portal — account tab
            account_settings: 'Accountinstellingen',
            name: 'Naam',
            name_placeholder: 'Je naam',
            domain: 'Domein',
            domain_placeholder: 'https://jouwwebsite.nl',
            save_changes: 'Wijzigingen opslaan',
            password: 'Wachtwoord',
            password_desc: 'Gebruik de wachtwoord-reset-link om je wachtwoord te wijzigen.',
            reset_password: 'Wachtwoord resetten',

            // Language toggle
            lang_en: 'EN',
            lang_nl: 'NL'
        }
    };

    function detectLang() {
        // 1. URL parameter ?lang=
        var urlParams = new URLSearchParams(window.location.search);
        var urlLang = urlParams.get('lang');
        if (urlLang === 'nl' || urlLang === 'en') {
            return urlLang;
        }

        // 2. PHP-provided user language (from wp_localize_script)
        var config = window.FyndableI18nConfig || {};
        if (config.userLang === 'nl' || config.userLang === 'en') {
            return config.userLang;
        }

        // 3. localStorage
        try {
            var stored = localStorage.getItem(STORAGE_KEY);
            if (stored === 'nl' || stored === 'en') {
                return stored;
            }
        } catch (e) { /* localStorage may be unavailable */ }

        // 4. navigator.language
        var browserLang = (navigator.language || navigator.userLanguage || 'en').toLowerCase();
        if (browserLang.indexOf('nl') === 0) {
            return 'nl';
        }

        // 5. fallback
        return 'en';
    }

    function t(key, replacements) {
        var dict = dictionaries[currentLang] || dictionaries.en;
        var value = dict[key];
        if (value === undefined) {
            // Fallback to English if key missing in current language
            value = dictionaries.en[key];
            if (value === undefined) {
                return key;
            }
        }
        if (replacements && typeof replacements === 'object') {
            for (var placeholder in replacements) {
                if (Object.prototype.hasOwnProperty.call(replacements, placeholder)) {
                    value = value.replace(new RegExp('%' + placeholder, 'g'), replacements[placeholder]);
                }
            }
        }
        return value;
    }

    function getLang() {
        return currentLang;
    }

    function setLang(lang) {
        if (lang !== 'nl' && lang !== 'en') {
            return;
        }
        currentLang = lang;
        try {
            localStorage.setItem(STORAGE_KEY, lang);
        } catch (e) { /* ignore */ }
        // Fire event so UI components can re-render
        document.dispatchEvent(new CustomEvent('langchange', { detail: { lang: lang } }));
    }

    function init() {
        currentLang = detectLang();
        // Persist the detected language so it's stable
        try {
            localStorage.setItem(STORAGE_KEY, currentLang);
        } catch (e) { /* ignore */ }
    }

    init();

    return {
        t: t,
        getLang: getLang,
        setLang: setLang,
        dictionaries: dictionaries
    };
})();
