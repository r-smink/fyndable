package com.fyndable.mobile.data.api

import okhttp3.Credentials
import okhttp3.Interceptor
import okhttp3.Response

class AuthInterceptor(
    private val authProvider: () -> AuthCredentials?
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val creds = authProvider()
        val request = if (creds != null) {
            val basic = Credentials.basic(creds.username, creds.password)
            chain.request().newBuilder()
                .addHeader("Authorization", basic)
                .build()
        } else {
            chain.request()
        }
        return chain.proceed(request)
    }
}

data class AuthCredentials(
    val username: String,
    val password: String,
    val siteUrl: String,
)
