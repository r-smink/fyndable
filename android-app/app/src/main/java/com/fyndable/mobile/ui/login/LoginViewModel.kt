package com.fyndable.mobile.ui.login

import android.util.Base64
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.fyndable.mobile.data.api.AuthCredentials
import com.fyndable.mobile.data.model.QrLoginPayload
import com.fyndable.mobile.data.store.AuthStore
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import okhttp3.OkHttpClient
import okhttp3.Request

class LoginViewModel(private val authStore: AuthStore) : ViewModel() {

    sealed class LoginState {
        data object Idle : LoginState()
        data object Loading : LoginState()
        data object Success : LoginState()
        data class Error(val message: String) : LoginState()
    }

    private val _state = MutableStateFlow<LoginState>(LoginState.Idle)
    val state: StateFlow<LoginState> = _state.asStateFlow()

    private val json = Json { ignoreUnknownKeys = true }

    fun login(username: String, password: String, siteUrl: String) {
        if (username.isBlank() || password.isBlank() || siteUrl.isBlank()) {
            _state.value = LoginState.Error("Vul alle velden in")
            return
        }

        _state.value = LoginState.Loading
        viewModelScope.launch {
            try {
                val cleanUrl = normalizeUrl(siteUrl)
                val testUrl = "$cleanUrl/wp-json/sseo-ai/v1/keywords?limit=1"
                val basic = Base64.encodeToString(
                    "$username:$password".toByteArray(),
                    Base64.NO_WRAP
                )

                val client = OkHttpClient.Builder()
                    .connectTimeout(15, java.util.concurrent.TimeUnit.SECONDS)
                    .readTimeout(15, java.util.concurrent.TimeUnit.SECONDS)
                    .build()

                val request = Request.Builder()
                    .url(testUrl)
                    .addHeader("Authorization", "Basic $basic")
                    .build()

                withContext(Dispatchers.IO) {
                    client.newCall(request).execute().use { response ->
                        if (response.isSuccessful) {
                            authStore.saveCredentials(username, password, cleanUrl)
                            _state.value = LoginState.Success
                        } else {
                            val errorBody = response.body?.string().orEmpty()
                            val msg = if (response.code == 401 || response.code == 403) {
                                "Ongeldige inloggegevens"
                            } else {
                                "Server fout: ${response.code}"
                            }
                            _state.value = LoginState.Error(msg)
                        }
                    }
                }
            } catch (e: Exception) {
                _state.value = LoginState.Error(
                    e.message ?: "Kan geen verbinding maken met de server"
                )
            }
        }
    }

    fun loginWithQr(qrJson: String) {
        _state.value = LoginState.Loading
        viewModelScope.launch {
            try {
                val payload = json.decodeFromString<QrLoginPayload>(qrJson)
                if (payload.site.isBlank() || payload.user.isBlank() || payload.pass.isBlank()) {
                    _state.value = LoginState.Error("Ongeldige QR-code")
                    return@launch
                }

                val cleanUrl = normalizeUrl(payload.site)
                val testUrl = "$cleanUrl/wp-json/sseo-ai/v1/keywords?limit=1"
                val basic = Base64.encodeToString(
                    "${payload.user}:${payload.pass}".toByteArray(),
                    Base64.NO_WRAP
                )

                val client = OkHttpClient.Builder()
                    .connectTimeout(15, java.util.concurrent.TimeUnit.SECONDS)
                    .readTimeout(15, java.util.concurrent.TimeUnit.SECONDS)
                    .build()

                val request = Request.Builder()
                    .url(testUrl)
                    .addHeader("Authorization", "Basic $basic")
                    .build()

                withContext(Dispatchers.IO) {
                    client.newCall(request).execute().use { response ->
                        if (response.isSuccessful) {
                            authStore.saveCredentials(payload.user, payload.pass, cleanUrl)
                            _state.value = LoginState.Success
                        } else {
                            _state.value = LoginState.Error(
                                if (response.code == 401 || response.code == 403)
                                    "Ongeldige inloggegevens" else "Server fout: ${response.code}"
                            )
                        }
                    }
                }
            } catch (e: kotlinx.serialization.SerializationException) {
                _state.value = LoginState.Error("Ongeldige QR-code format")
            } catch (e: Exception) {
                _state.value = LoginState.Error(
                    e.message ?: "Kan geen verbinding maken met de server"
                )
            }
        }
    }

    private fun normalizeUrl(url: String): String {
        var u = url.trim().trimEnd('/')
        if (!u.startsWith("http://") && !u.startsWith("https://")) {
            u = "https://$u"
        }
        return u
    }
}
