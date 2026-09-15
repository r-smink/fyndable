package com.fyndable.admin

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.fyndable.admin.ui.nav.BottomNavBar
import com.fyndable.admin.ui.nav.Screen
import com.fyndable.admin.ui.theme.FyndableAdminTheme
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            FyndableAdminTheme {
                AppRoot()
            }
        }
    }
}

@Composable
fun AppRoot() {
    val navController = rememberNavController()
    val backStack by navController.currentBackStackEntryAsState()
    val currentRoute = backStack?.destination?.route

    val authVm: com.fyndable.admin.ui.login.AuthViewModel = hiltViewModel()
    val isLoggedIn by authVm.isLoggedIn.collectAsStateWithLifecycle(initialValue = null)

    when (isLoggedIn) {
        null -> com.fyndable.admin.ui.components.LoadingIndicator()
        false -> com.fyndable.admin.ui.login.LoginScreen(onLoggedIn = { authVm.refresh() })
        true -> {
            Scaffold(
                bottomBar = {
                    if (currentRoute in com.fyndable.admin.ui.nav.bottomNavItems.map { it.route }) {
                        BottomNavBar(currentRoute) { route ->
                            navController.navigate(route) {
                                popUpTo(Screen.Dashboard.route) { saveState = true }
                                launchSingleTop = true
                                restoreState = true
                            }
                        }
                    }
                },
            ) { innerPadding ->
                NavHost(
                    navController = navController,
                    startDestination = Screen.Dashboard.route,
                    modifier = Modifier.padding(innerPadding),
                ) {
                    composable(Screen.Dashboard.route) {
                        com.fyndable.admin.ui.dashboard.DashboardScreen(
                            onNavigate = { navController.navigate(it) },
                        )
                    }
                    composable(Screen.Licenses.route) {
                        com.fyndable.admin.ui.licenses.LicensesScreen()
                    }
                    composable(Screen.GeoScan.route) {
                        com.fyndable.admin.ui.geoscan.GeoScanScreen()
                    }
                    composable(Screen.Support.route) {
                        com.fyndable.admin.ui.support.SupportScreen()
                    }
                    composable(Screen.Usage.route) {
                        com.fyndable.admin.ui.usage.UsageScreen()
                    }
                    composable(Screen.AiModels.route) {
                        com.fyndable.admin.ui.aimodels.AiModelsScreen()
                    }
                }
            }
        }
    }
}
