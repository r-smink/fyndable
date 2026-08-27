package com.fyndable.mobile.data.api

import okhttp3.Credentials
import okhttp3.Interceptor
import okhttp3.Response

class AuthInterceptor(
    private val credentials: AuthCredentials?
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = if (credentials != null) {
            val basic = Credentials.basic(credentials.username, credentials.password)
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
