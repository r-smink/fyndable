package com.fyndable.mobile.data.api

import com.fyndable.mobile.data.store.AuthStore
import java.util.concurrent.TimeUnit
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory

object NetworkModule {

    private val json = Json {
        ignoreUnknownKeys = true
        coerceInputValues = true
        encodeDefaults = false
    }

    fun getApi(authStore: AuthStore): FyndableApi {
        val creds = runBlocking { authStore.getCredentials() }
            ?: throw IllegalStateException("No active site selected")
        return getApi(creds)
    }

    fun getApi(creds: AuthCredentials): FyndableApi {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BASIC
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor(creds))
            .addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(120, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()

        val baseUrl = if (creds.siteUrl.endsWith("/")) {
            "${creds.siteUrl}wp-json/sseo-ai/v1/"
        } else {
            "${creds.siteUrl}/wp-json/sseo-ai/v1/"
        }

        val contentType = "application/json".toMediaType()
        val retrofit = Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(json.asConverterFactory(contentType))
            .build()

        return retrofit.create(FyndableApi::class.java)
    }

    fun invalidate() {
        // API instances are no longer cached here; callers obtain a fresh API each time.
    }
}
