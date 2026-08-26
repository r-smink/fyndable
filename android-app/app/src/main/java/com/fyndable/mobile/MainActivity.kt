package com.fyndable.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.getValue
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.navigation.AppNavigation
import com.fyndable.mobile.ui.theme.FyndableTheme

class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val authStore = AuthStore(applicationContext)

        setContent {
            FyndableTheme {
                val credentials by authStore.credentialsFlow.collectAsStateWithLifecycle(initialValue = null)
                AppNavigation(
                    authStore = authStore,
                    isAuthenticated = credentials != null,
                )
            }
        }
    }
}
