package com.fyndable.mobile.data.store

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.fyndable.mobile.data.api.AuthCredentials
import java.util.UUID
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.serialization.Serializable
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json

private val Context.authDataStore: DataStore<Preferences> by preferencesDataStore(name = "auth_store")

private val json = Json { ignoreUnknownKeys = true }

@Serializable
data class SiteAccount(
    val id: String,
    val label: String = "",
    val siteUrl: String,
    val username: String,
    val password: String,
    val uuid: String? = null,
    val lastUsed: Long = 0L,
)

class AuthStore(private val context: Context) {

    private object Keys {
        val SITES = stringPreferencesKey("sites_json")
        val ACTIVE_SITE = stringPreferencesKey("active_site_id")
    }

    val sitesFlow: Flow<List<SiteAccount>> = context.authDataStore.data.map { prefs ->
        prefs[Keys.SITES]?.let { json.decodeFromString<List<SiteAccount>>(it) } ?: emptyList()
    }

    val activeSiteFlow: Flow<SiteAccount?> = context.authDataStore.data.map { prefs ->
        val sites = prefs[Keys.SITES]?.let { json.decodeFromString<List<SiteAccount>>(it) } ?: emptyList()
        val activeId = prefs[Keys.ACTIVE_SITE]
        sites.find { it.id == activeId }
    }

    val credentialsFlow: Flow<AuthCredentials?> = activeSiteFlow.map { site ->
        site?.let { AuthCredentials(it.username, it.password, it.siteUrl) }
    }

    suspend fun getCredentials(): AuthCredentials? = credentialsFlow.first()

    suspend fun getSites(): List<SiteAccount> = sitesFlow.first()

    suspend fun getActiveSite(): SiteAccount? = activeSiteFlow.first()

    suspend fun saveCredentials(
        username: String,
        password: String,
        siteUrl: String,
        uuid: String? = null,
        label: String? = null,
    ) {
        val cleanUrl = siteUrl.trim().trimEnd('/')
        val existing = getSites()
        val siteId = uuid ?: existing.find {
            it.siteUrl == cleanUrl && it.username == username
        }?.id ?: UUID.randomUUID().toString()
        val site = SiteAccount(
            id = siteId,
            label = label?.takeIf { it.isNotBlank() } ?: cleanUrl.replace(Regex("^https?://"), ""),
            siteUrl = cleanUrl,
            username = username,
            password = password,
            uuid = uuid,
            lastUsed = System.currentTimeMillis(),
        )
        val updated = existing.filterNot { it.siteUrl == cleanUrl && it.username == username } + site
        context.authDataStore.edit { prefs ->
            prefs[Keys.SITES] = json.encodeToString(updated)
            prefs[Keys.ACTIVE_SITE] = site.id
        }
    }

    suspend fun selectSite(id: String) {
        context.authDataStore.edit { prefs ->
            val sites = prefs[Keys.SITES]?.let { json.decodeFromString<List<SiteAccount>>(it) } ?: return@edit
            if (sites.any { it.id == id }) {
                prefs[Keys.ACTIVE_SITE] = id
            }
        }
    }

    suspend fun deleteSite(id: String) {
        context.authDataStore.edit { prefs ->
            val sites = prefs[Keys.SITES]?.let { json.decodeFromString<List<SiteAccount>>(it) }?.toMutableList() ?: return@edit
            sites.removeAll { it.id == id }
            prefs[Keys.SITES] = json.encodeToString(sites)
            if (prefs[Keys.ACTIVE_SITE] == id) {
                if (sites.isNotEmpty()) {
                    prefs[Keys.ACTIVE_SITE] = sites.first().id
                } else {
                    prefs.remove(Keys.ACTIVE_SITE)
                }
            }
        }
    }

    suspend fun clear() {
        context.authDataStore.edit { it.clear() }
    }
}
