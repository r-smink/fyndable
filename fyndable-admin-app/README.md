# Fyndable Admin — Android App

Internal management app for the Fyndable SaaS portal. Built with Kotlin + Jetpack Compose.

## Features

- **Dashboard** — license stats, MRR/ARR, revenue by tier, active/paid tenants
- **Licenses** — list with filters, generate (single/batch), revoke, copy key
- **GEO Readiness Scan** — run prospect scans (URL + keywords), view recent scans, full report with score breakdown, findings, recommendations
- **Support Tickets** — list with filters/search, ticket detail with conversation thread, staff reply, update status
- **Usage Reports** — per-tenant API calls / cost / content generated, onboarding status
- **AI Models** — view and edit standard/premium model routing per use case, refresh model list from OpenRouter

## Authentication

The app authenticates against the WordPress SaaS portal using **Application Passwords** (HTTP Basic Auth).

### Setup (one-time, on portal.fyndable.ai)

1. Create a dedicated WordPress user with `manage_options` (e.g. `app-admin`).
2. Go to **Users → Profile → Application Passwords**, enter a name (e.g. "Android Admin App") and click **Add New Application Password**.
3. Copy the generated password (format: `xxxx xxxx xxxx xxxx xxxx xxxxx`).

### In the app

- **WordPress Username**: the WP user login (e.g. `app-admin`)
- **Application Password**: the password from step 3 above (spaces are stripped automatically)

The portal URL (`https://portal.fyndable.ai`) is hardcoded in the app and not shown or editable in the UI.

Credentials are stored encrypted on-device using `EncryptedSharedPreferences`.

## Backend API

The app consumes the admin REST API at `ai-seo-saas/v1/admin/*` on the SaaS portal. See `AGENTS.md` in the workspace root for the full endpoint reference.

## Build

### Prerequisites

- Android Studio Ladybug (or newer)
- JDK 17
- Android SDK 34

### Steps

1. Open the `fyndable-admin-app` folder in Android Studio.
2. Android Studio will generate the Gradle wrapper jar automatically (or run `gradle wrapper` once if you have Gradle installed).
3. Let Gradle sync — it will download all dependencies.
4. Connect an Android device or start an emulator (API 26+).
5. Click **Run** or run `./gradlew assembleDebug` from the command line.

The debug APK will be at `app/build/outputs/apk/debug/app-debug.apk`.

## Tech Stack

| Layer | Library |
|-------|---------|
| UI | Jetpack Compose (Material 3) |
| DI | Hilt |
| Networking | Retrofit + OkHttp |
| Serialization | kotlinx.serialization |
| Storage | EncryptedSharedPreferences |
| Navigation | Navigation Compose |
| Image loading | Coil |

## Project Structure

```
app/src/main/java/com/fyndable/admin/
├── FyndableApp.kt              # Application (Hilt)
├── MainActivity.kt             # Single-activity Compose host
├── data/
│   ├── remote/
│   │   ├── ApiService.kt       # Retrofit interface (all admin endpoints)
│   │   ├── AuthInterceptor.kt  # Basic Auth + dynamic base URL
│   │   └── Dtos.kt             # All request/response DTOs
│   ├── prefs/
│   │   └── CredentialStore.kt  # Encrypted credential storage
│   └── repo/
│       └── Repositories.kt     # All repositories + ApiResult wrapper
├── di/
│   └── AppModule.kt            # Hilt providers
└── ui/
    ├── theme/Theme.kt          # Fyndable brand colors + typography
    ├── nav/AppNavigation.kt    # Bottom nav routes
    ├── components/             # Reusable UI components
    ├── login/                  # Login screen
    ├── dashboard/              # Dashboard screen
    ├── licenses/               # Licenses screen (list, generate, revoke)
    ├── geoscan/                # GEO Readiness scan screen
    ├── support/                # Support tickets screen
    ├── usage/                  # Usage reports screen
    └── aimodels/               # AI model routing screen
```

## Notes

- GEO scans can take 30–90 seconds. The OkHttp read timeout is set to 120s and the UI shows a progress indicator.
- The portal base URL is hardcoded (`https://portal.fyndable.ai`) and not user-configurable. The `AuthInterceptor` reads it from `CredentialStore` at request time.
- Central provider API keys (OpenAI/OpenRouter/DataForSEO) never leave the server — the app only manages routing configuration, not credentials.
