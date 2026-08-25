package com.fyndable.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import com.fyndable.mobile.data.store.AuthStore
import com.fyndable.mobile.ui.navigation.AppNavigation
import com.fyndable.mobile.ui.theme.FyndableTheme

class MainActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val authStore = AuthStore(applicationContext)

        setContent {
            FyndableTheme {
                val credentials by authStore.credentialsFlow.collectAsState(initial = null)
                AppNavigation(
                    authStore = authStore,
                    isAuthenticated = credentials != null,
                )
            }
        }
    }
}
