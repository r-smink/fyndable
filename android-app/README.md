# Fyndable Mobile

Native Android app for the Fyndable SEO platform.

## Tech Stack

- **Kotlin** 2.1.0
- **Jetpack Compose** with Material 3
- **Navigation Compose** for screen routing
- **Retrofit 2 + OkHttp** for REST API calls
- **kotlinx.serialization** for JSON parsing
- **DataStore Preferences** for credential storage
- **Coroutines** for async operations
- **minSdk 26** (Android 8.0) / **targetSdk 35** (Android 15)
- **AGP 8.7.3** / **Gradle 8.11.1**

## Architecture

```
com.fyndable.mobile/
├── FyndableApp.kt          — Application class
├── MainActivity.kt         — Single-activity entry point
├── data/
│   ├── api/                — FyndableApi interface, AuthInterceptor, NetworkModule
│   ├── model/              — Serializable data models
│   └── store/              — AuthStore (DataStore-backed credential storage)
└── ui/
    ├── theme/              — Fyndable brand colors, Material 3 theme, typography
    ├── components/         — Shared composables (logo, loading, error, empty states)
    ├── navigation/         — Bottom nav + NavHost
    ├── login/              — Login screen + ViewModel (Application Passwords auth)
    ├── keywords/           — Keyword research screen
    ├── clusters/           — Topic clusters screen
    ├── generate/           — AI article generator screen
    ├── posts/              — Created posts management screen
    └── performance/        — Rank tracking screen
```

## Authentication

The app uses **WordPress Application Passwords** (Basic auth). Users enter:
1. Their WordPress site URL
2. WordPress username
3. Application Password (created via WP Admin → Users → Profile → Application Passwords)

Credentials are stored in **DataStore** (encrypted at rest by Android Keystore on supported devices).

## API Endpoints

All calls go to the existing `sseo-ai/v1` WordPress REST API namespace:

| Feature          | Endpoint                        |
|------------------|---------------------------------|
| Keywords list    | `GET /keywords`                 |
| Add keyword      | `POST /keywords/add`            |
| Generate keywords| `POST /keywords/generate`       |
| Clusters list    | `GET /clusters/list`            |
| Cluster detail   | `GET /clusters/{id}`            |
| Generate cluster | `POST /clusters/generate`       |
| Generate content | `POST /clusters/generate-content`|
| Write article    | `POST /write-article`           |
| Created posts    | `GET /created-posts`            |
| Post stats       | `GET /created-posts/stats`      |
| Update post      | `PUT /created-posts/{id}`       |
| Delete post      | `DELETE /created-posts/{id}`    |
| Rank keywords    | `GET /ranks/keywords`           |
| Check rank       | `POST /ranks/check-now`         |

## Building

```bash
# Debug build
./gradlew assembleDebug

# Release build
./gradlew assembleRelease

# Install on connected device
./gradlew installDebug
```

## Brand Colors

| Name           | Hex       |
|----------------|-----------|
| Fyndable Blue  | `#34A9DD` |
| Fyndable Purple| `#5B57A0` |
| Fyndable Magenta| `#8C3793` |
| Dark Background| `#0F172A` |
| Dark Surface   | `#1E293B` |
