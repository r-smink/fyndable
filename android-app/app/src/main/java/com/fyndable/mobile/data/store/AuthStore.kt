package com.fyndable.mobile.data.store

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.fyndable.mobile.data.api.AuthCredentials
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.authDataStore: DataStore<Preferences> by preferencesDataStore(name = "auth_store")

class AuthStore(private val context: Context) {

    private object Keys {
        val USERNAME = stringPreferencesKey("username")
        val PASSWORD = stringPreferencesKey("password")
        val SITE_URL = stringPreferencesKey("site_url")
    }

    val credentialsFlow: Flow<AuthCredentials?> = context.authDataStore.data.map { prefs ->
        val username = prefs[Keys.USERNAME]
        val password = prefs[Keys.PASSWORD]
        val siteUrl = prefs[Keys.SITE_URL]
        if (username != null && password != null && siteUrl != null) {
            AuthCredentials(username, password, siteUrl)
        } else {
            null
        }
    }

    suspend fun getCredentials(): AuthCredentials? = credentialsFlow.first()

    suspend fun saveCredentials(username: String, password: String, siteUrl: String) {
        context.authDataStore.edit { prefs ->
            prefs[Keys.USERNAME] = username
            prefs[Keys.PASSWORD] = password
            prefs[Keys.SITE_URL] = siteUrl
        }
    }

    suspend fun clear() {
        context.authDataStore.edit { it.clear() }
    }
}
