package com.fyndable.admin.data.prefs

import android.content.Context
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Stores the portal base URL, WordPress username and application password
 * in EncryptedSharedPreferences. All access is synchronous so it can be used
 * from OkHttp interceptors (which run on background threads and cannot call
 * suspend functions).
 *
 * The base URL should be the site root, e.g. `https://portal.fyndable.ai`.
 * The `/wp-json/ai-seo-saas/v1/` suffix is appended by the API layer.
 */
class CredentialStore(private val context: Context) {

    private val masterKey: MasterKey by lazy {
        MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
    }

    private val prefs by lazy {
        EncryptedSharedPreferences.create(
            context,
            "fyndable_credentials",
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    /**
     * Hardcoded portal URL — not user-configurable.
     * Kept in code (not in prefs) so it can never be overwritten or leaked via backups.
     */
    val baseUrl: String
        get() = PORTAL_BASE_URL

    fun getUsername(): String = prefs.getString(KEY_USERNAME, "") ?: ""
    fun getAppPassword(): String = prefs.getString(KEY_APP_PASSWORD, "") ?: ""

    fun setCredentials(username: String, appPassword: String) {
        prefs.edit()
            .putString(KEY_USERNAME, username.trim())
            .putString(KEY_APP_PASSWORD, appPassword.trim())
            .apply()
    }

    fun clearAll() {
        prefs.edit().clear().apply()
    }

    fun hasCredentials(): Boolean =
        getUsername().isNotBlank() && getAppPassword().isNotBlank()

    companion object {
        /** Fixed Fyndable SaaS portal URL. */
        const val PORTAL_BASE_URL = "https://portal.fyndable.ai"

        private const val KEY_USERNAME = "wp_username"
        private const val KEY_APP_PASSWORD = "wp_app_password"
    }
}
