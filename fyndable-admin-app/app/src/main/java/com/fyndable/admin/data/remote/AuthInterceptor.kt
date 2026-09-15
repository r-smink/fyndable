package com.fyndable.admin.data.remote

import com.fyndable.admin.data.prefs.CredentialStore
import okhttp3.Interceptor
import okhttp3.Response
import java.net.URI

/**
 * Adds HTTP Basic Auth (WordPress Application Password) to every request and
 * rewrites the placeholder base URL host to the saved portal URL.
 *
 * The Retrofit instance is built with a placeholder base URL
 * (`https://localhost/wp-json/ai-seo-saas/v1/`). This interceptor replaces the
 * scheme + host + port with the user's saved portal URL so all relative
 * endpoint paths resolve correctly without rebuilding the Retrofit client.
 */
class AuthInterceptor(private val credentialStore: CredentialStore) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val original = chain.request()
        val builder = original.newBuilder()

        // --- Basic Auth ---
        val username = credentialStore.getUsername()
        val appPassword = credentialStore.getAppPassword()
        if (username.isNotBlank() && appPassword.isNotBlank()) {
            val credentials = okhttp3.Credentials.basic(username, appPassword)
            builder.header("Authorization", credentials)
        }

        // --- Dynamic base URL ---
        val savedBaseUrl = credentialStore.baseUrl
        if (savedBaseUrl.isNotBlank()) {
            try {
                val savedUri = URI(savedBaseUrl)
                val newUrl = original.url.newBuilder()
                    .scheme(savedUri.scheme ?: "https")
                    .host(savedUri.host)
                    .apply {
                        if (savedUri.port > 0 && savedUri.port != savedUri.defaultPort()) {
                            port(savedUri.port)
                        }
                    }
                    .build()
                builder.url(newUrl)
            } catch (_: Exception) {
                // If the saved URL is malformed, fall through with the original URL.
            }
        }

        return chain.proceed(builder.build())
    }
}

/** Returns the default port for the URI's scheme, or -1 if unknown. */
private fun URI.defaultPort(): Int = when (scheme?.lowercase()) {
    "http" -> 80
    "https" -> 443
    else -> -1
}
