package com.fyndable.admin.di

import android.content.Context
import com.fyndable.admin.data.prefs.CredentialStore
import com.fyndable.admin.data.remote.ApiService
import com.fyndable.admin.data.remote.AuthInterceptor
import com.fyndable.admin.data.repo.AiModelRepository
import com.fyndable.admin.data.repo.GeoScanRepository
import com.fyndable.admin.data.repo.LicenseRepository
import com.fyndable.admin.data.repo.SupportRepository
import com.fyndable.admin.data.repo.TenantRepository
import com.fyndable.admin.data.repo.UsageRepository
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {

    @Provides
    @Singleton
    fun provideCredentialStore(@ApplicationContext context: Context): CredentialStore =
        CredentialStore(context)

    @Provides
    @Singleton
    fun provideJson(): Json = Json {
        ignoreUnknownKeys = true
        coerceInputValues = true
        isLenient = true
        explicitNulls = false
    }

    @Provides
    @Singleton
    fun provideAuthInterceptor(credentialStore: CredentialStore): AuthInterceptor =
        AuthInterceptor(credentialStore)

    @Provides
    @Singleton
    fun provideOkHttpClient(authInterceptor: AuthInterceptor): OkHttpClient {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BASIC
        }
        return OkHttpClient.Builder()
            .addInterceptor(authInterceptor)
            .addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(120, TimeUnit.SECONDS) // GEO scans can take 30-90s
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
    }

    @Provides
    @Singleton
    fun provideRetrofit(client: OkHttpClient, json: Json): Retrofit {
        // Base URL is hardcoded to the Fyndable portal. The AuthInterceptor
        // still reads it from CredentialStore so all requests resolve to the
        // same host regardless of the placeholder here.
        val contentType = "application/json".toMediaType()
        return Retrofit.Builder()
            .baseUrl("${com.fyndable.admin.data.prefs.CredentialStore.PORTAL_BASE_URL}/wp-json/ai-seo-saas/v1/")
            .client(client)
            .addConverterFactory(json.asConverterFactory(contentType))
            .build()
    }

    @Provides
    @Singleton
    fun provideApiService(retrofit: Retrofit): ApiService = retrofit.create(ApiService::class.java)

    @Provides
    @Singleton
    fun provideLicenseRepository(api: ApiService): LicenseRepository = LicenseRepository(api)

    @Provides
    @Singleton
    fun provideTenantRepository(api: ApiService): TenantRepository = TenantRepository(api)

    @Provides
    @Singleton
    fun provideUsageRepository(api: ApiService): UsageRepository = UsageRepository(api)

    @Provides
    @Singleton
    fun provideSupportRepository(api: ApiService): SupportRepository = SupportRepository(api)

    @Provides
    @Singleton
    fun provideGeoScanRepository(api: ApiService): GeoScanRepository = GeoScanRepository(api)

    @Provides
    @Singleton
    fun provideAiModelRepository(api: ApiService): AiModelRepository = AiModelRepository(api)
}
