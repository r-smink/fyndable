package com.fyndable.mobile.data.api

import com.fyndable.mobile.data.store.AuthStore
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit

object NetworkModule {

    private val json = Json {
        ignoreUnknownKeys = true
        coerceInputValues = true
        encodeDefaults = false
    }

    private var currentApi: FyndableApi? = null
    private var currentSiteUrl: String? = null

    fun getApi(authStore: AuthStore): FyndableApi {
        val creds = runBlocking { authStore.getCredentials() }
        val siteUrl = creds?.siteUrl ?: ""

        if (currentApi != null && currentSiteUrl == siteUrl && siteUrl.isNotEmpty()) {
            return currentApi!!
        }

        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BASIC
        }

        val client = OkHttpClient.Builder()
            .addInterceptor(AuthInterceptor { runBlocking { authStore.getCredentials() } })
            .addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(120, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()

        val baseUrl = if (siteUrl.endsWith("/")) {
            "${siteUrl}wp-json/sseo-ai/v1/"
        } else {
            "$siteUrl/wp-json/sseo-ai/v1/"
        }

        val contentType = "application/json".toMediaType()
        val retrofit = Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(json.asConverterFactory(contentType))
            .build()

        currentApi = retrofit.create(FyndableApi::class.java)
        currentSiteUrl = siteUrl
        return currentApi!!
    }

    fun invalidate() {
        currentApi = null
        currentSiteUrl = null
    }
}
